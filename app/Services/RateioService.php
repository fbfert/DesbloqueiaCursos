<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ApuracaoMensal;
use App\Models\CursoEvento;
use App\Models\CursoPessoaVinculada;
use App\Models\CursoRateio;
use App\Models\CursoRateioParticipante;
use App\Models\ProfessorFiscal;
use App\Models\Turma;
use Exception;

class RateioService
{
    private $apuracaoModel;
    private $cursoRateioModel;
    private $cursoRateioParticipanteModel;
    private $cursoPessoaModel;
    private $cursoModel;
    private $turmaModel;
    private $professorFiscalModel;
    private $configuracaoGlobalService;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->apuracaoModel = new ApuracaoMensal();
        $this->cursoRateioModel = new CursoRateio();
        $this->cursoRateioParticipanteModel = new CursoRateioParticipante();
        $this->cursoPessoaModel = new CursoPessoaVinculada();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->professorFiscalModel = new ProfessorFiscal();
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listAdmin($apuracaoId = null)
    {
        $rateios = $apuracaoId ? $this->cursoRateioModel->findByApuracao($apuracaoId) : $this->cursoRateioModel->allAdmin();
        $apuracoes = $this->apuracaoModel->allAdmin();

        foreach ($rateios as &$rateio) {
            $participantes = $this->cursoRateioParticipanteModel->findByRateio($rateio['id']);
            $rateio['participantes_count'] = count($participantes);
            $rateio['percentual_restante_empresa'] = round(max(0, 100 - (float) $rateio['percentual_total']), 2);
        }
        unset($rateio);

        return array(
            'apuracoes' => $apuracoes,
            'rateios' => $rateios,
        );
    }

    public function formData($rateioId = null, $apuracaoId = null)
    {
        $rateio = null;
        if ($rateioId) {
            $encontrado = $this->cursoRateioModel->findById($rateioId);
            if ($encontrado) {
                $rateio = $this->appendRateioContext($encontrado);
            }
        }
        $apuracoes = $this->apuracaoModel->allAdmin();

        if (!$apuracaoId && $rateio && !empty($rateio['apuracao_id'])) {
            $apuracaoId = (int) $rateio['apuracao_id'];
        }

        if (!$apuracaoId && !empty($apuracoes)) {
            $apuracaoId = (int) $apuracoes[0]['id'];
        }

        return array(
            'rateio' => $rateio,
            'apuracao_selecionada' => $apuracaoId,
            'apuracoes' => $apuracoes,
            'cursos' => $this->cursoModel->allForSelect(),
            'turmas' => $this->turmaModel->allForSelect(),
            'professores_fiscal' => $this->professorFiscalModel->allActive(),
            'participantes' => $rateio ? $rateio['participantes'] : array(),
            'percentual_maximo' => $this->percentualMaximo(),
        );
    }

    public function apurarCompetencia($competencia, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', (string) $competencia)) {
            return array('ok' => false, 'message' => 'Competencia invalida.');
        }

        if ($this->apuracaoModel->findByCompetencia($competencia)) {
            return array('ok' => false, 'message' => 'Ja existe apuracao para esta competencia.');
        }

        $dateInicio = $competencia . '-01';
        $dateFim = date('Y-m-t', strtotime($dateInicio));
        $orders = $this->carregarPedidosFechados($dateInicio, $dateFim);

