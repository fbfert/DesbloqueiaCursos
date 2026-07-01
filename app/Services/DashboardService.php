<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ApuracaoMensal;
use App\Models\Categoria;
use App\Models\Certificado;
use App\Models\ComprovantePix;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\Pedido;
use App\Models\RepasseProfessor;
use App\Models\RpaEspelho;
use App\Models\Turma;
use App\Models\Usuario;

class DashboardService
{
    private $rbacService;
    private $auditService;
    private $pedidoModel;
    private $inscricaoModel;
    private $cursoModel;
    private $turmaModel;
    private $categoriaModel;
    private $usuarioModel;
    private $comprovanteModel;
    private $certificadoModel;
    private $apuracaoModel;
    private $repasseModel;
    private $rpaModel;

    public function __construct()
    {
        $this->rbacService = new RbacService();
        $this->auditService = new AuditService();
        $this->pedidoModel = new Pedido();
        $this->inscricaoModel = new Inscricao();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->categoriaModel = new Categoria();
        $this->usuarioModel = new Usuario();
        $this->comprovanteModel = new ComprovantePix();
        $this->certificadoModel = new Certificado();
        $this->apuracaoModel = new ApuracaoMensal();
        $this->repasseModel = new RepasseProfessor();
        $this->rpaModel = new RpaEspelho();
    }

    public function adminDashboard(array $filters = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->canAccessAdminDashboard($actorUserId)) {
            $this->registrarAcessoNegado('dashboard.admin.negado', $actorUserId, $ipAddress, $userAgent, $filters);

            return array(
                'ok' => false,
                'message' => 'Acesso negado.',
                'redirect' => $this->isProfessor($actorUserId) ? '/professor/dashboard' : '/',
            );
        }

        $range = $this->normalizeRange($filters);
        $options = $this->loadAdminOptions();

