<?php use App\Core\Helpers; ?>

<?php
$curso = isset($curso) && is_array($curso) ? $curso : null;
$turma = isset($turma) && is_array($turma) ? $turma : null;
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$turmas = isset($turmas) && is_array($turmas) ? $turmas : array();
$modulos = isset($modulos) && is_array($modulos) ? $modulos : array();
$materiais = isset($materiais) && is_array($materiais) ? $materiais : array();
$links = isset($links) && is_array($links) ? $links : array();
$atividades = isset($atividades) && is_array($atividades) ? $atividades : array();
$participantes = isset($participantes) && is_array($participantes) ? $participantes : array();
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array();
$tabs = isset($tabs) && is_array($tabs) ? $tabs : array();
$modalidades = isset($modalidades) && is_array($modalidades) ? $modalidades : array('presencial', 'online_ao_vivo', 'hibrido', 'sob_demanda');
$selected_tab = isset($selected_tab) && $selected_tab !== '' ? (string) $selected_tab : 'visao-geral';
$cursosFiltros = isset($cursos_filtros) && is_array($cursos_filtros) ? $cursos_filtros : array();
$cursosPagination = isset($cursos_pagination) && is_array($cursos_pagination) ? $cursos_pagination : array('total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1);
$cursosStatusTotals = isset($cursos_status_totals) && is_array($cursos_status_totals) ? $cursos_status_totals : array();
$turmasLista = isset($turmas_listadas) && is_array($turmas_listadas) ? $turmas_listadas : array();
$turmasPagination = isset($turmas_pagination) && is_array($turmas_pagination) ? $turmas_pagination : array('total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1);
$turmasFiltros = isset($turmas_filtros) && is_array($turmas_filtros) ? $turmas_filtros : array();

$can_manage_turmas = !empty($can_manage_turmas);
$can_delete_conteudo_definitivo = !empty($can_delete_conteudo_definitivo);

$conteudo_modulos = isset($conteudo_modulos) && is_array($conteudo_modulos) ? $conteudo_modulos : array();
$conteudo_modulos_arquivados = isset($conteudo_modulos_arquivados) && is_array($conteudo_modulos_arquivados) ? $conteudo_modulos_arquivados : array();
$conteudo_modulo_selecionado = isset($conteudo_modulo_selecionado) && is_array($conteudo_modulo_selecionado) ? $conteudo_modulo_selecionado : null;
$conteudo_modulo_itens = isset($conteudo_modulo_itens) && is_array($conteudo_modulo_itens) ? $conteudo_modulo_itens : array();
$conteudo_modulo_itens_arquivados = isset($conteudo_modulo_itens_arquivados) && is_array($conteudo_modulo_itens_arquivados) ? $conteudo_modulo_itens_arquivados : array();
$conteudo_modulo_selecionado_id = isset($conteudo_modulo_selecionado_id) ? (int) $conteudo_modulo_selecionado_id : 0;
$conteudo_modo_modulo = !empty($conteudo_modo_modulo);
$conteudo_modulo_erro = isset($conteudo_modulo_erro) ? (string) $conteudo_modulo_erro : '';
$cursoSolicitado = !empty($curso_solicitado);

$instrucaoEditar = isset($instrucao_selecionada) ? $instrucao_selecionada : null;
$moduloEditar = isset($modulo_selecionado) ? $modulo_selecionado : null;
$aulaEditar = isset($aula_selecionada) ? $aula_selecionada : null;
$materialEditar = isset($material_selecionado) ? $material_selecionado : null;
$linkEditar = isset($link_selecionado) ? $link_selecionado : null;
$selectedTab = isset($selected_tab) && $selected_tab !== '' ? $selected_tab : 'visao-geral';
$abasLms = isset($tabs) && !empty($tabs) ? $tabs : array(
    array('slug' => 'visao-geral', 'label' => 'Visão geral'),
    array('slug' => 'turmas', 'label' => 'Turmas'),
    array('slug' => 'conteudo', 'label' => 'Conteúdo'),
    array('slug' => 'participantes', 'label' => 'Participantes'),
    array('slug' => 'presenca', 'label' => 'Presença'),
    array('slug' => 'avaliacoes-notas', 'label' => 'Avaliações / Notas'),
    array('slug' => 'certificados', 'label' => 'Certificados'),
    array('slug' => 'relatorios', 'label' => 'Relatórios'),
    array('slug' => 'configuracoes', 'label' => 'Configurações'),
    array('slug' => 'aptos-certificado', 'label' => 'Aptos para certificado'),
);
$abasMenu = array_values(array_filter($abasLms, function ($abaItem) {
    $slug = isset($abaItem['slug']) ? (string) $abaItem['slug'] : '';
    return !in_array($slug, array('modulos-aulas', 'materiais', 'atividades'), true);
}));
$quantidadeModulos = !empty($modulos) ? count($modulos) : 0;
$quantidadeAulas = 0;

if (!empty($modulos)) {
    foreach ($modulos as $modulo) {
        $quantidadeAulas += !empty($modulo['aulas']) ? count($modulo['aulas']) : 0;
    }
}

$quantidadeMateriais = !empty($materiais) ? count($materiais) : 0;
$quantidadeLinks = !empty($links) ? count($links) : 0;
$quantidadeAtividades = !empty($atividades) ? count($atividades) : 0;
$quantidadeParticipantes = !empty($participantes) ? count($participantes) : 0;
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array('modulos' => 0, 'aulas' => 0, 'atividades' => 0, 'participantes' => 0, 'certificados' => 0, 'turmas' => 0);

if (!function_exists('areaCursoHeadingWithTooltip')) {
    function areaCursoHeadingWithTooltip($titulo, $ajuda = '', $nivel = 'h2')
    {
        static $contadorTooltip = 0;
        $contadorTooltip++;

        $nivel = in_array($nivel, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true) ? $nivel : 'h2';
        $tituloSeguro = \App\Core\Helpers::e((string) $titulo);

        if ($ajuda === '' || $ajuda === null) {
            return '<' . $nivel . ' class="area-curso-heading__title">' . $tituloSeguro . '</' . $nivel . '>';
        }

        $tooltipId = 'area-curso-tooltip-' . $contadorTooltip;

        return '<' . $nivel . ' class="area-curso-heading">'
            . '<span class="area-curso-heading__text">' . $tituloSeguro . '</span>'
            . '<span class="area-curso-tooltip">'
            . '<button type="button" class="area-curso-tooltip__button" aria-describedby="' . $tooltipId . '" aria-label="Mais informações sobre ' . $tituloSeguro . '">'
            . '<span class="u-sr-only">Mais informações</span>'
            . 'i'
            . '</button>'
            . '<span id="' . $tooltipId . '" class="area-curso-tooltip__bubble" role="tooltip">' . \App\Core\Helpers::e((string) $ajuda) . '</span>'
            . '</span>'
            . '</' . $nivel . '>';
    }
}

if (!function_exists('areaCursoQueryString')) {
    function areaCursoQueryString(array $params)
    {
        $query = array();
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query[$key] = $value;
        }

        return !empty($query) ? ('?' . http_build_query($query)) : '';
    }
}
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Área interna do curso</h1>
            <p class="admin-page__subtitle">Administração consolidada do curso com foco na aba Conteúdo.</p>
        </div>
    </section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<?php if (empty($curso)): ?>
    <?php
    $cursosPage = (int) ($cursosPagination['page'] ?? 1);
    $cursosPages = (int) ($cursosPagination['pages'] ?? 1);
    $cursosTotal = (int) ($cursosPagination['total'] ?? 0);
    $cursosPerPage = (int) ($cursosPagination['per_page'] ?? 20);
    $cursosFrom = $cursosTotal > 0 ? (($cursosPage - 1) * $cursosPerPage) + 1 : 0;
    $cursosTo = $cursosTotal > 0 ? min($cursosPage * $cursosPerPage, $cursosTotal) : 0;
    $cursosStatusAtual = (string) ($cursosFiltros['status'] ?? '');
    $cursosCategoriaAtual = (int) ($cursosFiltros['categoria_id'] ?? 0);
    $cursosTipoAtual = (string) ($cursosFiltros['tipo'] ?? '');
    $cursosModalidadeAtual = (string) ($cursosFiltros['modalidade'] ?? '');
    $cursosBuscaAtual = (string) ($cursosFiltros['busca'] ?? '');
    $cursosStatusAtivos = (int) ($cursosStatusTotals['ativo'] ?? 0);
    $cursosStatusRascunho = (int) ($cursosStatusTotals['rascunho'] ?? 0);
    $cursosStatusInativos = (int) (($cursosStatusTotals['inativo'] ?? 0) + ($cursosStatusTotals['arquivado'] ?? 0));
    $cursosTipoOptions = array(
        '' => 'Todos',
        'curso' => 'Curso',
        'evento' => 'Evento',
    );
    $cursosStatusOptions = array(
        '' => 'Todos',
        'ativo' => 'Ativo',
        'rascunho' => 'Rascunho',
        'inativo' => 'Inativo',
        'arquivado' => 'Arquivado',
    );
    $cursosModalidadeOptions = array('' => 'Todas');
    foreach ($modalidades as $modalidade) {
        $cursosModalidadeOptions[$modalidade] = Helpers::modalidadeCurso($modalidade);
    }
    ?>
    <section class="status-card" style="margin-bottom:12px;">
        <form method="get" action="/admin/area-curso" class="admin-filters">
            <div class="admin-filters__row">
                <label>Busca
                    <input type="text" name="cursos_busca" value="<?php echo Helpers::e($cursosBuscaAtual); ?>" placeholder="Nome, slug ou ID do curso">
                </label>
                <label>Categoria
                    <select name="cursos_categoria_id">
                        <option value="0">Todas</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo (int) $categoria['id']; ?>" <?php echo $cursosCategoriaAtual === (int) $categoria['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($categoria['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="cursos_status">
                        <?php foreach ($cursosStatusOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo $cursosStatusAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="admin-filters__row" style="margin-top:12px;">
                <label>Tipo
                    <select name="cursos_tipo">
                        <?php foreach ($cursosTipoOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo $cursosTipoAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Modalidade
                    <select name="cursos_modalidade">
                        <?php foreach ($cursosModalidadeOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo $cursosModalidadeAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="cta-group" style="margin-top:12px;">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/area-curso">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card" style="margin-bottom:12px;">
        <div class="cta-group" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <small><?php echo $cursosTotal > 0 ? ('Exibindo ' . $cursosFrom . ' a ' . $cursosTo . ' de ' . $cursosTotal . ' cursos.') : 'Nenhum curso encontrado.'; ?></small>
            <small>Ativos: <?php echo $cursosStatusAtivos; ?> | Rascunhos: <?php echo $cursosStatusRascunho; ?> | Inativos: <?php echo $cursosStatusInativos; ?></small>
        </div>
    </section>

    <?php if ($cursosPages > 1): ?>
        <section class="status-card" style="margin-bottom:12px;">
            <div class="cta-group" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <small>Página <?php echo (int) $cursosPage; ?> de <?php echo (int) $cursosPages; ?>.</small>
                <div class="cta-group">
                    <?php if ($cursosPage > 1): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/area-curso' . areaCursoQueryString(array(
                            'cursos_busca' => $cursosBuscaAtual,
                            'cursos_categoria_id' => $cursosCategoriaAtual,
                            'cursos_status' => $cursosStatusAtual,
                            'cursos_tipo' => $cursosTipoAtual,
                            'cursos_modalidade' => $cursosModalidadeAtual,
                            'cursos_page' => $cursosPage - 1,
                        ))); ?>">Anterior</a>
                    <?php endif; ?>
                    <?php for ($paginaAtual = 1; $paginaAtual <= $cursosPages; $paginaAtual++): ?>
                        <a class="button-link<?php echo $paginaAtual === $cursosPage ? ' button-link--primary' : ' button-link--ghost'; ?>" href="<?php echo Helpers::e('/admin/area-curso' . areaCursoQueryString(array(
                            'cursos_busca' => $cursosBuscaAtual,
                            'cursos_categoria_id' => $cursosCategoriaAtual,
                            'cursos_status' => $cursosStatusAtual,
                            'cursos_tipo' => $cursosTipoAtual,
                            'cursos_modalidade' => $cursosModalidadeAtual,
                            'cursos_page' => $paginaAtual,
                        ))); ?>"><?php echo (int) $paginaAtual; ?></a>
                    <?php endfor; ?>
                    <?php if ($cursosPage < $cursosPages): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/area-curso' . areaCursoQueryString(array(
                            'cursos_busca' => $cursosBuscaAtual,
                            'cursos_categoria_id' => $cursosCategoriaAtual,
                            'cursos_status' => $cursosStatusAtual,
                            'cursos_tipo' => $cursosTipoAtual,
                            'cursos_modalidade' => $cursosModalidadeAtual,
                            'cursos_page' => $cursosPage + 1,
                        ))); ?>">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($cursoSolicitado): ?>
        <section class="status-card">
            <h2>Curso não encontrado</h2>
            <p>Não foi possível localizar o curso informado ou ele pode ter sido removido.</p>
            <a class="button-link button-link--primary" href="/admin/cursos">Voltar para cursos</a>
        </section>
    <?php endif; ?>
    <section class="status-card admin-area-curso__selector">
        <div class="panel-header">
            <h2>Selecione um curso para administrar sua área interna</h2>
        </div>
        <?php
        $cursosAtivos = array();
        $cursosRascunho = array();
        $cursosInativos = array();
        if (!empty($cursos)) {
            foreach ($cursos as $c) {
                if (!empty($c['status']) && $c['status'] === 'ativo') {
                    $cursosAtivos[] = $c;
                } elseif (!empty($c['status']) && $c['status'] === 'rascunho') {
                    $cursosRascunho[] = $c;
                } else {
                    $cursosInativos[] = $c;
                }
            }
        }
        ?>
        <?php if (!empty($cursosAtivos)): ?>
            <div class="table-wrap">
                <table class="admin-table admin-table--area-cursos">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Modalidade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursosAtivos as $item): ?>
                            <tr>
                                <td><span class="muted">#<?php echo (int) $item['id']; ?></span></td>
                                <td><a href="/admin/area-curso?curso_id=<?php echo (int) $item['id']; ?>"><?php echo Helpers::e($item['nome']); ?></a></td>
                                <td><span class="badge"><?php echo Helpers::e($item['tipo']); ?></span></td>
                                <td><span class="badge"><?php echo Helpers::e(Helpers::modalidadeCurso($item['modalidade'])); ?></span></td>
                                <td><span class="badge badge--success">Ativo</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Nenhum curso ativo disponível para edição no momento.</p>
        <?php endif; ?>
        <?php if (!empty($cursosRascunho)): ?>
            <section class="admin-area-curso__drafts">
                <div class="panel-header">
                    <h2>Cursos e eventos em rascunho</h2>
                    <span class="badge badge--warn"><?php echo $cursosStatusRascunho; ?> itens</span>
                </div>
                <div class="table-wrap">
                    <table class="admin-table admin-table--area-cursos admin-table--muted">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Modalidade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cursosRascunho as $item): ?>
                                <tr>
                                    <td><span class="muted">#<?php echo (int) $item['id']; ?></span></td>
                                    <td><a href="/admin/area-curso?curso_id=<?php echo (int) $item['id']; ?>"><?php echo Helpers::e($item['nome']); ?></a></td>
                                    <td><span class="badge"><?php echo Helpers::e($item['tipo']); ?></span></td>
                                    <td><span class="badge"><?php echo Helpers::e(Helpers::modalidadeCurso($item['modalidade'])); ?></span></td>
                                    <td><span class="badge badge--warn">Rascunho</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php else: ?>
            <p class="muted admin-mt-12">Nenhum curso ou evento em rascunho no momento.</p>
        <?php endif; ?>
        <?php if (!empty($cursosInativos)): ?>
            <details class="admin-area-curso__inativos-details admin-mt-12">
                <summary class="panel-header">
                    <div>
                        <h2>Cursos inativos</h2>
                        <span class="badge"><?php echo $cursosStatusInativos; ?> itens</span>
                    </div>
                </summary>
                <div class="table-wrap admin-mt-8">
                    <table class="admin-table admin-table--area-cursos admin-table--muted">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Modalidade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cursosInativos as $item): ?>
                                <tr>
                                    <td><span class="muted">#<?php echo (int) $item['id']; ?></span></td>
                                    <td><a href="/admin/area-curso?curso_id=<?php echo (int) $item['id']; ?>"><?php echo Helpers::e($item['nome']); ?></a></td>
                                    <td><span class="badge"><?php echo Helpers::e($item['tipo']); ?></span></td>
                                    <td><span class="badge"><?php echo Helpers::e(Helpers::modalidadeCurso($item['modalidade'])); ?></span></td>
                                    <td><span class="badge badge--warn"><?php echo Helpers::e(ucfirst(str_replace('_', ' ', $item['status'] ?? 'rascunho'))); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </section>
    <?php return; ?>
