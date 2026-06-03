<?php use App\Core\Helpers; ?>
<?php
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
$statusConclusao = !empty($item['concluido_aluno']) ? 'Concluído' : 'Pendente';
$cursoProgressPercent = (float) ($resumo['percentual'] ?? ($resumo['percentual_progresso'] ?? 0));
$cursoProgressText = isset($resumo['concluidos_itens'], $resumo['total_itens']) ? (int) $resumo['concluidos_itens'] . '/' . (int) $resumo['total_itens'] . ' conteúdos concluídos' : 'Progresso indisponível';
$voltarModuloUrl = isset($conteudo_voltar_modulo_url) && $conteudo_voltar_modulo_url !== '' ? (string) $conteudo_voltar_modulo_url : '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
$anteriorUrl = isset($conteudo_anterior_url) ? (string) $conteudo_anterior_url : null;
$anteriorLabel = isset($conteudo_anterior_label) ? (string) $conteudo_anterior_label : null;
$proximoUrl = isset($conteudo_proximo_url) ? (string) $conteudo_proximo_url : null;
$proximoLabel = isset($conteudo_proximo_label) ? (string) $conteudo_proximo_label : null;
$itemConcluido = !empty($item['concluido_aluno']);
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

$renderNav = static function ($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel) {
    ?>
    <nav class="aluno-study-nav" aria-label="Navegação do conteúdo">
        <div class="aluno-study-nav__primary">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($voltarModuloUrl); ?>" title="Voltar ao módulo" aria-label="Voltar ao módulo">← Voltar ao módulo</a>
        </div>
        <div class="aluno-study-nav__secondary">
            <?php if (!empty($anteriorUrl)): ?>
                <a class="button-link button-link--ghost" href="<?php echo Helpers::e($anteriorUrl); ?>" title="<?php echo Helpers::e($anteriorLabel !== null ? 'Conteúdo anterior: ' . $anteriorLabel : 'Conteúdo anterior'); ?>" aria-label="<?php echo Helpers::e($anteriorLabel !== null ? 'Conteúdo anterior: ' . $anteriorLabel : 'Conteúdo anterior'); ?>">← Conteúdo anterior</a>
            <?php else: ?>
                <span class="button-link button-link--ghost is-disabled" aria-disabled="true" title="Conteúdo anterior indisponível">← Conteúdo anterior</span>
            <?php endif; ?>

            <?php if (!empty($proximoUrl)): ?>
                <a class="button-link" href="<?php echo Helpers::e($proximoUrl); ?>" title="<?php echo Helpers::e($proximoLabel !== null ? 'Próximo conteúdo: ' . $proximoLabel : 'Próximo conteúdo'); ?>" aria-label="<?php echo Helpers::e($proximoLabel !== null ? 'Próximo conteúdo: ' . $proximoLabel : 'Próximo conteúdo'); ?>">Próximo conteúdo →</a>
            <?php else: ?>
                <span class="button-link is-disabled" aria-disabled="true" title="Fim do módulo">Próximo conteúdo →</span>
            <?php endif; ?>
        </div>
    </nav>
    <?php
};
?>