        $grupos = array();
        foreach ($orders as $pedido) {
            $basePedido = max(0, (float) (isset($pedido['total']) ? $pedido['total'] : 0));
            $subtotalPedido = max(0, (float) (isset($pedido['subtotal']) ? $pedido['subtotal'] : 0));
            $descontoPedido = max(0, (float) (isset($pedido['desconto_total']) ? $pedido['desconto_total'] : 0));
            $baseProporcional = $basePedido;

            foreach ($pedido['itens'] as $item) {
                $chave = (int) $item['curso_evento_id'] . ':' . (int) ($item['turma_id'] ?: 0);
                if (!isset($grupos[$chave])) {
                    $grupos[$chave] = array(
                        'curso_evento_id' => (int) $item['curso_evento_id'],
                        'turma_id' => !empty($item['turma_id']) ? (int) $item['turma_id'] : null,
                        'base_bruta' => 0.00,
                        'desconto_cupons' => 0.00,
                        'base_liquida' => 0.00,
                        'pedidos' => array(),
                    );
                }

                $itensTotal = $subtotalPedido > 0 ? (float) $item['valor_total'] : (float) $item['valor_total'];
                $participacao = $subtotalPedido > 0 ? ($itensTotal / $subtotalPedido) : 1;
                $baseItemLiquida = round($baseProporcional * $participacao, 2);
                $descontoItem = max(0, round($itensTotal - $baseItemLiquida, 2));

                $grupos[$chave]['base_bruta'] += (float) $item['valor_total'];
                $grupos[$chave]['desconto_cupons'] += $descontoItem;
                $grupos[$chave]['base_liquida'] += $baseItemLiquida;
                $grupos[$chave]['pedidos'][$pedido['id']] = true;
            }
        }

        if (empty($grupos)) {
            return array('ok' => false, 'message' => 'Nenhum pedido fechado encontrado na competencia informada.');
        }

        $erroProfessores = array();
        $gruposCalculados = array();
        $percentualMaximo = (float) $this->configuracaoGlobalService->financeiro()['percentual_rateio_maximo'];
        if ($percentualMaximo <= 0) {
            $percentualMaximo = 75.00;
        }

        foreach ($grupos as $grupo) {
            $professores = $this->carregarProfessoresDoContexto($grupo['curso_evento_id'], $grupo['turma_id']);
            if (empty($professores)) {
                $erroProfessores[] = 'Curso/turma sem professor vinculado: curso ' . $grupo['curso_evento_id'] . ' / turma ' . ($grupo['turma_id'] ?: 'N/A');
                continue;
            }

            $grupo['professores'] = $professores;
            $grupo['percentual_total'] = $percentualMaximo;
            $grupo['valor_rateio_total'] = round($grupo['base_liquida'] * ($percentualMaximo / 100), 2);
            $gruposCalculados[] = $grupo;
        }

