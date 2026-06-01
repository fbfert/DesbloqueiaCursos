<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Categoria;
use App\Models\ComprovantePix;
use App\Models\CursoEvento;
use App\Models\ApuracaoMensal;
use App\Models\Pedido;
use App\Models\ProfessorFiscal;
use App\Models\Usuario;
use App\Models\Turma;
use App\Models\RepasseProfessor;
use App\Services\RbacService;

class FinanceiroService
{
    private $configuracaoGlobalService;
    private $rateioService;
    private $repasseService;
    private $apuracaoModel;
    private $repasseModel;
    private $professorFiscalModel;
    private $usuarioModel;
    private $pedidoModel;
    private $comprovanteModel;
    private $cursoModel;
    private $turmaModel;
    private $categoriaModel;
    private $auditService;
    private $rbacService;
    private $trashService;

    public function __construct()
    {
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->rateioService = new RateioService();
        $this->repasseService = new RepasseProfessorService();
        $this->apuracaoModel = new ApuracaoMensal();
        $this->repasseModel = new RepasseProfessor();
        $this->professorFiscalModel = new ProfessorFiscal();
        $this->usuarioModel = new Usuario();
        $this->pedidoModel = new Pedido();
        $this->comprovanteModel = new ComprovantePix();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->categoriaModel = new Categoria();
        $this->auditService = new AuditService();
        $this->rbacService = new RbacService();
        $this->trashService = new TrashService();
    }

    public function painelAdmin(array $filters = array())
    {
        $filtrosEntradas = $this->normalizarFiltrosEntradas($filters);
        $payload = array(
            'configuracao_financeira' => array(),
            'apuracoes' => array(),
            'repasses' => array(),
            'professores_fiscal' => array(),
            'professores' => array(),
            'filtros_entradas' => $filtrosEntradas,
            'entradas_resumo' => array(),
            'entradas_mensais' => array(),
            'entradas_por_curso' => array(),
            'entradas_pedidos' => array(),
            'financeiro_options' => array(
                'anos' => array((int) date('Y')),
                'meses' => array(),
                'cursos' => array(),
                'turmas' => array(),
                'categorias' => array(),
            ),
        );

        try {
            $payload['configuracao_financeira'] = $this->configuracaoGlobalService->financeiro();
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.configuracao_falha', array('message' => $exception->getMessage()));
            $payload['configuracao_financeira'] = array(
                'data_corte_financeiro' => null,
                'percentual_rateio_maximo' => 75.00,
                'observacao_repasse' => null,
            );
        }

        try {
            $payload['apuracoes'] = $this->apuracaoModel->allAdmin();
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.apuracoes_falha', array('message' => $exception->getMessage()));
        }

        try {
            $payload['repasses'] = $this->repasseModel->allAdmin();
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.repasses_falha', array('message' => $exception->getMessage()));
        }

        try {
            $payload['professores_fiscal'] = $this->professorFiscalModel->allActive();
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.professores_fiscal_falha', array('message' => $exception->getMessage()));
        }

        try {
            $payload['professores'] = $this->usuarioModel->professores();
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.professores_falha', array('message' => $exception->getMessage()));
        }

        try {
            $payload['financeiro_options'] = $this->opcoesFinanceiras();
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.opcoes_falha', array('message' => $exception->getMessage()));
            $payload['financeiro_options'] = array(
                'anos' => array((int) date('Y')),
                'meses' => array(),
                'cursos' => array(),
                'turmas' => array(),
                'categorias' => array(),
            );
        }

        try {
            $payload['entradas_resumo'] = $this->resumoEntradasConfirmadas($filtrosEntradas);
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.entradas_resumo_falha', array('message' => $exception->getMessage()));
            $payload['entradas_resumo'] = array(
                'periodo' => $filtrosEntradas['periodo_label'],
                'ano' => $filtrosEntradas['ano'],
                'mes' => $filtrosEntradas['mes'],
                'data_inicio' => $filtrosEntradas['data_inicio'],
                'data_fim' => $filtrosEntradas['data_fim'],
                'total_pedidos' => 0,
                'subtotal' => 0.00,
                'desconto_total' => 0.00,
                'acrescimo_total' => 0.00,
                'total' => 0.00,
                'ticket_medio' => 0.00,
                'repasses_calculados' => 0.00,
                'valor_retenido_total' => 0.00,
                'liquido_estimado' => 0.00,
                'pedidos_pendentes_confirmacao' => 0,
                'comprovantes_pix_analise' => 0,
            );
        }

        try {
            $payload['entradas_mensais'] = $this->listarEntradasMensais($filtrosEntradas);
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.entradas_mensais_falha', array('message' => $exception->getMessage()));
            $payload['entradas_mensais'] = array();
        }

        try {
            $payload['entradas_por_curso'] = $this->listarEntradasPorCurso($filtrosEntradas);
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.entradas_por_curso_falha', array('message' => $exception->getMessage()));
            $payload['entradas_por_curso'] = array();
        }

        try {
            $payload['entradas_pedidos'] = $this->listarUltimosPedidosConfirmados($filtrosEntradas, 20);
        } catch (\Exception $exception) {
            Logger::error('financeiro.painel.entradas_pedidos_falha', array('message' => $exception->getMessage()));
            $payload['entradas_pedidos'] = array();
        }

        return $payload;
    }

    public function painelProfessor($usuarioId)
    {
        return $this->repasseService->listarPorProfessor($usuarioId);
    }