<?php else: ?>
    <section class="status-card area-curso-workspace__header">
        <div class="area-curso-header">
            <div class="area-curso-header__content">
                <p class="area-curso-header__eyebrow">Área interna do curso</p>
                <h1 class="area-curso-header__title"><?php echo Helpers::e($curso['nome'] ?? 'Curso'); ?></h1>
                <div class="area-curso-header__meta">
                    <span class="badge">#<?php echo (int) $curso['id']; ?></span>
                    <span class="badge badge--success"><?php echo Helpers::e(ucfirst((string) ($curso['status'] ?? ''))); ?></span>
                    <span class="badge"><?php echo Helpers::e((string) ($curso['tipo'] ?? '')); ?></span>
                    <span class="badge"><?php echo Helpers::e(Helpers::modalidadeCurso($curso['modalidade'] ?? '')); ?></span>
                    <?php if (!empty($curso['categoria_nome'])): ?><span class="badge"><?php echo Helpers::e($curso['categoria_nome']); ?></span><?php endif; ?>
                    <?php if (!empty($curso['professor_responsavel']['nome'])): ?><span class="badge"><?php echo Helpers::e($curso['professor_responsavel']['nome']); ?></span><?php endif; ?>
                </div>
            </div>
            <div class="area-curso-header__actions">
                <a class="button-link button-link--ghost" href="/admin/area-curso">Trocar curso</a>
            </div>
        </div>
    </section>

    <section class="status-card area-curso-workspace__tabs">
        <div class="area-curso-tabs__mobile">
            <button type="button" class="button-link button-link--ghost area-curso-tabs__toggle" aria-expanded="false" aria-controls="area-curso-tabs-list" data-area-curso-tabs-toggle>
                Menu do curso
            </button>
        </div>
        <div class="area-curso-tabs" id="area-curso-tabs-list" data-area-curso-tabs-list hidden>
            <div class="area-curso-tabs__row">
                <?php foreach ($abasMenu as $aba): ?>
                    <a class="area-curso-tabs__link<?php echo $selectedTab === $aba['slug'] ? ' is-active' : ''; ?>" href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=<?php echo urlencode($aba['slug']); ?>">
                        <?php echo Helpers::e($aba['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="area-curso-workspace">
        <section class="status-card admin-area-curso__section admin-area-curso__overview area-curso-tab-panel<?php echo $selectedTab === 'visao-geral' ? ' is-active' : ''; ?>" data-area-curso-tab="visao-geral" id="area-curso-visao-geral">
            <div class="panel-header">
                <div>
                    <?php echo areaCursoHeadingWithTooltip('Visão geral', 'Resumo rápido do contexto pedagógico deste curso.'); ?>
                </div>
            </div>

                <div class="area-curso-overview-grid">
                    <div class="area-curso-overview-card">
                        <small>Curso</small>
                        <strong><?php echo Helpers::e($curso['nome'] ?? ''); ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Turma</small>
                        <strong><?php echo !empty($turma['nome']) ? Helpers::e($turma['nome']) : 'Curso inteiro'; ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Turmas</small>
                        <strong><?php echo (int) $resumo['turmas']; ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Módulos</small>
                        <strong><?php echo (int) $resumo['modulos']; ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Aulas</small>
                        <strong><?php echo (int) $resumo['aulas']; ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Materiais</small>
                        <strong><?php echo (int) $resumo['materiais']; ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Atividades</small>
                        <strong><?php echo (int) $resumo['atividades']; ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Alunos inscritos</small>
                        <strong><?php echo (int) $resumo['participantes']; ?></strong>
                    </div>
                    <div class="area-curso-overview-card">
                        <small>Certificados</small>
                        <strong><?php echo (int) $resumo['certificados']; ?></strong>
                    </div>
                </div>
            </section>

            <?php require BASE_PATH . '/resources/views/admin/area-curso/_turmas.php'; ?>

            <?php $areaCursoBaseUrl = '/admin/area-curso'; require BASE_PATH . '/resources/views/admin/area-curso/_conteudo.php'; ?>

            <?php require BASE_PATH . '/resources/views/admin/area-curso/_materiais.php'; ?>

            <?php $areaCursoBaseUrl = '/admin/area-curso'; require BASE_PATH . '/resources/views/admin/area-curso/_atividades.php'; ?>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder area-curso-tab-panel<?php echo $selectedTab === 'participantes' ? ' is-active' : ''; ?>" data-area-curso-tab="participantes" id="area-curso-participantes">
                <div class="panel-header">
                    <div>
                        <h2>Participantes</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder area-curso-tab-panel<?php echo $selectedTab === 'presenca' ? ' is-active' : ''; ?>" data-area-curso-tab="presenca" id="area-curso-presenca">
                <div class="panel-header">
                    <div>
                        <h2>Presença</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <?php $areaCursoBaseUrl = '/admin/area-curso'; require BASE_PATH . '/resources/views/admin/area-curso/_avaliacoes_notas_conteudo.php'; ?>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder area-curso-tab-panel<?php echo $selectedTab === 'certificados' ? ' is-active' : ''; ?>" data-area-curso-tab="certificados" id="area-curso-certificados">
                <div class="panel-header">
                    <div>
                        <h2>Certificados</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <?php require BASE_PATH . '/resources/views/admin/area-curso/_relatorios.php'; ?>

            <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'configuracoes' ? ' is-active' : ''; ?>" data-area-curso-tab="configuracoes" id="area-curso-configuracoes">
                <div class="panel-header">
                    <div>
                        <?php echo areaCursoHeadingWithTooltip('Critérios de conclusão', 'Esta configuração calcula a elegibilidade e não emite certificado automaticamente.'); ?>
                    </div>
                </div>
                <?php $criterios = isset($criterios_conclusao) && is_array($criterios_conclusao) ? $criterios_conclusao : array(); ?>
                <form method="post" action="/admin/area-curso/criterios-conclusao" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="curso_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="aba" value="configuracoes">

                    <label class="checkbox"><input type="checkbox" name="exigir_progresso" value="1" <?php echo !empty($criterios['exigir_progresso']) ? 'checked' : ''; ?>> Exigir conclusão de aulas</label>
                    <label>Percentual mínimo de progresso
                        <input type="number" name="progresso_minimo" min="0" max="100" step="0.01" value="<?php echo Helpers::e(number_format((float) ($criterios['progresso_minimo'] ?? 75), 2, '.', '')); ?>">
                    </label>

                    <label class="checkbox"><input type="checkbox" name="exigir_atividades" value="1" <?php echo !empty($criterios['exigir_atividades']) ? 'checked' : ''; ?>> Exigir atividades</label>
                    <label>Critério de atividades
                        <select name="criterio_atividades">
                            <?php $criterioAtividades = isset($criterios['criterio_atividades']) ? (string) $criterios['criterio_atividades'] : 'nenhuma'; ?>
                            <option value="nenhuma" <?php echo $criterioAtividades === 'nenhuma' ? 'selected' : ''; ?>>Nenhuma exigência</option>
                            <option value="todas_enviadas" <?php echo $criterioAtividades === 'todas_enviadas' ? 'selected' : ''; ?>>Todas enviadas</option>
                            <option value="todas_corrigidas" <?php echo $criterioAtividades === 'todas_corrigidas' ? 'selected' : ''; ?>>Todas corrigidas</option>
                            <option value="media_minima" <?php echo $criterioAtividades === 'media_minima' ? 'selected' : ''; ?>>Média mínima</option>
                        </select>
                    </label>
                    <label>Nota mínima nas atividades
                        <input type="number" name="nota_minima_atividades" min="0" step="0.01" value="<?php echo Helpers::e(isset($criterios['nota_minima_atividades']) && $criterios['nota_minima_atividades'] !== null ? number_format((float) $criterios['nota_minima_atividades'], 2, '.', '') : ''); ?>">
                    </label>

                    <label class="checkbox"><input type="checkbox" name="exigir_presenca" value="1" <?php echo !empty($criterios['exigir_presenca']) ? 'checked' : ''; ?>> Exigir presença</label>
                    <label>Percentual mínimo de presença
                        <input type="number" name="presenca_minima" min="0" max="100" step="0.01" value="<?php echo Helpers::e(number_format((float) ($criterios['presenca_minima'] ?? 75), 2, '.', '')); ?>">
                    </label>

                    <label class="checkbox"><input type="checkbox" name="exigir_avaliacao" value="1" <?php echo !empty($criterios['exigir_avaliacao']) ? 'checked' : ''; ?>> Exigir avaliação</label>
                    <label>Nota mínima de avaliação
                        <input type="number" name="nota_minima_avaliacao" min="0" step="0.01" value="<?php echo Helpers::e(isset($criterios['nota_minima_avaliacao']) && $criterios['nota_minima_avaliacao'] !== null ? number_format((float) $criterios['nota_minima_avaliacao'], 2, '.', '') : ''); ?>">
                    </label>

                    <p class="muted full"><?php echo Helpers::e($criterios['aviso_certificado_manual'] ?? 'A conclusão exibida aqui não emite certificado automaticamente.'); ?></p>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=configuracoes';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </section>

            <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'modulos-aulas' ? ' is-active' : ''; ?>" data-area-curso-tab="modulos-aulas" id="area-curso-modulos-aulas">
                <div class="panel-header">
                    <div>
                        <?php echo areaCursoHeadingWithTooltip('Módulos e aulas', 'Administração da estrutura pedagógica principal do curso.'); ?>
                    </div>
                </div>

                <div class="admin-area-curso__actions">
                    <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=conteudo">Novo módulo</a>
                    <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=conteudo">Nova aula</a>
                    <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=conteudo">Materiais</a>
                </div>

            </section>

            <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'modulos-aulas' ? ' is-active' : ''; ?>" data-area-curso-tab="modulos-aulas" id="area-curso-modulos-aulas-instrucoes">
                <div class="panel-header">
                    <div>
                        <?php echo areaCursoHeadingWithTooltip('Próxima ação do checkout', 'Este conteúdo pode ser exibido na página de sucesso do pedido.'); ?>
                    </div>
                </div>
                <form method="post" action="/admin/area-curso/instrucoes" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($instrucaoEditar['id']) ? (int) $instrucaoEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($instrucaoEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">Conteúdo<textarea name="conteudo" rows="4"><?php echo Helpers::e($instrucaoEditar['conteudo'] ?? ''); ?></textarea></label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($instrucaoEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($instrucaoEditar) ? (!empty($instrucaoEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=conteudo';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </section>

            <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'modulos-aulas' ? ' is-active' : ''; ?>" data-area-curso-tab="modulos-aulas" id="area-curso-modulos-aulas-modulos">
                <div class="panel-header">
                    <h2>Módulos</h2>
                    <span class="badge"><?php echo (int) $quantidadeModulos; ?> itens</span>
                </div>
                <form method="post" action="/admin/area-curso/modulos" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($moduloEditar['id']) ? (int) $moduloEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($moduloEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">Descrição<textarea name="descricao" rows="3"><?php echo Helpers::e($moduloEditar['descricao'] ?? ''); ?></textarea></label>
                    <label>Status
                        <select name="status">
                            <?php $statusModulo = 'publicado'; ?>
                            <?php if (!empty($moduloEditar)): ?>
                                <?php $statusModulo = !empty($moduloEditar['status']) ? $moduloEditar['status'] : (!empty($moduloEditar['visivel']) ? 'publicado' : 'oculto'); ?>
                            <?php endif; ?>
                            <option value="rascunho" <?php echo $statusModulo === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="publicado" <?php echo $statusModulo === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="oculto" <?php echo $statusModulo === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                        </select>
                    </label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($moduloEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=conteudo';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>

                <div class="table-wrap admin-mt-12">
                    <table class="admin-table admin-table--area-modulos">
                        <thead><tr><th>Título</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($modulos as $modulo): ?>
                                <tr>
                                    <td><?php echo Helpers::e($modulo['titulo']); ?></td>
                                    <td><?php echo (int) $modulo['ordem']; ?></td>
                                    <td><span class="badge"><?php echo Helpers::e($modulo['status'] ?? (!empty($modulo['visivel']) ? 'publicado' : 'oculto')); ?></span></td>
                                    <td>
                                        <div class="split-actions">
                                            <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&modulo_id=<?php echo (int) $modulo['id']; ?>&aba=conteudo">Editar</a>
                                        </div>
                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form admin-mt-8">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="tipo" value="modulo">
                                            <input type="hidden" name="id" value="<?php echo (int) $modulo['id']; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                            <input type="hidden" name="aba" value="modulos-aulas">
                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                            <button type="submit">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'modulos-aulas' ? ' is-active' : ''; ?>" data-area-curso-tab="modulos-aulas" id="area-curso-modulos-aulas-aulas">
                <div class="panel-header">
                    <h2>Aulas</h2>
                    <span class="badge"><?php echo (int) $quantidadeAulas; ?> itens</span>
                </div>
                <form method="post" action="/admin/area-curso/aulas" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($aulaEditar['id']) ? (int) $aulaEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Módulo
                        <select name="modulo_id">
                            <?php foreach ($modulos as $modulo): ?>
                                <option value="<?php echo (int) $modulo['id']; ?>" <?php echo !empty($aulaEditar) && (int) ($aulaEditar['modulo_id'] ?? 0) === (int) $modulo['id'] ? 'selected' : ''; ?>>
                                    <?php echo Helpers::e($modulo['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($aulaEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">Conteúdo<textarea name="conteudo" rows="3"><?php echo Helpers::e($aulaEditar['conteudo'] ?? ''); ?></textarea></label>
                    <label>Status
                        <?php $statusAula = 'publicado'; ?>
                        <?php if (!empty($aulaEditar)): ?>
                            <?php $statusAula = !empty($aulaEditar['status']) ? $aulaEditar['status'] : (!empty($aulaEditar['visivel']) ? 'publicado' : 'oculto'); ?>
                        <?php endif; ?>
                        <select name="status">
                            <option value="rascunho" <?php echo $statusAula === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="publicado" <?php echo $statusAula === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="oculto" <?php echo $statusAula === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                        </select>
                    </label>
                    <label>Tipo<input type="text" name="tipo" value="<?php echo Helpers::e($aulaEditar['tipo'] ?? 'texto'); ?>"></label>
                    <label>URL vídeo<input type="text" name="url_video" value="<?php echo Helpers::e($aulaEditar['url_video'] ?? ''); ?>"></label>
                    <label>Duração (minutos)<input type="number" name="duracao_minutos" value="<?php echo Helpers::e((string) ($aulaEditar['duracao_minutos'] ?? '')); ?>"></label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($aulaEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <label class="checkbox"><input type="checkbox" name="obrigatoria" value="1" <?php echo !empty($aulaEditar) && !empty($aulaEditar['obrigatoria']) ? 'checked' : ''; ?>> Obrigatória</label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=conteudo';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>

                <div class="table-wrap admin-mt-12">
                    <table class="admin-table admin-table--area-aulas">
                        <thead><tr><th>Título</th><th>Tipo</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($modulos as $modulo): ?>
                                <?php foreach ($modulo['aulas'] as $aula): ?>
                                <tr>
                                    <td><?php echo Helpers::e($aula['titulo']); ?></td>
                                    <td><?php echo Helpers::e($aula['tipo']); ?></td>
                                    <td><?php echo (int) $aula['ordem']; ?></td>
                                    <td><span class="badge"><?php echo Helpers::e($aula['status'] ?? (!empty($aula['visivel']) ? 'publicado' : 'oculto')); ?></span></td>
                                    <td>
                                        <div class="split-actions">
                                            <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aula_id=<?php echo (int) $aula['id']; ?>&aba=conteudo">Editar</a>
                                        </div>
                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form admin-mt-8">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="tipo" value="aula">
                                            <input type="hidden" name="id" value="<?php echo (int) $aula['id']; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                            <input type="hidden" name="aba" value="modulos-aulas">
                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                            <button type="submit">Remover</button>
                                        </form>
                                        <div class="admin-area-curso__aula-materiais">
                                            <div class="admin-area-curso__aula-materiais-head">
                                                <strong>Materiais</strong>
                                                <span class="badge"><?php echo (int) ($aula['total_materiais'] ?? 0); ?></span>
                                            </div>
                                            <div class="admin-area-curso__aula-materiais-actions">
                                                <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&modulo_id=<?php echo (int) $modulo['id']; ?>&aula_id=<?php echo (int) $aula['id']; ?>&aba=conteudo">Adicionar material</a>
                                            </div>
                                            <?php if (!empty($aula['materiais'])): ?>
                                                <div class="admin-area-curso__aula-materiais-list">
                                                    <?php foreach (array_slice($aula['materiais'], 0, 3) as $materialAula): ?>
                                                        <?php $materialStatusLinha = !empty($materialAula['status']) ? $materialAula['status'] : (!empty($materialAula['visivel']) ? 'publicado' : 'oculto'); ?>
                                                        <span class="badge badge--soft"><?php echo Helpers::e($materialAula['titulo']); ?> · <?php echo Helpers::e($materialStatusLinha); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'modulos-aulas' ? ' is-active' : ''; ?>" data-area-curso-tab="modulos-aulas" id="area-curso-modulos-aulas-links">
                <div class="panel-header">
                    <h2>Links externos</h2>
                    <span class="badge"><?php echo (int) $quantidadeLinks; ?> itens</span>
                </div>
                <form method="post" action="/admin/area-curso/links" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($linkEditar['id']) ? (int) $linkEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Módulo
                        <select name="modulo_id">
                            <option value="">Sem módulo</option>
                            <?php foreach ($modulos as $modulo): ?>
                                <option value="<?php echo (int) $modulo['id']; ?>" <?php echo !empty($linkEditar) && (int) ($linkEditar['modulo_id'] ?? 0) === (int) $modulo['id'] ? 'selected' : ''; ?>>
                                    <?php echo Helpers::e($modulo['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Aula
                        <select name="aula_id">
                            <option value="">Sem aula</option>
                            <?php foreach ($modulos as $modulo): ?>
                                <?php foreach ($modulo['aulas'] as $aula): ?>
                                    <option value="<?php echo (int) $aula['id']; ?>" <?php echo !empty($linkEditar) && (int) ($linkEditar['aula_id'] ?? 0) === (int) $aula['id'] ? 'selected' : ''; ?>>
                                        <?php echo Helpers::e($modulo['titulo'] . ' - ' . $aula['titulo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($linkEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">URL<input type="text" name="url" value="<?php echo Helpers::e($linkEditar['url'] ?? ''); ?>"></label>
                    <label>Tipo de link<input type="text" name="tipo_link" value="<?php echo Helpers::e($linkEditar['tipo_link'] ?? 'generico'); ?>"></label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($linkEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($linkEditar) ? (!empty($linkEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=conteudo';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>

                <div class="table-wrap admin-mt-12">
                    <table class="admin-table admin-table--area-links">
                        <thead><tr><th>Título</th><th>Tipo</th><th>URL</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($links as $link): ?>
                                <tr>
                                    <td><?php echo Helpers::e($link['titulo']); ?></td>
                                    <td><?php echo Helpers::e($link['tipo_link']); ?></td>
                                    <td><a href="<?php echo Helpers::e($link['url']); ?>" target="_blank" rel="noopener">Abrir</a></td>
                                    <td>
                                        <div class="split-actions">
                                            <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?>&link_id=<?php echo (int) $link['id']; ?>">Editar</a>
                                        </div>
                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form admin-mt-8">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="tipo" value="link">
                                            <input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                            <button type="submit">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'modulos-aulas' ? ' is-active' : ''; ?>" data-area-curso-tab="modulos-aulas" id="area-curso-modulos-aulas-participantes">
                <div class="panel-header">
                    <h2>Participantes</h2>
                    <span class="badge"><?php echo (int) $quantidadeParticipantes; ?> itens</span>
                </div>
                <div class="table-wrap">
                    <table class="admin-table admin-table--area-participantes">
                        <thead><tr><th>Nome</th><th>CPF</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($participantes as $participante): ?>
                                <tr>
                                    <td><?php echo Helpers::e($participante['nome']); ?></td>
                                    <td><?php echo Helpers::e($participante['cpf']); ?></td>
                                    <td><?php echo Helpers::e($participante['inscricao_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

<?php endif; ?>
</div>
<script>
(function () {
    var tabsList = document.querySelector('[data-area-curso-tabs-list]');
    var tabsToggle = document.querySelector('[data-area-curso-tabs-toggle]');
    var workspace = document.querySelector('.area-curso-workspace');
    var courseSwitchLink = document.querySelector('.area-curso-header__actions a[href="/admin/area-curso"]');
    var dirty = false;
    var submittedForms = new WeakSet();

    function hasSelectedCourse() {
        return !!workspace;
    }

    function updateTabsVisibility() {
        if (!tabsList || !tabsToggle) {
            return;
        }

        if (window.innerWidth > 880) {
            tabsList.hidden = false;
            tabsList.classList.remove('is-open');
            tabsToggle.setAttribute('aria-expanded', 'true');
            return;
        }

        tabsList.hidden = true;
        tabsList.classList.remove('is-open');
        tabsToggle.setAttribute('aria-expanded', 'false');
    }

    function setDirty(value) {
        dirty = value;
    }

    function askLeave() {
        if (!dirty) {
            return true;
        }
        return window.confirm('Há alterações não salvas nesta aba. Deseja sair sem salvar?');
    }

    if (hasSelectedCourse() && !window.location.search.match(/[?&]aba=/) && window.location.hash) {
        var hashMap = {
            '#area-curso-visao-geral': 'visao-geral',
            '#area-curso-turmas': 'turmas',
            '#area-curso-modulos-aulas': 'conteudo',
            '#area-curso-materiais': 'conteudo',
            '#area-curso-atividades': 'conteudo',
            '#area-curso-participantes': 'participantes',
            '#area-curso-presenca': 'presenca',
            '#area-curso-avaliacoes-notas': 'avaliacoes-notas',
            '#area-curso-certificados': 'certificados',
            '#area-curso-relatorios': 'relatorios',
            '#area-curso-configuracoes': 'configuracoes',
            '#area-curso-aptos-certificado': 'aptos-certificado'
        };
        var abaHash = hashMap[window.location.hash] || 'visao-geral';
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('aba', abaHash);
        currentUrl.hash = '';
        window.location.replace(currentUrl.toString());
        return;
    }

    if (tabsToggle && tabsList) {
        tabsToggle.addEventListener('click', function () {
            var open = tabsList.classList.contains('is-open');
            tabsList.classList.toggle('is-open', !open);
            tabsList.hidden = open;
            tabsToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
        updateTabsVisibility();
        window.addEventListener('resize', updateTabsVisibility);
    }

    document.querySelectorAll('.area-curso-tab-panel form').forEach(function (form) {
        form.addEventListener('submit', function () {
            submittedForms.add(form);
            setDirty(false);
        });

        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.addEventListener('change', function () {
                if (!submittedForms.has(form)) {
                    setDirty(true);
                }
            });
            field.addEventListener('input', function () {
                if (!submittedForms.has(form)) {
                    setDirty(true);
                }
            });
        });
    });

    document.querySelectorAll('.area-curso-tabs__link').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (!askLeave()) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });

    if (courseSwitchLink) {
        courseSwitchLink.addEventListener('click', function (event) {
            if (!askLeave()) {
                event.preventDefault();
            }
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (!dirty) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });
})();
</script>


