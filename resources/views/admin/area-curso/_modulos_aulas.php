<?php use App\Core\Helpers; ?>

<?php
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();
$modulos = isset($modulos) && is_array($modulos) ? $modulos : array();
$links = isset($links) && is_array($links) ? $links : array();
$csrfFieldAtual = isset($csrfField) ? (string) $csrfField : '';
$instrucaoEditarAtual = isset($instrucaoEditar) && is_array($instrucaoEditar) ? $instrucaoEditar : null;
$moduloEditarAtual = isset($moduloEditar) && is_array($moduloEditar) ? $moduloEditar : null;
$aulaEditarAtual = isset($aulaEditar) && is_array($aulaEditar) ? $aulaEditar : null;
$linkEditarAtual = isset($linkEditar) && is_array($linkEditar) ? $linkEditar : null;
$moduloSelecionadoAtual = isset($modulo_selecionado) && is_array($modulo_selecionado) ? $modulo_selecionado : null;
$aulaSelecionadaAtual = isset($aula_selecionada) && is_array($aula_selecionada) ? $aula_selecionada : null;
$quantidadeModulosAtual = isset($quantidadeModulos) ? (int) $quantidadeModulos : count($modulos);
$quantidadeAulasAtual = isset($quantidadeAulas) ? (int) $quantidadeAulas : 0;
$quantidadeLinksAtual = isset($quantidadeLinks) ? (int) $quantidadeLinks : count($links);
$quantidadeMateriaisAulasAtual = 0;
$quantidadeAtividadesAulasAtual = 0;

$cursoIdAtual = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;
$turmaIdAtual = !empty($turmaAtual['id']) ? (int) $turmaAtual['id'] : 0;

$buildAreaCursoUrl = function (array $params = array()) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array('curso_id' => $cursoIdAtual);
    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }

    foreach ($params as $chave => $valor) {
        if ($valor === null || $valor === '') {
            continue;
        }
        $query[$chave] = $valor;
    }

    return '/admin/area-curso?' . http_build_query($query);
};

$formatStatusBadge = function ($status) {
    $status = (string) $status;
    if ($status === 'publicado' || $status === 'aberta') {
        return 'badge badge--success';
    }
    if ($status === 'rascunho' || $status === 'planejada') {
        return 'badge badge--warn';
    }
    if ($status === 'oculto' || $status === 'encerrada') {
        return 'badge badge--soft';
    }

    return 'badge';
};

$formatStatusLabel = function ($status) {
    $mapa = array(
        'publicado' => 'Publicado',
        'rascunho' => 'Rascunho',
        'oculto' => 'Oculto',
        'aberta' => 'Aberta',
        'planejada' => 'Planejada',
        'encerrada' => 'Encerrada',
        'excluida' => 'Excluída',
    );

    $status = (string) $status;
    return isset($mapa[$status]) ? $mapa[$status] : ucfirst(str_replace('_', ' ', $status));
};

$formatDuracao = function ($valor) {
    $valor = (int) $valor;
    return $valor > 0 ? $valor . ' min' : '-';
};

foreach ($modulos as $moduloResumo) {
    $aulasModuloResumo = isset($moduloResumo['aulas']) && is_array($moduloResumo['aulas']) ? $moduloResumo['aulas'] : array();
    $quantidadeAulasAtual += count($aulasModuloResumo);

    foreach ($aulasModuloResumo as $aulaResumo) {
        $quantidadeMateriaisAulasAtual += (int) ($aulaResumo['total_materiais'] ?? 0);
        $quantidadeAtividadesAulasAtual += (int) ($aulaResumo['total_atividades'] ?? 0);
    }
}