        if (!empty($erroProfessores)) {
            return array('ok' => false, 'message' => implode(' | ', $erroProfessores));
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $apuracaoId = $this->apuracaoModel->create(array(
                'competencia' => $competencia,
                'data_inicio' => $dateInicio,
                'data_fim' => $dateFim,
                'base_bruta' => 0,
                'desconto_cupons' => 0,
                'base_liquida' => 0,
                'percentual_rateio_total' => 0,
                'valor_rateio_total' => 0,
                'valor_retenido_total' => 0,
                'status' => 'apurando',
                'criada_por_usuario_id' => $actorUserId,
            ));

            $totais = array(
                'base_bruta' => 0.00,
                'desconto_cupons' => 0.00,
                'base_liquida' => 0.00,
                'percentual_rateio_total' => 0.00,
                'valor_rateio_total' => 0.00,
                'valor_retenido_total' => 0.00,
            );

            foreach ($gruposCalculados as $grupo) {
                $rateioId = $this->cursoRateioModel->create(array(
                    'apuracao_id' => $apuracaoId,
                    'curso_evento_id' => $grupo['curso_evento_id'],
                    'turma_id' => $grupo['turma_id'],
                    'competencia' => $competencia,
                    'base_bruta' => round($grupo['base_bruta'], 2),
                    'desconto_cupons' => round($grupo['desconto_cupons'], 2),
                    'base_liquida' => round($grupo['base_liquida'], 2),
                    'percentual_total' => $grupo['percentual_total'],
                    'valor_rateio_total' => $grupo['valor_rateio_total'],
                    'status' => 'fechado',
                    'fechado_em' => date('Y-m-d H:i:s'),
                ));

                $quantidadeProfessores = count($grupo['professores']);
                $percentuais = $this->distribuirPercentuais($grupo['percentual_total'], $quantidadeProfessores);

                foreach ($grupo['professores'] as $indice => $professor) {
                    $perfilFiscal = $this->professorFiscalModel->findByUsuarioId((int) $professor['usuario_id']);
                    $tipoFiscal = $perfilFiscal ? $perfilFiscal['tipo_pessoa'] : 'pf';
                    $retencao = $perfilFiscal ? (float) $perfilFiscal['aliquota_retencao'] : 0.00;
                    $percentual = $percentuais[$indice];
                    $valorBase = round($grupo['base_liquida'] * ($percentual / 100), 2);
                    $valorBruto = $valorBase;
                    $valorRetido = round($valorBase * ($retencao / 100), 2);
                    $valorLiquido = round($valorBruto - $valorRetido, 2);
                    $status = $tipoFiscal === 'pj' ? 'aguardando_documento' : 'pendente';

                    $this->cursoRateioParticipanteModel->create(array(
                        'curso_rateio_id' => $rateioId,
                        'usuario_id' => (int) $professor['usuario_id'],
                        'tipo_fiscal' => $tipoFiscal,
                        'percentual' => $percentual,
                        'valor_base' => $valorBase,
                        'valor_rateado' => $valorBruto,
                        'retencao_percentual' => $retencao,
                        'valor_retenido' => $valorRetido,
                        'valor_liquido' => $valorLiquido,
                        'status' => $status,
                    ));

                    $totais['base_bruta'] += round($grupo['base_bruta'], 2) / $quantidadeProfessores;
                    $totais['desconto_cupons'] += round($grupo['desconto_cupons'], 2) / $quantidadeProfessores;
                    $totais['base_liquida'] += $valorBase;
                    $totais['percentual_rateio_total'] += $percentual;
                    $totais['valor_rateio_total'] += $valorBruto;
                    $totais['valor_retenido_total'] += $valorRetido;
                }
            }

            $this->apuracaoModel->updateSummary($apuracaoId, array(
                'base_bruta' => round($totais['base_bruta'], 2),
                'desconto_cupons' => round($totais['desconto_cupons'], 2),
                'base_liquida' => round($totais['base_liquida'], 2),
                'percentual_rateio_total' => round(min($percentualMaximo, $totais['percentual_rateio_total']), 2),
                'valor_rateio_total' => round($totais['valor_rateio_total'], 2),
                'valor_retenido_total' => round($totais['valor_retenido_total'], 2),
                'status' => 'fechada',
                'fechada_em' => date('Y-m-d H:i:s'),
            ));

            $this->auditService->record(
                'financeiro.apuracao.fechada',
                'apuracao_mensal',
                $apuracaoId,
                array('competencia' => $competencia, 'totais' => $totais, 'grupos' => count($gruposCalculados)),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('financeiro.apuracao.fechada', array(
                'apuracao_id' => $apuracaoId,
                'competencia' => $competencia,
            ));

            $pdo->commit();

            return array('ok' => true, 'apuracao_id' => $apuracaoId, 'totais' => $totais);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('financeiro.apuracao.falhou', array(
                'competencia' => $competencia,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function listarRateiosAdmin($apuracaoId = null)
    {
        if ($apuracaoId) {
            return $this->cursoRateioModel->findByApuracao($apuracaoId);
        }

        return $this->cursoRateioModel->allAdmin();
    }

    public function showRateioAdmin($rateioId)
    {
        $rateio = $this->cursoRateioModel->findById($rateioId);
        if (!$rateio) {
            return null;
        }

        return array(
            'rateio' => $this->appendRateioContext($rateio),
            'participantes' => $this->cursoRateioParticipanteModel->findByRateio($rateioId),
        );
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $apuracaoId = !empty($data['apuracao_id']) ? (int) $data['apuracao_id'] : 0;
        $cursoEventoId = !empty($data['curso_evento_id']) ? (int) $data['curso_evento_id'] : 0;
        $turmaId = isset($data['turma_id']) && $data['turma_id'] !== '' ? (int) $data['turma_id'] : null;
        $status = isset($data['status']) && in_array($data['status'], array('calculado', 'fechado', 'cancelado'), true) ? $data['status'] : 'calculado';
        $observacoes = isset($data['observacoes']) ? trim((string) $data['observacoes']) : null;
        $baseBruta = isset($data['base_bruta']) && $data['base_bruta'] !== '' ? (float) $data['base_bruta'] : 0.00;
        $descontoCupons = isset($data['desconto_cupons']) && $data['desconto_cupons'] !== '' ? (float) $data['desconto_cupons'] : 0.00;
        $baseLiquida = isset($data['base_liquida']) && $data['base_liquida'] !== '' ? (float) $data['base_liquida'] : max(0, $baseBruta - $descontoCupons);

        if ($apuracaoId <= 0) {
            return array('ok' => false, 'errors' => array('Informe a apuracao.'));
        }

        $apuracao = $this->apuracaoModel->findById($apuracaoId);
        if (!$apuracao) {
            return array('ok' => false, 'errors' => array('Apuracao nao encontrada.'));
        }

        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'errors' => array('Informe o curso/evento.'));
        }

        $curso = $this->cursoModel->findById($cursoEventoId);
        if (!$curso) {
            return array('ok' => false, 'errors' => array('Curso/evento nao encontrado.'));
        }

        if ($turmaId) {
            $turma = $this->turmaModel->findById($turmaId);
            if (!$turma || (int) $turma['curso_evento_id'] !== $cursoEventoId) {
                return array('ok' => false, 'errors' => array('Turma invalida para este curso/evento.'));
            }
        }

        $rateioAtual = $id > 0 ? $this->cursoRateioModel->findById($id) : null;
        if ($id > 0 && !$rateioAtual) {
            return array('ok' => false, 'errors' => array('Rateio nao encontrado.'));
        }

        $duplicado = $this->cursoRateioModel->findByContext($apuracaoId, $cursoEventoId, $turmaId, $id > 0 ? $id : null);
        if ($duplicado) {
            return array('ok' => false, 'errors' => array('Ja existe um rateio para este contexto.'));
        }

        $participantes = $this->normalizarParticipantes(isset($data['participantes']) ? $data['participantes'] : array());
        if (empty($participantes)) {
            return array('ok' => false, 'errors' => array('Informe ao menos um participante.'));
        }

        $percentualMaximo = $this->percentualMaximo();
        $percentualTotal = 0.00;
        $usuariosVistos = array();
        $participantesValidos = array();
        $errors = array();

        foreach ($participantes as $indice => $participante) {
            $usuarioId = !empty($participante['usuario_id']) ? (int) $participante['usuario_id'] : 0;
            if ($usuarioId <= 0) {
                $errors[] = 'Linha ' . ($indice + 1) . ': informe o professor.';
                continue;
            }

            if (isset($usuariosVistos[$usuarioId])) {
                $errors[] = 'Linha ' . ($indice + 1) . ': o mesmo professor nao pode ser repetido.';
                continue;
            }
            $usuariosVistos[$usuarioId] = true;

            $percentual = isset($participante['percentual']) ? (float) $participante['percentual'] : 0.00;
            if ($percentual <= 0) {
                $errors[] = 'Linha ' . ($indice + 1) . ': percentual invalido.';
                continue;
            }

            $perfilFiscal = $this->professorFiscalModel->findByUsuarioId($usuarioId);
            if (!$perfilFiscal) {
                $errors[] = 'Linha ' . ($indice + 1) . ': o professor precisa de perfil fiscal ativo.';
                continue;
            }

            $tipoFiscal = isset($participante['tipo_fiscal']) && in_array($participante['tipo_fiscal'], array('pf', 'pj'), true)
                ? $participante['tipo_fiscal']
                : $perfilFiscal['tipo_pessoa'];

            $retencao = isset($participante['retencao_percentual']) && $participante['retencao_percentual'] !== ''
                ? (float) $participante['retencao_percentual']
                : (float) $perfilFiscal['aliquota_retencao'];

            $participantesValidos[] = array(
                'usuario_id' => $usuarioId,
                'tipo_fiscal' => $tipoFiscal,
                'percentual' => $percentual,
                'retencao_percentual' => $retencao,
                'perfil_fiscal' => $perfilFiscal,
            );

            $percentualTotal += $percentual;
        }

        if (!empty($errors)) {
            return array('ok' => false, 'errors' => $errors);
        }

        $percentualTotal = round($percentualTotal, 2);
        if ($percentualTotal > $percentualMaximo) {
            return array('ok' => false, 'errors' => array('A soma dos percentuais nao pode ultrapassar ' . number_format($percentualMaximo, 2, ',', '.') . '%.'));
        }

        $valorRateioTotal = round($baseLiquida * ($percentualTotal / 100), 2);
        $percentualRestanteEmpresa = round(max(0, 100 - $percentualTotal), 2);
        $competencia = $apuracao['competencia'];
        $participantesExistentes = $rateioAtual ? $this->cursoRateioParticipanteModel->findByRateio($rateioAtual['id']) : array();
        $participantesExistentesMap = array();
        foreach ($participantesExistentes as $existente) {
            $participantesExistentesMap[(int) $existente['usuario_id']] = $existente;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $payload = array(
                'apuracao_id' => $apuracaoId,
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => $turmaId,
                'competencia' => $competencia,
                'base_bruta' => $baseBruta,
                'desconto_cupons' => $descontoCupons,
                'base_liquida' => $baseLiquida,
                'percentual_total' => $percentualTotal,
                'valor_rateio_total' => $valorRateioTotal,
                'status' => $status,
                'observacoes' => $observacoes,
                'fechado_em' => $status === 'fechado' ? date('Y-m-d H:i:s') : null,
            );

            if ($rateioAtual) {
                $this->cursoRateioModel->update($payload, $id);
                $acao = 'financeiro.rateio.atualizado';
            } else {
                $id = $this->cursoRateioModel->create($payload);
                $acao = 'financeiro.rateio.criado';
            }

            $submittedUserIds = array();
            foreach ($participantesValidos as $participante) {
                $submittedUserIds[] = $participante['usuario_id'];
                $valorBase = round($baseLiquida * ($participante['percentual'] / 100), 2);
                $valorRateado = $valorBase;
                $valorRetido = round($valorBase * ($participante['retencao_percentual'] / 100), 2);
                $valorLiquido = round($valorRateado - $valorRetido, 2);
                $statusParticipante = $participante['tipo_fiscal'] === 'pj' ? 'aguardando_documento' : 'pendente';

                if (isset($participantesExistentesMap[$participante['usuario_id']])) {
                    $existente = $participantesExistentesMap[$participante['usuario_id']];
                    $this->cursoRateioParticipanteModel->update(array(
                        'tipo_fiscal' => $participante['tipo_fiscal'],
                        'percentual' => $participante['percentual'],
                        'valor_base' => $valorBase,
                        'valor_rateado' => $valorRateado,
                        'retencao_percentual' => $participante['retencao_percentual'],
                        'valor_retenido' => $valorRetido,
                        'valor_liquido' => $valorLiquido,
                        'status' => $statusParticipante,
                    ), $existente['id']);
                } else {
                    $this->cursoRateioParticipanteModel->create(array(
                        'curso_rateio_id' => $id,
                        'usuario_id' => $participante['usuario_id'],
                        'tipo_fiscal' => $participante['tipo_fiscal'],
                        'percentual' => $participante['percentual'],
                        'valor_base' => $valorBase,
                        'valor_rateado' => $valorRateado,
                        'retencao_percentual' => $participante['retencao_percentual'],
                        'valor_retenido' => $valorRetido,
                        'valor_liquido' => $valorLiquido,
                        'status' => $statusParticipante,
                    ));
                }
            }

            foreach ($participantesExistentes as $existente) {
                if (!in_array((int) $existente['usuario_id'], $submittedUserIds, true)) {
                    $this->trashService->record(
                        'cursos_rateio_participantes',
                        $existente['id'],
                        'Atualizacao do rateio',
                        $existente,
                        $actorUserId,
                        $ipAddress,
                        $userAgent
                    );
                    $this->cursoRateioParticipanteModel->softDelete($existente['id']);
                }
            }

            $this->auditService->record(
                $acao,
                'cursos_rateio',
                $id,
                array(
                    'anterior' => $rateioAtual,
                    'novo' => $payload,
                    'participantes' => $participantesValidos,
                    'percentual_restante_empresa' => $percentualRestanteEmpresa,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array(
                'curso_rateio_id' => $id,
                'apuracao_id' => $apuracaoId,
                'percentual_total' => $percentualTotal,
            ));

            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('financeiro.rateio.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluir($rateioId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $rateio = $this->cursoRateioModel->findById($rateioId);
        if (!$rateio) {
            return array('ok' => false, 'message' => 'Rateio nao encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para excluir o rateio.');
        }

        $participantes = $this->cursoRateioParticipanteModel->findByRateio($rateioId);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            foreach ($participantes as $participante) {
                $this->trashService->record(
                    'cursos_rateio_participantes',
                    $participante['id'],
                    $justificativa,
                    $participante,
                    $actorUserId,
                    $ipAddress,
                    $userAgent
                );
                $this->cursoRateioParticipanteModel->softDelete($participante['id']);
            }

            $this->trashService->record('cursos_rateio', $rateioId, $justificativa, $rateio, $actorUserId, $ipAddress, $userAgent);
            $this->cursoRateioModel->softDelete($rateioId);

            $this->auditService->record(
                'financeiro.rateio.excluido',
                'cursos_rateio',
                $rateioId,
                array('justificativa' => $justificativa, 'snapshot' => $rateio),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('financeiro.rateio.excluido', array('curso_rateio_id' => $rateioId));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('financeiro.rateio.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    private function carregarPedidosFechados($dataInicio, $dataFim)
    {
        $sql = 'SELECT p.id, p.codigo, p.total, p.subtotal, p.desconto_total, p.aprovado_em,
                       cp.pix_aprovado_em,
                       sh.confirmado_em,
                       pi.id AS pedido_item_id, pi.curso_evento_id, pi.turma_id, pi.valor_total, pi.quantidade
                FROM pedidos p
                INNER JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.deleted_at IS NULL
                LEFT JOIN (
                    SELECT pedido_id, MAX(analisado_em) AS pix_aprovado_em
                    FROM comprovantes_pix
                    WHERE deleted_at IS NULL
                      AND status = "aprovado"
                    GROUP BY pedido_id
                ) cp ON cp.pedido_id = p.id
                LEFT JOIN (
                    SELECT pedido_id, MAX(created_at) AS confirmado_em
                    FROM status_pedidos_historico
                    WHERE status_novo IN ("aprovado", "pago")
                    GROUP BY pedido_id
                ) sh ON sh.pedido_id = p.id
                WHERE p.deleted_at IS NULL
                  AND p.status IN ("aprovado", "pago")
                  AND COALESCE(p.is_presente, 0) = 0
                  AND COALESCE(p.aprovado_em, cp.pix_aprovado_em, sh.confirmado_em, p.updated_at, p.created_at) BETWEEN :data_inicio AND :data_fim
                ORDER BY p.id ASC, pi.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array(
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ));

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $pedidos = array();
        foreach ($rows as $row) {
            $pedidoId = (int) $row['id'];
            if (!isset($pedidos[$pedidoId])) {
                $pedidos[$pedidoId] = array(
                    'id' => $pedidoId,
                    'codigo' => $row['codigo'],
                    'total' => $row['total'],
                    'subtotal' => $row['subtotal'],
                    'desconto_total' => $row['desconto_total'],
                    'aprovado_em' => $row['aprovado_em'],
                    'itens' => array(),
                );
            }

            $pedidos[$pedidoId]['itens'][] = $row;
        }

        return array_values($pedidos);
    }

    private function carregarProfessoresDoContexto($cursoId, $turmaId = null)
    {
        $sql = 'SELECT DISTINCT u.id AS usuario_id, u.nome, u.email
                FROM usuarios u
                INNER JOIN (
                    SELECT usuario_id
                    FROM curso_pessoas_vinculadas
                    WHERE curso_evento_id = :curso_evento_id
                      AND tipo_pessoa = "professor"
                      AND deleted_at IS NULL
                    UNION
                    SELECT usuario_id
                    FROM usuario_turmas
                    WHERE turma_id = :turma_id
                      AND tipo_vinculo = "professor"
                      AND deleted_at IS NULL
                ) vinculos ON vinculos.usuario_id = u.id
                WHERE u.deleted_at IS NULL
                ORDER BY u.nome ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId ?: 0,
        ));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function appendRateioContext(array $rateio)
    {
        $rateio['participantes'] = $this->cursoRateioParticipanteModel->findByRateio($rateio['id']);
        $rateio['participantes_count'] = count($rateio['participantes']);
        $rateio['percentual_restante_empresa'] = round(max(0, 100 - (float) $rateio['percentual_total']), 2);

        return $rateio;
    }

    private function normalizarParticipantes(array $participantes)
    {
        $normalizados = array();

        foreach ($participantes as $participante) {
            if (!is_array($participante)) {
                continue;
            }

            $usuarioId = isset($participante['usuario_id']) ? (int) $participante['usuario_id'] : 0;
            $tipoFiscal = isset($participante['tipo_fiscal']) ? trim((string) $participante['tipo_fiscal']) : '';
            $percentual = isset($participante['percentual']) ? trim((string) $participante['percentual']) : '';
            $retencao = isset($participante['retencao_percentual']) ? trim((string) $participante['retencao_percentual']) : '';

            if ($usuarioId <= 0 && $tipoFiscal === '' && $percentual === '' && $retencao === '') {
                continue;
            }

            $normalizados[] = array(
                'usuario_id' => $usuarioId,
                'tipo_fiscal' => $tipoFiscal !== '' ? $tipoFiscal : 'pf',
                'percentual' => $percentual !== '' ? (float) $percentual : 0.00,
                'retencao_percentual' => $retencao !== '' ? (float) $retencao : null,
            );
        }

        return $normalizados;
    }

    private function percentualMaximo()
    {
        $percentualMaximo = (float) $this->configuracaoGlobalService->financeiro()['percentual_rateio_maximo'];
        if ($percentualMaximo <= 0) {
            $percentualMaximo = 75.00;
        }

        return $percentualMaximo;
    }

    private function distribuirPercentuais($percentualTotal, $quantidade)
    {
        $percentuais = array();
        if ($quantidade <= 0) {
            return $percentuais;
        }

        $percentualBase = round($percentualTotal / $quantidade, 2);
        $soma = 0.00;
        for ($i = 0; $i < $quantidade; $i++) {
            if ($i === $quantidade - 1) {
                $percentuais[] = round($percentualTotal - $soma, 2);
            } else {
                $percentuais[] = $percentualBase;
                $soma += $percentualBase;
            }
        }

        return $percentuais;
    }
}



