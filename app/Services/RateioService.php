<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ApuracaoMensal;
use App\Models\CursoPessoaVinculada;
use App\Models\CursoRateio;
use App\Models\CursoRateioParticipante;
use App\Models\ProfessorFiscal;
use Exception;

class RateioService
{
    private $apuracaoModel;
    private $cursoRateioModel;
    private $cursoRateioParticipanteModel;
    private $cursoPessoaModel;
    private $professorFiscalModel;
    private $configuracaoGlobalService;
    private $auditService;

    public function __construct()
    {
        $this->apuracaoModel = new ApuracaoMensal();
        $this->cursoRateioModel = new CursoRateio();
        $this->cursoRateioParticipanteModel = new CursoRateioParticipante();
        $this->cursoPessoaModel = new CursoPessoaVinculada();
        $this->professorFiscalModel = new ProfessorFiscal();
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->auditService = new AuditService();
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

    private function carregarPedidosFechados($dataInicio, $dataFim)
    {
        $sql = 'SELECT p.id, p.codigo, p.total, p.subtotal, p.desconto_total, p.aprovado_em,
                       pi.id AS pedido_item_id, pi.curso_evento_id, pi.turma_id, pi.valor_total, pi.quantidade
                FROM pedidos p
                INNER JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND p.status = "aprovado"
                  AND DATE(COALESCE(p.aprovado_em, p.created_at)) BETWEEN :data_inicio AND :data_fim
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