$moduloIdEditarAtual = !empty($moduloEditarAtual['id']) ? (int) $moduloEditarAtual['id'] : 0;
$aulaIdEditarAtual = !empty($aulaEditarAtual['id']) ? (int) $aulaEditarAtual['id'] : 0;
$linkIdEditarAtual = !empty($linkEditarAtual['id']) ? (int) $linkEditarAtual['id'] : 0;
$instrucaoIdEditarAtual = !empty($instrucaoEditarAtual['id']) ? (int) $instrucaoEditarAtual['id'] : 0;
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'modulos-aulas' ? ' is-active' : ''; ?>" data-area-curso-tab="modulos-aulas" id="area-curso-modulos-aulas">
    <div class="area-curso-modulos-aulas">
        <div class="area-curso-section-heading">
            <div>
                <?php echo areaCursoHeadingWithTooltip('Módulos e aulas', 'Organize a estrutura pedagógica do curso, seus módulos, aulas, materiais e links de apoio.'); ?>
                <p class="muted">Organize a estrutura pedagógica do curso, seus módulos, aulas, materiais e links de apoio.</p>
            </div>
            <div class="area-curso-modulos-aulas__actions area-curso-actions">
                <a class="button-link button-link--primary" href="#area-curso-modulos-aulas-form-modulo">Novo módulo</a>
                <a class="button-link" href="#area-curso-modulos-aulas-form-aula">Nova aula</a>
                <a class="button-link" href="#area-curso-modulos-aulas-form-link">Adicionar link externo</a>
                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'materiais'))); ?>">Gerenciar materiais</a>
            </div>
        </div>

        <div class="area-curso-modulos-aulas__summary area-curso-summary-grid admin-mt-12">
            <div class="area-curso-summary-card">
                <small>Total de módulos</small>
                <strong><?php echo (int) $quantidadeModulosAtual; ?></strong>
                <span>cadastrados para este curso</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Total de aulas</small>
                <strong><?php echo (int) $quantidadeAulasAtual; ?></strong>
                <span>distribuídas entre os módulos</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Links externos</small>
                <strong><?php echo (int) $quantidadeLinksAtual; ?></strong>
                <span>materiais de apoio e referências</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Materiais nas aulas</small>
                <strong><?php echo (int) $quantidadeMateriaisAulasAtual; ?></strong>
                <span>vinculados diretamente às aulas</span>
            </div>
            <div class="area-curso-summary-card">
                <small>Atividades nas aulas</small>
                <strong><?php echo (int) $quantidadeAtividadesAulasAtual; ?></strong>
                <span>associadas ao conteúdo pedagógico</span>
            </div>
        </div>

        <details class="area-curso-collapsible-form admin-mt-16" id="area-curso-modulos-aulas-form-instrucoes"<?php echo !empty($instrucaoEditarAtual) ? ' open' : ''; ?>>
            <summary>Próxima ação do checkout</summary>
            <div class="area-curso-collapsible-form__body">
                <form method="post" action="/admin/area-curso/instrucoes" class="form-grid admin-area-curso__form">
                    <?php echo $csrfFieldAtual; ?>
                    <input type="hidden" name="id" value="<?php echo (int) $instrucaoIdEditarAtual; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Título
                        <input type="text" name="titulo" value="<?php echo Helpers::e($instrucaoEditarAtual['titulo'] ?? ''); ?>">
                    </label>
                    <label class="full">Conteúdo
                        <textarea name="conteudo" class="js-wysiwyg" data-wysiwyg="full" rows="4"><?php echo Helpers::e($instrucaoEditarAtual['conteudo'] ?? ''); ?></textarea>
                    </label>
                    <label>Ordem
                        <input type="number" name="ordem" value="<?php echo Helpers::e((string) ($instrucaoEditarAtual['ordem'] ?? 1)); ?>" min="1">
                    </label>
                    <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($instrucaoEditarAtual) ? (!empty($instrucaoEditarAtual['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
                    <?php
                    $cancel_url = $buildAreaCursoUrl(array('aba' => 'modulos-aulas'));
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </div>
        </details>

        <details class="area-curso-collapsible-form admin-mt-16" id="area-curso-modulos-aulas-form-modulo"<?php echo !empty($moduloEditarAtual) ? ' open' : ''; ?>>
            <summary><?php echo !empty($moduloEditarAtual) ? 'Editar módulo' : 'Criar ou editar módulo'; ?></summary>
            <div class="area-curso-collapsible-form__body">
                <form method="post" action="/admin/area-curso/modulos" class="form-grid admin-area-curso__form">
                    <?php echo $csrfFieldAtual; ?>
                    <input type="hidden" name="id" value="<?php echo $moduloIdEditarAtual; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Título
                        <input type="text" name="titulo" value="<?php echo Helpers::e($moduloEditarAtual['titulo'] ?? ''); ?>">
                    </label>
                    <label class="full">Descrição
                        <textarea name="descricao" class="js-wysiwyg" data-wysiwyg="basic" rows="3"><?php echo Helpers::e($moduloEditarAtual['descricao'] ?? ''); ?></textarea>
                    </label>
                    <label>Status
                        <?php $statusModuloAtual = 'publicado'; ?>
                        <?php if (!empty($moduloEditarAtual)): ?>
                            <?php $statusModuloAtual = !empty($moduloEditarAtual['status']) ? $moduloEditarAtual['status'] : (!empty($moduloEditarAtual['visivel']) ? 'publicado' : 'oculto'); ?>
                        <?php endif; ?>
                        <select name="status">
                            <option value="rascunho" <?php echo $statusModuloAtual === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="publicado" <?php echo $statusModuloAtual === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="oculto" <?php echo $statusModuloAtual === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                        </select>
                    </label>
                    <label>Ordem
                        <input type="number" name="ordem" value="<?php echo Helpers::e((string) ($moduloEditarAtual['ordem'] ?? 1)); ?>" min="1">
                    </label>
                    <?php
                    $cancel_url = $buildAreaCursoUrl(array('aba' => 'modulos-aulas'));
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </div>
        </details>

        <details class="area-curso-collapsible-form admin-mt-16" id="area-curso-modulos-aulas-form-aula"<?php echo !empty($aulaEditarAtual) ? ' open' : ''; ?>>
            <summary><?php echo !empty($aulaEditarAtual) ? 'Editar aula' : 'Criar ou editar aula'; ?></summary>
            <div class="area-curso-collapsible-form__body">
                <form method="post" action="/admin/area-curso/aulas" class="form-grid admin-area-curso__form">
                    <?php echo $csrfFieldAtual; ?>
                    <input type="hidden" name="id" value="<?php echo $aulaIdEditarAtual; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Módulo
                        <select name="modulo_id">
                            <?php foreach ($modulos as $modulo): ?>
                                <?php $selecionadoModuloNaAula = !empty($aulaEditarAtual) ? (int) ($aulaEditarAtual['modulo_id'] ?? 0) : (!empty($moduloSelecionadoAtual['id']) ? (int) $moduloSelecionadoAtual['id'] : 0); ?>
                                <option value="<?php echo (int) $modulo['id']; ?>" <?php echo $selecionadoModuloNaAula === (int) $modulo['id'] ? 'selected' : ''; ?>>
                                    <?php echo Helpers::e($modulo['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Título
                        <input type="text" name="titulo" value="<?php echo Helpers::e($aulaEditarAtual['titulo'] ?? ''); ?>">
                    </label>
                    <label class="full">Conteúdo
                        <textarea name="conteudo" class="js-wysiwyg" data-wysiwyg="full" rows="3"><?php echo Helpers::e($aulaEditarAtual['conteudo'] ?? ''); ?></textarea>
                    </label>
                    <label>Status
                        <?php $statusAulaAtual = 'publicado'; ?>
                        <?php if (!empty($aulaEditarAtual)): ?>
                            <?php $statusAulaAtual = !empty($aulaEditarAtual['status']) ? $aulaEditarAtual['status'] : (!empty($aulaEditarAtual['visivel']) ? 'publicado' : 'oculto'); ?>
                        <?php endif; ?>
                        <select name="status">
                            <option value="rascunho" <?php echo $statusAulaAtual === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="publicado" <?php echo $statusAulaAtual === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="oculto" <?php echo $statusAulaAtual === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                        </select>
                    </label>
                    <label>Tipo
                        <input type="text" name="tipo" value="<?php echo Helpers::e($aulaEditarAtual['tipo'] ?? 'texto'); ?>">
                    </label>
                    <label>URL vídeo
                        <input type="text" name="url_video" value="<?php echo Helpers::e($aulaEditarAtual['url_video'] ?? ''); ?>">
                    </label>
                    <label>Duração (minutos)
                        <input type="number" name="duracao_minutos" value="<?php echo Helpers::e((string) ($aulaEditarAtual['duracao_minutos'] ?? '')); ?>">
                    </label>
                    <label>Ordem
                        <input type="number" name="ordem" value="<?php echo Helpers::e((string) ($aulaEditarAtual['ordem'] ?? 1)); ?>" min="1">
                    </label>
                    <label class="checkbox"><input type="checkbox" name="obrigatoria" value="1" <?php echo !empty($aulaEditarAtual) && !empty($aulaEditarAtual['obrigatoria']) ? 'checked' : ''; ?>> Obrigatória</label>
                    <?php
                    $cancel_url = $buildAreaCursoUrl(array('aba' => 'modulos-aulas'));
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </div>
        </details>

        <details class="area-curso-collapsible-form admin-mt-16" id="area-curso-modulos-aulas-form-link"<?php echo !empty($linkEditarAtual) ? ' open' : ''; ?>>
            <summary><?php echo !empty($linkEditarAtual) ? 'Editar link externo' : 'Adicionar link externo'; ?></summary>
            <div class="area-curso-collapsible-form__body">
                <form method="post" action="/admin/area-curso/links" class="form-grid admin-area-curso__form">
                    <?php echo $csrfFieldAtual; ?>
                    <input type="hidden" name="id" value="<?php echo $linkIdEditarAtual; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Módulo
                        <select name="modulo_id">
                            <option value="">Sem módulo</option>
                            <?php foreach ($modulos as $modulo): ?>
                                <?php $selecionadoModuloNoLink = !empty($linkEditarAtual) ? (int) ($linkEditarAtual['modulo_id'] ?? 0) : (!empty($moduloSelecionadoAtual['id']) ? (int) $moduloSelecionadoAtual['id'] : 0); ?>
                                <option value="<?php echo (int) $modulo['id']; ?>" <?php echo $selecionadoModuloNoLink === (int) $modulo['id'] ? 'selected' : ''; ?>>
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
                                    <?php $selecionadaAulaNoLink = !empty($linkEditarAtual) ? (int) ($linkEditarAtual['aula_id'] ?? 0) : (!empty($aulaSelecionadaAtual['id']) ? (int) $aulaSelecionadaAtual['id'] : 0); ?>
                                    <option value="<?php echo (int) $aula['id']; ?>" <?php echo $selecionadaAulaNoLink === (int) $aula['id'] ? 'selected' : ''; ?>>
                                        <?php echo Helpers::e($modulo['titulo'] . ' - ' . $aula['titulo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Título
                        <input type="text" name="titulo" value="<?php echo Helpers::e($linkEditarAtual['titulo'] ?? ''); ?>">
                    </label>
                    <label class="full">URL
                        <input type="text" name="url" value="<?php echo Helpers::e($linkEditarAtual['url'] ?? ''); ?>">
                    </label>
                    <label>Tipo de link
                        <input type="text" name="tipo_link" value="<?php echo Helpers::e($linkEditarAtual['tipo_link'] ?? 'generico'); ?>">
                    </label>
                    <label>Ordem
                        <input type="number" name="ordem" value="<?php echo Helpers::e((string) ($linkEditarAtual['ordem'] ?? 1)); ?>" min="1">
                    </label>
                    <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($linkEditarAtual) ? (!empty($linkEditarAtual['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
                    <?php
                    $cancel_url = $buildAreaCursoUrl(array('aba' => 'modulos-aulas'));
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </div>
        </details>

        <div class="area-curso-estrutura admin-mt-16">
            <?php if (empty($modulos)): ?>
                <div class="area-curso-empty-state">
                    <p class="muted">Nenhum módulo cadastrado ainda.</p>
                    <a class="button-link button-link--primary" href="#area-curso-modulos-aulas-form-modulo">Criar primeiro módulo</a>
                </div>
            <?php else: ?>
                <?php foreach ($modulos as $moduloAtual): ?>
                    <?php
                    $aulasModulo = isset($moduloAtual['aulas']) && is_array($moduloAtual['aulas']) ? $moduloAtual['aulas'] : array();
                    $moduloId = (int) $moduloAtual['id'];
                    $statusModuloLinha = !empty($moduloAtual['status']) ? $moduloAtual['status'] : (!empty($moduloAtual['visivel']) ? 'publicado' : 'oculto');
                    $statusModuloLabel = $formatStatusLabel($statusModuloLinha);
                    $totalMateriaisModulo = !empty($moduloAtual['total_materiais']) ? (int) $moduloAtual['total_materiais'] : 0;
                    $totalAtividadesModulo = !empty($moduloAtual['total_atividades']) ? (int) $moduloAtual['total_atividades'] : 0;
                    ?>
                    <article class="area-curso-modulo-card">
                        <div class="area-curso-modulo-card__header">
                            <div>
                                <h3><?php echo Helpers::e($moduloAtual['titulo']); ?></h3>
                                <div class="area-curso-modulo-card__meta">
                                    <span class="badge"><?php echo Helpers::e($statusModuloLabel); ?></span>
                                    <span class="badge badge--soft">Ordem <?php echo (int) $moduloAtual['ordem']; ?></span>
                                    <span class="badge badge--soft"><?php echo (int) count($aulasModulo); ?> aulas</span>
                                    <span class="badge badge--soft"><?php echo (int) $totalMateriaisModulo; ?> materiais</span>
                                    <span class="badge badge--soft"><?php echo (int) $totalAtividadesModulo; ?> atividades</span>
                                </div>
                            </div>
                            <div class="area-curso-modulo-card__actions area-curso-actions">
                                <a class="button-link" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'modulos-aulas', 'modulo_id' => $moduloId))); ?>">Editar</a>
                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'modulos-aulas', 'modulo_id' => $moduloId, 'aula_id' => 0))); ?>">Nova aula neste módulo</a>
                                <details class="area-curso-collapsible-form area-curso-collapsible-form--inline">
                                    <summary>Remover</summary>
                                    <div class="area-curso-collapsible-form__body">
                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form">
                                            <?php echo $csrfFieldAtual; ?>
                                            <input type="hidden" name="tipo" value="modulo">
                                            <input type="hidden" name="id" value="<?php echo $moduloId; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                            <input type="hidden" name="aba" value="modulos-aulas">
                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                            <button type="submit">Enviar para a lixeira</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </div>

                        <div class="area-curso-aula-list">
                            <?php if (empty($aulasModulo)): ?>
                                <div class="area-curso-empty-state">
                                    <p class="muted">Este módulo ainda não possui aulas.</p>
                                    <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'modulos-aulas', 'modulo_id' => $moduloId))); ?>">Adicionar aula neste módulo</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($aulasModulo as $aulaAtual): ?>
                                    <?php
                                    $aulaId = (int) $aulaAtual['id'];
                                    $statusAulaLinha = !empty($aulaAtual['status']) ? $aulaAtual['status'] : (!empty($aulaAtual['visivel']) ? 'publicado' : 'oculto');
                                    $totalMateriaisAula = !empty($aulaAtual['total_materiais']) ? (int) $aulaAtual['total_materiais'] : 0;
                                    $totalAtividadesAula = !empty($aulaAtual['total_atividades']) ? (int) $aulaAtual['total_atividades'] : 0;
                                    ?>
                                    <article class="area-curso-aula-item">
                                        <div class="area-curso-aula-item__header">
                                            <div>
                                                <strong><?php echo Helpers::e($aulaAtual['titulo']); ?></strong>
                                                <div class="area-curso-aula-item__meta">
                                                    <span class="badge badge--soft"><?php echo Helpers::e($aulaAtual['tipo'] ?? 'texto'); ?></span>
                                                    <span class="badge badge--soft">Ordem <?php echo (int) $aulaAtual['ordem']; ?></span>
                                                    <span class="badge badge--soft"><?php echo $formatDuracao($aulaAtual['duracao_minutos'] ?? 0); ?></span>
                                                    <span class="<?php echo Helpers::e($formatStatusBadge($statusAulaLinha)); ?>"><?php echo Helpers::e($formatStatusLabel($statusAulaLinha)); ?></span>
                                                    <?php if (!empty($aulaAtual['obrigatoria'])): ?>
                                                        <span class="badge badge--warn">Obrigatória</span>
                                                    <?php endif; ?>
                                                    <span class="badge badge--soft"><?php echo $totalMateriaisAula; ?> materiais</span>
                                                    <span class="badge badge--soft"><?php echo $totalAtividadesAula; ?> atividades</span>
                                                </div>
                                            </div>
                                            <div class="area-curso-aula-item__actions area-curso-actions">
                                                <a class="button-link" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'modulos-aulas', 'modulo_id' => $moduloId, 'aula_id' => $aulaId))); ?>">Editar</a>
                                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'materiais', 'modulo_id' => $moduloId, 'aula_id' => $aulaId))); ?>">Adicionar material</a>
                                                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'atividades', 'atividade_modulo_id' => $moduloId, 'atividade_aula_id' => $aulaId))); ?>">Adicionar atividade</a>
                                                <details class="area-curso-collapsible-form area-curso-collapsible-form--inline">
                                                    <summary>Remover</summary>
                                                    <div class="area-curso-collapsible-form__body">
                                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form">
                                                            <?php echo $csrfFieldAtual; ?>
                                                            <input type="hidden" name="tipo" value="aula">
                                                            <input type="hidden" name="id" value="<?php echo $aulaId; ?>">
                                                            <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                            <input type="hidden" name="aba" value="modulos-aulas">
                                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                                            <button type="submit">Enviar para a lixeira</button>
                                                        </form>
                                                    </div>
                                                </details>
                                            </div>
                                        </div>
                                        <?php if (!empty($aulaAtual['materiais']) || !empty($aulaAtual['atividades'])): ?>
                                            <div class="area-curso-aula-item__stats">
                                                <span class="badge badge--soft"><?php echo $totalMateriaisAula; ?> materiais</span>
                                                <span class="badge badge--soft"><?php echo $totalAtividadesAula; ?> atividades</span>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <details class="area-curso-collapsible-form admin-mt-16" id="area-curso-modulos-aulas-links-list">
            <summary>Links externos cadastrados</summary>
            <div class="area-curso-collapsible-form__body">
                <?php if (empty($links)): ?>
                    <div class="area-curso-empty-state">
                        <p class="muted">Nenhum link externo cadastrado.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table admin-table--area-links">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>URL</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $linkAtual): ?>
                                    <?php $linkId = (int) $linkAtual['id']; ?>
                                    <tr>
                                        <td><?php echo Helpers::e($linkAtual['titulo']); ?></td>
                                        <td><?php echo Helpers::e($linkAtual['tipo_link']); ?></td>
                                        <td><a href="<?php echo Helpers::e($linkAtual['url']); ?>" target="_blank" rel="noopener">Abrir</a></td>
                                        <td>
                                            <div class="split-actions area-curso-actions">
                                                <a href="<?php echo Helpers::e($buildAreaCursoUrl(array('aba' => 'modulos-aulas', 'link_id' => $linkId))); ?>">Editar</a>
                                            </div>
                                            <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form admin-mt-8">
                                                <?php echo $csrfFieldAtual; ?>
                                                <input type="hidden" name="tipo" value="link">
                                                <input type="hidden" name="id" value="<?php echo $linkId; ?>">
                                                <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
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
                <?php endif; ?>
            </div>
        </details>
    </div>
</section>

<script>
(function () {
    var map = {
        '#area-curso-modulos-aulas-form-instrucoes': 'area-curso-modulos-aulas-form-instrucoes',
        '#area-curso-modulos-aulas-form-modulo': 'area-curso-modulos-aulas-form-modulo',
        '#area-curso-modulos-aulas-form-aula': 'area-curso-modulos-aulas-form-aula',
        '#area-curso-modulos-aulas-form-link': 'area-curso-modulos-aulas-form-link'
    };

    function abrirDetalhePorHash() {
        var alvo = map[window.location.hash];
        if (!alvo) {
            return;
        }

        var detalhe = document.getElementById(alvo);
        if (detalhe && detalhe.tagName === 'DETAILS') {
            detalhe.open = true;
        }
    }

    abrirDetalhePorHash();
    window.addEventListener('hashchange', abrirDetalhePorHash);
})();
</script>
