<?php use App\Core\Helpers; ?>

<?php
$areaCursoBaseUrl = isset($areaCursoBaseUrl) && $areaCursoBaseUrl !== '' ? (string) $areaCursoBaseUrl : '/admin/area-curso';
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();
$selectedTab = isset($selected_tab) && $selected_tab !== '' ? (string) $selected_tab : 'visao-geral';
$conteudoModulos = isset($conteudo_modulos) && is_array($conteudo_modulos) ? $conteudo_modulos : array();

$cursoIdAtual = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;
$turmaIdAtual = !empty($turmaAtual['id']) ? (int) $turmaAtual['id'] : 0;

$buildAreaCursoUrl = function (array $params = array()) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array('curso_id' => $cursoIdAtual, 'aba' => 'conteudo');
    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }

    foreach ($params as $chave => $valor) {
        if ($valor === null || $valor === '' || $valor === 0) {
            continue;
        }
        $query[$chave] = $valor;
    }

    return '/admin/area-curso?' . http_build_query($query);
};

$buildConteudoUrl = function ($path, array $params = array()) use ($cursoIdAtual, $turmaIdAtual) {
    $query = array('curso_id' => $cursoIdAtual);
    if ($turmaIdAtual > 0) {
        $query['turma_id'] = $turmaIdAtual;
    }
    foreach ($params as $chave => $valor) {
        if ($valor === null || $valor === '' || $valor === 0) {
            continue;
        }
        $query[$chave] = $valor;
    }

    return '/admin/area-curso/conteudo/' . ltrim((string) $path, '/') . '?' . http_build_query($query);
};