<section class="aluno-study-mode">
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

    <section class="status-card aluno-study-hero">
        <?php $renderNav($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel); ?>

        <div class="aluno-study-hero__meta">
            <span class="pill pill--neutral"><?php echo Helpers::e($cursoTitulo); ?></span>
            <?php if ($turmaNome !== ''): ?>
                <span class="pill pill--neutral"><?php echo Helpers::e($turmaNome); ?></span>
            <?php endif; ?>
            <span class="pill pill--neutral"><?php echo Helpers::e($moduloTitulo); ?></span>
            <span class="pill pill--neutral"><?php echo Helpers::e($tipoLabel); ?></span>
            <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
            <?php if ($itemConcluido): ?>
                <span class="pill pill--success">Concluído</span>
            <?php else: ?>
                <span class="pill pill--warning">Pendente</span>
            <?php endif; ?>
        </div>

        <div class="aluno-study-hero__title">
            <p class="aluno-course-eyebrow">Modo de estudo</p>
            <h1><?php echo Helpers::e($itemTitulo); ?></h1>
            <p class="aluno-study-hero__subtitle"><?php echo Helpers::e($cursoProgressText); ?></p>
        </div>

        <?php if ($descricaoCurta !== ''): ?>
            <p class="aluno-study-hero__description"><?php echo Helpers::e($descricaoCurta); ?></p>
        <?php endif; ?>
    </section>

    <section class="status-card aluno-study-content">
        <?php if ($tipo === 'texto' || $tipo === 'etiqueta'): ?>
            <div class="conteudo-item-rich conteudo-item-rich--reading">
                <?php echo $renderRich($conteudoTexto !== '' ? $conteudoTexto : $detalheProfessor, 'full'); ?>
            </div>
        <?php elseif ($tipo === 'arquivo'): ?>
            <article class="conteudo-item-file">
                <div class="conteudo-item-file__header">
                    <strong>Arquivo do conteúdo</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-file__description"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
                <?php if ($arquivoDisponivel): ?>
                    <div class="conteudo-item-actions">
                        <a class="button-link" href="<?php echo Helpers::e($arquivoUrl); ?>">Baixar arquivo</a>
                    </div>
                <?php else: ?>
                    <p class="conteudo-item-note">O arquivo não está disponível no momento.</p>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'link'): ?>
            <article class="conteudo-item-link">
                <div class="conteudo-item-link__meta">
                    <strong>Link de acesso</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($conteudoTexto !== ''): ?>
                    <div class="conteudo-item-link__description"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div>
                <?php endif; ?>
                <?php if ($arquivoDisponivel): ?>
                    <div class="conteudo-item-actions">
                        <a class="button-link" href="<?php echo Helpers::e($arquivoUrl); ?>">Abrir link</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'video'): ?>
            <article class="conteudo-item-video">
                <div class="conteudo-item-video__meta">
                    <strong>Vídeo do conteúdo</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <?php if ($videoEmbed !== ''): ?>
                    <div class="conteudo-item-video__embed">
                        <?php echo $renderRich($videoEmbed, 'full'); ?>
                    </div>
                <?php elseif ($videoUrl !== ''): ?>
                    <div class="conteudo-item-actions">
                        <a class="button-link" href="<?php echo Helpers::e($videoUrl); ?>" target="_blank" rel="noopener noreferrer">Assistir vídeo</a>
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
                        <form method="post" action="/aluno/cursos/conteudo/avaliacao/enviar" class="conteudo-item-form">
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
                            <button type="submit">Enviar resposta</button>
                        </form>
                    <?php else: ?>
                        <p class="conteudo-item-note">O reenvio desta avaliação não está disponível no momento.</p>
                    <?php endif; ?>
                </section>
            </article>
        <?php else: ?>
            <div class="conteudo-item-note">Este conteúdo ainda não possui uma visualização específica.</div>
        <?php endif; ?>
    </section>

    <section class="status-card aluno-study-completion">
        <?php if ($tipo !== 'avaliacao_textual'): ?>
            <?php if (!$itemConcluido): ?>
                <form method="post" action="/aluno/cursos/conteudo/item/concluir" class="conteudo-item-concluir-form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
                    <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloId; ?>">
                    <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
                    <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
                    <button type="submit">Marcar como concluído</button>
                </form>
            <?php else: ?>
                <div class="aluno-study-completion__status">
                    <strong>Conteúdo concluído</strong>
                    <span>Este conteúdo já foi marcado como concluído.</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="status-card aluno-study-footer">
        <?php $renderNav($voltarModuloUrl, $anteriorUrl, $anteriorLabel, $proximoUrl, $proximoLabel); ?>
    </section>
</section>