    public function exportarEntradasCsv(array $filters = array())
    {
        $filtros = $this->normalizarFiltrosEntradas($filters);
        $resumo = $this->resumoEntradasConfirmadas($filtros);
        $mensais = $this->listarEntradasMensais($filtros);
        $porCurso = $this->listarEntradasPorCurso($filtros);
        $pedidos = $this->listarUltimosPedidosConfirmados($filtros, 20);

        return array(
            'ok' => true,
            'filename' => 'entradas-financeiras-' . date('Ymd-His') . '.csv',
            'content' => $this->gerarCsvEntradas(array(
                'resumo' => $resumo,
                'mensais' => $mensais,
                'por_curso' => $porCurso,
                'pedidos' => $pedidos,
            ), $filtros),
        );
    }

    private function normalizarFiltrosEntradas(array $filters)
    {
        $anoAtual = (int) date('Y');
        $ano = isset($filters['ano']) ? preg_replace('/\D+/', '', (string) $filters['ano']) : '';
        if (strlen($ano) !== 4) {
            $ano = (string) $anoAtual;
        }

        $mes = isset($filters['mes']) ? preg_replace('/\D+/', '', (string) $filters['mes']) : '';
        if ($mes !== '' && strlen($mes) === 1) {
            $mes = '0' . $mes;
        }
        if ($mes !== '' && (!preg_match('/^(0[1-9]|1[0-2])$/', $mes))) {
            $mes = '';
        }

        $inicio = $ano . '-01-01 00:00:00';
        $fim = $ano . '-12-31 23:59:59';
        if ($mes !== '') {
            $inicioMes = $ano . '-' . $mes . '-01';
            $inicio = $inicioMes . ' 00:00:00';
            $fim = date('Y-m-t 23:59:59', strtotime($inicioMes));
        }

        $cursoId = isset($filters['curso_evento_id']) ? (int) $filters['curso_evento_id'] : 0;
        $turmaId = isset($filters['turma_id']) ? (int) $filters['turma_id'] : 0;
        $categoriaId = isset($filters['categoria_id']) ? (int) $filters['categoria_id'] : 0;

        return array(
            'ano' => (string) $ano,
            'mes' => $mes,
            'curso_evento_id' => $cursoId > 0 ? $cursoId : 0,
            'turma_id' => $turmaId > 0 ? $turmaId : 0,
            'categoria_id' => $categoriaId > 0 ? $categoriaId : 0,
            'data_inicio' => $inicio,
            'data_fim' => $fim,
            'periodo_label' => $mes !== '' ? 'Mês ' . $mes . '/' . $ano : 'Ano ' . $ano,
            'is_mes_especifico' => $mes !== '',
        );
    }

    private function opcoesFinanceiras()
    {
        $anoAtual = (int) date('Y');
        $anos = array();
        for ($ano = $anoAtual - 5; $ano <= $anoAtual + 1; $ano++) {
            $anos[] = $ano;
        }
        rsort($anos);

        return array(
            'anos' => $anos,
            'meses' => array(
                '01' => 'Janeiro',
                '02' => 'Fevereiro',
                '03' => 'Março',
                '04' => 'Abril',
                '05' => 'Maio',
                '06' => 'Junho',
                '07' => 'Julho',
                '08' => 'Agosto',
                '09' => 'Setembro',
                '10' => 'Outubro',
                '11' => 'Novembro',
                '12' => 'Dezembro',
            ),
            'cursos' => $this->cursoModel->allForSelect(),
            'turmas' => $this->turmaModel->allForSelect(),
            'categorias' => $this->categoriaModel->allForSelect(),
        );
    }

    private function resumoEntradasConfirmadas(array $filtros)
    {
        $params = array();
        $sqlBase = 'SELECT p.id, p.subtotal, p.desconto_total, p.acrescimo_total, p.total, '
            . $this->sqlDataConfirmacao()
            . ' AS data_confirmacao '
            . $this->queryBaseEntradasConfirmadas($filtros, $params);

        $sql = 'SELECT COUNT(DISTINCT x.id) AS total_pedidos,
                       COALESCE(SUM(x.subtotal), 0) AS subtotal,
                       COALESCE(SUM(x.desconto_total), 0) AS desconto_total,
                       COALESCE(SUM(x.acrescimo_total), 0) AS acrescimo_total,
                       COALESCE(SUM(x.total), 0) AS total,
                       CASE WHEN COUNT(DISTINCT x.id) > 0 THEN COALESCE(SUM(x.total), 0) / COUNT(DISTINCT x.id) ELSE 0 END AS ticket_medio
                FROM (' . $sqlBase . ') x';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $resumo = $stmt->fetch(\PDO::FETCH_ASSOC);
        $resumo = $resumo ?: array();

        $repasses = $this->somarRepassesApuracoesDoPeriodo($filtros);
        $pedidosPendentes = $this->contarPedidosPendentesConfirmacao($filtros);
        $comprovantesEmAnalise = $this->contarComprovantesEmAnalise($filtros);

        $entradasConfirmadas = isset($resumo['total']) ? (float) $resumo['total'] : 0.00;
        $valorRateioTotal = isset($repasses['valor_rateio_total']) ? (float) $repasses['valor_rateio_total'] : 0.00;

        return array(
            'periodo' => $filtros['periodo_label'],
            'ano' => $filtros['ano'],
            'mes' => $filtros['mes'],
            'data_inicio' => $filtros['data_inicio'],
            'data_fim' => $filtros['data_fim'],
            'total_pedidos' => isset($resumo['total_pedidos']) ? (int) $resumo['total_pedidos'] : 0,
            'subtotal' => isset($resumo['subtotal']) ? (float) $resumo['subtotal'] : 0.00,
            'desconto_total' => isset($resumo['desconto_total']) ? (float) $resumo['desconto_total'] : 0.00,
            'acrescimo_total' => isset($resumo['acrescimo_total']) ? (float) $resumo['acrescimo_total'] : 0.00,
            'total' => $entradasConfirmadas,
            'ticket_medio' => isset($resumo['ticket_medio']) ? (float) $resumo['ticket_medio'] : 0.00,
            'repasses_calculados' => $valorRateioTotal,
            'valor_retenido_total' => isset($repasses['valor_retenido_total']) ? (float) $repasses['valor_retenido_total'] : 0.00,
            'liquido_estimado' => round($entradasConfirmadas - $valorRateioTotal, 2),
            'pedidos_pendentes_confirmacao' => (int) $pedidosPendentes,
            'comprovantes_pix_analise' => (int) $comprovantesEmAnalise,
        );
    }