$formatStatusBadge = function ($status) {
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

$formatStatusLabel = function ($status) {
    $mapa = array(
        'publicado' => 'Publicado',
        'rascunho' => 'Rascunho',
        'oculto' => 'Oculto',
        'arquivado' => 'Arquivado',
    );
    $status = (string) $status;
    return isset($mapa[$status]) ? $mapa[$status] : ucfirst(str_replace('_', ' ', $status));
};

$formatTipoLabel = function ($tipo) {
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

$formatTipoHint = function ($tipo) {
    $mapa = array(
        'etiqueta' => 'Bloco de destaque para avisos e orientações.',
        'texto' => 'Conteúdo em texto com editor.',
        'arquivo' => 'Arquivo para download (limite 10 MB).',
        'link' => 'Link externo (nova aba, embed ou botão).',
        'avaliacao_textual' => 'Atividade com envio e correção textual.',
        'video' => 'Vídeo via URL (embed).',
    );
    $tipo = (string) $tipo;
    return isset($mapa[$tipo]) ? $mapa[$tipo] : '';
};

$moduloSelecionadoId = isset($conteudo_modulo_selecionado_id) ? (int) $conteudo_modulo_selecionado_id : 0;
$moduloSelecionado = isset($conteudo_modulo_selecionado) && is_array($conteudo_modulo_selecionado) ? $conteudo_modulo_selecionado : null;
$moduloSelecionadoItens = isset($conteudo_modulo_itens) && is_array($conteudo_modulo_itens) ? $conteudo_modulo_itens : array();
$moduloErro = isset($conteudo_modulo_erro) ? trim((string) $conteudo_modulo_erro) : '';
$modoModuloAtivo = $moduloSelecionadoId > 0 && !empty($moduloSelecionado);
$temModulos = !empty($conteudoModulos);
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'conteudo' ? ' is-active' : ''; ?>" data-area-curso-tab="conteudo" id="area-curso-conteudo">
    <div class="panel-header area-curso-conteudo__header">
        <div>
            <h2>Módulos do Curso</h2>
            <?php if ($modoModuloAtivo): ?>
                <p class="muted" style="margin:6px 0 0;">Você está vendo apenas os conteúdos do módulo selecionado.</p>
            <?php endif; ?>
        </div>

        <div class="area-curso-conteudo__toolbar">
            <?php if ($modoModuloAtivo): ?>
                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl()); ?>">Voltar aos módulos</a>
            <?php else: ?>
                <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildConteudoUrl('modulos/criar')); ?>">Novo módulo</a>
                <?php if ($temModulos): ?>
                    <a class="button-link" href="<?php echo Helpers::e($buildConteudoUrl('itens/criar')); ?>">Novo conteúdo</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($moduloErro !== ''): ?>
        <div class="area-curso-empty-state">
            <strong>Não foi possível abrir o módulo selecionado.</strong>
            <p class="muted" style="margin:0;"><?php echo Helpers::e($moduloErro); ?></p>
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl()); ?>">Voltar aos módulos</a>
        </div>
    <?php elseif ($modoModuloAtivo && !empty($moduloSelecionado)): ?>
        <?php
        $descricaoModulo = trim((string) strip_tags((string) ($moduloSelecionado['descricao'] ?? '')));
        $ordemModulo = isset($moduloSelecionado['ordem']) ? (int) $moduloSelecionado['ordem'] : 0;
        $itensDoModulo = !empty($moduloSelecionadoItens) && is_array($moduloSelecionadoItens)
            ? $moduloSelecionadoItens
            : (isset($moduloSelecionado['itens']) && is_array($moduloSelecionado['itens']) ? $moduloSelecionado['itens'] : array());
        $contadorItens = count($itensDoModulo);
        ?>

        <article class="area-curso-conteudo-module is-selected">
            <div class="area-curso-conteudo-module__header">
                <div>
                    <div class="area-curso-conteudo-module__title-row">
                        <a class="area-curso-conteudo-module__title-link" href="<?php echo Helpers::e($buildAreaCursoUrl(array('modulo_id' => (int) $moduloSelecionado['id'], 'conteudo_modulo_id' => (int) $moduloSelecionado['id']))); ?>">
                            <?php echo Helpers::e((string) ($moduloSelecionado['titulo'] ?? 'Módulo')); ?>
                        </a>
                        <span class="badge">#<?php echo (int) ($ordemModulo > 0 ? $ordemModulo : 1); ?></span>
                        <span class="badge"><?php echo (int) $contadorItens; ?> itens</span>
                        <span class="<?php echo Helpers::e($formatStatusBadge($moduloSelecionado['status'] ?? 'rascunho')); ?>"><?php echo Helpers::e($formatStatusLabel($moduloSelecionado['status'] ?? 'rascunho')); ?></span>
                    </div>

                    <div class="area-curso-conteudo-module__meta">
                        <span class="muted">Ordem <?php echo (int) ($ordemModulo > 0 ? $ordemModulo : 1); ?></span>
                        <span class="muted">ID <?php echo (int) ($moduloSelecionado['id'] ?? 0); ?></span>
                    </div>

                    <?php if ($descricaoModulo !== ''): ?>
                        <p class="muted" style="margin:8px 0 0;"><?php echo Helpers::e($descricaoModulo); ?></p>
                    <?php else: ?>
                        <p class="muted" style="margin:8px 0 0;">Este módulo ainda não tem descrição.</p>
                    <?php endif; ?>
                </div>

                <div class="area-curso-conteudo-actions">
                    <a class="button-link" href="<?php echo Helpers::e($buildConteudoUrl('modulos/editar', array('modulo_id' => (int) $moduloSelecionado['id']))); ?>">Editar</a>
                    <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulo/duplicar'); ?>">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="id" value="<?php echo (int) ($moduloSelecionado['id'] ?? 0); ?>">
                        <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                        <button type="submit" class="button-link">Duplicar</button>
                    </form>
                    <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulo/arquivar'); ?>">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="id" value="<?php echo (int) ($moduloSelecionado['id'] ?? 0); ?>">
                        <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                        <button type="submit" class="button-link button-link--danger" onclick="return confirmarAcaoCritica({ palavra: 'ARQUIVAR', pergunta: 'Você conferiu o arquivamento deste módulo?' });">Arquivar</button>
                    </form>
                    <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulos/ordenar'); ?>">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                        <input type="hidden" name="modulo_id" value="<?php echo (int) ($moduloSelecionado['id'] ?? 0); ?>">
                        <input type="hidden" name="direcao" value="subir">
                        <button type="submit" class="button-link">Subir</button>
                    </form>
                    <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulos/ordenar'); ?>">
                        <?php echo $csrfField; ?>
                        <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                        <input type="hidden" name="modulo_id" value="<?php echo (int) ($moduloSelecionado['id'] ?? 0); ?>">
                        <input type="hidden" name="direcao" value="descer">
                        <button type="submit" class="button-link">Descer</button>
                    </form>
                </div>
            </div>
        </article>

        <div class="area-curso-conteudo-module__items">
            <div class="panel-header" style="margin-top:4px;">
                <h3 style="margin:0;">Itens do módulo</h3>
                <span class="badge"><?php echo (int) $contadorItens; ?> itens</span>
            </div>

            <?php if (empty($itensDoModulo)): ?>
                <div class="area-curso-empty-state">
                    <strong>Este módulo ainda não possui conteúdos cadastrados.</strong>
                    <p class="muted" style="margin:0;">Use o botão de criação para adicionar o primeiro conteúdo deste módulo.</p>
                    <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildConteudoUrl('itens/criar', array('modulo_id' => (int) $moduloSelecionado['id']))); ?>">Novo conteúdo neste módulo</a>
                </div>
            <?php else: ?>
                <?php $indiceItem = 1; ?>
                <?php foreach ($itensDoModulo as $item): ?>
                    <?php
                    $itemId = (int) ($item['id'] ?? 0);
                    $ordemItem = isset($item['ordem']) ? (int) $item['ordem'] : $indiceItem;
                    $descricaoCurta = trim((string) ($item['descricao_curta'] ?? ''));
                    $tipoItem = (string) ($item['tipo'] ?? '');
                    $temMaisDeUmModulo = count($conteudoModulos) > 1;
                    ?>
                    <article class="area-curso-conteudo-item">
                        <div class="area-curso-conteudo-item__header">
                            <div>
                                <div class="area-curso-conteudo-item__title-row">
                                    <strong><?php echo Helpers::e((string) ($item['titulo'] ?? 'Conteúdo')); ?></strong>
                                    <span class="badge">#<?php echo (int) $ordemItem; ?></span>
                                    <?php if ($tipoItem !== ''): ?>
                                        <span class="badge" title="<?php echo Helpers::e($formatTipoHint($tipoItem)); ?>"><?php echo Helpers::e($formatTipoLabel($tipoItem)); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['obrigatorio'])): ?>
                                        <span class="badge badge--warn">Obrigatório</span>
                                    <?php else: ?>
                                        <span class="badge badge--soft">Opcional</span>
                                    <?php endif; ?>
                                    <span class="<?php echo Helpers::e($formatStatusBadge($item['status'] ?? 'rascunho')); ?>"><?php echo Helpers::e($formatStatusLabel($item['status'] ?? 'rascunho')); ?></span>
                                </div>

                                <div class="area-curso-conteudo-item__meta">
                                    <span class="muted">Módulo: <?php echo Helpers::e((string) ($moduloSelecionado['titulo'] ?? '')); ?></span>
                                    <?php if (!empty($item['tipo'])): ?>
                                        <span class="muted">Tipo: <?php echo Helpers::e($formatTipoLabel($tipoItem)); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($descricaoCurta !== ''): ?>
                                    <p class="muted" style="margin:8px 0 0;"><?php echo Helpers::e($descricaoCurta); ?></p>
                                <?php else: ?>
                                    <p class="muted" style="margin:8px 0 0;">Sem descrição curta.</p>
                                <?php endif; ?>
                            </div>

                            <div class="area-curso-conteudo-actions">
                                <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildConteudoUrl('itens/editar', array('item_id' => $itemId, 'modulo_id' => (int) $moduloSelecionado['id']))); ?>">Editar</a>
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/item/duplicar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="id" value="<?php echo $itemId; ?>">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <button type="submit" class="button-link">Duplicar</button>
                                </form>
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/item/arquivar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="id" value="<?php echo $itemId; ?>">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <button type="submit" class="button-link button-link--danger" onclick="return confirmarAcaoCritica({ palavra: 'ARQUIVAR', pergunta: 'Você conferiu o arquivamento deste item?' });">Arquivar</button>
                                </form>
                            </div>
                        </div>

                        <div class="area-curso-conteudo-item__body">
                            <div class="area-curso-conteudo-actions">
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/itens/ordenar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloSelecionado['id']; ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                    <input type="hidden" name="direcao" value="subir">
                                    <button type="submit" class="button-link">Subir</button>
                                </form>
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/itens/ordenar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloSelecionado['id']; ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                    <input type="hidden" name="direcao" value="descer">
                                    <button type="submit" class="button-link">Descer</button>
                                </form>

                                <?php if ($temMaisDeUmModulo): ?>
                                    <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/item/mover'); ?>" class="area-curso-conteudo-item__move-form">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                        <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                        <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                        <label style="margin:0;">
                                            <small class="muted">Mover para</small>
                                            <select name="novo_modulo_id">
                                                <?php foreach ($conteudoModulos as $m2): ?>
                                                    <option value="<?php echo (int) $m2['id']; ?>" <?php echo (int) $m2['id'] === (int) $moduloSelecionado['id'] ? 'selected' : ''; ?>>
                                                        <?php echo Helpers::e((string) ($m2['titulo'] ?? 'Módulo')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <button type="submit" class="button-link">Mover</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php $indiceItem++; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if (empty($conteudoModulos)): ?>
            <div class="area-curso-empty-state">
                <strong>Nenhum módulo cadastrado neste curso ainda.</strong>
                <p class="muted" style="margin:0;">Crie o primeiro módulo para começar a organizar o conteúdo do curso.</p>
                <a class="button-link button-link--primary" href="<?php echo Helpers::e($buildConteudoUrl('modulos/criar')); ?>">Criar primeiro módulo</a>
            </div>
        <?php else: ?>
            <div class="area-curso-conteudo__module-list">
                <?php $indiceModulo = 1; ?>
                <?php foreach ($conteudoModulos as $modulo): ?>
                    <?php
                    $moduloId = (int) ($modulo['id'] ?? 0);
                    $itensModulo = isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
                    $descricaoModulo = trim((string) strip_tags((string) ($modulo['descricao'] ?? '')));
                    $ordemModulo = isset($modulo['ordem']) ? (int) $modulo['ordem'] : $indiceModulo;
                    ?>
                    <article class="area-curso-conteudo-module">
                        <div class="area-curso-conteudo-module__header">
                            <div>
                                <div class="area-curso-conteudo-module__title-row">
                    <a class="area-curso-conteudo-module__title-link" href="<?php echo Helpers::e($buildAreaCursoUrl(array('modulo_id' => $moduloId, 'conteudo_modulo_id' => $moduloId))); ?>">
                        <?php echo Helpers::e((string) ($modulo['titulo'] ?? 'Módulo')); ?>
                    </a>
                                    <span class="badge">#<?php echo (int) $ordemModulo; ?></span>
                                    <span class="badge"><?php echo (int) count($itensModulo); ?> itens</span>
                                    <span class="<?php echo Helpers::e($formatStatusBadge($modulo['status'] ?? 'rascunho')); ?>"><?php echo Helpers::e($formatStatusLabel($modulo['status'] ?? 'rascunho')); ?></span>
                                </div>

                                <div class="area-curso-conteudo-module__meta">
                                    <span class="muted">Ordem <?php echo (int) $ordemModulo; ?></span>
                                    <span class="muted">ID <?php echo $moduloId; ?></span>
                                </div>

                                <?php if ($descricaoModulo !== ''): ?>
                                    <p class="muted" style="margin:8px 0 0;"><?php echo Helpers::e($descricaoModulo); ?></p>
                                <?php else: ?>
                                    <p class="muted" style="margin:8px 0 0;">Este módulo ainda não tem descrição.</p>
                                <?php endif; ?>
                            </div>

                            <div class="area-curso-conteudo-actions">
                                <a class="button-link" href="<?php echo Helpers::e($buildConteudoUrl('modulos/editar', array('modulo_id' => $moduloId))); ?>">Editar</a>
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulo/duplicar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="id" value="<?php echo $moduloId; ?>">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <button type="submit" class="button-link">Duplicar</button>
                                </form>
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulo/arquivar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="id" value="<?php echo $moduloId; ?>">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <button type="submit" class="button-link button-link--danger" onclick="return confirmarAcaoCritica({ palavra: 'ARQUIVAR', pergunta: 'Você conferiu o arquivamento deste módulo?' });">Arquivar</button>
                                </form>
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulos/ordenar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                    <input type="hidden" name="direcao" value="subir">
                                    <button type="submit" class="button-link">Subir</button>
                                </form>
                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulos/ordenar'); ?>">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="curso_evento_id" value="<?php echo $cursoIdAtual; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? $turmaIdAtual : ''; ?>">
                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                    <input type="hidden" name="direcao" value="descer">
                                    <button type="submit" class="button-link">Descer</button>
                                </form>
                            </div>
                        </div>
                    </article>
                    <?php $indiceModulo++; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
