<?php use App\Core\Helpers; ?>
<?php
$item      = isset($item) && is_array($item) ? $item : array();
$quiz      = isset($quiz) && is_array($quiz) ? $quiz : null;
$perguntas = isset($perguntas) && is_array($perguntas) ? $perguntas : array();
$cursoId   = isset($curso_id) ? (int) $curso_id : 0;
$itemId    = (int) ($item['id'] ?? 0);
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Pré-visualização do Quiz</h1>
            <p class="admin-page__subtitle"><?php echo Helpers::e((string) ($item['titulo'] ?? '')); ?></p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost"
               href="/admin/area-curso/conteudo/quiz/perguntas?item_id=<?php echo $itemId; ?>&curso_id=<?php echo $cursoId; ?>">
                Editar perguntas
            </a>
        </div>
    </section>

    <section class="status-card">
        <div class="conteudo-quiz-preview">
            <div class="conteudo-item-quiz__header">
                <strong>Quiz — <?php echo count($perguntas); ?> pergunta(s)</strong>
                <p class="muted" style="margin:4px 0 0;">
                    Esta é uma pré-visualização administrativa. Nenhuma tentativa é criada.
                </p>
            </div>

            <?php if ($quiz && !empty($quiz['instrucoes'])): ?>
                <div class="conteudo-item-quiz__instrucoes" style="background:#f9fafb; padding:12px; border-radius:4px; margin:12px 0;">
                    <?php echo nl2br(Helpers::e((string) $quiz['instrucoes'])); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($perguntas)): ?>
                <p class="muted">Nenhuma pergunta cadastrada.</p>
            <?php else: ?>
                <?php foreach ($perguntas as $idx => $pergunta): ?>
                    <div class="conteudo-quiz-pergunta" style="margin:16px 0; padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px;">
                        <p style="font-weight:600; margin:0 0 8px;">
                            <?php echo $idx + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
                        </p>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <?php foreach ($pergunta['alternativas'] as $altIdx => $alt): ?>
                                <label style="display:flex; gap:8px; align-items:flex-start; cursor:default;">
                                    <input type="radio" name="preview_q<?php echo (int) $pergunta['id']; ?>" disabled style="margin-top:4px;">
                                    <span><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($pergunta['explicacao'])): ?>
                            <p class="muted" style="font-size:0.85em; margin:8px 0 0; font-style:italic;">
                                Comentário: <?php echo Helpers::e((string) $pergunta['explicacao']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top:12px;">
                    <button type="button" class="button-link" disabled>Enviar respostas (somente pré-visualização)</button>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
