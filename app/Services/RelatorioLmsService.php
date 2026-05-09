<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Turma;
use App\Models\CursoEvento;

class RelatorioLmsService
{
    private const STATUS_INSCRICAO_VALIDOS = array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido');

    private $areaCursoService;
    private $cursoModel;
    private $turmaModel;
    private $elegibilidadeService;
    private $criterioService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->elegibilidadeService = new LmsElegibilidadeService();
        $this->criterioService = new LmsCriterioConclusaoService();
    }

    public function carregarAdmin($cursoId, $turmaId = null, array $filtros = array())
    {
        return $this->carregarContexto($cursoId, $turmaId, $filtros, null, 'admin');
    }

    public function carregarProfessor($usuarioId, $cursoId, $turmaId = null, array $filtros = array())
    {
        if (!$this->areaCursoService->contextoProfessorAutorizado($usuarioId, $cursoId, $turmaId)) {
            return array(
                'ok' => false,
                'message' => 'Contexto não autorizado para este professor.',
            );
        }

        return $this->carregarContexto($cursoId, $turmaId, $filtros, $usuarioId, 'professor');
    }

    public function exportarCsv($tipo, array $relatorio)
    {
        if ($tipo === 'atividades') {
            $arquivo = $this->csvAtividades($relatorio);
            $tipoNormalizado = 'atividades';
        } elseif ($tipo === 'aptos_certificado') {
            $arquivo = $this->csvAptosCertificado($relatorio);
            $tipoNormalizado = 'aptos_certificado';
        } else {
            $arquivo = $this->csvProgresso($relatorio);
            $tipoNormalizado = 'progresso';
        }

        Logger::info('area_curso.relatorios.exportado', array(
            'tipo' => $tipoNormalizado,
            'linhas' => $tipoNormalizado === 'atividades'
                ? count((isset($relatorio['atividades']) && is_array($relatorio['atividades']) ? $relatorio['atividades'] : array()))
                : count((isset($relatorio['painel_aptos']['alunos']) && is_array($relatorio['painel_aptos']['alunos']) ? $relatorio['painel_aptos']['alunos'] : (isset($relatorio['alunos']) && is_array($relatorio['alunos']) ? $relatorio['alunos'] : array()))),
        ));

        return array(
            'filename' => $arquivo['filename'],
            'content' => $arquivo['content'],
            'content_type' => 'text/csv; charset=UTF-8',
        );
    }

    private function carregarContexto($cursoId, $turmaId = null, array $filtros = array(), $usuarioId = null, $contexto = 'admin')
    {
        $cursoId = (int) $cursoId;
        $turmaId = $turmaId !== null && $turmaId !== '' ? (int) $turmaId : null;

        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido para o relatório.');
        }

        $curso = $this->cursoModel->findById($cursoId);
        $turma = $turmaId ? $this->turmaModel->findById($turmaId) : null;

        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso não encontrado.');
        }

        $filtros = $this->normalizarFiltros($filtros);
        $resumoConteudo = $this->resumoConteudo($cursoId, $turmaId);
        $inscricoesBase = $this->inscricoesValidas($cursoId, $turmaId, array());
        $resumoProgresso = $this->resumoProgresso($cursoId, $turmaId);
        $resumoEntregas = $this->resumoEntregas($cursoId, $turmaId);
        $mapaProgresso = $this->mapaProgresso($cursoId, $turmaId);
        $mapaEntregas = $this->mapaEntregas($cursoId, $turmaId);
        $alunosBase = $this->montarAlunos($inscricoesBase, $mapaProgresso, $mapaEntregas, $resumoConteudo);
        $elegibilidade = $this->elegibilidadeService->calcularPorInscricoes($inscricoesBase, $cursoId, $turmaId);
        $alunosBase = $this->aplicarElegibilidadeNosAlunos($alunosBase, $elegibilidade['mapa_por_inscricao']);
        $alunos = $this->aplicarFiltrosAlunos($alunosBase, $filtros);
        $atividades = $this->montarAtividades($cursoId, $turmaId, $filtros, $resumoConteudo['atividades_publicadas']);
        $pendencias = $this->montarPendencias($alunosBase, $resumoConteudo['certificados_emitidos']);
        $criterios = $this->criterioService->resolver($cursoId, $turmaId);
        $painelAptos = $this->montarPainelAptosCertificado($alunos, $criterios);

        return array(
            'ok' => true,
            'curso' => $curso,
            'turma' => $turma,
            'filtros' => $filtros,
            'resumo' => array(
                'alunos_inscritos' => count($inscricoesBase),
                'alunos_acesso_liberado' => count($inscricoesBase),
                'alunos_com_progresso_registrado' => $resumoProgresso['alunos_com_progresso_registrado'],
                'alunos_que_acessaram_sala' => $resumoProgresso['alunos_com_progresso_registrado'],
                'modulos_publicados' => $resumoConteudo['modulos_publicados'],
                'aulas_publicadas' => $resumoConteudo['aulas_publicadas'],
                'materiais_publicados' => $resumoConteudo['materiais_publicados'],
                'atividades_publicadas' => $resumoConteudo['atividades_publicadas'],
                'entregas_enviadas' => $resumoEntregas['entregas_enviadas'],
                'entregas_corrigidas' => $resumoEntregas['entregas_corrigidas'],
                'entregas_pendentes_correcao' => $resumoEntregas['entregas_pendentes_correcao'],
                'progresso_medio' => $this->calcularMediaProgresso($alunosBase),
                'certificados_emitidos' => $resumoConteudo['certificados_emitidos'],
                'elegibilidade_apto' => (int) ($elegibilidade['resumo']['apto'] ?? 0),
                'elegibilidade_pendente' => (int) ($elegibilidade['resumo']['pendente'] ?? 0),
                'elegibilidade_em_andamento' => (int) ($elegibilidade['resumo']['em_andamento'] ?? 0),
                'elegibilidade_nao_apto' => (int) ($elegibilidade['resumo']['nao_apto'] ?? 0),
                'elegibilidade_certificado_emitido' => (int) ($elegibilidade['resumo']['certificado_emitido'] ?? 0),
            ),
            'alunos' => $alunos,
            'atividades' => $atividades,
            'pendencias' => $pendencias,
            'elegibilidade' => $elegibilidade,
            'painel_aptos' => $painelAptos,
            'criterios_conclusao' => $criterios,
        );
    }

    private function aplicarElegibilidadeNosAlunos(array $alunos, array $mapaElegibilidade)
    {
        foreach ($alunos as &$aluno) {
            $inscricaoId = (int) ($aluno['inscricao_id'] ?? 0);
            $item = isset($mapaElegibilidade[$inscricaoId]) ? $mapaElegibilidade[$inscricaoId] : null;
            $aluno['elegibilidade_situacao'] = $item ? (string) $item['situacao'] : 'pendente';
            $aluno['elegibilidade_motivos'] = $item ? (array) $item['motivos'] : array('Elegibilidade não calculada.');
            $aluno['elegibilidade_motivos_texto'] = $item ? (string) $item['motivos_texto'] : 'Elegibilidade não calculada.';
            $aluno['elegibilidade_media_atividades'] = $item ? $item['media_atividades'] : null;
            $aluno['elegibilidade_atividades_corrigidas'] = $item ? (int) $item['atividades_corrigidas'] : 0;
            $aluno['elegibilidade_atividades_enviadas'] = $item ? (int) $item['atividades_enviadas'] : 0;
            $aluno['elegibilidade_presenca'] = $item ? $item['presenca_percentual'] : null;
            $aluno['elegibilidade_avaliacao'] = $item ? $item['avaliacao_nota'] : null;
        }
        unset($aluno);

        return $alunos;
    }

    private function normalizarFiltros(array $filtros)
    {
        $busca = isset($filtros['busca']) ? trim((string) $filtros['busca']) : '';
        $statusInscricao = isset($filtros['status_inscricao']) ? trim((string) $filtros['status_inscricao']) : '';
        $faixaProgresso = isset($filtros['faixa_progresso']) ? trim((string) $filtros['faixa_progresso']) : '';
        $atividadeId = isset($filtros['atividade_id']) ? (int) $filtros['atividade_id'] : 0;
        $statusCorrecao = isset($filtros['status_correcao']) ? trim((string) $filtros['status_correcao']) : '';
        $situacaoElegibilidade = isset($filtros['situacao_elegibilidade']) ? trim((string) $filtros['situacao_elegibilidade']) : '';
        $filtroCertificado = isset($filtros['filtro_certificado']) ? trim((string) $filtros['filtro_certificado']) : '';
        $tipoPendencia = isset($filtros['tipo_pendencia']) ? trim((string) $filtros['tipo_pendencia']) : '';

        if ($statusInscricao !== '' && !in_array($statusInscricao, self::STATUS_INSCRICAO_VALIDOS, true)) {
            $statusInscricao = '';
        }

        if ($faixaProgresso !== '' && !in_array($faixaProgresso, array('0', '1-49', '50-99', '100'), true)) {
            $faixaProgresso = '';
        }

        if ($statusCorrecao !== '' && !in_array($statusCorrecao, array('pendente', 'corrigida'), true)) {
            $statusCorrecao = '';
        }
        if ($situacaoElegibilidade !== '' && !in_array($situacaoElegibilidade, array('apto', 'pendente', 'nao_apto', 'em_andamento', 'certificado_emitido'), true)) {
            $situacaoElegibilidade = '';
        }
        if ($filtroCertificado !== '' && !in_array($filtroCertificado, array('com_certificado', 'sem_certificado'), true)) {
            $filtroCertificado = '';
        }
        if ($tipoPendencia !== '' && !in_array($tipoPendencia, array('progresso', 'atividades', 'presenca', 'avaliacao', 'aguardando_correcao'), true)) {
            $tipoPendencia = '';
        }

        return array(
            'busca' => $busca,
            'status_inscricao' => $statusInscricao,
            'faixa_progresso' => $faixaProgresso,
            'atividade_id' => $atividadeId,
            'status_correcao' => $statusCorrecao,
            'situacao_elegibilidade' => $situacaoElegibilidade,
            'filtro_certificado' => $filtroCertificado,
            'tipo_pendencia' => $tipoPendencia,
        );
    }

    private function resumoConteudo($cursoId, $turmaId = null)
    {
        $paramsModulos = array();
        $clauseModulos = $this->buildContextClause('m', $cursoId, $turmaId, $paramsModulos, 'mod_');

        $paramsAulas = array();
        $clauseAulasModulo = $this->buildContextClause('m', $cursoId, $turmaId, $paramsAulas, 'aul_m_');
        $clauseAulas = $this->buildContextClause('a', $cursoId, $turmaId, $paramsAulas, 'aul_a_');

        $paramsMateriais = array();
        $clauseMateriaisModulo = $this->buildContextClause('m', $cursoId, $turmaId, $paramsMateriais, 'mat_m_');
        $clauseMateriaisAula = $this->buildContextClause('a', $cursoId, $turmaId, $paramsMateriais, 'mat_a_');
        $clauseMateriais = $this->buildContextClause('mt', $cursoId, $turmaId, $paramsMateriais, 'mat_');

        $paramsAtividades = array();
        $clauseAtividadesModulo = $this->buildContextClause('m', $cursoId, $turmaId, $paramsAtividades, 'atv_m_');
        $clauseAtividadesAula = $this->buildContextClause('a', $cursoId, $turmaId, $paramsAtividades, 'atv_a_');
        $clauseAtividades = $this->buildContextClause('atv', $cursoId, $turmaId, $paramsAtividades, 'atv_');

        $paramsCertificados = array();
        $clauseCertificados = $this->buildContextClause('c', $cursoId, $turmaId, $paramsCertificados, 'cert_');

        $sql = 'SELECT
                    (SELECT COUNT(*)
                     FROM modulos m
                     WHERE m.deleted_at IS NULL
                       AND m.status = "publicado"
                       AND ' . $clauseModulos . ') AS modulos_publicados,
                    (SELECT COUNT(*)
                     FROM aulas a
                     INNER JOIN modulos m ON m.id = a.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                     WHERE a.deleted_at IS NULL
                       AND a.status = "publicado"
                       AND ' . $clauseAulasModulo . '
                       AND ' . $clauseAulas . ') AS aulas_publicadas,
                    (SELECT COUNT(*)
                     FROM materiais mt
                     INNER JOIN aulas a ON a.id = mt.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                     INNER JOIN modulos m ON m.id = mt.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                     WHERE mt.deleted_at IS NULL
                       AND mt.status = "publicado"
                       AND ' . $clauseMateriaisModulo . '
                       AND ' . $clauseMateriaisAula . '
                       AND ' . $clauseMateriais . ') AS materiais_publicados,
                    (SELECT COUNT(*)
                     FROM atividades atv
                     INNER JOIN aulas a ON a.id = atv.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                     INNER JOIN modulos m ON m.id = atv.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                     WHERE atv.deleted_at IS NULL
                       AND atv.status = "publicado"
                       AND ' . $clauseAtividadesModulo . '
                       AND ' . $clauseAtividadesAula . '
                       AND ' . $clauseAtividades . ') AS atividades_publicadas,
                    (SELECT COUNT(*)
                     FROM certificados c
                     WHERE c.deleted_at IS NULL
                       AND c.status = "emitido"
                       AND ' . $clauseCertificados . ') AS certificados_emitidos';

        $row = $this->queryRow($sql, array_merge($paramsModulos, $paramsAulas, $paramsMateriais, $paramsAtividades, $paramsCertificados));

        return array(
            'modulos_publicados' => (int) ($row['modulos_publicados'] ?? 0),
            'aulas_publicadas' => (int) ($row['aulas_publicadas'] ?? 0),
            'materiais_publicados' => (int) ($row['materiais_publicados'] ?? 0),
            'atividades_publicadas' => (int) ($row['atividades_publicadas'] ?? 0),
            'certificados_emitidos' => (int) ($row['certificados_emitidos'] ?? 0),
        );
    }

    private function inscricoesValidas($cursoId, $turmaId = null, array $filtros = array())
    {
        $params = array();
        $clause = $this->buildContextClause('i', $cursoId, $turmaId, $params, 'ins_');

        $sql = 'SELECT i.id AS inscricao_id,
                       i.usuario_id,
                       i.curso_evento_id,
                       i.turma_id,
                       i.status AS inscricao_status,
                       i.percentual_progresso AS inscricao_percentual,
                       i.concluida_em,
                       i.updated_at AS inscricao_updated_at,
                       u.nome AS aluno_nome,
                       u.email AS aluno_email,
                       u.cpf AS aluno_cpf,
                       u.telefone AS aluno_telefone,
                       t.nome AS turma_nome,
                       p.status AS pedido_status,
                       cp.status AS comprovante_status,
                       c.id AS certificado_id,
                       c.codigo AS certificado_codigo,
                       c.status AS certificado_status,
                       c.emitido_em AS certificado_emitido_em
                FROM inscricoes i
                INNER JOIN pedidos p ON p.id = i.pedido_id
                INNER JOIN usuarios u ON u.id = i.usuario_id
                LEFT JOIN turmas t ON t.id = i.turma_id
                LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                LEFT JOIN certificados c ON c.inscricao_id = i.id AND c.deleted_at IS NULL AND c.status = "emitido"
                WHERE i.deleted_at IS NULL
                  AND i.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                  AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                  AND ' . $clause;

        if (!empty($filtros['status_inscricao'])) {
            $sql .= ' AND i.status = :status_inscricao';
            $params['status_inscricao'] = $filtros['status_inscricao'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= ' AND (u.nome LIKE :busca OR u.email LIKE :busca OR u.cpf LIKE :busca)';
            $params['busca'] = '%' . $this->escapeLike($filtros['busca']) . '%';
        }

        $sql .= ' ORDER BY u.nome ASC, i.id DESC';

        $rows = $this->queryAll($sql, $params);

        return $rows;
    }

    private function resumoProgresso($cursoId, $turmaId = null)
    {
        $params = array();
        $clauseInscricao = $this->buildContextClause('i', $cursoId, $turmaId, $params, 'prog_i_');
        $clauseAula = $this->buildContextClause('a', $cursoId, $turmaId, $params, 'prog_a_');
        $clauseModulo = $this->buildContextClause('m', $cursoId, $turmaId, $params, 'prog_m_');

        $sql = 'SELECT COUNT(DISTINCT pa.inscricao_id) AS alunos_com_progresso_registrado
                FROM progresso_usuario_aulas pa
                INNER JOIN inscricoes i ON i.id = pa.inscricao_id
                INNER JOIN pedidos p ON p.id = i.pedido_id
                LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                INNER JOIN aulas a ON a.id = pa.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                INNER JOIN modulos m ON m.id = a.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                WHERE pa.concluido = 1
                  AND i.deleted_at IS NULL
                  AND i.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                  AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                  AND ' . $clauseInscricao . '
                  AND ' . $clauseAula . '
                  AND ' . $clauseModulo;

        $row = $this->queryRow($sql, $params);
        return array(
            'alunos_com_progresso_registrado' => (int) ($row['alunos_com_progresso_registrado'] ?? 0),
        );
    }

    private function resumoEntregas($cursoId, $turmaId = null)
    {
        $params = array();
        $clauseInscricao = $this->buildContextClause('i', $cursoId, $turmaId, $params, 'ent_i_');
        $clauseAtividade = $this->buildContextClause('atv', $cursoId, $turmaId, $params, 'ent_atv_');
        $clauseAula = $this->buildContextClause('a', $cursoId, $turmaId, $params, 'ent_a_');
        $clauseModulo = $this->buildContextClause('m', $cursoId, $turmaId, $params, 'ent_m_');

        $sql = 'SELECT
                    COUNT(*) AS entregas_enviadas,
                    SUM(CASE WHEN ae.status = "corrigida" THEN 1 ELSE 0 END) AS entregas_corrigidas,
                    SUM(CASE WHEN ae.status IN ("enviada", "reenviada", "atrasada", "devolvida") THEN 1 ELSE 0 END) AS entregas_pendentes_correcao
                FROM atividades_entregas ae
                INNER JOIN atividades atv ON atv.id = ae.atividade_id AND atv.deleted_at IS NULL AND atv.status = "publicado"
                INNER JOIN aulas a ON a.id = atv.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                INNER JOIN modulos m ON m.id = atv.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                INNER JOIN inscricoes i ON i.usuario_id = ae.usuario_id
                    AND i.curso_evento_id = ae.curso_evento_id
                    AND ((i.turma_id IS NULL AND ae.turma_id IS NULL) OR i.turma_id = ae.turma_id)
                INNER JOIN pedidos p ON p.id = i.pedido_id
                LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                WHERE ae.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                  AND i.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                  AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                  AND ' . $clauseInscricao . '
                  AND ' . $clauseAtividade . '
                  AND ' . $clauseAula . '
                  AND ' . $clauseModulo;

        $row = $this->queryRow($sql, $params);

        return array(
            'entregas_enviadas' => (int) ($row['entregas_enviadas'] ?? 0),
            'entregas_corrigidas' => (int) ($row['entregas_corrigidas'] ?? 0),
            'entregas_pendentes_correcao' => (int) ($row['entregas_pendentes_correcao'] ?? 0),
        );
    }

    private function mapaProgresso($cursoId, $turmaId = null)
    {
        $params = array();
        $clauseInscricao = $this->buildContextClause('i', $cursoId, $turmaId, $params, 'mp_i_');
        $clauseAula = $this->buildContextClause('a', $cursoId, $turmaId, $params, 'mp_a_');
        $clauseModulo = $this->buildContextClause('m', $cursoId, $turmaId, $params, 'mp_m_');

        $sql = 'SELECT pa.inscricao_id,
                       COUNT(DISTINCT pa.aula_id) AS aulas_concluidas,
                       MAX(COALESCE(pa.visualizado_em, pa.concluido_em, pa.updated_at, pa.created_at)) AS ultimo_acesso
                FROM progresso_usuario_aulas pa
                INNER JOIN inscricoes i ON i.id = pa.inscricao_id
                INNER JOIN pedidos p ON p.id = i.pedido_id
                LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                INNER JOIN aulas a ON a.id = pa.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                INNER JOIN modulos m ON m.id = a.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                WHERE pa.concluido = 1
                  AND i.deleted_at IS NULL
                  AND i.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                  AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                  AND ' . $clauseInscricao . '
                  AND ' . $clauseAula . '
                  AND ' . $clauseModulo . '
                GROUP BY pa.inscricao_id';

        $rows = $this->queryAll($sql, $params);
        $map = array();

        foreach ($rows as $row) {
            $map[(int) $row['inscricao_id']] = array(
                'aulas_concluidas' => (int) $row['aulas_concluidas'],
                'ultimo_acesso' => !empty($row['ultimo_acesso']) ? $row['ultimo_acesso'] : null,
            );
        }

        return $map;
    }

    private function mapaEntregas($cursoId, $turmaId = null)
    {
        $params = array();
        $clauseInscricao = $this->buildContextClause('i', $cursoId, $turmaId, $params, 'me_i_');
        $clauseAtividade = $this->buildContextClause('atv', $cursoId, $turmaId, $params, 'me_atv_');
        $clauseAula = $this->buildContextClause('a', $cursoId, $turmaId, $params, 'me_a_');
        $clauseModulo = $this->buildContextClause('m', $cursoId, $turmaId, $params, 'me_m_');

        $sql = 'SELECT ae.usuario_id,
                       ae.curso_evento_id,
                       ae.turma_id,
                       COUNT(*) AS entregas_enviadas,
                       SUM(CASE WHEN ae.status = "corrigida" THEN 1 ELSE 0 END) AS entregas_corrigidas,
                       SUM(CASE WHEN ae.status IN ("enviada", "reenviada", "atrasada", "devolvida") THEN 1 ELSE 0 END) AS entregas_pendentes_correcao,
                       AVG(CASE WHEN ae.status = "corrigida" AND ae.nota IS NOT NULL THEN ae.nota END) AS nota_media,
                       MAX(COALESCE(ae.corrigido_em, ae.entregue_em, ae.updated_at, ae.created_at)) AS ultimo_evento
                FROM atividades_entregas ae
                INNER JOIN atividades atv ON atv.id = ae.atividade_id AND atv.deleted_at IS NULL AND atv.status = "publicado"
                INNER JOIN aulas a ON a.id = atv.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                INNER JOIN modulos m ON m.id = atv.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                INNER JOIN inscricoes i ON i.usuario_id = ae.usuario_id
                    AND i.curso_evento_id = ae.curso_evento_id
                    AND ((i.turma_id IS NULL AND ae.turma_id IS NULL) OR i.turma_id = ae.turma_id)
                INNER JOIN pedidos p ON p.id = i.pedido_id
                LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                WHERE ae.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                  AND i.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                  AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                  AND ' . $clauseInscricao . '
                  AND ' . $clauseAtividade . '
                  AND ' . $clauseAula . '
                  AND ' . $clauseModulo . '
                GROUP BY ae.usuario_id, ae.curso_evento_id, ae.turma_id';

        $rows = $this->queryAll($sql, $params);
        $map = array();

        foreach ($rows as $row) {
            $key = $this->deliveryKey((int) $row['usuario_id'], (int) $row['curso_evento_id'], isset($row['turma_id']) && $row['turma_id'] !== null ? (int) $row['turma_id'] : null);
            $map[$key] = array(
                'entregas_enviadas' => (int) $row['entregas_enviadas'],
                'entregas_corrigidas' => (int) $row['entregas_corrigidas'],
                'entregas_pendentes_correcao' => (int) $row['entregas_pendentes_correcao'],
                'nota_media' => $row['nota_media'] !== null ? (float) $row['nota_media'] : null,
                'ultimo_evento' => !empty($row['ultimo_evento']) ? $row['ultimo_evento'] : null,
            );
        }

        return $map;
    }

    private function montarAlunos(array $inscricoes, array $mapaProgresso, array $mapaEntregas, array $resumoConteudo)
    {
        $alunos = array();
        $totalAulasPublicadas = (int) $resumoConteudo['aulas_publicadas'];
        $totalAtividadesPublicadas = (int) $resumoConteudo['atividades_publicadas'];

        foreach ($inscricoes as $inscricao) {
            $keyEntrega = $this->deliveryKey((int) $inscricao['usuario_id'], (int) $inscricao['curso_evento_id'], isset($inscricao['turma_id']) && $inscricao['turma_id'] !== null ? (int) $inscricao['turma_id'] : null);
            $progresso = isset($mapaProgresso[(int) $inscricao['inscricao_id']]) ? $mapaProgresso[(int) $inscricao['inscricao_id']] : array('aulas_concluidas' => 0, 'ultimo_acesso' => null);
            $entregas = isset($mapaEntregas[$keyEntrega]) ? $mapaEntregas[$keyEntrega] : array('entregas_enviadas' => 0, 'entregas_corrigidas' => 0, 'entregas_pendentes_correcao' => 0, 'nota_media' => null, 'ultimo_evento' => null);
            $aulasConcluidas = (int) $progresso['aulas_concluidas'];
            $progressoPercentual = $totalAulasPublicadas > 0 ? round(($aulasConcluidas / $totalAulasPublicadas) * 100, 2) : 0.00;
            $atividadesEntregues = (int) $entregas['entregas_enviadas'];
            $atividadesPendentes = $totalAtividadesPublicadas > 0 ? max($totalAtividadesPublicadas - $atividadesEntregues, 0) : 0;
            $ultimoAcesso = !empty($progresso['ultimo_acesso']) ? $progresso['ultimo_acesso'] : (!empty($entregas['ultimo_evento']) ? $entregas['ultimo_evento'] : null);
            $certificadoEmitido = !empty($inscricao['certificado_id']) && (string) $inscricao['certificado_status'] === 'emitido';

            $alunos[] = array(
                'inscricao_id' => (int) $inscricao['inscricao_id'],
                'usuario_id' => (int) $inscricao['usuario_id'],
                'aluno_nome' => $inscricao['aluno_nome'],
                'aluno_email' => $inscricao['aluno_email'],
                'aluno_cpf' => $inscricao['aluno_cpf'],
                'turma_nome' => !empty($inscricao['turma_nome']) ? $inscricao['turma_nome'] : 'Curso inteiro',
                'status_inscricao' => $inscricao['inscricao_status'],
                'pedido_status' => $inscricao['pedido_status'],
                'comprovante_status' => $inscricao['comprovante_status'],
                'aulas_concluidas' => $aulasConcluidas,
                'total_aulas_publicadas' => $totalAulasPublicadas,
                'progresso_percentual' => $progressoPercentual,
                'entregas_enviadas' => (int) $entregas['entregas_enviadas'],
                'entregas_corrigidas' => (int) $entregas['entregas_corrigidas'],
                'entregas_pendentes_correcao' => (int) $entregas['entregas_pendentes_correcao'],
                'atividades_entregues' => $atividadesEntregues,
                'atividades_pendentes' => $atividadesPendentes,
                'nota_media' => isset($entregas['nota_media']) ? $this->normalizarNota($entregas['nota_media']) : null,
                'ultimo_acesso' => $ultimoAcesso,
                'certificado_emitido' => $certificadoEmitido,
                'certificado_codigo' => $inscricao['certificado_codigo'],
                'certificado_emitido_em' => $inscricao['certificado_emitido_em'],
                'acesso_liberado' => true,
                'inativo' => $progressoPercentual <= 0 && $atividadesEntregues <= 0,
                'sem_acesso' => empty($ultimoAcesso) && $aulasConcluidas <= 0 && $atividadesEntregues <= 0,
                'sem_certificado' => !$certificadoEmitido,
            );
        }

        return $alunos;
    }

    private function montarAtividades($cursoId, $turmaId = null, array $filtros = array(), $totalEsperado = 0)
    {
        $params = array();
        $clauseAtividade = $this->buildContextClause('atv', $cursoId, $turmaId, $params, 'rel_atv_');
        $clauseAula = $this->buildContextClause('a', $cursoId, $turmaId, $params, 'rel_a_');
        $clauseModulo = $this->buildContextClause('m', $cursoId, $turmaId, $params, 'rel_m_');

        $sql = 'SELECT atv.id,
                       atv.titulo,
                       atv.prazo,
                       atv.nota_maxima,
                       atv.status,
                       atv.visivel,
                       atv.ordem,
                       a.titulo AS aula_titulo,
                       m.titulo AS modulo_titulo,
                       COALESCE(env.total_entregas, 0) AS entregas_enviadas,
                       COALESCE(env.entregas_corrigidas, 0) AS entregas_corrigidas,
                       COALESCE(env.entregas_pendentes_correcao, 0) AS entregas_pendentes_correcao,
                       env.media_nota,
                       env.menor_nota,
                       env.maior_nota
                FROM atividades atv
                INNER JOIN aulas a ON a.id = atv.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                INNER JOIN modulos m ON m.id = atv.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                LEFT JOIN (
                    SELECT ae.atividade_id,
                           COUNT(*) AS total_entregas,
                           SUM(CASE WHEN ae.status = "corrigida" THEN 1 ELSE 0 END) AS entregas_corrigidas,
                           SUM(CASE WHEN ae.status IN ("enviada", "reenviada", "atrasada", "devolvida") THEN 1 ELSE 0 END) AS entregas_pendentes_correcao,
                           AVG(CASE WHEN ae.status = "corrigida" AND ae.nota IS NOT NULL THEN ae.nota END) AS media_nota,
                           MIN(CASE WHEN ae.status = "corrigida" AND ae.nota IS NOT NULL THEN ae.nota END) AS menor_nota,
                           MAX(CASE WHEN ae.status = "corrigida" AND ae.nota IS NOT NULL THEN ae.nota END) AS maior_nota
                    FROM atividades_entregas ae
                    INNER JOIN atividades atv2 ON atv2.id = ae.atividade_id AND atv2.deleted_at IS NULL AND atv2.status = "publicado"
                    INNER JOIN aulas a2 ON a2.id = atv2.aula_id AND a2.deleted_at IS NULL AND a2.status = "publicado"
                    INNER JOIN modulos m2 ON m2.id = atv2.modulo_id AND m2.deleted_at IS NULL AND m2.status = "publicado"
                    INNER JOIN inscricoes i ON i.usuario_id = ae.usuario_id
                        AND i.curso_evento_id = ae.curso_evento_id
                        AND ((i.turma_id IS NULL AND ae.turma_id IS NULL) OR i.turma_id = ae.turma_id)
                    INNER JOIN pedidos p ON p.id = i.pedido_id
                    LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                    WHERE ae.deleted_at IS NULL
                      AND i.deleted_at IS NULL
                      AND i.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                      AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                      AND ' . $this->buildContextClause('atv2', $cursoId, $turmaId, $params, 'rel_sub_atv_') . '
                      AND ' . $this->buildContextClause('a2', $cursoId, $turmaId, $params, 'rel_sub_a_') . '
                      AND ' . $this->buildContextClause('m2', $cursoId, $turmaId, $params, 'rel_sub_m_') . '
                    GROUP BY ae.atividade_id
                ) env ON env.atividade_id = atv.id
                WHERE atv.deleted_at IS NULL
                  AND atv.status = "publicado"
                  AND ' . $clauseAtividade . '
                  AND ' . $clauseAula . '
                  AND ' . $clauseModulo . '
                ORDER BY m.ordem ASC, a.ordem ASC, atv.ordem ASC, atv.id ASC';

        $rows = $this->queryAll($sql, $params);
        $atividades = array();

        foreach ($rows as $row) {
            $atividades[] = array(
                'id' => (int) $row['id'],
                'titulo' => $row['titulo'],
                'aula_titulo' => $row['aula_titulo'],
                'modulo_titulo' => $row['modulo_titulo'],
                'prazo' => $row['prazo'],
                'nota_maxima' => $row['nota_maxima'] !== null ? (float) $row['nota_maxima'] : null,
                'status' => $row['status'],
                'visivel' => !empty($row['visivel']),
                'ordem' => (int) $row['ordem'],
                'total_esperado' => (int) $totalEsperado,
                'entregas_enviadas' => (int) $row['entregas_enviadas'],
                'entregas_corrigidas' => (int) $row['entregas_corrigidas'],
                'entregas_pendentes_correcao' => (int) $row['entregas_pendentes_correcao'],
                'media_nota' => $row['media_nota'] !== null ? (float) $row['media_nota'] : null,
                'menor_nota' => $row['menor_nota'] !== null ? (float) $row['menor_nota'] : null,
                'maior_nota' => $row['maior_nota'] !== null ? (float) $row['maior_nota'] : null,
            );
        }

        return $this->aplicarFiltrosAtividades($atividades, $filtros);
    }

    private function aplicarFiltrosAlunos(array $alunos, array $filtros)
    {
        if (!empty($filtros['situacao_elegibilidade'])) {
            $filtrados = array();
            foreach ($alunos as $aluno) {
                if ((string) ($aluno['elegibilidade_situacao'] ?? '') === (string) $filtros['situacao_elegibilidade']) {
                    $filtrados[] = $aluno;
                }
            }
            $alunos = $filtrados;
        }

        if (!empty($filtros['filtro_certificado'])) {
            $filtrados = array();
            foreach ($alunos as $aluno) {
                if ($filtros['filtro_certificado'] === 'com_certificado' && !empty($aluno['certificado_emitido'])) {
                    $filtrados[] = $aluno;
                }
                if ($filtros['filtro_certificado'] === 'sem_certificado' && empty($aluno['certificado_emitido'])) {
                    $filtrados[] = $aluno;
                }
            }
            $alunos = $filtrados;
        }

        if (!empty($filtros['tipo_pendencia'])) {
            $filtrados = array();
            foreach ($alunos as $aluno) {
                $motivosTexto = mb_strtolower((string) ($aluno['elegibilidade_motivos_texto'] ?? ''), 'UTF-8');
                $temPendencia = false;
                if ($filtros['tipo_pendencia'] === 'progresso') {
                    $temPendencia = strpos($motivosTexto, 'progresso') !== false;
                } elseif ($filtros['tipo_pendencia'] === 'atividades') {
                    $temPendencia = strpos($motivosTexto, 'atividade') !== false;
                } elseif ($filtros['tipo_pendencia'] === 'presenca') {
                    $temPendencia = strpos($motivosTexto, 'presença') !== false || strpos($motivosTexto, 'presenca') !== false;
                } elseif ($filtros['tipo_pendencia'] === 'avaliacao') {
                    $temPendencia = strpos($motivosTexto, 'avaliação') !== false || strpos($motivosTexto, 'avaliacao') !== false;
                } elseif ($filtros['tipo_pendencia'] === 'aguardando_correcao') {
                    $temPendencia = (int) ($aluno['entregas_pendentes_correcao'] ?? 0) > 0 || strpos($motivosTexto, 'aguardando correção') !== false || strpos($motivosTexto, 'aguardando correcao') !== false;
                }

                if ($temPendencia) {
                    $filtrados[] = $aluno;
                }
            }
            $alunos = $filtrados;
        }

        if (!empty($filtros['faixa_progresso'])) {
            $filtrados = array();
            foreach ($alunos as $aluno) {
                $progresso = (float) $aluno['progresso_percentual'];

                if ($filtros['faixa_progresso'] === '0' && $progresso <= 0) {
                    $filtrados[] = $aluno;
                } elseif ($filtros['faixa_progresso'] === '1-49' && $progresso > 0 && $progresso < 50) {
                    $filtrados[] = $aluno;
                } elseif ($filtros['faixa_progresso'] === '50-99' && $progresso >= 50 && $progresso < 100) {
                    $filtrados[] = $aluno;
                } elseif ($filtros['faixa_progresso'] === '100' && $progresso >= 100) {
                    $filtrados[] = $aluno;
                }
            }

            $alunos = $filtrados;
        }

        return $alunos;
    }

    private function montarPainelAptosCertificado(array $alunos, array $criterios)
    {
        $resumo = array(
            'total_alunos' => count($alunos),
            'aptos' => 0,
            'pendentes' => 0,
            'nao_aptos' => 0,
            'em_andamento' => 0,
            'certificado_emitido' => 0,
            'sem_certificado' => 0,
            'aguardando_correcao' => 0,
            'pendencia_progresso' => 0,
            'pendencia_atividade' => 0,
        );

        foreach ($alunos as $aluno) {
            $situacao = (string) ($aluno['elegibilidade_situacao'] ?? 'pendente');
            if ($situacao === 'apto') {
                $resumo['aptos']++;
            } elseif ($situacao === 'pendente') {
                $resumo['pendentes']++;
            } elseif ($situacao === 'nao_apto') {
                $resumo['nao_aptos']++;
            } elseif ($situacao === 'em_andamento') {
                $resumo['em_andamento']++;
            }

            if (!empty($aluno['certificado_emitido'])) {
                $resumo['certificado_emitido']++;
            } else {
                $resumo['sem_certificado']++;
            }

            if ((int) ($aluno['entregas_pendentes_correcao'] ?? 0) > 0) {
                $resumo['aguardando_correcao']++;
            }

            $motivosTexto = mb_strtolower((string) ($aluno['elegibilidade_motivos_texto'] ?? ''), 'UTF-8');
            if (strpos($motivosTexto, 'progresso') !== false) {
                $resumo['pendencia_progresso']++;
            }
            if (strpos($motivosTexto, 'atividade') !== false) {
                $resumo['pendencia_atividade']++;
            }
        }

        return array(
            'resumo' => $resumo,
            'alunos' => $alunos,
            'criterios' => $criterios,
            'aviso' => 'Este painel apenas informa elegibilidade. A emissão de certificado continua pelo fluxo atual.',
        );
    }

    private function montarPendencias(array $alunos, $certificadosEmitidos = 0)
    {
        $semAcesso = array();
        $zeroProgresso = array();
        $atividadesPendentes = array();
        $aguardandoCorrecao = array();
        $semCertificado = array();

        foreach ($alunos as $aluno) {
            if (!empty($aluno['sem_acesso'])) {
                $semAcesso[] = $aluno;
            }

            if ((float) $aluno['progresso_percentual'] <= 0) {
                $zeroProgresso[] = $aluno;
            }

            if ((int) $aluno['atividades_pendentes'] > 0) {
                $atividadesPendentes[] = $aluno;
            }

            if ((int) $aluno['entregas_pendentes_correcao'] > 0) {
                $aguardandoCorrecao[] = $aluno;
            }

            if (empty($aluno['certificado_emitido'])) {
                $semCertificado[] = $aluno;
            }
        }

        return array(
            'sem_acesso' => $semAcesso,
            'zero_progresso' => $zeroProgresso,
            'atividades_pendentes' => $atividadesPendentes,
            'aguardando_correcao' => $aguardandoCorrecao,
            'sem_certificado' => $semCertificado,
            'certificados_emitidos' => (int) $certificadosEmitidos,
        );
    }

    private function aplicarFiltrosAtividades(array $atividades, array $filtros)
    {
        if (!empty($filtros['atividade_id'])) {
            $filtradas = array();
            foreach ($atividades as $atividade) {
                if ((int) $atividade['id'] === (int) $filtros['atividade_id']) {
                    $filtradas[] = $atividade;
                }
            }
            $atividades = $filtradas;
        }

        if (!empty($filtros['status_correcao'])) {
            $filtradas = array();
            foreach ($atividades as $atividade) {
                if ($filtros['status_correcao'] === 'pendente' && (int) $atividade['entregas_pendentes_correcao'] > 0) {
                    $filtradas[] = $atividade;
                } elseif ($filtros['status_correcao'] === 'corrigida' && (int) $atividade['entregas_corrigidas'] > 0) {
                    $filtradas[] = $atividade;
                }
            }
            $atividades = $filtradas;
        }

        return $atividades;
    }

    private function calcularMediaProgresso(array $alunos)
    {
        if (empty($alunos)) {
            return 0.00;
        }

        $total = 0.0;
        foreach ($alunos as $aluno) {
            $total += (float) $aluno['progresso_percentual'];
        }

        return round($total / count($alunos), 2);
    }

    private function buildContextClause($alias, $cursoId, $turmaId, array &$params, $prefix)
    {
        $cursoKey = $prefix . 'curso_evento_id';
        $params[$cursoKey] = (int) $cursoId;
        $sql = $alias . '.curso_evento_id = :' . $cursoKey;

        if ($turmaId !== null) {
            $turmaKey = $prefix . 'turma_id';
            $params[$turmaKey] = (int) $turmaId;
            $sql .= ' AND (' . $alias . '.turma_id = :' . $turmaKey . ' OR ' . $alias . '.turma_id IS NULL)';
        } else {
            $sql .= ' AND ' . $alias . '.turma_id IS NULL';
        }

        return $sql;
    }

    private function deliveryKey($usuarioId, $cursoId, $turmaId = null)
    {
        return $usuarioId . '|' . $cursoId . '|' . ($turmaId === null ? 'null' : $turmaId);
    }

    private function normalizarNota($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return round((float) $valor, 2);
    }

    private function escapeLike($valor)
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string) $valor);
    }

    private function csvProgresso(array $relatorio)
    {
        $headers = array(
            'Aluno',
            'E-mail',
            'Turma',
            'Status da inscrição',
            'Progresso (%)',
            'Aulas concluídas',
            'Total de aulas publicadas',
            'Atividades entregues',
            'Atividades pendentes',
            'Nota média',
            'Último acesso',
            'Certificado emitido',
            'Situação de elegibilidade',
            'Motivos da elegibilidade',
        );

        $rows = array();
        foreach ((isset($relatorio['alunos']) && is_array($relatorio['alunos']) ? $relatorio['alunos'] : array()) as $aluno) {
            $rows[] = array(
                $this->csvSafeValue($aluno['aluno_nome']),
                $this->csvSafeValue($aluno['aluno_email']),
                $this->csvSafeValue($aluno['turma_nome']),
                $this->csvSafeValue($aluno['status_inscricao']),
                $this->csvSafeValue(number_format((float) $aluno['progresso_percentual'], 2, ',', '.')),
                (int) $aluno['aulas_concluidas'],
                (int) $aluno['total_aulas_publicadas'],
                (int) $aluno['atividades_entregues'],
                (int) $aluno['atividades_pendentes'],
                $this->csvSafeValue($aluno['nota_media'] !== null ? number_format((float) $aluno['nota_media'], 2, ',', '.') : ''),
                $this->csvSafeValue(!empty($aluno['ultimo_acesso']) ? date('d/m/Y H:i', strtotime($aluno['ultimo_acesso'])) : ''),
                !empty($aluno['certificado_emitido']) ? 'Sim' : 'Não',
                $this->csvSafeValue($this->humanizarSituacaoElegibilidade((string) ($aluno['elegibilidade_situacao'] ?? 'pendente'))),
                $this->csvSafeValue((string) ($aluno['elegibilidade_motivos_texto'] ?? '')),
            );
        }

        return array(
            'filename' => 'relatorio-progresso-' . date('Ymd-His') . '.csv',
            'content' => $this->buildCsv($headers, $rows),
        );
    }

    private function csvAtividades(array $relatorio)
    {
        $headers = array(
            'Atividade',
            'Aula',
            'Módulo',
            'Prazo',
            'Total esperado',
            'Entregas enviadas',
            'Entregas corrigidas',
            'Entregas pendentes',
            'Média das notas',
            'Menor nota',
            'Maior nota',
        );

        $rows = array();
        foreach ((isset($relatorio['atividades']) && is_array($relatorio['atividades']) ? $relatorio['atividades'] : array()) as $atividade) {
            $rows[] = array(
                $this->csvSafeValue($atividade['titulo']),
                $this->csvSafeValue($atividade['aula_titulo']),
                $this->csvSafeValue($atividade['modulo_titulo']),
                $this->csvSafeValue(!empty($atividade['prazo']) ? date('d/m/Y H:i', strtotime($atividade['prazo'])) : ''),
                (int) $atividade['total_esperado'],
                (int) $atividade['entregas_enviadas'],
                (int) $atividade['entregas_corrigidas'],
                (int) $atividade['entregas_pendentes_correcao'],
                $this->csvSafeValue($atividade['media_nota'] !== null ? number_format((float) $atividade['media_nota'], 2, ',', '.') : ''),
                $this->csvSafeValue($atividade['menor_nota'] !== null ? number_format((float) $atividade['menor_nota'], 2, ',', '.') : ''),
                $this->csvSafeValue($atividade['maior_nota'] !== null ? number_format((float) $atividade['maior_nota'], 2, ',', '.') : ''),
            );
        }

        return array(
            'filename' => 'relatorio-atividades-' . date('Ymd-His') . '.csv',
            'content' => $this->buildCsv($headers, $rows),
        );
    }

    private function csvAptosCertificado(array $relatorio)
    {
        $headers = array(
            'Aluno',
            'E-mail',
            'Turma',
            'Status da inscrição',
            'Progresso (%)',
            'Atividades enviadas',
            'Atividades corrigidas',
            'Média das atividades',
            'Situação de elegibilidade',
            'Certificado emitido',
            'Motivos/Pendências',
        );

        $painel = isset($relatorio['painel_aptos']) && is_array($relatorio['painel_aptos']) ? $relatorio['painel_aptos'] : array();
        $alunos = isset($painel['alunos']) && is_array($painel['alunos']) ? $painel['alunos'] : array();
        $rows = array();

        foreach ($alunos as $aluno) {
            $rows[] = array(
                $this->csvSafeValue((string) ($aluno['aluno_nome'] ?? '')),
                $this->csvSafeValue((string) ($aluno['aluno_email'] ?? '')),
                $this->csvSafeValue((string) ($aluno['turma_nome'] ?? '')),
                $this->csvSafeValue((string) ($aluno['status_inscricao'] ?? '')),
                $this->csvSafeValue(number_format((float) ($aluno['progresso_percentual'] ?? 0), 2, ',', '.')),
                (int) ($aluno['atividades_entregues'] ?? 0),
                (int) ($aluno['entregas_corrigidas'] ?? 0),
                $this->csvSafeValue(isset($aluno['nota_media']) && $aluno['nota_media'] !== null ? number_format((float) $aluno['nota_media'], 2, ',', '.') : ''),
                $this->csvSafeValue($this->humanizarSituacaoElegibilidade((string) ($aluno['elegibilidade_situacao'] ?? 'pendente'))),
                !empty($aluno['certificado_emitido']) ? 'Sim' : 'Não',
                $this->csvSafeValue((string) ($aluno['elegibilidade_motivos_texto'] ?? '')),
            );
        }

        return array(
            'filename' => 'relatorio-aptos-certificado-' . date('Ymd-His') . '.csv',
            'content' => $this->buildCsv($headers, $rows),
        );
    }

    private function buildCsv(array $headers, array $rows)
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function csvSafeValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        if ($value !== '' && preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    private function humanizarSituacaoElegibilidade($situacao)
    {
        $mapa = array(
            'em_andamento' => 'Em andamento',
            'pendente' => 'Pendente',
            'apto' => 'Apto',
            'nao_apto' => 'Não apto',
            'certificado_emitido' => 'Certificado emitido',
        );

        return isset($mapa[$situacao]) ? $mapa[$situacao] : 'Pendente';
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
}
