<?php use App\Core\Helpers; ?>

<?php
$item = isset($item) && is_array($item) ? $item : null;
$detalhe = isset($detalhe) && is_array($detalhe) ? $detalhe : array();
$modulos = isset($modulos) && is_array($modulos) ? $modulos : array();
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array();

$value = function ($key, $default = '') use ($oldInput, $item) {
    if (array_key_exists($key, $oldInput)) {
        return $oldInput[$key];
    }
    if (is_array($item) && array_key_exists($key, $item)) {
        return $item[$key];
    }
    return $default;
};

$cursoId = isset($curso_id) ? (int) $curso_id : 0;
$turmaId = isset($turma_id) ? (int) $turma_id : 0;
$resolvedCancelUrl = isset($cancel_url) ? (string) $cancel_url : ('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo' . ($turmaId > 0 ? '&turma_id=' . $turmaId : ''));

$formatTipoHint = function ($tipo) {
    $mapa = array(
        'etiqueta' => 'Bloco de destaque para avisos e orientações.',
        'texto' => 'Conteúdo em texto com editor.',
        'arquivo' => 'Arquivo para download (limite 10 MB).',
        'link' => 'Link externo (nova aba, embed ou botão).',
        'avaliacao_textual' => 'Atividade com envio e correção textual.',
        'video' => 'Vídeo via URL (embed).',
        'html' => 'Página HTML/CSS/JS própria, exibida isolada (iframe).',
        'video_incorporado' => 'Vídeo com código de incorporação (embed) colado direto.',
    );
    $tipo = (string) $tipo;
    return isset($mapa[$tipo]) ? $mapa[$tipo] : '';
};

