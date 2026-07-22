<?php use App\Core\Helpers; ?>

<?php
$areaCursoBaseUrl = isset($areaCursoBaseUrl) && $areaCursoBaseUrl !== '' ? (string) $areaCursoBaseUrl : '/professor/area-curso';
$cursoAtual = isset($curso) && is_array($curso) ? $curso : array();
$turmaAtual = isset($turma) && is_array($turma) ? $turma : array();

$conteudoModulos = isset($conteudo_modulos) && is_array($conteudo_modulos) ? $conteudo_modulos : array();
$moduloEditar = isset($conteudo_modulo_editar) && is_array($conteudo_modulo_editar) ? $conteudo_modulo_editar : null;
$itemEditar = isset($conteudo_item_editar) && is_array($conteudo_item_editar) ? $conteudo_item_editar : null;
$itemDetalhe = isset($conteudo_item_detalhe) && is_array($conteudo_item_detalhe) ? $conteudo_item_detalhe : array();

$cursoIdAtual = !empty($cursoAtual['id']) ? (int) $cursoAtual['id'] : 0;
$turmaIdAtual = !empty($turmaAtual['id']) ? (int) $turmaAtual['id'] : 0;
$moduloSelecionadoPorContexto = isset($_GET['conteudo_modulo_id']) ? (int) $_GET['conteudo_modulo_id'] : 0;

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

    return '/professor/area-curso?' . http_build_query($query);
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

$editorValue = function ($value) {
    return Helpers::e(Helpers::decodeEditorHtml((string) $value));
};

