<?php use App\Core\Helpers; ?>

<?php
$areaCursoBaseUrl = isset($areaCursoBaseUrl) && $areaCursoBaseUrl !== '' ? (string) $areaCursoBaseUrl : '/admin/area-curso';
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();

$conteudoModulos = isset($conteudo_modulos) && is_array($conteudo_modulos) ? $conteudo_modulos : array();
$conteudoModulosArquivados = isset($conteudo_modulos_arquivados) && is_array($conteudo_modulos_arquivados) ? $conteudo_modulos_arquivados : array();
$conteudoModuloSelecionado = isset($conteudo_modulo_selecionado) && is_array($conteudo_modulo_selecionado) ? $conteudo_modulo_selecionado : null;
$conteudoModuloSelecionadoId = isset($conteudo_modulo_selecionado_id) ? (int) $conteudo_modulo_selecionado_id : 0;
$conteudoModoModulo = !empty($conteudo_modo_modulo) || $conteudoModuloSelecionadoId > 0;
$conteudoModuloErro = isset($conteudo_modulo_erro) ? trim((string) $conteudo_modulo_erro) : '';
$conteudoVoltarModulosUrl = isset($conteudo_voltar_modulos_url) && trim((string) $conteudo_voltar_modulos_url) !== ''
    ? (string) $conteudo_voltar_modulos_url
    : $buildConteudoUrl();
$conteudoModuloItens = isset($conteudo_modulo_itens) && is_array($conteudo_modulo_itens) ? $conteudo_modulo_itens : array();
$conteudoModuloItensArquivados = isset($conteudo_modulo_itens_arquivados) && is_array($conteudo_modulo_itens_arquivados) ? $conteudo_modulo_itens_arquivados : array();
$canDeleteConteudoDefinitivo = !empty($can_delete_conteudo_definitivo);
$conteudoPodeMoverItem = isset($conteudoModulos) && is_array($conteudoModulos) && count($conteudoModulos) > 1;

$cursoIdAtual = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;
$turmaIdAtual = !empty($turmaAtual['id']) ? (int) $turmaAtual['id'] : 0;

$buildConteudoUrl = function (array $params = array()) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array('curso_id' => $cursoIdAtual, 'aba' => 'conteudo');
    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }

    return '/admin/area-curso?' . http_build_query($query);
};

$buildNovoModuloUrl = function () use ($cursoIdAtual, $turmaIdAtual) {
    return '/admin/area-curso/conteudo/modulos/criar?curso_id=' . $cursoIdAtual . ($turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : '');
};

$buildNovoConteudoUrl = function ($moduloId = 0) use ($cursoIdAtual, $turmaIdAtual) {
    $url = '/admin/area-curso/conteudo/itens/criar?curso_id=' . $cursoIdAtual . ($turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : '');
    if ((int) $moduloId > 0) {
        $url .= '&modulo_id=' . (int) $moduloId;
    }
    return $url;
};

$conteudoFormatStatusBadge = function ($status) {
    $status = (string) $status;
    if ($status === 'publicado') {
        return 'badge badge--success';
    }
    if ($status === 'rascunho') {
        return 'badge badge--warn';
    }
    if ($status === 'oculto') {
        return 'badge badge--soft';
    }
    return 'badge';
};

$conteudoFormatStatusLabel = function ($status) {
    $mapa = array(
        'publicado' => 'Publicado',
        'rascunho' => 'Rascunho',
        'oculto' => 'Oculto',
        'arquivado' => 'Arquivado',
    );

    $status = (string) $status;
    return isset($mapa[$status]) ? $mapa[$status] : ucfirst(str_replace('_', ' ', $status));
};

$conteudoFormatTipoLabel = function ($tipo) {
    $mapa = array(
        'etiqueta' => 'Etiqueta',
        'texto' => 'Texto',
        'arquivo' => 'Arquivo',
        'link' => 'Link',
        'avaliacao_textual' => 'Avaliação textual',
        'video' => 'Vídeo',
    );

    $tipo = (string) $tipo;
    return isset($mapa[$tipo]) ? $mapa[$tipo] : ucfirst(str_replace('_', ' ', $tipo));
};