        return array(
            'ok' => true,
            'scope' => 'admin',
            'filters' => $range['filters'],
            'cards' => $this->buildAdminCards($range),
            'top_courses' => $this->listTopCourses($range),
            'revenue_by_period' => $this->listRevenueByPeriod($range),
            'repasses_by_competencia' => $this->listRepassesByCompetencia($range),
            'options' => $options,
        );
    }

    public function professorDashboard($usuarioId, array $filters = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->canAccessProfessorDashboard($usuarioId)) {
            $this->registrarAcessoNegado('dashboard.professor.negado', $usuarioId, $ipAddress, $userAgent, $filters);

            return array(
                'ok' => false,
                'message' => 'Acesso negado.',
                'redirect' => '/',
            );
        }

        $range = $this->normalizeRange($filters);
        $catalogo = new CatalogoService();
        $catalogoData = $catalogo->professorOverview($usuarioId);
        $repasses = $this->filterProfessorRepassesByRange($this->repasseModel->forProfessor($usuarioId), $range);
        $espelhos = $this->filterProfessorEspelhosByRange($this->rpaModel->forProfessor($usuarioId), $range);

        $cards = $this->buildProfessorCards($usuarioId, $range, $catalogoData, $repasses, $espelhos);

        return array(
            'ok' => true,
            'scope' => 'professor',
            'filters' => $range['filters'],
            'cards' => $cards,
            'cursos' => $catalogoData['cursos'],
            'turmas' => $catalogoData['turmas'],
            'vinculos_cursos' => $catalogoData['vinculos_cursos'],
            'vinculos_turmas' => $catalogoData['vinculos_turmas'],
            'repasses' => $repasses,
            'espelhos' => $espelhos,
        );
    }

    private function buildAdminCards(array $range)
    {
        $salesToday = $this->sumSales('today');
        $salesWeek = $this->sumSales('week');
        $salesMonth = $this->sumSales('month');

        return array(
            array(
                'label' => 'Vendas do dia',
                'value' => $this->formatCurrency($salesToday['revenue']),
                'subvalue' => $salesToday['total'] . ' pedidos',
            ),
            array(
                'label' => 'Vendas da semana',
                'value' => $this->formatCurrency($salesWeek['revenue']),
                'subvalue' => $salesWeek['total'] . ' pedidos',
            ),
            array(
                'label' => 'Vendas do mes',
                'value' => $this->formatCurrency($salesMonth['revenue']),
                'subvalue' => $salesMonth['total'] . ' pedidos',
            ),
            array(
                'label' => 'Novos usuarios hoje',
                'value' => $this->countUsersByRange('today'),
                'subvalue' => 'cadastros',
            ),
            array(
                'label' => 'Novos usuarios na semana',
                'value' => $this->countUsersByRange('week'),
                'subvalue' => 'cadastros',
            ),
            array(
                'label' => 'Novos usuarios no mes',
                'value' => $this->countUsersByRange('month'),
                'subvalue' => 'cadastros',
            ),
            array(
                'label' => 'Cursos e eventos cadastrados',
                'value' => $this->countRecords('cursos_eventos'),
                'subvalue' => 'itens ativos no catalogo',
            ),
            array(
                'label' => 'Disponiveis para venda',
                'value' => $this->countAvailableCourses(),
                'subvalue' => 'cursos/eventos ativos',
            ),
            array(
                'label' => 'Pedidos pendentes',
                'value' => $this->countPendingOrders(),
                'subvalue' => 'aguardando acao',
            ),
            array(
                'label' => 'Comprovantes em analise',
                'value' => $this->countPixInAnalysis(),
                'subvalue' => 'pix atual',
            ),
            array(
                'label' => 'Inscrições ativas',
                'value' => $this->countEnrollments(array('ativa', 'em_andamento')),
                'subvalue' => 'matriculas',
            ),
            array(
                'label' => 'Inscrições concluidas',
                'value' => $this->countEnrollments(array('concluida', 'concluida_sem_certificado', 'certificado_emitido')),
                'subvalue' => 'matriculas',
            ),
            array(
                'label' => 'Certificados emitidos',
                'value' => $this->countCertificatesIssued(),
                'subvalue' => 'emissao manual',
            ),
            array(
                'label' => 'Total a pagar a professores',
                'value' => $this->formatCurrency($this->sumRepassesByStatus(array('pendente', 'aguardando_documento', 'documento_recebido', 'aprovado'))),
                'subvalue' => 'repasse liquido',
            ),
            array(
                'label' => 'Total pago a professores',
                'value' => $this->formatCurrency($this->sumRepassesByStatus(array('pago'))),
                'subvalue' => 'repasse liquido',
            ),
        );
    }

    private function buildProfessorCards($usuarioId, array $range, array $catalogoData, array $repasses, array $espelhos)
    {
        $repassesTotals = $this->sumRows($repasses, 'valor_liquido');
        $espelhosTotals = $this->sumRows($espelhos, 'valor_liquido');

        return array(
            array(
                'label' => 'Cursos atribuídos',
                'value' => count($catalogoData['cursos']),
                'subvalue' => 'acesso restrito',
            ),
            array(
                'label' => 'Turmas atribuídas',
                'value' => count($catalogoData['turmas']),
                'subvalue' => 'acesso restrito',
            ),
            array(
                'label' => 'Inscrições ativas',
                'value' => $this->countProfessorEnrollments($usuarioId, array('ativa', 'em_andamento'), $range),
                'subvalue' => 'no periodo',
            ),
            array(
                'label' => 'Inscrições concluidas',
                'value' => $this->countProfessorEnrollments($usuarioId, array('concluida', 'concluida_sem_certificado', 'certificado_emitido'), $range),
                'subvalue' => 'no periodo',
            ),
            array(
                'label' => 'Certificados emitidos',
                'value' => $this->countProfessorCertificates($usuarioId, $range),
                'subvalue' => 'no periodo',
            ),
            array(
                'label' => 'Repasses no periodo',
                'value' => $this->formatCurrency($repassesTotals),
                'subvalue' => count($repasses) . ' registros',
            ),
            array(
                'label' => 'RPA espelhos',
                'value' => $this->formatCurrency($espelhosTotals),
                'subvalue' => count($espelhos) . ' registros',
            ),
            array(
                'label' => 'Pendencias financeiras',
                'value' => $this->countProfessorRepasseStatus($repasses, array('pendente', 'aguardando_documento')),
                'subvalue' => 'no periodo',
            ),
        );
    }

    private function listTopCourses(array $range)
    {
        $params = $this->buildOrderFilters($range['filters']);
        $sql = 'SELECT ce.id,
                       ce.nome,
                       ce.slug,
                       ce.tipo,
                       ce.modalidade,
                       c.nome AS categoria_nome,
                       SUM(pi.quantidade) AS total_vendido,
                       COUNT(DISTINCT p.id) AS total_pedidos,
                       COALESCE(SUM(pi.valor_total), 0) AS receita
                FROM pedidos p
                INNER JOIN pedido_itens pi ON pi.pedido_id = p.id
                   AND pi.deleted_at IS NULL
                INNER JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id
                LEFT JOIN categorias c ON c.id = ce.categoria_id
                WHERE ' . $params['where'] . '
                GROUP BY ce.id, ce.nome, ce.slug, ce.tipo, ce.modalidade, c.nome
                ORDER BY receita DESC, total_vendido DESC, ce.nome ASC
                LIMIT 10';

        $rows = $this->queryAll($sql, $params['values']);
        return $rows;
    }

    private function listRevenueByPeriod(array $range)
    {
        $params = $this->buildOrderFilters($range['filters']);
        $sql = 'SELECT DATE(COALESCE(p.aprovado_em, p.created_at)) AS periodo,
                       COUNT(DISTINCT p.id) AS total_pedidos,
                       COALESCE(SUM(p.total), 0) AS receita
                FROM pedidos p
                WHERE ' . $params['where'] . '
                GROUP BY DATE(COALESCE(p.aprovado_em, p.created_at))
                ORDER BY periodo ASC';

        $rows = $this->queryAll($sql, $params['values']);
        return $this->withBars($rows, 'receita');
    }

    private function listRepassesByCompetencia(array $range)
    {
        $sql = 'SELECT ap.competencia,
                       COUNT(*) AS total_repasses,
                       COALESCE(SUM(rp.valor_bruto), 0) AS valor_bruto,
                       COALESCE(SUM(rp.valor_retenido), 0) AS valor_retenido,
                       COALESCE(SUM(rp.valor_liquido), 0) AS valor_liquido,
                       COALESCE(SUM(CASE WHEN rp.status = "pago" THEN rp.valor_liquido ELSE 0 END), 0) AS valor_pago
                FROM repasses_professores rp
                INNER JOIN apuracoes_mensais ap ON ap.id = rp.apuracao_id
                WHERE rp.deleted_at IS NULL
                  AND ap.deleted_at IS NULL
                  AND ap.fechada_em BETWEEN :inicio AND :fim
                GROUP BY ap.competencia
                ORDER BY ap.competencia DESC';

        $rows = $this->queryAll($sql, array(
            'inicio' => $range['inicio_sql'],
            'fim' => $range['fim_sql'],
        ));

        return $this->withBars($rows, 'valor_liquido');
    }

    private function loadAdminOptions()
    {
        $cursos = $this->cursoModel->allWithCategoryAndCounts();
        $turmas = $this->turmaModel->allWithCourse();
        $categorias = $this->categoriaModel->allWithCounts();

        return array(
            'cursos' => $cursos,
            'turmas' => $turmas,
            'categorias' => $categorias,
            'cidades' => $this->distinctValues('pedidos', 'pagador_cidade'),
            'estados' => $this->distinctValues('pedidos', 'pagador_estado'),
        );
    }

    private function normalizeRange(array $filters)
    {
        $periodo = isset($filters['periodo']) ? trim((string) $filters['periodo']) : 'mes';
        $allowed = array('hoje', 'semana', 'mes', 'custom');
        if (!in_array($periodo, $allowed, true)) {
            $periodo = 'mes';
        }

        if ($periodo === 'hoje') {
            $inicio = date('Y-m-d 00:00:00');
            $fim = date('Y-m-d 23:59:59');
        } elseif ($periodo === 'semana') {
            $inicio = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $fim = date('Y-m-d 23:59:59');
        } elseif ($periodo === 'custom') {
            $inicio = !empty($filters['data_inicio']) ? $filters['data_inicio'] . ' 00:00:00' : date('Y-m-01 00:00:00');
            $fim = !empty($filters['data_fim']) ? $filters['data_fim'] . ' 23:59:59' : date('Y-m-d 23:59:59');
        } else {
            $inicio = date('Y-m-01 00:00:00');
            $fim = date('Y-m-d 23:59:59');
        }

        return array(
            'inicio_sql' => $inicio,
            'fim_sql' => $fim,
            'filters' => array(
                'periodo' => $periodo,
                'data_inicio' => isset($filters['data_inicio']) ? trim((string) $filters['data_inicio']) : substr($inicio, 0, 10),
                'data_fim' => isset($filters['data_fim']) ? trim((string) $filters['data_fim']) : substr($fim, 0, 10),
                'curso_evento_id' => isset($filters['curso_evento_id']) ? (int) $filters['curso_evento_id'] : 0,
                'turma_id' => isset($filters['turma_id']) ? (int) $filters['turma_id'] : 0,
                'categoria_id' => isset($filters['categoria_id']) ? (int) $filters['categoria_id'] : 0,
                'cidade' => isset($filters['cidade']) ? trim((string) $filters['cidade']) : '',
                'estado' => isset($filters['estado']) ? strtoupper(trim((string) $filters['estado'])) : '',
            ),
        );
    }

    private function buildOrderFilters(array $filters)
    {
        $where = array(
            'p.deleted_at IS NULL',
            'p.status IN ("aprovado", "pago")',
            'COALESCE(p.is_presente, 0) = 0',
            'COALESCE(p.aprovado_em, p.created_at) BETWEEN :inicio AND :fim',
        );

        $values = array(
            'inicio' => $filters['data_inicio'] . ' 00:00:00',
            'fim' => $filters['data_fim'] . ' 23:59:59',
        );

        if (!empty($filters['cidade'])) {
            $where[] = 'p.pagador_cidade = :cidade';
            $values['cidade'] = $filters['cidade'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'p.pagador_estado = :estado';
            $values['estado'] = $filters['estado'];
        }

        $exists = array();
        if (!empty($filters['curso_evento_id']) || !empty($filters['turma_id']) || !empty($filters['categoria_id'])) {
            $exists[] = 'SELECT 1 FROM pedido_itens pi';
            $exists[] = 'INNER JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id';
            $exists[] = 'WHERE pi.pedido_id = p.id';
            $exists[] = 'AND pi.deleted_at IS NULL';
            $exists[] = 'AND ce.deleted_at IS NULL';

            if (!empty($filters['curso_evento_id'])) {
                $exists[] = 'AND ce.id = :curso_evento_id';
                $values['curso_evento_id'] = (int) $filters['curso_evento_id'];
            }

            if (!empty($filters['turma_id'])) {
                $exists[] = 'AND pi.turma_id = :turma_id';
                $values['turma_id'] = (int) $filters['turma_id'];
            }

            if (!empty($filters['categoria_id'])) {
                $exists[] = 'AND ce.categoria_id = :categoria_id';
                $values['categoria_id'] = (int) $filters['categoria_id'];
            }

            $where[] = 'EXISTS (' . implode(' ', $exists) . ')';
        }

        return array(
            'where' => implode(' AND ', $where),
            'values' => $values,
        );
    }

    private function canAccessAdminDashboard($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->rbacService->isSuperAdmin($usuarioId)) {
            return true;
        }

        $permissions = array(
            'rbac.dashboard.ver',
            'pedidos.ver',
            'financeiro.ver',
            'conteudo.ver',
            'marketing.ver',
            'certificados.ver',
            'configuracoes_globais.ver',
            'emails.ver',
            'academico.ver',
            'area_curso.gerenciar',
            'cupons.ver',
            'promocionais.presentes.ver',
        );

        foreach ($permissions as $permission) {
            if ($this->rbacService->userHasPermission($usuarioId, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function canAccessProfessorDashboard($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->canAccessAdminDashboard($usuarioId)) {
            return true;
        }

        $permissions = array(
            'professor.ver',
            'professor.gerenciar',
            'catalogo.professor.ver',
            'area_curso.professor.ver',
            'financeiro.professor.ver',
            'academico.ver',
        );

        foreach ($permissions as $permission) {
            if ($this->rbacService->userHasPermission($usuarioId, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function countRecords($table)
    {
        return (int) $this->queryValue(
            'SELECT COUNT(*) AS total FROM ' . $table . ' WHERE deleted_at IS NULL'
        );
    }

    private function distinctValues($table, $column)
    {
        $sql = 'SELECT DISTINCT ' . $column . ' AS value
                FROM ' . $table . '
                WHERE deleted_at IS NULL
                  AND ' . $column . ' IS NOT NULL
                  AND ' . $column . ' <> ""
                ORDER BY ' . $column . ' ASC';

        $rows = $this->queryAll($sql);
        $values = array();

        foreach ($rows as $row) {
            if (isset($row['value'])) {
                $values[] = $row['value'];
            }
        }

        return $values;
    }

    private function countAvailableCourses()
    {
        return (int) $this->queryValue(
            'SELECT COUNT(*) AS total FROM cursos_eventos WHERE deleted_at IS NULL AND status = "ativo"'
        );
    }

    private function countPendingOrders()
    {
        return (int) $this->queryValue(
            'SELECT COUNT(*) AS total
             FROM pedidos
             WHERE deleted_at IS NULL
               AND status IN ("aguardando_pagamento", "comprovante_enviado", "em_analise", "pendencia", "aguardando_reenvio")'
        );
    }

    private function countPixInAnalysis()
    {
        return (int) $this->queryValue(
            'SELECT COUNT(*) AS total
             FROM comprovantes_pix cp
             INNER JOIN pedidos p ON p.id = cp.pedido_id
             WHERE cp.deleted_at IS NULL
               AND cp.is_atual = 1
               AND p.deleted_at IS NULL
               AND p.status <> "aguardando_reenvio"
               AND cp.status IN ("pendente", "em_analise")'
        );
    }

    private function countEnrollments(array $statuses)
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = 'SELECT COUNT(*) AS total
                FROM inscricoes i
                INNER JOIN pedidos p ON p.id = i.pedido_id
                WHERE i.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND COALESCE(p.is_presente, 0) = 0
                  AND i.status IN (' . $placeholders . ')';

        return (int) $this->queryValue($sql, $statuses);
    }

    private function countCertificatesIssued()
    {
        return (int) $this->queryValue(
            'SELECT COUNT(*) AS total
             FROM certificados c
             INNER JOIN inscricoes i ON i.id = c.inscricao_id
             INNER JOIN pedidos p ON p.id = i.pedido_id
             WHERE c.deleted_at IS NULL
               AND i.deleted_at IS NULL
               AND p.deleted_at IS NULL
               AND COALESCE(p.is_presente, 0) = 0
               AND c.status = "emitido"'
        );
    }

    private function sumRepassesByStatus(array $statuses)
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = 'SELECT COALESCE(SUM(valor_liquido), 0) AS total
                FROM repasses_professores
                WHERE deleted_at IS NULL
                  AND status IN (' . $placeholders . ')';

        return (float) $this->queryValue($sql, $statuses);
    }

    private function countUsersByRange($rangeKey)
    {
        $ranges = $this->buildRanges();
        if (!isset($ranges[$rangeKey])) {
            return 0;
        }

        return (int) $this->queryValue(
            'SELECT COUNT(*) AS total
             FROM usuarios
             WHERE deleted_at IS NULL
               AND created_at BETWEEN :inicio AND :fim',
            array(
                'inicio' => $ranges[$rangeKey]['inicio'],
                'fim' => $ranges[$rangeKey]['fim'],
            )
        );
    }

    private function sumSales($rangeKey)
    {
        $ranges = $this->buildRanges();
        if (!isset($ranges[$rangeKey])) {
            return array('total' => 0, 'revenue' => 0.00);
        }

        $row = $this->queryRow(
            'SELECT COUNT(DISTINCT p.id) AS total,
                    COALESCE(SUM(p.total), 0) AS revenue
             FROM pedidos p
             WHERE p.deleted_at IS NULL
               AND p.status IN ("aprovado", "pago")
               AND COALESCE(p.is_presente, 0) = 0
               AND COALESCE(p.aprovado_em, p.created_at) BETWEEN :inicio AND :fim',
            array(
                'inicio' => $ranges[$rangeKey]['inicio'],
                'fim' => $ranges[$rangeKey]['fim'],
            )
        );

        return array(
            'total' => $row ? (int) $row['total'] : 0,
            'revenue' => $row ? (float) $row['revenue'] : 0.00,
        );
    }

    private function buildRanges()
    {
        return array(
            'today' => array(
                'inicio' => date('Y-m-d 00:00:00'),
                'fim' => date('Y-m-d 23:59:59'),
            ),
            'week' => array(
                'inicio' => date('Y-m-d 00:00:00', strtotime('monday this week')),
                'fim' => date('Y-m-d 23:59:59'),
            ),
            'month' => array(
                'inicio' => date('Y-m-01 00:00:00'),
                'fim' => date('Y-m-d 23:59:59'),
            ),
        );
    }

    private function countProfessorEnrollments($usuarioId, array $statuses, array $range)
    {
        $ids = $this->accessibleCourseIds($usuarioId);
        $turmas = $this->accessibleTurmaIds($usuarioId);

        if (empty($ids) && empty($turmas)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = 'SELECT COUNT(*) AS total
                FROM inscricoes i
                INNER JOIN pedidos p ON p.id = i.pedido_id
                WHERE i.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND COALESCE(p.is_presente, 0) = 0
                  AND i.status IN (' . $placeholders . ')
                  AND COALESCE(i.updated_at, i.created_at) BETWEEN ? AND ?
                  AND (' . $this->buildProfessorScopeClause($ids, $turmas) . ')';

        $params = array_merge(
            $statuses,
            array(
                $range['inicio_sql'],
                $range['fim_sql'],
            ),
            $this->buildProfessorScopeParams($ids, $turmas)
        );

        return (int) $this->queryValue($sql, $params);
    }

    private function countProfessorCertificates($usuarioId, array $range)
    {
        $ids = $this->accessibleCourseIds($usuarioId);
        $turmas = $this->accessibleTurmaIds($usuarioId);

        if (empty($ids) && empty($turmas)) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS total
                FROM certificados c
                INNER JOIN inscricoes i ON i.id = c.inscricao_id
                INNER JOIN pedidos p ON p.id = i.pedido_id
                WHERE c.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND COALESCE(p.is_presente, 0) = 0
                  AND c.status = "emitido"
                  AND c.emitido_em BETWEEN ? AND ?
                  AND (' . $this->buildProfessorScopeClause($ids, $turmas, 'c') . ')';

        return (int) $this->queryValue(
            $sql,
            array_merge(
                array(
                    $range['inicio_sql'],
                    $range['fim_sql'],
                ),
                $this->buildProfessorScopeParams($ids, $turmas, 'c')
            )
        );
    }

    private function countProfessorRepasseStatus(array $repasses, array $statuses)
    {
        $total = 0;
        foreach ($repasses as $repasse) {
            if (in_array($repasse['status'], $statuses, true)) {
                $total++;
            }
        }

        return $total;
    }

    private function accessibleCourseIds($usuarioId)
    {
        $cursos = $this->cursoModel->findAccessibleByUser($usuarioId);
        $ids = array();

        foreach ($cursos as $curso) {
            $ids[] = (int) $curso['id'];
        }

        return array_values(array_unique($ids));
    }

    private function accessibleTurmaIds($usuarioId)
    {
        $turmas = $this->turmaModel->findAccessibleByUser($usuarioId);
        $ids = array();

        foreach ($turmas as $turma) {
            $ids[] = (int) $turma['id'];
        }

        return array_values(array_unique($ids));
    }

    private function buildProfessorScopeClause(array $cursoIds, array $turmaIds, $alias = 'i')
    {
        $parts = array();

        if (!empty($cursoIds)) {
            $parts[] = $alias . '.curso_evento_id IN (' . implode(',', array_fill(0, count($cursoIds), '?')) . ')';
        }

        if (!empty($turmaIds)) {
            $parts[] = $alias . '.turma_id IN (' . implode(',', array_fill(0, count($turmaIds), '?')) . ')';
        }

        if (empty($parts)) {
            return '1 = 0';
        }

        return implode(' OR ', $parts);
    }

    private function buildProfessorScopeParams(array $cursoIds, array $turmaIds, $alias = 'i')
    {
        return array_merge($cursoIds, $turmaIds);
    }

    private function filterProfessorRepassesByRange(array $repasses, array $range)
    {
        $inicioCompetencia = substr($range['inicio_sql'], 0, 7);
        $fimCompetencia = substr($range['fim_sql'], 0, 7);

        $filtered = array();
        foreach ($repasses as $repasse) {
            if ($repasse['competencia'] >= $inicioCompetencia && $repasse['competencia'] <= $fimCompetencia) {
                $filtered[] = $repasse;
            }
        }

        return $filtered;
    }

    private function filterProfessorEspelhosByRange(array $espelhos, array $range)
    {
        $inicioCompetencia = substr($range['inicio_sql'], 0, 7);
        $fimCompetencia = substr($range['fim_sql'], 0, 7);

        $filtered = array();
        foreach ($espelhos as $espelho) {
            if ($espelho['competencia'] >= $inicioCompetencia && $espelho['competencia'] <= $fimCompetencia) {
                $filtered[] = $espelho;
            }
        }

        return $filtered;
    }

    private function sumRows(array $rows, $key)
    {
        $total = 0.0;

        foreach ($rows as $row) {
            if (isset($row[$key])) {
                $total += (float) $row[$key];
            }
        }

        return $total;
    }

    private function withBars(array $rows, $valueKey)
    {
        $max = 0.0;
        foreach ($rows as $row) {
            $value = isset($row[$valueKey]) ? (float) $row[$valueKey] : 0.0;
            if ($value > $max) {
                $max = $value;
            }
        }

        foreach ($rows as &$row) {
            $value = isset($row[$valueKey]) ? (float) $row[$valueKey] : 0.0;
            $row['barra_percentual'] = $max > 0 ? round(($value / $max) * 100, 2) : 0;
        }
        unset($row);

        return $rows;
    }

    private function queryValue($sql, array $params = array())
    {
        $row = $this->queryRow($sql, $params);
        if (!$row) {
            return 0;
        }

        $values = array_values($row);
        return isset($values[0]) ? $values[0] : 0;
    }

    private function queryRow($sql, array $params = array())
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function queryAll($sql, array $params = array())
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function formatCurrency($value)
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private function isProfessor($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        return $this->rbacService->userHasPermission($usuarioId, 'professor.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'professor.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'catalogo.professor.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'area_curso.professor.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.professor.ver');
    }

    private function registrarAcessoNegado($evento, $usuarioId, $ipAddress = null, $userAgent = null, array $context = array())
    {
        $payload = array_merge($context, array(
            'usuario_id' => $usuarioId,
            'ip_address' => $ipAddress,
        ));

        $this->auditService->record($evento, 'dashboard', $usuarioId, $payload, $usuarioId, $ipAddress, $userAgent);
        Logger::error($evento, $payload);
    }
}