    private function listarEntradasMensais(array $filtros)
    {
        $params = array();
        $sqlBase = 'SELECT p.id, p.codigo, p.subtotal, p.desconto_total, p.acrescimo_total, p.total, '
            . $this->sqlDataConfirmacao()
            . ' AS data_confirmacao '
            . $this->queryBaseEntradasConfirmadas($filtros, $params);

        $sql = 'SELECT DATE_FORMAT(x.data_confirmacao, "%Y-%m") AS competencia,
                       COUNT(DISTINCT x.id) AS total_pedidos,
                       COALESCE(SUM(x.subtotal), 0) AS subtotal,
                       COALESCE(SUM(x.desconto_total), 0) AS desconto_total,
                       COALESCE(SUM(x.acrescimo_total), 0) AS acrescimo_total,
                       COALESCE(SUM(x.total), 0) AS total,
                       CASE WHEN COUNT(DISTINCT x.id) > 0 THEN COALESCE(SUM(x.total), 0) / COUNT(DISTINCT x.id) ELSE 0 END AS ticket_medio
                FROM (' . $sqlBase . ') x
                GROUP BY DATE_FORMAT(x.data_confirmacao, "%Y-%m")
                ORDER BY competencia DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $apuracoes = $this->apuracaoModel->allAdmin();
        $apuracoesPorCompetencia = array();
        foreach ($apuracoes as $apuracao) {
            $apuracoesPorCompetencia[(string) $apuracao['competencia']] = $apuracao;
        }

        $repassesPorApuracao = $this->mapearRepassesPorApuracao($this->repasseModel->allAdmin());

        $resultado = array();
        foreach ($rows as $row) {
            $apuracao = isset($apuracoesPorCompetencia[$row['competencia']]) ? $apuracoesPorCompetencia[$row['competencia']] : null;
            $repassesResumo = $apuracao && !empty($apuracao['id']) && isset($repassesPorApuracao[(int) $apuracao['id']])
                ? $this->resumirRepassesApuracao($repassesPorApuracao[(int) $apuracao['id']])
                : array('total' => 0, 'pagos' => 0, 'pendentes' => 0, 'situacao' => 'Sem repasses');

            $valorRateio = 0.00;
            if ($apuracao) {
                if ((string) $apuracao['status'] !== 'fechada') {
                    $percentualRateio = isset($apuracao['percentual_rateio_total']) ? (float) $apuracao['percentual_rateio_total'] : 0.00;
                    $valorRateio = round((float) $row['total'] * ($percentualRateio / 100), 2);
                } else {
                    $valorRateio = (float) $apuracao['valor_rateio_total'];
                }
            }
            $totalConfirmado = (float) $row['total'];

            $situacaoApuracao = 'Sem apuração';
            if ($apuracao) {
                if ((string) $apuracao['status'] !== 'fechada') {
                    $situacaoApuracao = 'Apuração aberta';
                } elseif (!empty($repassesResumo['total'])) {
                    $situacaoApuracao = 'Apuração fechada · ' . $repassesResumo['situacao'];
                } else {
                    $situacaoApuracao = 'Apuração fechada';
                }
            }

            $resultado[] = array(
                'competencia' => $row['competencia'],
                'competencia_label' => $this->formatarCompetencia($row['competencia']),
                'total_pedidos' => (int) $row['total_pedidos'],
                'subtotal' => (float) $row['subtotal'],
                'desconto_total' => (float) $row['desconto_total'],
                'acrescimo_total' => (float) $row['acrescimo_total'],
                'total' => $totalConfirmado,
                'ticket_medio' => (float) $row['ticket_medio'],
                'repasses_calculados' => $valorRateio,
                'valor_retenido_total' => $apuracao ? (float) $apuracao['valor_retenido_total'] : 0.00,
                'liquido_estimado' => round($totalConfirmado - $valorRateio, 2),
                'situacao_apuracao' => $situacaoApuracao,
                'apuracao_status' => $apuracao ? (string) $apuracao['status'] : null,
                'apuracao_fechada_em' => $apuracao ? $apuracao['fechada_em'] : null,
                'apuracao_id' => $apuracao ? (int) $apuracao['id'] : 0,
                'repasses_situacao' => $repassesResumo['situacao'],
                'repasses_pagos' => $repassesResumo['pagos'],
                'repasses_pendentes' => $repassesResumo['pendentes'],
                'is_mes_atual' => $row['competencia'] === date('Y-m'),
                'data_inicio' => substr($row['competencia'], 0, 7) . '-01',
                'data_fim' => date('Y-m-t', strtotime(substr($row['competencia'], 0, 7) . '-01')),
            );
        }

        return $resultado;
    }