$conteudoFormatTipoHint = function ($tipo) {
    $mapa = array(
        'etiqueta' => 'Bloco de orientação e aviso.',
        'texto' => 'Página de conteúdo com editor.',
        'arquivo' => 'Arquivo para download.',
        'link' => 'Link externo, botão ou embed.',
        'avaliacao_textual' => 'Atividade discursiva com correção.',
        'video' => 'Vídeo incorporado por URL.',
    );

    $tipo = (string) $tipo;
    return isset($mapa[$tipo]) ? $mapa[$tipo] : '';
};

$conteudoResumoTexto = function ($texto) {
    return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $texto)));
};

$conteudoModuleUrl = function ($moduloId) use ($buildConteudoUrl) {
    return $buildConteudoUrl(array('modulo_id' => (int) $moduloId));
};

$conteudoTemSelecionado = $conteudoModoModulo && !empty($conteudoModuloSelecionado);
?>

<section class="status-card admin-area-curso-conteudo-page">
    <div class="panel-header admin-area-curso-conteudo-page__header">
        <div>
            <h2><?php echo Helpers::e($conteudoModoModulo && !empty($conteudoModuloSelecionado) ? 'Conteúdos do módulo: ' . (string) ($conteudoModuloSelecionado['titulo'] ?? 'Módulo') : 'Módulos do curso'); ?></h2>
            <p class="muted">
                <?php echo $conteudoModoModulo
                    ? 'Abra um conteúdo para editar, arquive quando necessário e mantenha os arquivados em uma seção separada.'
                    : 'Gerencie os módulos do curso em uma lista compacta, com os arquivados abaixo da lista principal.'; ?>
            </p>
        </div>
        <div class="cta-group admin-compact-toolbar admin-area-curso-conteudo__toolbar">
            <?php if ($conteudoModoModulo): ?>
                <a class="button-link button-link--ghost admin-icon-btn" href="<?php echo Helpers::e($conteudoVoltarModulosUrl); ?>" title="Voltar aos módulos" aria-label="Voltar aos módulos">
                    <span aria-hidden="true">←</span><span class="u-sr-only">Voltar aos módulos</span>
                </a>
                <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildNovoConteudoUrl($conteudoModuloSelecionadoId)); ?>">Novo conteúdo</a>
            <?php else: ?>
                <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildNovoModuloUrl()); ?>">Novo módulo</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($conteudoModuloErro !== ''): ?>
        <div class="alert-danger admin-area-curso-conteudo-page__alert">
            <?php echo Helpers::e($conteudoModuloErro); ?>
        </div>
    <?php endif; ?>

    <?php if ($conteudoModoModulo && !empty($conteudoModuloSelecionado)): ?>
        <?php
        $moduloId = (int) ($conteudoModuloSelecionado['id'] ?? 0);
        $moduloTitulo = (string) ($conteudoModuloSelecionado['titulo'] ?? 'Módulo');
        $statusModulo = (string) ($conteudoModuloSelecionado['status'] ?? 'publicado');
        $ordemModulo = isset($conteudoModuloSelecionado['ordem']) ? (int) $conteudoModuloSelecionado['ordem'] : 0;
        $descricaoModulo = $conteudoResumoTexto($conteudoModuloSelecionado['descricao'] ?? '');
        $totalItensAtivos = (int) count($conteudoModuloItens);
        $totalItensArquivados = (int) count($conteudoModuloItensArquivados);
        ?>

        <article class="admin-area-curso-conteudo-module is-selected">
            <div class="admin-area-curso-conteudo-module__header">
                <div class="admin-area-curso-conteudo-module__summary">
                    <div class="admin-area-curso-conteudo-module__title-row">
                        <span class="badge">Ordem <?php echo $ordemModulo > 0 ? (int) $ordemModulo : '-'; ?></span>
                        <span class="<?php echo Helpers::e($conteudoFormatStatusBadge($statusModulo)); ?>"><?php echo Helpers::e($conteudoFormatStatusLabel($statusModulo)); ?></span>
                        <span class="badge badge--soft"><?php echo $totalItensAtivos; ?> ativos</span>
                        <?php if ($totalItensArquivados > 0): ?>
                            <span class="badge"><?php echo $totalItensArquivados; ?> arquivados</span>
                        <?php endif; ?>
                    </div>
                    <a class="admin-area-curso-conteudo-module__title-link" href="<?php echo Helpers::e($conteudoModuleUrl($moduloId)); ?>">
                        <?php echo Helpers::e($moduloTitulo); ?>
                    </a>
                    <?php if ($descricaoModulo !== ''): ?>
                        <div class="muted admin-area-curso-conteudo-module__description"><?php echo Helpers::e($descricaoModulo); ?></div>
                    <?php endif; ?>
                </div>
                <div class="admin-conteudo-actions area-curso-conteudo-actions">
                    <a class="button-link button-link--ghost admin-icon-btn" href="/admin/area-curso/conteudo/modulos/editar?curso_id=<?php echo $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : ''; ?>&modulo_id=<?php echo $moduloId; ?>" title="Editar módulo" aria-label="Editar módulo">
                        <span aria-hidden="true">✎</span><span class="u-sr-only">Editar módulo</span>
                    </a>
                    <form method="post" action="/admin/area-curso/conteudo/modulo/arquivar">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="id" value="<?php echo $moduloId; ?>">
                        <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                        <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Arquivar módulo" aria-label="Arquivar módulo" onclick="return confirm('Arquivar este módulo?');">
                            <span aria-hidden="true">🗄</span><span class="u-sr-only">Arquivar módulo</span>
                        </button>
                    </form>
                    <a class="button-link button-link--ghost admin-icon-btn" href="<?php echo Helpers::e($buildNovoConteudoUrl($moduloId)); ?>" title="Novo conteúdo" aria-label="Novo conteúdo">
                        <span aria-hidden="true">▤</span><span class="u-sr-only">Novo conteúdo</span>
                    </a>
                </div>
            </div>

            <div class="admin-area-curso-conteudo-module__items">
                <div class="panel-header admin-area-curso-conteudo-module__items-header">
                    <h3>Conteúdos ativos</h3>
                    <span class="badge"><?php echo $totalItensAtivos; ?> itens</span>
                </div>

                <?php if (empty($conteudoModuloItens)): ?>
                    <p class="muted admin-area-curso-conteudo-module__empty">Nenhum conteúdo ativo neste módulo.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="admin-table admin-conteudo-itens-table admin-table--conteudo-itens">
                            <colgroup>
                                <col class="admin-conteudo-col-ordem">
                                <col class="admin-conteudo-col-titulo">
                                <col class="admin-conteudo-col-tipo">
                                <col class="admin-conteudo-col-status">
                                <col class="admin-conteudo-col-acoes">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Ordem</th>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conteudoModuloItens as $item): ?>
                                    <?php
                                    $itemId = (int) ($item['id'] ?? 0);
                                    $itemStatus = isset($item['status']) ? (string) $item['status'] : 'publicado';
                                    $itemTipo = isset($item['tipo']) ? (string) $item['tipo'] : '';
                                    $itemTitulo = (string) ($item['titulo'] ?? '');
                                    $itemDescricao = $conteudoResumoTexto($item['descricao_curta'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?php echo isset($item['ordem']) ? (int) $item['ordem'] : 0; ?></td>
                                        <td>
                                            <a class="admin-area-curso-conteudo__cell-title" href="/admin/area-curso/conteudo/itens/editar?curso_id=<?php echo $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : ''; ?>&item_id=<?php echo $itemId; ?>" title="Editar conteúdo" aria-label="Editar conteúdo">
                                                <span class="admin-area-curso-conteudo__cell-title-text"><?php echo Helpers::e($itemTitulo); ?></span>
                                            </a>
                                            <?php if ($itemDescricao !== ''): ?>
                                                <div class="muted admin-area-curso-conteudo__cell-muted"><?php echo Helpers::e($itemDescricao); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" title="<?php echo Helpers::e($conteudoFormatTipoHint($itemTipo)); ?>"><?php echo Helpers::e($conteudoFormatTipoLabel($itemTipo)); ?></span>
                                            <?php if (!empty($item['obrigatorio'])): ?>
                                                <span class="badge badge--warn">Obrigatório</span>
                                            <?php else: ?>
                                                <span class="badge badge--soft">Opcional</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="<?php echo Helpers::e($conteudoFormatStatusBadge($itemStatus)); ?>"><?php echo Helpers::e($conteudoFormatStatusLabel($itemStatus)); ?></span></td>
                                        <td class="admin-conteudo-col-acoes">
                                            <div class="admin-conteudo-actions admin-conteudo-actions-main area-curso-conteudo-actions">
                                                <a class="button-link button-link--ghost admin-icon-btn" href="/admin/area-curso/conteudo/itens/editar?curso_id=<?php echo $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : ''; ?>&item_id=<?php echo $itemId; ?>" title="Editar conteúdo" aria-label="Editar conteúdo">
                                                    <span aria-hidden="true">✎</span><span class="u-sr-only">Editar conteúdo</span>
                                                </a>
                                                <form method="post" action="/admin/area-curso/conteudo/item/duplicar">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="id" value="<?php echo $itemId; ?>">
                                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                                    <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Duplicar conteúdo" aria-label="Duplicar conteúdo" onclick="return confirm('Duplicar este conteúdo?');">
                                                        <span aria-hidden="true">⧉</span><span class="u-sr-only">Duplicar conteúdo</span>
                                                    </button>
                                                </form>
                                                <form method="post" action="/admin/area-curso/conteudo/item/arquivar">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="id" value="<?php echo $itemId; ?>">
                                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                                    <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Arquivar conteúdo" aria-label="Arquivar conteúdo" onclick="return confirm('Arquivar este conteúdo?');">
                                                        <span aria-hidden="true">🗄</span><span class="u-sr-only">Arquivar conteúdo</span>
                                                    </button>
                                                </form>
                                            </div>

                                            <?php if ($conteudoPodeMoverItem): ?>
                                                <form method="post" action="/admin/area-curso/conteudo/item/mover" class="admin-area-curso-conteudo-item__move-form admin-conteudo-actions-move">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                                    <label>
                                                        <small>Mover para</small>
                                                        <select name="novo_modulo_id">
                                                            <?php foreach ($conteudoModulos as $moduloMovel): ?>
                                                                <?php $moduloMovelId = (int) ($moduloMovel['id'] ?? 0); ?>
                                                                <?php if ($moduloMovelId > 0): ?>
                                                                    <option value="<?php echo $moduloMovelId; ?>" <?php echo $moduloMovelId === $moduloId ? 'selected' : ''; ?>>
                                                                        <?php echo Helpers::e((string) ($moduloMovel['titulo'] ?? 'Módulo')); ?>
                                                                    </option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Mover conteúdo" aria-label="Mover conteúdo">
                                                        <span aria-hidden="true">↔</span><span class="u-sr-only">Mover conteúdo</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <div class="admin-conteudo-actions admin-conteudo-actions-main admin-area-curso-conteudo-item__order-actions">
                                                <form method="post" action="/admin/area-curso/conteudo/itens/ordenar">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                                    <input type="hidden" name="direcao" value="subir">
                                                    <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Mover para cima" aria-label="Mover para cima">
                                                        <span aria-hidden="true">↑</span><span class="u-sr-only">Mover para cima</span>
                                                    </button>
                                                </form>
                                                <form method="post" action="/admin/area-curso/conteudo/itens/ordenar">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                                    <input type="hidden" name="direcao" value="descer">
                                                    <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Mover para baixo" aria-label="Mover para baixo">
                                                        <span aria-hidden="true">↓</span><span class="u-sr-only">Mover para baixo</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cta-group admin-area-curso-conteudo__bottom-toolbar">
                <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildNovoConteudoUrl($moduloId)); ?>">Novo conteúdo</a>
            </div>

            <?php if (!empty($conteudoModuloItensArquivados)): ?>
                <details class="admin-archived-section admin-area-curso-conteudo-module__archived">
                    <summary>Conteúdos arquivados (<?php echo (int) count($conteudoModuloItensArquivados); ?>)</summary>
                    <div class="table-wrap admin-area-curso-conteudo-module__archived-body">
                        <table class="admin-table admin-conteudo-itens-table admin-table--conteudo-arquivados">
                            <thead>
                                <tr>
                                    <th>Ordem</th>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Atualizado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conteudoModuloItensArquivados as $itemArquivado): ?>
                                    <?php
                                    $itemArquivadoId = (int) ($itemArquivado['id'] ?? 0);
                                    $itemArquivadoTipo = isset($itemArquivado['tipo']) ? (string) $itemArquivado['tipo'] : '';
                                    $itemArquivadoStatus = isset($itemArquivado['status']) ? (string) $itemArquivado['status'] : 'arquivado';
                                    ?>
                                    <tr>
                                        <td><?php echo isset($itemArquivado['ordem']) ? (int) $itemArquivado['ordem'] : 0; ?></td>
                                        <td>
                                            <a class="admin-area-curso-conteudo__cell-title" href="/admin/area-curso/conteudo/itens/editar?curso_id=<?php echo $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : ''; ?>&item_id=<?php echo $itemArquivadoId; ?>" title="Editar conteúdo" aria-label="Editar conteúdo">
                                                <?php echo Helpers::e((string) ($itemArquivado['titulo'] ?? '')); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge" title="<?php echo Helpers::e($conteudoFormatTipoHint($itemArquivadoTipo)); ?>"><?php echo Helpers::e($conteudoFormatTipoLabel($itemArquivadoTipo)); ?></span>
                                            <span class="<?php echo Helpers::e($conteudoFormatStatusBadge($itemArquivadoStatus)); ?>"><?php echo Helpers::e($conteudoFormatStatusLabel($itemArquivadoStatus)); ?></span>
                                        </td>
                                        <td><?php echo Helpers::e((string) ($itemArquivado['updated_at'] ?? $itemArquivado['created_at'] ?? '-')); ?></td>
                                        <td>
                                            <div class="admin-conteudo-actions area-curso-conteudo-actions">
                                                <a class="button-link button-link--ghost admin-icon-btn" href="/admin/area-curso/conteudo/itens/editar?curso_id=<?php echo $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : ''; ?>&item_id=<?php echo $itemArquivadoId; ?>" title="Editar conteúdo" aria-label="Editar conteúdo">
                                                    <span aria-hidden="true">✎</span><span class="u-sr-only">Editar conteúdo</span>
                                                </a>
                                                <?php if ($canDeleteConteudoDefinitivo): ?>
                                                    <details class="admin-area-curso-conteudo__delete-details">
                                                        <summary title="Excluir definitivamente" aria-label="Excluir definitivamente">
                                                            <span aria-hidden="true">✕</span><span class="u-sr-only">Excluir definitivamente</span>
                                                        </summary>
                                                        <form method="post" action="/admin/area-curso/conteudo/item/excluir-definitivamente" class="admin-form admin-area-curso-conteudo__delete-form">
                                                            <?php echo $csrfField; ?>
                                                            <input type="hidden" name="id" value="<?php echo $itemArquivadoId; ?>">
                                                            <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                                            <label>Justificativa da lixeira
                                                                <textarea name="justificativa" rows="2" required placeholder="Informe a justificativa da exclusão definitiva."></textarea>
                                                            </label>
                                                            <button type="submit" class="button-link button-link--danger admin-icon-btn" onclick="return confirm('Tem certeza que deseja excluir definitivamente este conteúdo arquivado? Esta ação não pode ser desfeita.');" title="Excluir definitivamente" aria-label="Excluir definitivamente">
                                                                <span aria-hidden="true">✕</span><span class="u-sr-only">Excluir definitivamente</span>
                                                            </button>
                                                        </form>
                                                    </details>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <?php if (empty($conteudoModulos)): ?>
            <div class="admin-area-curso-conteudo-page__empty">
                <strong>Nenhum módulo ativo cadastrado.</strong>
                <p class="muted">Crie o primeiro módulo para começar a estruturar o conteúdo deste curso.</p>
                <div class="cta-group admin-compact-toolbar">
                    <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildNovoModuloUrl()); ?>">Novo módulo</a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table admin-conteudo-modulos-table admin-table--conteudo-modulos">
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Módulo</th>
                            <th>Conteúdos</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conteudoModulos as $modulo): ?>
                            <?php
                            $moduloId = (int) ($modulo['id'] ?? 0);
                            $moduloTitulo = (string) ($modulo['titulo'] ?? 'Módulo');
                            $moduloStatus = isset($modulo['status']) ? (string) $modulo['status'] : 'publicado';
                            $moduloItensAtivos = isset($modulo['total_itens_ativos']) ? (int) $modulo['total_itens_ativos'] : (isset($modulo['itens_ativos']) && is_array($modulo['itens_ativos']) ? count($modulo['itens_ativos']) : 0);
                            $moduloItensArquivados = isset($modulo['total_itens_arquivados']) ? (int) $modulo['total_itens_arquivados'] : (isset($modulo['itens_arquivados']) && is_array($modulo['itens_arquivados']) ? count($modulo['itens_arquivados']) : 0);
                            $moduloUrl = $conteudoModuleUrl($moduloId);
                            ?>
                            <tr>
                                <td><?php echo isset($modulo['ordem']) ? (int) $modulo['ordem'] : 0; ?></td>
                                <td>
                                    <a class="admin-area-curso-conteudo__cell-title" href="<?php echo Helpers::e($moduloUrl); ?>" title="Ver conteúdos do módulo" aria-label="Ver conteúdos do módulo">
                                        <?php echo Helpers::e($moduloTitulo); ?>
                                    </a>
                                </td>
                                <td>
                                    <a class="button-link button-link--ghost admin-icon-btn" href="<?php echo Helpers::e($moduloUrl); ?>" title="Ver conteúdos" aria-label="Ver conteúdos">
                                        <span aria-hidden="true">▤</span><span class="u-sr-only">Ver conteúdos</span>
                                    </a>
                                    <span class="badge badge--soft"><?php echo $moduloItensAtivos; ?> ativos</span>
                                    <?php if ($moduloItensArquivados > 0): ?>
                                        <span class="badge"><?php echo $moduloItensArquivados; ?> arquivados</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="<?php echo Helpers::e($conteudoFormatStatusBadge($moduloStatus)); ?>"><?php echo Helpers::e($conteudoFormatStatusLabel($moduloStatus)); ?></span></td>
                                <td>
                                    <div class="admin-conteudo-actions area-curso-conteudo-actions">
                                        <a class="button-link button-link--ghost admin-icon-btn" href="/admin/area-curso/conteudo/modulos/editar?curso_id=<?php echo $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : ''; ?>&modulo_id=<?php echo $moduloId; ?>" title="Editar módulo" aria-label="Editar módulo">
                                            <span aria-hidden="true">✎</span><span class="u-sr-only">Editar módulo</span>
                                        </a>
                                        <form method="post" action="/admin/area-curso/conteudo/modulo/arquivar">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="id" value="<?php echo $moduloId; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                            <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Arquivar módulo" aria-label="Arquivar módulo" onclick="return confirm('Arquivar este módulo?');">
                                                <span aria-hidden="true">🗄</span><span class="u-sr-only">Arquivar módulo</span>
                                            </button>
                                        </form>
                                        <form method="post" action="/admin/area-curso/conteudo/modulos/ordenar">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                            <input type="hidden" name="direcao" value="subir">
                                            <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Mover para cima" aria-label="Mover para cima">
                                                <span aria-hidden="true">↑</span><span class="u-sr-only">Mover para cima</span>
                                            </button>
                                        </form>
                                        <form method="post" action="/admin/area-curso/conteudo/modulos/ordenar">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                            <input type="hidden" name="direcao" value="descer">
                                            <button type="submit" class="button-link button-link--ghost admin-icon-btn" title="Mover para baixo" aria-label="Mover para baixo">
                                                <span aria-hidden="true">↓</span><span class="u-sr-only">Mover para baixo</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="cta-group admin-area-curso-conteudo__bottom-toolbar">
                <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildNovoModuloUrl()); ?>">Novo módulo</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($conteudoModulosArquivados)): ?>
            <details class="admin-archived-section admin-area-curso-conteudo__archived-modules">
                <summary>Módulos arquivados (<?php echo (int) count($conteudoModulosArquivados); ?>)</summary>
                <div class="table-wrap admin-area-curso-conteudo__archived-modules-body">
                    <table class="admin-table admin-conteudo-modulos-table admin-table--conteudo-arquivados">
                        <thead>
                            <tr>
                                <th>Ordem</th>
                                <th>Módulo</th>
                                <th>Conteúdos</th>
                                <th>Atualizado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conteudoModulosArquivados as $moduloArquivado): ?>
                                <?php
                                $moduloArquivadoId = (int) ($moduloArquivado['id'] ?? 0);
                                $moduloArquivadoStatus = isset($moduloArquivado['status']) ? (string) $moduloArquivado['status'] : 'arquivado';
                                $moduloArquivadoItensAtivos = isset($moduloArquivado['total_itens_ativos']) ? (int) $moduloArquivado['total_itens_ativos'] : 0;
                                $moduloArquivadoItensArquivados = isset($moduloArquivado['total_itens_arquivados']) ? (int) $moduloArquivado['total_itens_arquivados'] : 0;
                                $moduloArquivadoUrl = $conteudoModuleUrl($moduloArquivadoId);
                                ?>
                                <tr>
                                    <td><?php echo isset($moduloArquivado['ordem']) ? (int) $moduloArquivado['ordem'] : 0; ?></td>
                                    <td>
                                        <a class="admin-area-curso-conteudo__cell-title" href="<?php echo Helpers::e($moduloArquivadoUrl); ?>" title="Ver conteúdos do módulo" aria-label="Ver conteúdos do módulo">
                                            <?php echo Helpers::e((string) ($moduloArquivado['titulo'] ?? '')); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a class="button-link button-link--ghost admin-icon-btn" href="<?php echo Helpers::e($moduloArquivadoUrl); ?>" title="Ver conteúdos" aria-label="Ver conteúdos">
                                            <span aria-hidden="true">▤</span><span class="u-sr-only">Ver conteúdos</span>
                                        </a>
                                        <span class="badge badge--soft"><?php echo $moduloArquivadoItensAtivos; ?> ativos</span>
                                        <span class="badge"><?php echo $moduloArquivadoItensArquivados; ?> arquivados</span>
                                    </td>
                                    <td><?php echo Helpers::e((string) ($moduloArquivado['updated_at'] ?? $moduloArquivado['created_at'] ?? '-')); ?></td>
                                    <td>
                                        <div class="admin-conteudo-actions area-curso-conteudo-actions">
                                            <a class="button-link button-link--ghost admin-icon-btn" href="/admin/area-curso/conteudo/modulos/editar?curso_id=<?php echo $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . $turmaIdAtual : ''; ?>&modulo_id=<?php echo $moduloArquivadoId; ?>" title="Editar módulo" aria-label="Editar módulo">
                                                <span aria-hidden="true">✎</span><span class="u-sr-only">Editar módulo</span>
                                            </a>
                                            <?php if ($canDeleteConteudoDefinitivo): ?>
                                                <details class="admin-area-curso-conteudo__delete-details">
                                                    <summary title="Excluir definitivamente" aria-label="Excluir definitivamente">
                                                        <span aria-hidden="true">✕</span><span class="u-sr-only">Excluir definitivamente</span>
                                                    </summary>
                                                    <form method="post" action="/admin/area-curso/conteudo/modulo/excluir-definitivamente" class="admin-form admin-area-curso-conteudo__delete-form">
                                                        <?php echo $csrfField; ?>
                                                        <input type="hidden" name="id" value="<?php echo $moduloArquivadoId; ?>">
                                                        <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                                        <label>Justificativa da lixeira
                                                            <textarea name="justificativa" rows="2" required placeholder="Informe a justificativa da exclusão definitiva."></textarea>
                                                        </label>
                                                        <button type="submit" class="button-link button-link--danger admin-icon-btn" onclick="return confirm('Tem certeza que deseja excluir definitivamente este módulo arquivado? Esta ação não pode ser desfeita.');" title="Excluir definitivamente" aria-label="Excluir definitivamente">
                                                            <span aria-hidden="true">✕</span><span class="u-sr-only">Excluir definitivamente</span>
                                                        </button>
                                                    </form>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</section>
