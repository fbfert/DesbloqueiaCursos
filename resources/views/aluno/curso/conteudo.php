<?php use App\Core\Helpers; use App\Core\Session; use App\Support\HtmlEmbedRenderer; use App\Support\VideoEmbedResolver; ?>
<?php
if (!isset($frontend_template)) { try { $frontend_template = (new \App\Services\ConfiguracaoGlobalService())->templateVisualPortal(); } catch (\Throwable $e) { $frontend_template = 'v1'; } }
if ((string) $frontend_template === 'v4-claude') { require BASE_PATH . '/resources/views/v4-claude/aluno/curso/conteudo.php'; return; }
if (!function_exists('aluno_conteudo_valor')) {
    function aluno_conteudo_valor(array $array, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array) && $array[$key] !== null && $array[$key] !== '') {
                return $array[$key];
            }
        }

        return $default;
    }
}

$inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$turmaNome = Helpers::normalizarTextoLms(!empty($turma['nome']) ? $turma['nome'] : '');
$cursoTitulo = $cursoNome !== '' ? $cursoNome : 'Curso';
$modulo = isset($conteudo_modulo) && is_array($conteudo_modulo) ? $conteudo_modulo : array();
$item = isset($conteudo_item) && is_array($conteudo_item) ? $conteudo_item : array();
$detalhe = isset($conteudo_detalhe) && is_array($conteudo_detalhe) ? $conteudo_detalhe : array();
$resumo = isset($conteudo_resumo) && is_array($conteudo_resumo) ? $conteudo_resumo : array();
$moduloId = (int) ($modulo['id'] ?? 0);
$itemId = (int) ($item['id'] ?? 0);
$itemTitulo = Helpers::normalizarTextoLms((string) ($item['titulo'] ?? 'Conteúdo'));
$moduloTitulo = Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo'));
$tipoLabel = isset($item['tipo_label']) ? (string) $item['tipo_label'] : 'Conteúdo';
$tipo = isset($item['tipo']) ? (string) $item['tipo'] : '';
$statusTexto = isset($item['status_label']) ? (string) $item['status_label'] : 'Pendente';
$statusClasse = isset($item['status_class']) ? (string) $item['status_class'] : 'pill--neutral';
$statusProgressoItem = isset($conteudo_progresso['status']) ? (string) $conteudo_progresso['status'] : '';
$itemConcluido = !empty($item['concluido_aluno']) || in_array($statusProgressoItem, array('concluido', 'aprovada', 'corrigida'), true);
$statusConclusao = $itemConcluido ? 'Concluído' : 'Não concluído';
$cursoProgressPercent = (float) ($resumo['percentual'] ?? ($resumo['percentual_progresso'] ?? 0));
$cursoProgressText = isset($resumo['concluidos_itens'], $resumo['total_itens']) ? (int) $resumo['concluidos_itens'] . '/' . (int) $resumo['total_itens'] . ' conteúdos concluídos' : 'Progresso indisponível';
$voltarModuloUrl = isset($conteudo_voltar_modulo_url) && $conteudo_voltar_modulo_url !== '' ? (string) $conteudo_voltar_modulo_url : '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
$anteriorUrl = isset($conteudo_anterior_url) ? (string) $conteudo_anterior_url : null;
$anteriorLabel = isset($conteudo_anterior_label) ? (string) $conteudo_anterior_label : null;
$proximoUrl = isset($conteudo_proximo_url) ? (string) $conteudo_proximo_url : null;
$proximoLabel = isset($conteudo_proximo_label) ? (string) $conteudo_proximo_label : null;
$arquivoUrl = isset($item['acao_url']) ? (string) $item['acao_url'] : '';
$arquivoDisponivel = $arquivoUrl !== '';
$conteudoTexto = aluno_conteudo_valor($detalhe, array('conteudo', 'texto', 'descricao', 'corpo', 'html'), '');
$videoUrl = aluno_conteudo_valor($detalhe, array('url', 'video_url', 'link'), '');
$videoEmbed = aluno_conteudo_valor($detalhe, array('embed_html', 'embed', 'html'), '');
$avaliacoes = isset($conteudo_avaliacao_entregas) && is_array($conteudo_avaliacao_entregas) ? $conteudo_avaliacao_entregas : array();
$ultimaEntrega = !empty($avaliacoes) ? $avaliacoes[0] : null;
$avaliacaoPodeEnviar = !empty($conteudo_avaliacao_pode_enviar);
$descricaoCurta = trim((string) ($item['descricao_curta'] ?? ''));
$detalheProfessor = trim((string) aluno_conteudo_valor($detalhe, array('observacao', 'orientacao', 'descricao', 'conteudo'), ''));
$renderRich = static function ($html, $mode = 'basic') {
    return Helpers::renderSafeHtml((string) $html, $mode);
};