    private function listarEntradasPorCurso(array $filtros)
    {
        $params = array();
        $sqlBase = 'SELECT p.id,
                           pi.curso_evento_id,
                           pi.turma_id,
                           pi.quantidade,
                           CASE
                               WHEN p.subtotal > 0 THEN p.total * (pi.valor_total / p.subtotal)
                               ELSE pi.valor_total
                           END AS receita_proporcional,
                           CASE
                               WHEN p.subtotal > 0 THEN p.desconto_total * (pi.valor_total / p.subtotal)
                               ELSE 0
                           END AS desconto_proporcional,
                           ' . $this->sqlDataConfirmacao() . ' AS data_confirmacao '
            . $this->queryBaseConfirmadasComItens($filtros, $params);

        $sql = 'SELECT x.curso_evento_id,
                       x.turma_id,
                       ce.nome AS curso_nome,
                       t.nome AS turma_nome,
                       COUNT(DISTINCT x.id) AS total_pedidos,
                       COALESCE(SUM(CASE WHEN x.quantidade > 0 THEN x.quantidade ELSE 1 END), 0) AS itens_vendidos,
                       COALESCE(SUM(x.receita_proporcional), 0) AS receita_proporcional,
                       COALESCE(SUM(x.desconto_proporcional), 0) AS desconto_proporcional,
                       CASE WHEN COUNT(DISTINCT x.id) > 0 THEN COALESCE(SUM(x.receita_proporcional), 0) / COUNT(DISTINCT x.id) ELSE 0 END AS ticket_medio
                FROM (' . $sqlBase . ') x
                INNER JOIN cursos_eventos ce ON ce.id = x.curso_evento_id AND ce.deleted_at IS NULL
                LEFT JOIN turmas t ON t.id = x.turma_id AND t.deleted_at IS NULL
                GROUP BY x.curso_evento_id, x.turma_id, ce.nome, t.nome
                ORDER BY receita_proporcional DESC, ce.nome ASC, t.nome ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalPeriodo = 0.00;
        foreach ($rows as $row) {
            $totalPeriodo += (float) $row['receita_proporcional'];
        }

        $resultado = array();
        foreach ($rows as $row) {
            $receita = (float) $row['receita_proporcional'];
            $resultado[] = array_merge($row, array(
                'percentual_periodo' => $totalPeriodo > 0 ? round(($receita / $totalPeriodo) * 100, 2) : 0.00,
                'receita_proporcional' => $receita,
                'desconto_proporcional' => (float) $row['desconto_proporcional'],
                'ticket_medio' => (float) $row['ticket_medio'],
                'total_pedidos' => (int) $row['total_pedidos'],
                'itens_vendidos' => (int) $row['itens_vendidos'],
            ));
        }

        return $resultado;
    }

    private function listarUltimosPedidosConfirmados(array $filtros, $limit = 20)
    {
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 20;
        }

        $params = array();
        $sql = 'SELECT p.id, p.codigo, p.pagador_nome, p.pagador_email, p.status, p.total, p.subtotal, p.desconto_total, p.acrescimo_total,
                       GROUP_CONCAT(DISTINCT ce.nome ORDER BY ce.nome SEPARATOR ", ") AS cursos_nome,
                       ' . $this->sqlDataConfirmacao() . ' AS data_confirmacao,
                       CASE
                           WHEN cp.pix_aprovado_em IS NOT NULL THEN "comprovante_pix"
                           WHEN p.aprovado_em IS NOT NULL THEN "aprovado_em"
                           WHEN sh.confirmado_em IS NOT NULL THEN "historico_status"
                           ELSE "fallback"
                       END AS origem_confirmacao
                FROM pedidos p
                INNER JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.deleted_at IS NULL
                LEFT JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id AND ce.deleted_at IS NULL
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
                  AND p.total > 0
                  AND ' . $this->sqlDataConfirmacao() . ' BETWEEN :data_inicio AND :data_fim';

        $params['data_inicio'] = $filtros['data_inicio'];
        $params['data_fim'] = $filtros['data_fim'];

        if ($filtros['curso_evento_id'] > 0 || $filtros['turma_id'] > 0 || $filtros['categoria_id'] > 0) {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM pedido_itens pi_f
                        INNER JOIN cursos_eventos ce_f ON ce_f.id = pi_f.curso_evento_id AND ce_f.deleted_at IS NULL
                        LEFT JOIN turmas t_f ON t_f.id = pi_f.turma_id AND t_f.deleted_at IS NULL
                        WHERE pi_f.pedido_id = p.id
                          AND pi_f.deleted_at IS NULL';
            if ($filtros['curso_evento_id'] > 0) {
                $sql .= ' AND pi_f.curso_evento_id = :curso_evento_id';
                $params['curso_evento_id'] = $filtros['curso_evento_id'];
            }
            if ($filtros['turma_id'] > 0) {
                $sql .= ' AND pi_f.turma_id = :turma_id';
                $params['turma_id'] = $filtros['turma_id'];
            }
            if ($filtros['categoria_id'] > 0) {
                $sql .= ' AND ce_f.categoria_id = :categoria_id';
                $params['categoria_id'] = $filtros['categoria_id'];
            }
            $sql .= ' )';
        }