$formatTipoLabel = function ($tipo) {
    $mapa = array(
        'etiqueta' => 'Etiqueta',
        'texto' => 'Texto',
        'arquivo' => 'Arquivo',
        'link' => 'Link',
        'avaliacao_textual' => 'Avaliação textual',
        'video' => 'Vídeo',
        'quiz' => 'Quiz',
        'html' => 'HTML',
        'video_incorporado' => 'Vídeo incorporado',
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
        'quiz' => 'Atividade de múltipla escolha.',
        'html' => 'Página HTML/CSS/JS própria, exibida isolada.',
        'video_incorporado' => 'Vídeo com embed colado, exibido num player 16:9.',
    );
    $tipo = (string) $tipo;
    return isset($mapa[$tipo]) ? $mapa[$tipo] : '';
};
?>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Conteúdo</h2>
            <p class="muted">Alimente o curso aqui (módulos e itens).</p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl()); ?>">Limpar seleção</a>
            <a class="button-link" href="/professor/area-curso?curso_id=<?php echo (int) $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . (int) $turmaIdAtual : ''; ?>">Voltar</a>
        </div>
    </div>

    <section class="panel" style="margin-top:12px;">
        <div class="panel-header"><div><h3><?php echo $moduloEditar ? 'Editar módulo' : 'Novo módulo'; ?></h3></div></div>
        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulo/salvar'); ?>" class="form-grid">
            <?php echo $csrfField; ?>
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
            <?php if ($moduloEditar): ?>
                <input type="hidden" name="id" value="<?php echo (int) $moduloEditar['id']; ?>">
            <?php endif; ?>

            <label>Título<input type="text" name="titulo" value="<?php echo Helpers::e($moduloEditar['titulo'] ?? ''); ?>" required></label>
            <label>Descrição
                <textarea name="descricao" class="js-conteudo-rich-editor" data-editor-mode="full" rows="5"><?php echo $editorValue($moduloEditar['descricao'] ?? ''); ?></textarea>
            </label>
            <label>Status
                <?php $statusModulo = (string) ($moduloEditar['status'] ?? 'rascunho'); ?>
                <select name="status">
                    <option value="rascunho" <?php echo $statusModulo === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                    <option value="publicado" <?php echo $statusModulo === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                    <option value="oculto" <?php echo $statusModulo === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                    <option value="arquivado" <?php echo $statusModulo === 'arquivado' ? 'selected' : ''; ?>>Arquivado</option>
                </select>
            </label>

            <?php
            $cancel_url = $buildAreaCursoUrl();
            $show_save_as_copy = false;
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
    </section>

    <section class="panel" style="margin-top:12px;">
        <div class="panel-header"><div><h3><?php echo $itemEditar ? 'Editar item' : 'Adicionar conteúdo'; ?></h3></div></div>
        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/item/salvar'); ?>" class="form-grid" enctype="multipart/form-data">
            <?php echo $csrfField; ?>
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
            <?php if ($itemEditar): ?>
                <input type="hidden" name="id" value="<?php echo (int) $itemEditar['id']; ?>">
            <?php endif; ?>

            <?php $moduloSelecionado = (int) ($itemEditar['modulo_id'] ?? 0); ?>
            <?php if ($moduloSelecionado <= 0 && $moduloSelecionadoPorContexto > 0): $moduloSelecionado = $moduloSelecionadoPorContexto; endif; ?>
            <label>Módulo
                <select name="modulo_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($conteudoModulos as $m): ?>
                        <option value="<?php echo (int) $m['id']; ?>" <?php echo $moduloSelecionado === (int) $m['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($m['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <?php $tipoSelecionado = (string) ($itemEditar['tipo'] ?? 'texto'); ?>
            <label>Tipo
                <select name="tipo" id="prof-conteudo-item-tipo" required>
                    <option value="etiqueta" title="<?php echo Helpers::e($formatTipoHint('etiqueta')); ?>" <?php echo $tipoSelecionado === 'etiqueta' ? 'selected' : ''; ?>>Etiqueta</option>
                    <option value="texto" title="<?php echo Helpers::e($formatTipoHint('texto')); ?>" <?php echo $tipoSelecionado === 'texto' ? 'selected' : ''; ?>>Texto</option>
                    <option value="arquivo" title="<?php echo Helpers::e($formatTipoHint('arquivo')); ?>" <?php echo $tipoSelecionado === 'arquivo' ? 'selected' : ''; ?>>Arquivo</option>
                    <option value="link" title="<?php echo Helpers::e($formatTipoHint('link')); ?>" <?php echo $tipoSelecionado === 'link' ? 'selected' : ''; ?>>Link</option>
                    <option value="avaliacao_textual" title="<?php echo Helpers::e($formatTipoHint('avaliacao_textual')); ?>" <?php echo $tipoSelecionado === 'avaliacao_textual' ? 'selected' : ''; ?>>Avaliação textual</option>
                    <option value="video" title="<?php echo Helpers::e($formatTipoHint('video')); ?>" <?php echo $tipoSelecionado === 'video' ? 'selected' : ''; ?>>Vídeo</option>
                </select>
            </label>
            <div class="muted" style="grid-column: 1 / -1;">
                Etiqueta: bloco de orientação exibido ao aluno. | Texto: página de conteúdo com editor. | Arquivo: material para download. | Link: endereço externo, botão ou embed. | Avaliação textual: pergunta discursiva com nota e feedback. | Vídeo: vídeo incorporado por link/embed.
            </div>

            <label>Título<input type="text" name="titulo" value="<?php echo Helpers::e($itemEditar['titulo'] ?? ''); ?>" required></label>
            <label>Descrição curta<textarea name="descricao_curta" rows="3"><?php echo Helpers::e($itemEditar['descricao_curta'] ?? ''); ?></textarea></label>
            <label class="checkbox"><input type="checkbox" name="obrigatorio" value="1" <?php echo !empty($itemEditar['obrigatorio']) ? 'checked' : ''; ?>> Obrigatório</label>
            <label>Status
                <?php $statusItem = (string) ($itemEditar['status'] ?? 'rascunho'); ?>
                <select name="status">
                    <option value="rascunho" <?php echo $statusItem === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                    <option value="publicado" <?php echo $statusItem === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                    <option value="oculto" <?php echo $statusItem === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                    <option value="arquivado" <?php echo $statusItem === 'arquivado' ? 'selected' : ''; ?>>Arquivado</option>
                </select>
            </label>

            <div id="prof-conteudo-tipo-etiqueta" style="grid-column: 1 / -1;">
                <h4 style="margin:0;">Etiqueta</h4>
                <label>Conteúdo
                    <textarea name="etiqueta_conteudo" class="js-conteudo-rich-editor" data-editor-mode="full" rows="8"><?php echo $editorValue($itemDetalhe['conteudo'] ?? ''); ?></textarea>
                </label>
            </div>

            <div id="prof-conteudo-tipo-texto" style="grid-column: 1 / -1;">
                <h4 style="margin:0;">Texto</h4>
                <label>Conteúdo
                    <textarea name="texto_conteudo" class="js-conteudo-rich-editor" data-editor-mode="full" rows="10"><?php echo $editorValue($itemDetalhe['conteudo'] ?? ''); ?></textarea>
                </label>
            </div>

            <div id="prof-conteudo-tipo-link" style="grid-column: 1 / -1;">
                <h4 style="margin:0;">Link</h4>
                <label>URL<input type="url" name="link_url" value="<?php echo Helpers::e($itemDetalhe['url'] ?? ''); ?>"></label>
                <?php $modoLink = (string) ($itemDetalhe['modo_abertura'] ?? 'nova_aba'); ?>
                <label>Modo de abertura
                    <select name="link_modo_abertura">
                        <option value="nova_aba" <?php echo $modoLink === 'nova_aba' ? 'selected' : ''; ?>>Nova aba</option>
                        <option value="embed" <?php echo $modoLink === 'embed' ? 'selected' : ''; ?>>Embed</option>
                        <option value="botao" <?php echo $modoLink === 'botao' ? 'selected' : ''; ?>>Botão</option>
                    </select>
                </label>
            </div>

            <div id="prof-conteudo-tipo-video" style="grid-column: 1 / -1;">
                <h4 style="margin:0;">Vídeo</h4>
                <label>URL<input type="url" name="video_url" value="<?php echo Helpers::e($itemDetalhe['url'] ?? ''); ?>"></label>
                <label>Duração (segundos)<input type="number" name="video_duracao_segundos" value="<?php echo Helpers::e($itemDetalhe['duracao_segundos'] ?? ''); ?>" min="0"></label>
            </div>

            <div id="prof-conteudo-tipo-avaliacao" style="grid-column: 1 / -1;">
                <h4 style="margin:0;">Avaliação textual</h4>
                <label>Enunciado
                    <textarea name="avaliacao_enunciado" class="js-conteudo-rich-editor" data-editor-mode="full" rows="10"><?php echo $editorValue($itemDetalhe['enunciado'] ?? ''); ?></textarea>
                </label>
                <label>Orientações
                    <textarea name="avaliacao_orientacoes" class="js-conteudo-rich-editor" data-editor-mode="full" rows="6"><?php echo $editorValue($itemDetalhe['orientacoes'] ?? ''); ?></textarea>
                </label>
                <label>Nota máxima<input type="number" name="avaliacao_nota_maxima" min="0" step="0.01" value="<?php echo Helpers::e($itemDetalhe['nota_maxima'] ?? ''); ?>"></label>
                <label>Nota mínima<input type="number" name="avaliacao_nota_minima" min="0" step="0.01" value="<?php echo Helpers::e($itemDetalhe['nota_minima'] ?? ''); ?>"></label>
                <label>Peso<input type="number" name="avaliacao_peso" min="0.01" step="0.01" value="<?php echo Helpers::e($itemDetalhe['peso'] ?? '1.00'); ?>"></label>
                <label>Prazo<input type="datetime-local" name="avaliacao_prazo" value="<?php echo !empty($itemDetalhe['prazo']) ? Helpers::e(str_replace(' ', 'T', substr((string) $itemDetalhe['prazo'], 0, 16))) : ''; ?>"></label>
                <label class="checkbox"><input type="checkbox" name="avaliacao_permite_reenvio" value="1" <?php echo !empty($itemDetalhe['permite_reenvio']) ? 'checked' : ''; ?>> Permitir reenvio</label>
                <label class="checkbox"><input type="checkbox" name="avaliacao_reenvio_livre_ate_prazo" value="1" <?php echo !isset($itemDetalhe['reenvio_livre_ate_prazo']) || !empty($itemDetalhe['reenvio_livre_ate_prazo']) ? 'checked' : ''; ?>> Reenvio livre até o prazo</label>
            </div>

            <div id="prof-conteudo-tipo-arquivo" style="grid-column: 1 / -1;">
                <h4 style="margin:0;">Arquivo</h4>
                <p class="muted" style="margin:0;">Limite: 10 MB. Extensões permitidas: pdf, jpg, jpeg, png, webp, doc, docx, odt, xls, xlsx, ods, ppt, pptx, odp, txt, csv.</p>
                <label>Arquivo
                    <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.odt,.xls,.xlsx,.ods,.ppt,.pptx,.odp,.txt,.csv">
                </label>
                <label class="checkbox"><input type="checkbox" name="arquivo_permite_download" value="1" <?php echo !empty($itemDetalhe['permite_download']) ? 'checked' : ''; ?>> Permitir download</label>
                <?php if (!empty($itemDetalhe['nome_original'])): ?>
                    <p class="muted" style="margin:0;">Arquivo atual: <?php echo Helpers::e($itemDetalhe['nome_original']); ?></p>
                <?php endif; ?>
            </div>

            <?php
            $cancel_url = $buildAreaCursoUrl();
            $show_save_as_copy = false;
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>

        <script>
            (function () {
                function toggleTipo() {
                    var tipo = document.getElementById('prof-conteudo-item-tipo');
                    if (!tipo) {
                        return;
                    }

                    var value = tipo.value || 'texto';
                    var blocks = {
                        etiqueta: document.getElementById('prof-conteudo-tipo-etiqueta'),
                        texto: document.getElementById('prof-conteudo-tipo-texto'),
                        link: document.getElementById('prof-conteudo-tipo-link'),
                        video: document.getElementById('prof-conteudo-tipo-video'),
                        avaliacao_textual: document.getElementById('prof-conteudo-tipo-avaliacao'),
                        arquivo: document.getElementById('prof-conteudo-tipo-arquivo')
                    };

                    Object.keys(blocks).forEach(function (key) {
                        if (!blocks[key]) {
                            return;
                        }
                        blocks[key].style.display = key === value ? '' : 'none';
                    });
                }

                function initRichEditors() {
                    if (window.initConteudoRichEditors) {
                        window.initConteudoRichEditors();
                        return;
                    }
                    if (window.initAreaCursoWysiwyg) {
                        window.initAreaCursoWysiwyg();
                    }
                }

                function init() {
                    toggleTipo();
                    initRichEditors();
                }

                document.addEventListener('change', function (ev) {
                    if (ev.target && ev.target.id === 'prof-conteudo-item-tipo') {
                        toggleTipo();
                    }
                });

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }

                window.addEventListener('load', initRichEditors);
            })();
        </script>
    </section>

    <section class="panel" style="margin-top:12px;">
        <div class="panel-header"><div><h3>Módulos e itens</h3></div></div>

        <?php if (empty($conteudoModulos)): ?>
            <p class="muted">Nenhum módulo cadastrado ainda.</p>
        <?php endif; ?>

        <div style="display:grid; gap:12px;">
            <?php foreach ($conteudoModulos as $modulo): ?>
                <?php $itensModulo = isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array(); ?>
                <div class="status-card" style="padding:14px;">
                    <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <strong><?php echo Helpers::e($modulo['titulo']); ?></strong>
                            <div class="muted" style="margin-top:4px;"><?php echo Helpers::e(trim(strip_tags((string) ($modulo['descricao'] ?? '')))); ?></div>
                            <div style="margin-top:6px;">
                                <span class="<?php echo Helpers::e($formatStatusBadge($modulo['status'] ?? 'rascunho')); ?>"><?php echo Helpers::e($formatStatusLabel($modulo['status'] ?? 'rascunho')); ?></span>
                            </div>
                        </div>
                        <div class="cta-group" style="gap:6px;">
                            <a class="button-link" href="<?php echo Helpers::e($buildAreaCursoUrl(array('conteudo_modulo_id' => (int) $modulo['id']))); ?>">Editar</a>
                            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($buildAreaCursoUrl(array('conteudo_modulo_id' => (int) $modulo['id']))); ?>">Adicionar conteúdo</a>
                            <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulo/duplicar'); ?>">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="id" value="<?php echo (int) $modulo['id']; ?>">
                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                <button type="submit" class="button-link">Duplicar</button>
                            </form>
                            <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulo/arquivar'); ?>">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="id" value="<?php echo (int) $modulo['id']; ?>">
                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                <button type="submit" class="button-link button-link--ghost" onclick="return confirm('Arquivar este módulo?');">Arquivar</button>
                            </form>
                        </div>
                    </div>

                    <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
                        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulos/ordenar'); ?>">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                            <input type="hidden" name="modulo_id" value="<?php echo (int) $modulo['id']; ?>">
                            <input type="hidden" name="direcao" value="subir">
                            <button type="submit" class="button-link">Subir</button>
                        </form>
                        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/modulos/ordenar'); ?>">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                            <input type="hidden" name="modulo_id" value="<?php echo (int) $modulo['id']; ?>">
                            <input type="hidden" name="direcao" value="descer">
                            <button type="submit" class="button-link">Descer</button>
                        </form>
                    </div>

                    <div style="margin-top:12px;">
                        <strong>Itens</strong>
                        <?php if (empty($itensModulo)): ?>
                            <p class="muted" style="margin-top:6px;">Nenhum item neste módulo.</p>
                        <?php else: ?>
                            <div style="display:grid; gap:8px; margin-top:8px;">
                                <?php foreach ($itensModulo as $item): ?>
                                    <div style="border:1px solid rgba(0,0,0,.08); padding:10px; border-radius:10px;">
                                        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                                            <div>
                                                <div>
                                                    <span class="badge" title="<?php echo Helpers::e($formatTipoHint($item['tipo'] ?? '')); ?>"><?php echo Helpers::e($formatTipoLabel($item['tipo'] ?? '')); ?></span>
                                                    <?php if (!empty($item['obrigatorio'])): ?>
                                                        <span class="badge badge--warn">Obrigatório</span>
                                                    <?php else: ?>
                                                        <span class="badge badge--soft">Opcional</span>
                                                    <?php endif; ?>
                                                    <span class="<?php echo Helpers::e($formatStatusBadge($item['status'] ?? 'rascunho')); ?>"><?php echo Helpers::e($formatStatusLabel($item['status'] ?? 'rascunho')); ?></span>
                                                </div>
                                                <strong style="display:block; margin-top:6px;"><?php echo Helpers::e($item['titulo']); ?></strong>
                                                <?php if (!empty($item['descricao_curta'])): ?>
                                                    <div class="muted" style="margin-top:4px;"><?php echo Helpers::e($item['descricao_curta']); ?></div>
                                                <?php endif; ?>
                                                <?php if (($item['tipo'] ?? '') === 'arquivo'): ?>
                                                    <?php $arquivoItem = isset($item['arquivo_detalhe']) && is_array($item['arquivo_detalhe']) ? $item['arquivo_detalhe'] : array(); ?>
                                                    <?php if (!empty($arquivoItem['caminho'])): ?>
                                                        <div class="muted" style="margin-top:4px;">
                                                            Arquivo: <?php echo Helpers::e((string) ($arquivoItem['nome_original'] ?? 'sem nome')); ?> |
                                                            Extensão: <?php echo Helpers::e((string) ($arquivoItem['extensao'] ?? '-')); ?> |
                                                            Tamanho: <?php echo Helpers::e(number_format(((int) ($arquivoItem['tamanho_bytes'] ?? 0)) / 1024, 1, ',', '.')); ?> KB
                                                        </div>
                                                        <div style="margin-top:4px;">
                                                            <a class="button-link" href="/professor/area-curso/conteudo/arquivo/download?id=<?php echo (int) $item['id']; ?>&curso_id=<?php echo (int) $cursoIdAtual; ?><?php echo $turmaIdAtual > 0 ? '&turma_id=' . (int) $turmaIdAtual : ''; ?>">Baixar arquivo</a>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="muted" style="margin-top:4px;">Item em rascunho sem arquivo enviado.</div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="cta-group" style="gap:6px;">
                                                <a class="button-link" href="<?php echo Helpers::e($buildAreaCursoUrl(array('conteudo_item_id' => (int) $item['id']))); ?>">Editar</a>
                                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/item/duplicar'); ?>">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                                    <button type="submit" class="button-link">Duplicar</button>
                                                </form>
                                                <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/item/arquivar'); ?>">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                                    <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                                    <button type="submit" class="button-link button-link--ghost" onclick="return confirm('Arquivar este item?');">Arquivar</button>
                                                </form>
                                            </div>
                                        </div>

                                        <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
                                            <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/itens/ordenar'); ?>">
                                                <?php echo $csrfField; ?>
                                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                                <input type="hidden" name="modulo_id" value="<?php echo (int) $modulo['id']; ?>">
                                                <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                <input type="hidden" name="direcao" value="subir">
                                                <button type="submit" class="button-link">Subir</button>
                                            </form>
                                            <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/itens/ordenar'); ?>">
                                                <?php echo $csrfField; ?>
                                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                                <input type="hidden" name="modulo_id" value="<?php echo (int) $modulo['id']; ?>">
                                                <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                <input type="hidden" name="direcao" value="descer">
                                                <button type="submit" class="button-link">Descer</button>
                                            </form>

                                            <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl . '/conteudo/item/mover'); ?>" class="form-grid" style="align-items:end;">
                                                <?php echo $csrfField; ?>
                                                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoIdAtual; ?>">
                                                <input type="hidden" name="turma_id" value="<?php echo $turmaIdAtual > 0 ? (int) $turmaIdAtual : ''; ?>">
                                                <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                <label style="margin:0;">
                                                    <small class="muted">Mover para</small>
                                                    <select name="novo_modulo_id">
                                                        <?php foreach ($conteudoModulos as $m2): ?>
                                                            <option value="<?php echo (int) $m2['id']; ?>" <?php echo (int) $m2['id'] === (int) $modulo['id'] ? 'selected' : ''; ?>>
                                                                <?php echo Helpers::e($m2['titulo']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <button type="submit" class="button-link">Mover</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>
