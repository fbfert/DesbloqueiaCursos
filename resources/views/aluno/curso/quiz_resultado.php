<?php use App\Core\Helpers; ?>
<?php
$dados = isset($dados) && is_array($dados) ? $dados : array();
$tentativa = isset($tentativa) && is_array($tentativa) ? $tentativa : array();
$quiz = isset($quiz) && is_array($quiz) ? $quiz : array();
$perguntas = isset($perguntas) && is_array($perguntas) ? $perguntas : array();
?>

<div class="study-page aluno-study-mode">
    <section class="status-card aluno-study-hero study-shell study-shell--hero">
        <div class="aluno-study-hero__title">
            <p class="aluno-course-eyebrow">Resultado do quiz</p>
            <h1><?php echo Helpers::e((string) ($quiz['titulo'] ?? 'Quiz')); ?></h1>
            <p class="aluno-study-hero__subtitle">
                <?php echo (int) ($tentativa['total_acertos'] ?? 0); ?> de <?php echo (int) ($tentativa['total_perguntas'] ?? 0); ?> acertos
                <?php if (isset($tentativa['percentual'])): ?>
                    - <?php echo number_format((float) $tentativa['percentual'], 1, ',', '.'); ?>%
                <?php endif; ?>
            </p>
        </div>
    </section>

    <section class="status-card aluno-study-content study-shell study-shell--content">
        <?php if (!empty($quiz['exibir_resultado_apos_envio'])): ?>
            <div class="conteudo-quiz-resultado__summary">
                <?php if (!empty($tentativa['aprovado'])): ?>
                    <p class="conteudo-quiz-resultado__aprovado">Aprovado.</p>
                <?php else: ?>
                    <p class="conteudo-quiz-resultado__reprovado">Não atingiu o percentual mínimo.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($quiz['exibir_gabarito_apos_envio']) || !empty($quiz['exibir_comentarios_apos_envio'])): ?>
            <?php foreach ($perguntas as $idxP => $pergunta): ?>
                <div class="conteudo-quiz-pergunta-resultado" style="margin:12px 0; padding:10px; border-radius:4px; border:1px solid var(--color-border, #e5e7eb);">
                    <p style="font-weight:600; margin:0 0 6px;"><?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?></p>
                    <?php $resposta = $pergunta['resposta'] ?? null; ?>
                    <?php foreach (($pergunta['alternativas'] ?? array()) as $alt): ?>
                        <?php
                        $isRespondida = $resposta && (int) ($resposta['alternativa_id'] ?? 0) === (int) $alt['id'];
                        $isCorreta = isset($alt['correta']) && !empty($alt['correta']);
                        $altClass = '';
                        if ($isRespondida && $isCorreta) {
                            $altClass = 'color:green; font-weight:600;';
                        } elseif ($isRespondida && !$isCorreta) {
                            $altClass = 'color:red;';
                        } elseif ($isCorreta) {
                            $altClass = 'color:green;';
                        }
                        ?>
                        <div style="<?php echo $altClass; ?> display:flex; gap:6px; align-items:center; margin:2px 0;">
                            <?php if ($isRespondida): ?><span>▶</span><?php endif; ?>
                            <?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($quiz['exibir_comentarios_apos_envio']) && !empty($pergunta['explicacao'])): ?>
                        <p class="muted" style="font-size:0.85em; margin:6px 0 0; font-style:italic;"><?php echo Helpers::e((string) $pergunta['explicacao']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="conteudo-item-actions" style="margin-top:16px;">
            <a class="button-link button-link--ghost" href="/aluno/meus-cursos">Voltar aos meus cursos</a>
        </div>
    </section>
</div>