        $sql .= ' GROUP BY p.id, p.codigo, p.pagador_nome, p.pagador_email, p.status, p.total, p.subtotal, p.desconto_total, p.acrescimo_total,
                           p.aprovado_em, p.updated_at, p.created_at, cp.pix_aprovado_em, sh.confirmado_em
                  ORDER BY data_confirmacao DESC, p.id DESC
                  LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $resultado = array();
        foreach ($rows as $row) {
            $resultado[] = array(
                'id' => (int) $row['id'],
                'codigo' => $row['codigo'],
                'pagador_nome' => $row['pagador_nome'],
                'pagador_email' => $row['pagador_email'],
                'status' => $row['status'],
                'total' => (float) $row['total'],
                'subtotal' => (float) $row['subtotal'],
                'desconto_total' => (float) $row['desconto_total'],
                'acrescimo_total' => (float) $row['acrescimo_total'],
                'cursos_nome' => $row['cursos_nome'],
                'data_confirmacao' => $row['data_confirmacao'],
                'origem_confirmacao' => $row['origem_confirmacao'],
            );
        }

        return $resultado;
    }

    private function queryBaseEntradasConfirmadas(array $filtros, array &$params)
    {
        $params = array(
            'data_inicio' => $filtros['data_inicio'],
            'data_fim' => $filtros['data_fim'],
        );

        $sql = ' FROM pedidos p
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
                   AND p.total > 0
                   AND ' . $this->sqlDataConfirmacao() . ' BETWEEN :data_inicio AND :data_fim';

        if ($filtros['curso_evento_id'] > 0 || $filtros['turma_id'] > 0 || $filtros['categoria_id'] > 0) {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM pedido_itens pi_f
                        INNER JOIN cursos_eventos ce_f ON ce_f.id = pi_f.curso_evento_id AND ce_f.deleted_at IS NULL
                        LEFT JOIN turmas t_f ON t_f.id = pi_f.turma_id AND t_f.deleted_at IS NULL
                        WHERE pi_f.pedido_id = p.id
                          AND pi_f.deleted_at IS NULL';
            if ($filtros['curso_evento_id'] > 0) {
                $sql .= ' AND pi_f.curso_evento_id = :curso_evento_id';
                $params['curso_evento_id'] = $filtros['curso_evento_id'];
            }
            if ($filtros['turma_id'] > 0) {
                $sql .= ' AND pi_f.turma_id = :turma_id';
                $params['turma_id'] = $filtros['turma_id'];
            }
            if ($filtros['categoria_id'] > 0) {
                $sql .= ' AND ce_f.categoria_id = :categoria_id';
                $params['categoria_id'] = $filtros['categoria_id'];
            }
            $sql .= ' )';
        }

        return $sql;
    }

    private function queryBaseConfirmadasComItens(array $filtros, array &$params)
    {
        $params = array(
            'data_inicio' => $filtros['data_inicio'],
            'data_fim' => $filtros['data_fim'],
        );

        $sql = ' FROM pedidos p
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
                   AND p.total > 0
                   AND ' . $this->sqlDataConfirmacao() . ' BETWEEN :data_inicio AND :data_fim';

        if ($filtros['curso_evento_id'] > 0) {
            $sql .= ' AND pi.curso_evento_id = :curso_evento_id';
            $params['curso_evento_id'] = $filtros['curso_evento_id'];
        }
        if ($filtros['turma_id'] > 0) {
            $sql .= ' AND pi.turma_id = :turma_id';
            $params['turma_id'] = $filtros['turma_id'];
        }
        if ($filtros['categoria_id'] > 0) {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM cursos_eventos ce_f
                        WHERE ce_f.id = pi.curso_evento_id
                          AND ce_f.deleted_at IS NULL
                          AND ce_f.categoria_id = :categoria_id
                      )';
            $params['categoria_id'] = $filtros['categoria_id'];
        }

        return $sql;
    }

    private function sqlJoinsConfirmacao()
    {
        return ' LEFT JOIN (
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
                 ) sh ON sh.pedido_id = p.id';
    }

    private function sqlDataConfirmacao()
    {
        return 'COALESCE(p.aprovado_em, cp.pix_aprovado_em, sh.confirmado_em, p.updated_at, p.created_at)';
    }

    private function somarRepassesApuracoesDoPeriodo(array $filtros)
    {
        $sql = 'SELECT COALESCE(SUM(ap.valor_rateio_total), 0) AS valor_rateio_total,
                       COALESCE(SUM(ap.valor_retenido_total), 0) AS valor_retenido_total
                FROM apuracoes_mensais ap
                WHERE ap.deleted_at IS NULL
                  AND ap.data_inicio BETWEEN :data_inicio AND :data_fim';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array(
            'data_inicio' => substr($filtros['data_inicio'], 0, 10),
            'data_fim' => substr($filtros['data_fim'], 0, 10),
        ));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: array('valor_rateio_total' => 0, 'valor_retenido_total' => 0);
    }

    private function contarPedidosPendentesConfirmacao(array $filtros)
    {
        $params = array(
            'data_inicio' => $filtros['data_inicio'],
            'data_fim' => $filtros['data_fim'],
        );

        $sql = 'SELECT COUNT(DISTINCT p.id) AS total
                FROM pedidos p
                WHERE p.deleted_at IS NULL
                  AND COALESCE(p.is_presente, 0) = 0
                  AND p.total > 0
                  AND p.status NOT IN ("aprovado", "pago", "cancelado", "reembolsado", "expirado")
                  AND p.created_at BETWEEN :data_inicio AND :data_fim';

        if ($filtros['curso_evento_id'] > 0 || $filtros['turma_id'] > 0 || $filtros['categoria_id'] > 0) {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM pedido_itens pi_f
                        INNER JOIN cursos_eventos ce_f ON ce_f.id = pi_f.curso_evento_id AND ce_f.deleted_at IS NULL
                        LEFT JOIN turmas t_f ON t_f.id = pi_f.turma_id AND t_f.deleted_at IS NULL
                        WHERE pi_f.pedido_id = p.id
                          AND pi_f.deleted_at IS NULL';
            if ($filtros['curso_evento_id'] > 0) {
                $sql .= ' AND pi_f.curso_evento_id = :curso_evento_id';
                $params['curso_evento_id'] = $filtros['curso_evento_id'];
            }
            if ($filtros['turma_id'] > 0) {
                $sql .= ' AND pi_f.turma_id = :turma_id';
                $params['turma_id'] = $filtros['turma_id'];
            }
            if ($filtros['categoria_id'] > 0) {
                $sql .= ' AND ce_f.categoria_id = :categoria_id';
                $params['categoria_id'] = $filtros['categoria_id'];
            }
            $sql .= ' )';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return isset($row['total']) ? (int) $row['total'] : 0;
    }

    private function contarComprovantesEmAnalise(array $filtros)
    {
        $params = array(
            'data_inicio' => $filtros['data_inicio'],
            'data_fim' => $filtros['data_fim'],
        );

        $sql = 'SELECT COUNT(DISTINCT cp.id) AS total
                FROM comprovantes_pix cp
                INNER JOIN pedidos p ON p.id = cp.pedido_id
                WHERE cp.deleted_at IS NULL
                  AND cp.is_atual = 1
                  AND cp.status IN ("pendente", "em_analise")
                  AND p.deleted_at IS NULL
                  AND p.total > 0
                  AND cp.enviado_em BETWEEN :data_inicio AND :data_fim';

        if ($filtros['curso_evento_id'] > 0 || $filtros['turma_id'] > 0 || $filtros['categoria_id'] > 0) {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM pedido_itens pi_f
                        INNER JOIN cursos_eventos ce_f ON ce_f.id = pi_f.curso_evento_id AND ce_f.deleted_at IS NULL
                        LEFT JOIN turmas t_f ON t_f.id = pi_f.turma_id AND t_f.deleted_at IS NULL
                        WHERE pi_f.pedido_id = p.id
                          AND pi_f.deleted_at IS NULL';
            if ($filtros['curso_evento_id'] > 0) {
                $sql .= ' AND pi_f.curso_evento_id = :curso_evento_id';
                $params['curso_evento_id'] = $filtros['curso_evento_id'];
            }
            if ($filtros['turma_id'] > 0) {
                $sql .= ' AND pi_f.turma_id = :turma_id';
                $params['turma_id'] = $filtros['turma_id'];
            }
            if ($filtros['categoria_id'] > 0) {
                $sql .= ' AND ce_f.categoria_id = :categoria_id';
                $params['categoria_id'] = $filtros['categoria_id'];
            }
            $sql .= ' )';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return isset($row['total']) ? (int) $row['total'] : 0;
    }

    private function mapearRepassesPorApuracao(array $repasses)
    {
        $mapa = array();
        foreach ($repasses as $repasse) {
            $apuracaoId = isset($repasse['apuracao_id']) ? (int) $repasse['apuracao_id'] : 0;
            if ($apuracaoId <= 0) {
                continue;
            }
            if (!isset($mapa[$apuracaoId])) {
                $mapa[$apuracaoId] = array();
            }
            $mapa[$apuracaoId][] = $repasse;
        }

        return $mapa;
    }

    private function resumirRepassesApuracao(array $repasses)
    {
        $resumo = array(
            'total' => count($repasses),
            'pagos' => 0,
            'pendentes' => 0,
            'valor_pago' => 0.00,
            'valor_total' => 0.00,
            'situacao' => 'Sem repasses',
        );

        foreach ($repasses as $repasse) {
            $status = isset($repasse['status']) ? (string) $repasse['status'] : '';
            $valor = isset($repasse['valor_liquido']) ? (float) $repasse['valor_liquido'] : 0.00;
            $resumo['valor_total'] += $valor;
            if ($status === 'pago') {
                $resumo['pagos']++;
                $resumo['valor_pago'] += $valor;
            } else {
                $resumo['pendentes']++;
            }
        }

        if ($resumo['total'] > 0) {
            if ($resumo['pendentes'] === 0) {
                $resumo['situacao'] = 'Repasses pagos';
            } elseif ($resumo['pagos'] > 0) {
                $resumo['situacao'] = 'Repasses parciais';
            } else {
                $resumo['situacao'] = 'Repasses pendentes';
            }
        }

        return $resumo;
    }

    private function formatarCompetencia($competencia)
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', (string) $competencia)) {
            return (string) $competencia;
        }

        $meses = array(
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Março',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        );

        $ano = substr($competencia, 0, 4);
        $mes = substr($competencia, 5, 2);

        return (isset($meses[$mes]) ? $meses[$mes] : $mes) . '/' . $ano;
    }

    private function csvRow(array $valores)
    {
        $escapado = array();
        foreach ($valores as $valor) {
            $texto = (string) $valor;
            $texto = str_replace('"', '""', $texto);
            if (strpos($texto, ';') !== false || strpos($texto, '"') !== false || strpos($texto, "\n") !== false || strpos($texto, "\r") !== false) {
                $texto = '"' . $texto . '"';
            }
            $escapado[] = $texto;
        }

        return implode(';', $escapado);
    }

    private function gerarCsvEntradas(array $dados, array $filtros)
    {
        $linhas = array();
        $linhas[] = 'Resumo mensal';
        $linhas[] = $this->csvRow(array('Competência', 'Pedidos confirmados', 'Subtotal', 'Descontos', 'Acréscimos', 'Entradas confirmadas', 'Ticket médio', 'Rateio calculado', 'Retido', 'Líquido estimado', 'Status da apuração'));
        foreach ((array) $dados['mensais'] as $linha) {
            $linhas[] = $this->csvRow(array(
                $linha['competencia_label'],
                $linha['total_pedidos'],
                number_format((float) $linha['subtotal'], 2, ',', '.'),
                number_format((float) $linha['desconto_total'], 2, ',', '.'),
                number_format((float) $linha['acrescimo_total'], 2, ',', '.'),
                number_format((float) $linha['total'], 2, ',', '.'),
                number_format((float) $linha['ticket_medio'], 2, ',', '.'),
                number_format((float) $linha['repasses_calculados'], 2, ',', '.'),
                number_format((float) $linha['valor_retenido_total'], 2, ',', '.'),
                number_format((float) $linha['liquido_estimado'], 2, ',', '.'),
                $linha['situacao_apuracao'],
            ));
        }

        $linhas[] = '';
        $linhas[] = 'Entradas por curso/turma';
        $linhas[] = $this->csvRow(array('Curso', 'Turma', 'Pedidos', 'Itens vendidos', 'Receita proporcional', 'Desconto proporcional', 'Ticket médio', 'Percentual sobre total'));
        foreach ((array) $dados['por_curso'] as $linha) {
            $linhas[] = $this->csvRow(array(
                $linha['curso_nome'],
                $linha['turma_nome'],
                $linha['total_pedidos'],
                $linha['itens_vendidos'],
                number_format((float) $linha['receita_proporcional'], 2, ',', '.'),
                number_format((float) $linha['desconto_proporcional'], 2, ',', '.'),
                number_format((float) $linha['ticket_medio'], 2, ',', '.'),
                number_format((float) $linha['percentual_periodo'], 2, ',', '.'),
            ));
        }

        $linhas[] = '';
        $linhas[] = 'Últimos pedidos confirmados';
        $linhas[] = $this->csvRow(array('Código', 'Pagador', 'E-mail', 'Status', 'Data confirmação', 'Origem confirmação', 'Total', 'Cursos'));
        foreach ((array) $dados['pedidos'] as $linha) {
            $linhas[] = $this->csvRow(array(
                $linha['codigo'],
                $linha['pagador_nome'],
                $linha['pagador_email'],
                $linha['status'],
                $linha['data_confirmacao'],
                $linha['origem_confirmacao'],
                number_format((float) $linha['total'], 2, ',', '.'),
                $linha['cursos_nome'],
            ));
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $linhas) . "\r\n";
    }

    public function apurarCompetencia($competencia, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $result = $this->rateioService->apurarCompetencia($competencia, $actorUserId, $ipAddress, $userAgent);

        if (empty($result['ok'])) {
            return $result;
        }

        $this->auditService->record(
            'financeiro.apuracao.gerada',
            'apuracao_mensal',
            $result['apuracao_id'],
            array('competencia' => $competencia),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('financeiro.apuracao.gerada', array(
            'apuracao_id' => $result['apuracao_id'],
            'competencia' => $competencia,
        ));

        return $result;
    }

    public function gerarRepasses($apuracaoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeGerirFinanceiro($actorUserId)) {
            $this->registrarAcessoNegado('financeiro.repasses.negado', $apuracaoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        return $this->repasseService->gerarRepassesDaApuracao($apuracaoId, $actorUserId, $ipAddress, $userAgent);
    }

    public function salvarProfessorFiscal(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeGerirFinanceiro($actorUserId)) {
            $this->registrarAcessoNegado('financeiro.professor_fiscal.negado', isset($data['usuario_id']) ? $data['usuario_id'] : null, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        if (empty($data['usuario_id']) || (int) $data['usuario_id'] <= 0) {
            return array('ok' => false, 'message' => 'Informe o professor.');
        }

        $tipoPessoa = isset($data['tipo_pessoa']) ? trim((string) $data['tipo_pessoa']) : 'pf';
        if (!in_array($tipoPessoa, array('pf', 'pj'), true)) {
            return array('ok' => false, 'message' => 'Tipo fiscal invalido.');
        }

        if ($tipoPessoa === 'pj' && empty($data['cnpj']) && empty($data['razao_social'])) {
            return array('ok' => false, 'message' => 'Para PJ informe CNPJ e razao social.');
        }

        return $this->repasseService->salvarProfessorFiscal($data, $actorUserId, $ipAddress, $userAgent);
    }

    public function duplicarProfessorFiscal(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeGerirFinanceiro($actorUserId)) {
            $this->registrarAcessoNegado('financeiro.professor_fiscal.copia_negada', isset($data['id']) ? $data['id'] : null, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Perfil fiscal não encontrado.');
        }

        $original = $this->professorFiscalModel->findById($id);
        if (!$original) {
            return array('ok' => false, 'message' => 'Perfil fiscal não encontrado.');
        }

        $usuarioId = !empty($data['usuario_id']) ? (int) $data['usuario_id'] : 0;
        if ($usuarioId <= 0) {
            return array('ok' => false, 'message' => 'Selecione o professor para criar a cópia.');
        }
        if ((int) $original['usuario_id'] === $usuarioId) {
            return array('ok' => false, 'message' => 'Selecione outro professor para criar a cópia.');
        }
        if ($this->professorFiscalModel->findByUsuarioId($usuarioId)) {
            return array('ok' => false, 'message' => 'O professor selecionado já possui perfil fiscal.');
        }

        $payload = array(
            'usuario_id' => $usuarioId,
            'tipo_pessoa' => isset($data['tipo_pessoa']) && in_array($data['tipo_pessoa'], array('pf', 'pj'), true) ? $data['tipo_pessoa'] : $original['tipo_pessoa'],
            'cpf' => isset($data['cpf']) ? $data['cpf'] : $original['cpf'],
            'cnpj' => isset($data['cnpj']) ? $data['cnpj'] : $original['cnpj'],
            'razao_social' => isset($data['razao_social']) ? $data['razao_social'] : $original['razao_social'],
            'nome_fantasia' => isset($data['nome_fantasia']) ? $data['nome_fantasia'] : $original['nome_fantasia'],
            'inscricao_municipal' => isset($data['inscricao_municipal']) ? $data['inscricao_municipal'] : $original['inscricao_municipal'],
            'aliquota_retencao' => isset($data['aliquota_retencao']) ? $data['aliquota_retencao'] : $original['aliquota_retencao'],
            'exige_nota_fiscal' => !empty($data['exige_nota_fiscal']) ? 1 : (!empty($original['exige_nota_fiscal']) ? 1 : 0),
            'email_financeiro' => isset($data['email_financeiro']) ? $data['email_financeiro'] : $original['email_financeiro'],
            'observacao' => isset($data['observacao']) ? $data['observacao'] : $original['observacao'],
            'status' => 'inativo',
        );

        return $this->repasseService->salvarProfessorFiscal($payload, $actorUserId, $ipAddress, $userAgent);
    }

    public function removerProfessorFiscal($perfilId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeGerirFinanceiro($actorUserId)) {
            $this->registrarAcessoNegado('financeiro.professor_fiscal.remocao_negada', $perfilId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        $perfil = $this->professorFiscalModel->findById($perfilId);
        if (!$perfil) {
            return array('ok' => false, 'message' => 'Perfil fiscal nao encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para remover o perfil fiscal.');
        }

        $this->trashService->record('professores_fiscal', $perfilId, $justificativa, $perfil, $actorUserId, $ipAddress, $userAgent);
        $this->professorFiscalModel->softDelete($perfilId);

        $this->auditService->record(
            'financeiro.professor_fiscal.removido',
            'professores_fiscal',
            $perfilId,
            array('justificativa' => $justificativa, 'snapshot' => $perfil),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('financeiro.professor_fiscal.removido', array('professor_fiscal_id' => $perfilId));

        return array('ok' => true);
    }

    public function registrarDocumento($repasseId, array $file, $tipoDocumento = 'outro', $numeroDocumento = null, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeManipularRepasse($repasseId, $actorUserId)) {
            $this->registrarAcessoNegado('financeiro.documento.negado', $repasseId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        return $this->repasseService->anexarDocumento($repasseId, $file, $tipoDocumento, $numeroDocumento, $observacao, $actorUserId, $ipAddress, $userAgent);
    }

    public function registrarPagamento($repasseId, array $dados, ?array $arquivo = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeManipularRepasse($repasseId, $actorUserId)) {
            $this->registrarAcessoNegado('financeiro.pagamento.negado', $repasseId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        return $this->repasseService->registrarPagamento($repasseId, $dados, $arquivo, $actorUserId, $ipAddress, $userAgent);
    }

    public function listarRepassesApuracao($apuracaoId = null)
    {
        if (!empty($apuracaoId)) {
            return $this->repasseModel->forApuracao($apuracaoId);
        }

        return $this->repasseModel->allAdmin();
    }

    private function podeGerirFinanceiro($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        return $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver');
    }

    private function podeManipularRepasse($repasseId, $usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->podeGerirFinanceiro($usuarioId)) {
            return true;
        }

        $repasse = $this->repasseModel->findById($repasseId);
        if (!$repasse) {
            return false;
        }

        return (int) $repasse['usuario_id'] === (int) $usuarioId;
    }

    private function registrarAcessoNegado($evento, $recursoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'recurso_id' => $recursoId,
            'usuario_id' => $actorUserId,
            'ip_address' => $ipAddress,
        );

        $this->auditService->record($evento, 'financeiro', $recursoId, $payload, $actorUserId, $ipAddress, $userAgent);
        Logger::error($evento, $payload);
    }
}