$chipsConteudo = array();
$adicionarChip = static function (&$chips, $label, $classe = 'neutral') {
    $label = trim((string) $label);
    if ($label === '') {
        return;
    }

    if (!function_exists('mb_strtolower')) {
        $key = strtolower($label);
    } else {
        $key = mb_strtolower($label, 'UTF-8');
    }

    foreach ($chips as $chip) {
        if (($chip['key'] ?? '') === $key) {
            return;
        }
    }

    $chips[] = array(
        'key' => $key,
        'label' => $label,
        'class' => $classe,
    );
};

$renderNav = static function ($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel) {
    ?>
    <nav class="study-nav-icons" aria-label="Navegação do conteúdo">
        <a
            class="study-nav-icons__btn"
            href="<?php echo Helpers::e($voltarModuloUrl); ?>"
            title="Voltar ao módulo"
            aria-label="Voltar ao módulo"
        >
            <span aria-hidden="true">↑</span>
            <span class="sr-only">Voltar ao módulo</span>
        </a>

        <?php if (!empty($anteriorUrl)): ?>
            <a
                class="study-nav-icons__btn"
                href="<?php echo Helpers::e($anteriorUrl); ?>"
                title="Conteúdo anterior"
                aria-label="Conteúdo anterior"
            >
                <span aria-hidden="true">←</span>
                <span class="sr-only">Conteúdo anterior</span>
            </a>
        <?php else: ?>
            <span
                class="study-nav-icons__btn study-nav-icons__btn--disabled"
                title="Não há conteúdo anterior"
                aria-label="Não há conteúdo anterior"
                aria-disabled="true"
            >
                <span aria-hidden="true">←</span>
                <span class="sr-only">Não há conteúdo anterior</span>
            </span>
        <?php endif; ?>

        <?php if (!empty($proximoUrl)): ?>
            <a
                class="study-nav-icons__btn study-nav-icons__btn--primary"
                href="<?php echo Helpers::e($proximoUrl); ?>"
                title="Próximo conteúdo"
                aria-label="Próximo conteúdo"
            >
                <span aria-hidden="true">→</span>
                <span class="sr-only">Próximo conteúdo</span>
            </a>
        <?php else: ?>
            <span
                class="study-nav-icons__btn study-nav-icons__btn--disabled"
                title="Não há próximo conteúdo"
                aria-label="Não há próximo conteúdo"
                aria-disabled="true"
            >
                <span aria-hidden="true">→</span>
                <span class="sr-only">Não há próximo conteúdo</span>
            </span>
        <?php endif; ?>
    </nav>
    <?php
};
?>