$editorValue = function ($value) {
    return Helpers::e(Helpers::decodeEditorHtml((string) $value));
};
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e(isset($title) ? $title : 'Novo conteúdo'); ?></h1>
            <p class="admin-page__subtitle">Escolha o módulo, o tipo e os detalhes do conteúdo em uma tela dedicada.</p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($resolvedCancelUrl); ?>">Voltar à aba Conteúdo</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <?php if (empty($modulos)): ?>
        <section class="status-card area-curso-empty-state">
            <strong>Você ainda não tem módulos cadastrados.</strong>
            <p class="muted" style="margin:0;">Crie o primeiro módulo antes de cadastrar conteúdos vinculados.</p>
            <a class="button-link button-link--primary" href="/admin/area-curso/conteudo/modulos/criar?curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . $turmaId : ''; ?>">Novo módulo</a>
        </section>
    <?php endif; ?>

    <?php if (!empty($modulos)): ?>
        <section class="status-card">
            <form method="post" action="<?php echo Helpers::e(isset($action_url) ? $action_url : '/admin/area-curso/conteudo/item/salvar'); ?>" class="admin-form admin-area-curso__form" enctype="multipart/form-data">
                <?php echo $csrfField; ?>
                <input type="hidden" name="id" value="<?php echo !empty($item['id']) ? (int) $item['id'] : 0; ?>">
                <input type="hidden" name="curso_evento_id" value="<?php echo $cursoId; ?>">
                <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">

                <?php
                $moduloSelecionado = (int) (array_key_exists('modulo_id', $oldInput) ? $oldInput['modulo_id'] : (!empty($item['modulo_id']) ? $item['modulo_id'] : (isset($modulo_selecionado) ? $modulo_selecionado : 0)));
                ?>
                <label>
                    Módulo
                    <select name="modulo_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($modulos as $modulo): ?>
                            <option value="<?php echo (int) $modulo['id']; ?>" <?php echo $moduloSelecionado === (int) $modulo['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($modulo['titulo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php $tipoSelecionado = (string) $value('tipo', 'texto'); ?>
                <label>
                    Tipo
                    <select name="tipo" id="conteudo-item-tipo" required>
                        <option value="etiqueta" title="<?php echo Helpers::e($formatTipoHint('etiqueta')); ?>" <?php echo $tipoSelecionado === 'etiqueta' ? 'selected' : ''; ?>>Etiqueta</option>
                        <option value="texto" title="<?php echo Helpers::e($formatTipoHint('texto')); ?>" <?php echo $tipoSelecionado === 'texto' ? 'selected' : ''; ?>>Texto</option>
                        <option value="arquivo" title="<?php echo Helpers::e($formatTipoHint('arquivo')); ?>" <?php echo $tipoSelecionado === 'arquivo' ? 'selected' : ''; ?>>Arquivo</option>
                        <option value="link" title="<?php echo Helpers::e($formatTipoHint('link')); ?>" <?php echo $tipoSelecionado === 'link' ? 'selected' : ''; ?>>Link</option>
                        <option value="avaliacao_textual" title="<?php echo Helpers::e($formatTipoHint('avaliacao_textual')); ?>" <?php echo $tipoSelecionado === 'avaliacao_textual' ? 'selected' : ''; ?>>Avaliação textual</option>
                        <option value="video" title="<?php echo Helpers::e($formatTipoHint('video')); ?>" <?php echo $tipoSelecionado === 'video' ? 'selected' : ''; ?>>Vídeo</option>
                        <option value="quiz" <?php echo $tipoSelecionado === 'quiz' ? 'selected' : ''; ?>>Quiz (múltipla escolha)</option>
                        <option value="html" title="<?php echo Helpers::e($formatTipoHint('html')); ?>" <?php echo $tipoSelecionado === 'html' ? 'selected' : ''; ?>>HTML</option>
                        <option value="video_incorporado" title="<?php echo Helpers::e($formatTipoHint('video_incorporado')); ?>" <?php echo $tipoSelecionado === 'video_incorporado' ? 'selected' : ''; ?>>Vídeo incorporado</option>
                    </select>
                </label>

                <div class="muted" style="grid-column: 1 / -1;">
                    Etiqueta: bloco de orientação exibido ao aluno. | Texto: página de conteúdo com editor. | Arquivo: material para download. | Link: endereço externo, botão ou embed. | Avaliação textual: pergunta discursiva com nota e feedback. | Vídeo: vídeo incorporado por link/embed. | Quiz: atividade de múltipla escolha com correção automática. | HTML: página HTML/CSS/JS própria, colada e exibida isolada. | Vídeo incorporado: cole o código de embed (iframe) de um provedor de vídeo, exibido num player 16:9.
                </div>

                <label>
                    Título
                    <input type="text" name="titulo" value="<?php echo Helpers::e((string) $value('titulo', '')); ?>" required>
                </label>

                <label>
                    Descrição curta (opcional)
                    <textarea name="descricao_curta" rows="3"><?php echo Helpers::e((string) $value('descricao_curta', '')); ?></textarea>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="obrigatorio" value="1" <?php echo !empty($value('obrigatorio', null)) ? 'checked' : ''; ?>>
                    Obrigatório
                </label>

                <label>
                    Status
                    <?php $statusItem = (string) $value('status', 'publicado'); ?>
                    <select name="status">
                        <option value="rascunho" <?php echo $statusItem === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                        <option value="publicado" <?php echo $statusItem === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                        <option value="oculto" <?php echo $statusItem === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                        <option value="arquivado" <?php echo $statusItem === 'arquivado' ? 'selected' : ''; ?>>Arquivado</option>
                    </select>
                </label>

                <div id="conteudo-tipo-etiqueta" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Etiqueta</h4>
                    <label class="admin-form-grid__full">
                        Conteúdo
                        <textarea name="etiqueta_conteudo" class="js-conteudo-rich-editor" data-editor-mode="full" rows="8"><?php echo $editorValue($value('etiqueta_conteudo', !empty($detalhe['conteudo']) ? $detalhe['conteudo'] : '')); ?></textarea>
                    </label>
                </div>

                <div id="conteudo-tipo-texto" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Texto</h4>
                    <label class="admin-form-grid__full">
                        Conteúdo
                        <textarea name="texto_conteudo" class="js-conteudo-rich-editor" data-editor-mode="full" rows="10"><?php echo $editorValue($value('texto_conteudo', !empty($detalhe['conteudo']) ? $detalhe['conteudo'] : '')); ?></textarea>
                    </label>
                </div>

                <div id="conteudo-tipo-link" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Link externo</h4>
                    <label class="admin-form-grid__full">
                        URL
                        <input type="url" name="link_url" value="<?php echo Helpers::e((string) $value('link_url', !empty($detalhe['url']) ? $detalhe['url'] : '')); ?>">
                    </label>
                    <?php $modoLink = (string) $value('link_modo_abertura', !empty($detalhe['modo_abertura']) ? $detalhe['modo_abertura'] : 'nova_aba'); ?>
                    <label class="admin-form-grid__full">
                        Modo de abertura
                        <select name="link_modo_abertura">
                            <option value="nova_aba" <?php echo $modoLink === 'nova_aba' ? 'selected' : ''; ?>>Nova aba</option>
                            <option value="embed" <?php echo $modoLink === 'embed' ? 'selected' : ''; ?>>Embed</option>
                            <option value="botao" <?php echo $modoLink === 'botao' ? 'selected' : ''; ?>>Botão</option>
                        </select>
                    </label>
                </div>

                <div id="conteudo-tipo-video" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Vídeo</h4>
                    <label class="admin-form-grid__full">
                        URL
                        <input type="url" name="video_url" value="<?php echo Helpers::e((string) $value('video_url', !empty($detalhe['url']) ? $detalhe['url'] : '')); ?>">
                    </label>
                    <label class="admin-form-grid__full">
                        Duração (segundos, opcional)
                        <input type="number" name="video_duracao_segundos" value="<?php echo Helpers::e((string) $value('video_duracao_segundos', !empty($detalhe['duracao_segundos']) ? $detalhe['duracao_segundos'] : '')); ?>" min="0">
                    </label>
                </div>

                <div id="conteudo-tipo-avaliacao" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Avaliação textual</h4>
                    <label>
                        Enunciado
                        <textarea name="avaliacao_enunciado" class="js-conteudo-rich-editor" data-editor-mode="full" rows="10"><?php echo $editorValue($value('avaliacao_enunciado', !empty($detalhe['enunciado']) ? $detalhe['enunciado'] : '')); ?></textarea>
                    </label>
                    <label>
                        Orientações (opcional)
                        <textarea name="avaliacao_orientacoes" class="js-conteudo-rich-editor" data-editor-mode="full" rows="6"><?php echo $editorValue($value('avaliacao_orientacoes', !empty($detalhe['orientacoes']) ? $detalhe['orientacoes'] : '')); ?></textarea>
                    </label>
                    <label>
                        Nota máxima (opcional)
                        <input type="number" name="avaliacao_nota_maxima" min="0" step="0.01" value="<?php echo Helpers::e((string) $value('avaliacao_nota_maxima', !empty($detalhe['nota_maxima']) ? $detalhe['nota_maxima'] : '')); ?>">
                    </label>
                    <label>
                        Nota mínima para aprovação (opcional)
                        <input type="number" name="avaliacao_nota_minima" min="0" step="0.01" value="<?php echo Helpers::e((string) $value('avaliacao_nota_minima', !empty($detalhe['nota_minima']) ? $detalhe['nota_minima'] : '')); ?>">
                    </label>
                    <label>
                        Peso
                        <input type="number" name="avaliacao_peso" min="0.01" step="0.01" value="<?php echo Helpers::e((string) $value('avaliacao_peso', !empty($detalhe['peso']) ? $detalhe['peso'] : '1.00')); ?>">
                    </label>
                    <label>
                        Prazo (opcional)
                        <input type="datetime-local" name="avaliacao_prazo" value="<?php echo !empty($detalhe['prazo']) ? Helpers::e(str_replace(' ', 'T', substr((string) $detalhe['prazo'], 0, 16))) : Helpers::e((string) $value('avaliacao_prazo', '')); ?>">
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="avaliacao_permite_reenvio" value="1" <?php echo !empty($value('avaliacao_permite_reenvio', !empty($detalhe['permite_reenvio']) ? $detalhe['permite_reenvio'] : null)) ? 'checked' : ''; ?>>
                        Permitir reenvio
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="avaliacao_reenvio_livre_ate_prazo" value="1" <?php echo !isset($detalhe['reenvio_livre_ate_prazo']) || !empty($value('avaliacao_reenvio_livre_ate_prazo', !empty($detalhe['reenvio_livre_ate_prazo']) ? $detalhe['reenvio_livre_ate_prazo'] : null)) ? 'checked' : ''; ?>>
                        Reenvio livre até o prazo
                    </label>
                </div>

                <div id="conteudo-tipo-arquivo" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Arquivo</h4>
                    <p class="muted" style="margin:0;">Limite: 10 MB. Extensões permitidas: pdf, jpg, jpeg, png, webp, doc, docx, odt, xls, xlsx, ods, ppt, pptx, odp, txt, csv.</p>
                    <label>
                        Arquivo
                        <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.odt,.xls,.xlsx,.ods,.ppt,.pptx,.odp,.txt,.csv">
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="arquivo_permite_download" value="1" <?php echo !empty($value('arquivo_permite_download', !empty($detalhe['permite_download']) ? $detalhe['permite_download'] : null)) ? 'checked' : ''; ?>>
                        Permitir download
                    </label>
                    <?php if (!empty($detalhe['nome_original'])): ?>
                        <p class="muted" style="margin:0;">Arquivo atual: <?php echo Helpers::e((string) $detalhe['nome_original']); ?></p>
                    <?php endif; ?>
                </div>

                <div id="conteudo-tipo-html" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">HTML</h4>
                    <p class="muted" style="margin:0;">Cole aqui o HTML completo (pode incluir <code>&lt;style&gt;</code> e <code>&lt;script&gt;</code>). O conteúdo é gravado como está, sem edição, e exibido ao aluno dentro de uma área isolada.</p>
                    <label class="admin-form-grid__full">
                        Código HTML
                        <textarea name="html_conteudo" id="conteudo-html-source" rows="20" style="font-family: monospace; white-space: pre;" spellcheck="false"><?php echo Helpers::e((string) $value('html_conteudo', !empty($detalhe['conteudo']) ? $detalhe['conteudo'] : '')); ?></textarea>
                    </label>
                    <div class="cta-group">
                        <button type="button" id="conteudo-html-preview-btn" class="button-link button-link--ghost">Pré-visualizar em nova aba</button>
                    </div>
                </div>

                <div id="conteudo-tipo-video_incorporado" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Vídeo incorporado</h4>
                    <p class="muted" style="margin:0;">Cole aqui o código de incorporação (embed) fornecido pelo provedor do vídeo (ex.: YouTube, Vimeo, Panda, Wistia — normalmente um <code>&lt;iframe&gt;</code>). O código é gravado como está, sem edição, e exibido ao aluno num player de proporção 16:9, dentro de uma área isolada.</p>
                    <label class="admin-form-grid__full">
                        Código de incorporação (embed)
                        <textarea name="video_incorporado_conteudo" id="conteudo-video-incorporado-source" rows="12" style="font-family: monospace; white-space: pre;" spellcheck="false"><?php echo Helpers::e((string) $value('video_incorporado_conteudo', !empty($detalhe['conteudo']) ? $detalhe['conteudo'] : '')); ?></textarea>
                    </label>
                    <div class="cta-group">
                        <button type="button" id="conteudo-video-incorporado-preview-btn" class="button-link button-link--ghost">Pré-visualizar em nova aba</button>
                    </div>
                </div>

                <?php
                // --- Bloco Quiz ---
                $quizDetalhe = ($tipoSelecionado === 'quiz' && !empty($detalhe) && isset($detalhe['id'])) ? $detalhe : array();
                $qVal = function ($key, $default = '') use ($oldInput, $quizDetalhe) {
                    $prefixKey = 'quiz_' . $key;
                    if (array_key_exists($prefixKey, $oldInput)) { return $oldInput[$prefixKey]; }
                    if (array_key_exists($key, $quizDetalhe)) { return $quizDetalhe[$key]; }
                    return $default;
                };
                ?>
                <div id="conteudo-tipo-quiz" class="form-grid" style="grid-column: 1 / -1;">
                    <h4 style="margin:0;">Configurações do Quiz</h4>
                    <p class="muted" style="margin:0;">O quiz será criado inicialmente sem perguntas. Depois de salvar, adicione as perguntas e alternativas no editor de perguntas.</p>

                    <label style="grid-column: 1 / -1;">
                        Instruções (exibidas ao aluno antes de iniciar)
                        <textarea name="quiz_instrucoes" rows="3"><?php echo Helpers::e((string) $qVal('instrucoes', '')); ?></textarea>
                    </label>

                    <label>
                        Máximo de tentativas
                        <input type="number" name="quiz_tentativas_maximas" min="1" step="1" placeholder="Deixe em branco para ilimitado" value="<?php echo Helpers::e((string) $qVal('tentativas_maximas', '')); ?>">
                        <span class="field-hint">Deixe em branco para tentativas ilimitadas.</span>
                    </label>

                    <label>
                        Percentual mínimo para aprovação (%)
                        <input type="number" name="quiz_percentual_minimo" min="0" max="100" step="0.01" value="<?php echo Helpers::e((string) $qVal('percentual_minimo', '0')); ?>">
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_exige_aprovacao" value="1" <?php echo !empty($qVal('exige_aprovacao')) ? 'checked' : ''; ?>>
                        Exigir aprovação para concluir o item
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_exibir_resultado_apos_envio" value="1" <?php echo $qVal('exibir_resultado_apos_envio', 1) ? 'checked' : ''; ?>>
                        Exibir resultado após envio (percentual e acertos)
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_exibir_gabarito_apos_envio" value="1" <?php echo $qVal('exibir_gabarito_apos_envio', 1) ? 'checked' : ''; ?>>
                        Exibir gabarito após envio (alternativa correta)
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_exibir_comentarios_apos_envio" value="1" <?php echo $qVal('exibir_comentarios_apos_envio', 1) ? 'checked' : ''; ?>>
                        Exibir comentários/explicações após envio
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_embaralhar_perguntas" value="1" <?php echo !empty($qVal('embaralhar_perguntas')) ? 'checked' : ''; ?>>
                        Embaralhar ordem das perguntas
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_embaralhar_alternativas" value="1" <?php echo !empty($qVal('embaralhar_alternativas')) ? 'checked' : ''; ?>>
                        Embaralhar alternativas de cada pergunta
                    </label>

                    <h4 style="grid-column: 1 / -1; margin:16px 0 0;">Simulado (tempo e banco de questões)</h4>
                    <p class="muted" style="grid-column: 1 / -1; margin:0;">
                        Deixe a duração em branco e o modo em “Todas as perguntas” para manter o comportamento
                        padrão do quiz. Use “Sortear por blocos” para provas longas com banco de questões.
                    </p>

                    <label>
                        Duração da prova (minutos)
                        <input type="number" name="quiz_duracao_minutos" min="1" step="1"
                               placeholder="Deixe em branco para não ter limite"
                               value="<?php echo Helpers::e((string) $qVal('duracao_minutos', '')); ?>">
                        <span class="field-hint">O prazo é controlado no servidor. Ex.: 330 para a PND.</span>
                    </label>

                    <label>
                        Modo de seleção das questões
                        <?php $modoAtual = (string) $qVal('modo_selecao', 'todas'); ?>
                        <select name="quiz_modo_selecao">
                            <option value="todas" <?php echo $modoAtual !== 'blocos' ? 'selected' : ''; ?>>Todas as perguntas cadastradas</option>
                            <option value="blocos" <?php echo $modoAtual === 'blocos' ? 'selected' : ''; ?>>Sortear por blocos (banco de questões)</option>
                        </select>
                    </label>

                    <label>
                        Ao esgotar o tempo
                        <?php $acaoAtual = (string) $qVal('acao_ao_expirar', 'enviar_automatico'); ?>
                        <select name="quiz_acao_ao_expirar">
                            <option value="enviar_automatico" <?php echo $acaoAtual !== 'encerrar_sem_envio' ? 'selected' : ''; ?>>Enviar automaticamente o que estiver salvo</option>
                            <option value="encerrar_sem_envio" <?php echo $acaoAtual === 'encerrar_sem_envio' ? 'selected' : ''; ?>>Encerrar a tentativa sem corrigir</option>
                        </select>
                    </label>

                    <label>
                        Limite de caracteres da discursiva
                        <input type="number" name="quiz_limite_caracteres_discursiva" min="100" step="100"
                               placeholder="Padrão: 50.000"
                               value="<?php echo Helpers::e((string) $qVal('limite_caracteres_discursiva', '')); ?>">
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_evitar_repeticao_tentativas" value="1" <?php echo $qVal('evitar_repeticao_tentativas', 1) ? 'checked' : ''; ?>>
                        Evitar repetir questões entre as tentativas do mesmo aluno
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="quiz_permitir_banco_insuficiente" value="1" <?php echo !empty($qVal('permitir_banco_insuficiente')) ? 'checked' : ''; ?>>
                        Permitir publicar mesmo com banco insuficiente (exceção consciente)
                    </label>

                    <?php if (!empty($item['id']) && $tipoSelecionado === 'quiz'): ?>
                        <div style="grid-column: 1 / -1; margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <a class="button-link button-link--secondary"
                               href="/admin/area-curso/conteudo/quiz/perguntas?item_id=<?php echo (int) $item['id']; ?>&curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . $turmaId : ''; ?>">
                                Editar perguntas
                            </a>
                            <a class="button-link button-link--ghost"
                               href="/admin/area-curso/conteudo/quiz/blocos?item_id=<?php echo (int) $item['id']; ?>&curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . $turmaId : ''; ?>">
                                Blocos de sorteio
                            </a>
                            <a class="button-link button-link--ghost"
                               href="/admin/area-curso/conteudo/quiz/discursivas?item_id=<?php echo (int) $item['id']; ?>&curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . $turmaId : ''; ?>">
                                Corrigir discursivas
                            </a>
                            <a class="button-link button-link--ghost"
                               href="/admin/area-curso/conteudo/quiz/preview?item_id=<?php echo (int) $item['id']; ?>&curso_id=<?php echo $cursoId; ?>">
                                Pré-visualizar
                            </a>
                            <a class="button-link button-link--ghost"
                               href="/admin/area-curso/conteudo/quiz/resultados?item_id=<?php echo (int) $item['id']; ?>&curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . $turmaId : ''; ?>">
                                Ver resultados
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $show_save_and_new = false;
                $show_save_and_exit = false;
                $show_save_as_copy = false;
                $cancel_url = $resolvedCancelUrl;
                $save_label = 'Salvar';
                $cancel_label = 'Cancelar';
                require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                ?>
            </form>

            <form id="conteudo-html-preview-form" method="post" action="/admin/area-curso/conteudo/html/preview" target="_blank" style="display:none;">
                <?php echo $csrfField; ?>
                <input type="hidden" name="html_conteudo" id="conteudo-html-preview-input">
            </form>
        </section>
    <?php endif; ?>
</div>

<script>
(function () {
    var bootRetries = 0;
    var bootTimer = null;

    function toggleTipo() {
        var tipo = document.getElementById('conteudo-item-tipo');
        if (!tipo) {
            return;
        }

        var value = tipo.value || 'texto';
        var blocks = {
            etiqueta: document.getElementById('conteudo-tipo-etiqueta'),
            texto: document.getElementById('conteudo-tipo-texto'),
            link: document.getElementById('conteudo-tipo-link'),
            video: document.getElementById('conteudo-tipo-video'),
            avaliacao_textual: document.getElementById('conteudo-tipo-avaliacao'),
            arquivo: document.getElementById('conteudo-tipo-arquivo'),
            quiz: document.getElementById('conteudo-tipo-quiz'),
            html: document.getElementById('conteudo-tipo-html'),
            video_incorporado: document.getElementById('conteudo-tipo-video_incorporado')
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
            return;
        }

        return false;
    }

    function bootConteudoEditor() {
        if (initRichEditors() !== false) {
            bootRetries = 0;
            if (bootTimer) {
                window.clearTimeout(bootTimer);
                bootTimer = null;
            }
            return true;
        }

        if (bootRetries >= 20) {
            return false;
        }

        bootRetries += 1;
        if (!bootTimer) {
            bootTimer = window.setTimeout(function () {
                bootTimer = null;
                bootConteudoEditor();
            }, 50);
        }

        return false;
    }

    function previewHtml() {
        var source = document.getElementById('conteudo-html-source');
        var input = document.getElementById('conteudo-html-preview-input');
        var form = document.getElementById('conteudo-html-preview-form');
        if (source && input && form) {
            input.value = source.value;
            form.submit();
        }
    }

    function previewVideoIncorporado() {
        var source = document.getElementById('conteudo-video-incorporado-source');
        var input = document.getElementById('conteudo-html-preview-input');
        var form = document.getElementById('conteudo-html-preview-form');
        if (source && input && form) {
            input.value = source.value;
            form.submit();
        }
    }

    function init() {
        toggleTipo();
        bootConteudoEditor();
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'conteudo-item-tipo') {
            toggleTipo();
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target && event.target.id === 'conteudo-html-preview-btn') {
            previewHtml();
        }
        if (event.target && event.target.id === 'conteudo-video-incorporado-preview-btn') {
            previewVideoIncorporado();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('load', bootConteudoEditor);
    window.addEventListener('pageshow', bootConteudoEditor);
})();
</script>