<div class="study-page aluno-study-mode">
    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success" aria-live="polite">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error" aria-live="polite">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="status-card aluno-study-hero study-shell study-shell--hero">
        <?php $renderNav($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel); ?>

        <div class="aluno-study-hero__title">
            <p class="aluno-course-eyebrow">Modo de estudo</p>
            <h1><?php echo Helpers::e($itemTitulo); ?></h1>
            <p class="aluno-study-hero__subtitle"><?php echo Helpers::e($cursoProgressText); ?></p>
        </div>

        <?php if ($descricaoCurta !== ''): ?>
            <p class="aluno-study-hero__description"><?php echo Helpers::e($descricaoCurta); ?></p>
        <?php endif; ?>
    </section>

    <section class="status-card aluno-study-content study-shell study-shell--content">
        <?php if ($tipo === 'texto'): ?>
            <div class="conteudo-item-rich conteudo-item-rich--reading js-conteudo-texto-audio" data-audio-texto="1">
                <?php echo $renderRich($conteudoTexto !== '' ? $conteudoTexto : $detalheProfessor, 'full'); ?>
            </div>
        <?php elseif ($tipo === 'html'): ?>
            <article class="conteudo-item-html">
                <iframe
                    id="conteudo-html-frame-<?php echo $itemId; ?>"
                    class="js-conteudo-html-frame conteudo-item-html__frame"
                    sandbox="allow-scripts allow-popups"
                    title="<?php echo Helpers::e($itemTitulo); ?>"
                    loading="lazy"
                    srcdoc="<?php echo Helpers::e(HtmlEmbedRenderer::wrap($conteudoTexto, 'conteudo-html-frame-' . $itemId)); ?>"
                ></iframe>
            </article>
        <?php elseif ($tipo === 'arquivo'): ?>
            <?php
            $arquivoExtensao = trim((string) aluno_conteudo_valor($detalhe, array('extensao'), ''), '. ');
            $arquivoNome = trim((string) aluno_conteudo_valor($detalhe, array('nome_original', 'nome_arquivo'), ''));
            $arquivoTamanho = Helpers::formatarTamanhoArquivo(aluno_conteudo_valor($detalhe, array('tamanho_bytes'), 0));
            $arquivoIcone = Helpers::iconeArquivo($arquivoExtensao);
            ?>
            <article class="conteudo-item-file">
                <div class="conteudo-item-file__header">
                    <strong>Arquivo do conteúdo</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-file__description"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
                <?php if ($arquivoDisponivel): ?>
                    <div class="conteudo-item-file__card">
                        <span class="conteudo-item-file__icon" aria-hidden="true"><?php echo $arquivoIcone; ?></span>
                        <div class="conteudo-item-file__info">
                            <strong class="conteudo-item-file__name"><?php echo Helpers::e($arquivoNome !== '' ? $arquivoNome : $itemTitulo); ?></strong>
                            <span class="conteudo-item-file__specs">
                                <?php if ($arquivoExtensao !== ''): ?><span class="pill pill--neutral"><?php echo Helpers::e(strtoupper($arquivoExtensao)); ?></span><?php endif; ?>
                                <?php if ($arquivoTamanho !== ''): ?><span><?php echo Helpers::e($arquivoTamanho); ?></span><?php endif; ?>
                            </span>
                        </div>
                        <a class="button-link" href="<?php echo Helpers::e($arquivoUrl); ?>">Baixar arquivo</a>
                    </div>
                <?php else: ?>
                    <p class="conteudo-item-note">O arquivo não está disponível no momento.</p>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'link'): ?>
            <?php
            $linkUrlRaw = (string) aluno_conteudo_valor($detalhe, array('url'), '');
            $linkHost = $linkUrlRaw !== '' ? (string) preg_replace('/^www\./', '', (string) parse_url($linkUrlRaw, PHP_URL_HOST)) : '';
            $linkDescricao = $conteudoTexto !== '' ? $conteudoTexto : $descricaoCurta;
            ?>
            <article class="conteudo-item-link">
                <div class="conteudo-item-link__meta">
                    <strong>Link de acesso</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($linkDescricao !== ''): ?>
                    <div class="conteudo-item-link__description"><?php echo $renderRich($linkDescricao, 'basic'); ?></div>
                <?php endif; ?>
                <?php if ($arquivoDisponivel): ?>
                    <div class="conteudo-item-link__card">
                        <span class="conteudo-item-link__icon" aria-hidden="true">🔗</span>
                        <div class="conteudo-item-link__info">
                            <strong class="conteudo-item-link__name"><?php echo Helpers::e($itemTitulo); ?></strong>
                            <?php if ($linkHost !== ''): ?><span class="conteudo-item-link__host"><?php echo Helpers::e($linkHost); ?></span><?php endif; ?>
                        </div>
                        <a class="button-link" href="<?php echo Helpers::e($arquivoUrl); ?>">Abrir link ↗</a>
                    </div>
                <?php else: ?>
                    <p class="conteudo-item-note">O link não está disponível no momento.</p>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'video'): ?>
            <?php $videoEmbedInfo = $videoEmbed !== '' ? null : VideoEmbedResolver::resolve($videoUrl); ?>
            <article class="conteudo-item-video">
                <div class="conteudo-item-video__meta">
                    <strong>Vídeo do conteúdo</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($videoEmbed !== ''): ?>
                    <div class="conteudo-item-video__embed">
                        <?php echo $renderRich($videoEmbed, 'full'); ?>
                    </div>
                <?php elseif ($videoEmbedInfo !== null): ?>
                    <div class="conteudo-item-video__player">
                        <iframe
                            src="<?php echo Helpers::e($videoEmbedInfo['embedUrl']); ?>"
                            title="<?php echo Helpers::e($itemTitulo); ?>"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen
                        ></iframe>
                    </div>
                    <?php if ($videoUrl !== ''): ?>
                        <p class="conteudo-item-video__external">
                            <a href="<?php echo Helpers::e($videoUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir no site original ↗</a>
                        </p>
                    <?php endif; ?>
                <?php elseif ($videoUrl !== ''): ?>
                    <div class="conteudo-item-actions">
                        <a class="button-link" href="<?php echo Helpers::e($videoUrl); ?>" target="_blank" rel="noopener noreferrer">Assistir vídeo ↗</a>
                    </div>
                <?php else: ?>
                    <p class="conteudo-item-note">Não há vídeo disponível para este conteúdo.</p>
                <?php endif; ?>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-note"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'video_incorporado'): ?>
            <?php
            $videoIncorporadoConteudo = aluno_conteudo_valor($detalhe, array('conteudo'), '');
            $videoIncorporadoSrcdoc = $videoIncorporadoConteudo !== ''
                ? '<style>html,body{margin:0;padding:0;height:100%;overflow:hidden}iframe,video,embed,object{width:100%;height:100%;border:0;display:block}</style>' . $videoIncorporadoConteudo
                : '';
            ?>
            <article class="conteudo-item-video">
                <div class="conteudo-item-video__meta">
                    <strong>Vídeo do conteúdo</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($videoIncorporadoConteudo !== ''): ?>
                    <div class="conteudo-item-video__player">
                        <iframe
                            id="conteudo-video-incorporado-frame-<?php echo $itemId; ?>"
                            class="conteudo-item-video__frame"
                            sandbox="allow-scripts allow-popups"
                            title="<?php echo Helpers::e($itemTitulo); ?>"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                            srcdoc="<?php echo Helpers::e($videoIncorporadoSrcdoc); ?>"
                        ></iframe>
                    </div>
                <?php else: ?>
                    <p class="conteudo-item-note">Não há vídeo disponível para este conteúdo.</p>
                <?php endif; ?>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-note"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'avaliacao_textual'): ?>
            <article class="conteudo-item-eval">
                <header class="conteudo-item-eval__header">
                    <strong>Avaliação textual</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </header>

                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-eval__body"><?php echo $renderRich($conteudoTexto, 'full'); ?></div>
                <?php endif; ?>

                <?php if (!empty($ultimaEntrega)): ?>
                    <section class="conteudo-item-feedback">
                        <div class="conteudo-item-feedback__header">
                            <strong>Última entrega</strong>
                            <span><?php echo Helpers::e(Helpers::statusLms((string) ($ultimaEntrega['status'] ?? ''))); ?></span>
                        </div>
                        <div class="conteudo-item-feedback__body">
                            <?php if (!empty($ultimaEntrega['imagens'])): ?>
                                <div class="conteudo-item-eval__imagens">
                                    <?php foreach ($ultimaEntrega['imagens'] as $img): ?>
                                        <a href="/aluno/cursos/conteudo/avaliacao/imagem?id=<?php echo (int) $img['id']; ?>" target="_blank" rel="noopener noreferrer" class="conteudo-item-eval__imagem-item">
                                            <img src="/aluno/cursos/conteudo/avaliacao/imagem?id=<?php echo (int) $img['id']; ?>" alt="<?php echo Helpers::e((string) ($img['nome_original'] ?? 'Imagem enviada')); ?>" loading="lazy">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ultimaEntrega['nota'])): ?>
                                <p>Nota: <?php echo Helpers::e(number_format((float) $ultimaEntrega['nota'], 2, ',', '.')); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ultimaEntrega['feedback'])): ?>
                                <p><?php echo nl2br(Helpers::e((string) $ultimaEntrega['feedback'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="conteudo-item-actions">
                    <?php if ($avaliacaoPodeEnviar): ?>
                        <form method="post" action="/aluno/cursos/conteudo/avaliacao/enviar" enctype="multipart/form-data" class="conteudo-item-form">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
                            <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloId; ?>">
                            <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
                            <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
                            <label class="conteudo-item-form__field">
                                <span>Resposta</span>
                                <textarea name="resposta" rows="8" required placeholder="Digite sua resposta aqui"></textarea>
                            </label>
                            <label class="conteudo-item-form__field">
                                <span>Imagens (opcional)</span>
                                <input type="file" name="imagens[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple id="conteudo-avaliacao-imagens">
                                <small class="conteudo-item-note">Até 5 imagens (JPG, PNG ou WEBP), no máximo 1,5 MB cada.</small>
                                <small class="conteudo-item-note" data-imagens-erro hidden></small>
                            </label>
                            <button type="submit">Enviar resposta</button>
                        </form>
                        <script>
                        (function () {
                            var input = document.getElementById('conteudo-avaliacao-imagens');
                            if (!input) { return; }
                            var erro = input.form.querySelector('[data-imagens-erro]');
                            input.addEventListener('change', function () {
                                if (input.files && input.files.length > 5) {
                                    if (erro) { erro.textContent = 'Selecione no máximo 5 imagens.'; erro.hidden = false; }
                                    input.value = '';
                                } else if (erro) {
                                    erro.hidden = true;
                                }
                            });
                        })();
                        </script>
                    <?php else: ?>
                        <p class="conteudo-item-note">O reenvio desta avaliação não está disponível no momento.</p>
                    <?php endif; ?>
                </section>
            </article>
        <?php elseif ($tipo === 'quiz'): ?>
            <?php require BASE_PATH . '/resources/views/aluno/curso/_quiz.php'; ?>

        <?php else: ?>
            <div class="conteudo-item-note">Este conteúdo ainda não possui uma visualização específica.</div>
        <?php endif; ?>
    </section>

    <section class="study-shell study-shell--actions">
        <?php if ($tipo === 'texto' || $tipo === 'html'): ?>
            <div class="conteudo-conclusao-form conteudo-item-concluir-form">
                <div class="conteudo-conclusao-panel conteudo-conclusao-panel--concluido">
                    <div class="conteudo-conclusao-panel__estado" aria-live="polite">
                        <span class="conteudo-conclusao-icone" aria-hidden="true">✓</span>
                        <strong>Concluído</strong>
                    </div>
                    <p class="conteudo-item-note" style="margin:0;">Este conteúdo foi concluído automaticamente ao ser aberto.</p>
                </div>
            </div>
        <?php elseif ($tipo !== 'avaliacao_textual' && $tipo !== 'quiz'): ?>
            <form method="post" action="/aluno/cursos/conteudo/item/concluir" class="conteudo-conclusao-form conteudo-item-concluir-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
                <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloId; ?>">
                <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
                <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
                <input type="hidden" name="acao" value="<?php echo $itemConcluido ? 'desmarcar' : 'marcar'; ?>">

                <div class="conteudo-conclusao-panel <?php echo $itemConcluido ? 'conteudo-conclusao-panel--concluido' : 'conteudo-conclusao-panel--pendente'; ?>">
                    <div class="conteudo-conclusao-panel__estado" aria-live="polite">
                        <span class="conteudo-conclusao-icone" aria-hidden="true"><?php echo $itemConcluido ? '✓' : '○'; ?></span>
                        <strong><?php echo $itemConcluido ? 'Concluído' : 'Não concluído'; ?></strong>
                    </div>
                    <button type="submit" class="conteudo-conclusao-btn <?php echo $itemConcluido ? 'conteudo-conclusao-btn--concluido' : 'conteudo-conclusao-btn--pendente'; ?>" title="<?php echo $itemConcluido ? 'Reverter conclusão' : 'Concluir'; ?>" aria-label="<?php echo $itemConcluido ? 'Reverter conclusão' : 'Concluir'; ?>">
                        <span class="conteudo-conclusao-icone" aria-hidden="true"><?php echo $itemConcluido ? '↺' : '✓'; ?></span>
                        <span><?php echo $itemConcluido ? 'Reverter conclusão' : 'Concluir'; ?></span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="study-shell study-shell--nav-bottom">
        <?php $renderNav($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel); ?>
    </section>
</div>
