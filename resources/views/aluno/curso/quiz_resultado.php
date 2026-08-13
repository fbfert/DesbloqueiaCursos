<?php use App\Core\Helpers; ?>
<?php
$dados = isset($dados) && is_array($dados) ? $dados : array();
$tentativa = isset($tentativa) && is_array($tentativa) ? $tentativa : array();
$quiz = isset($quiz) && is_array($quiz) ? $quiz : array();
$perguntas = isset($perguntas) && is_array($perguntas) ? $perguntas : array();
$desempenho = isset($dados['desempenho']) && is_array($dados['desempenho']) ? $dados['desempenho'] : null;
$discursivas = isset($dados['discursivas']) && is_array($dados['discursivas']) ? $dados['discursivas'] : array();

// O denominador exibido é o das objetivas sorteadas (a discursiva fica fora).
$totalObjetivas = (int) ($tentativa['total_objetivas'] ?? 0);
if ($totalObjetivas <= 0) {
    $totalObjetivas = (int) ($tentativa['total_perguntas'] ?? 0);
}
?>

<div class="study-page aluno-study-mode">
    <section class="status-card aluno-study-hero study-shell study-shell--hero">
        <div class="aluno-study-hero__title">
            <p class="aluno-course-eyebrow">Resultado do quiz</p>
            <h1><?php echo Helpers::e((string) ($quiz['titulo'] ?? 'Quiz')); ?></h1>
            <p class="aluno-study-hero__subtitle">
                <?php echo (int) ($tentativa['total_acertos'] ?? 0); ?> de <?php echo $totalObjetivas; ?> acertos nas questões objetivas
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

        <?php if (!empty($tentativa['encerrada_por_tempo'])): ?>
            <p class="conteudo-item-note" role="status">
                O tempo da prova terminou e as respostas salvas foram enviadas automaticamente.
            </p>
        <?php endif; ?>

        <?php if (!empty($quiz['exibir_resultado_apos_envio']) && $desempenho && !empty($desempenho['blocos'])): ?>
            <div class="conteudo-quiz-desempenho" style="margin:16px 0;">
                <h2 style="font-size:1.05em; margin:0 0 8px;">Desempenho por bloco</h2>
                <ul style="list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:8px;">
                    <?php foreach ($desempenho['blocos'] as $bloco): ?>
                        <li style="padding:8px 12px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px;">
                            <strong><?php echo Helpers::e((string) $bloco['titulo']); ?></strong><br>
                            <?php echo (int) $bloco['acertos']; ?>/<?php echo (int) $bloco['total']; ?>
                            (<?php echo number_format((float) $bloco['percentual'], 0); ?>%)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if (!empty($desempenho['temas'])): ?>
                <details style="margin:12px 0;">
                    <summary style="cursor:pointer;">Desempenho por tema</summary>
                    <ul style="margin:8px 0 0; padding-left:18px;">
                        <?php foreach ($desempenho['temas'] as $tema): ?>
                            <li>
                                <?php echo Helpers::e((string) $tema['tema']); ?>:
                                <?php echo (int) $tema['acertos']; ?>/<?php echo (int) $tema['total']; ?>
                                (<?php echo number_format((float) $tema['percentual'], 0); ?>%)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($discursivas)): ?>
            <div style="margin:16px 0;">
                <h2 style="font-size:1.05em; margin:0 0 8px;">Questão discursiva</h2>
                <?php foreach ($discursivas as $discursiva): ?>
                    <div style="padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px; margin-bottom:8px;">
                        <p style="margin:0 0 6px; font-weight:600;"><?php echo nl2br(Helpers::e((string) $discursiva['enunciado'])); ?></p>
                        <p class="muted" style="font-size:0.85em; margin:0 0 8px;">
                            A nota da discursiva é informativa e não altera a aprovação nem o certificado.
                        </p>
                        <?php if ((string) $discursiva['status'] === 'corrigida'): ?>
                            <p style="margin:0 0 6px;">
                                <strong>Nota:</strong>
                                <?php echo Helpers::e(number_format((float) $discursiva['nota'], 2, ',', '.')); ?>
                                de <?php echo Helpers::e(number_format((float) $discursiva['nota_maxima'], 2, ',', '.')); ?>
                            </p>
                            <?php if (!empty($discursiva['feedback'])): ?>
                                <p style="margin:0;"><strong>Comentário do corretor:</strong><br>
                                    <?php echo nl2br(Helpers::e((string) $discursiva['feedback'])); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="pill pill--info" style="display:inline-block; margin:0;">Aguardando correção</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($quiz['exibir_gabarito_apos_envio']) || !empty($quiz['exibir_comentarios_apos_envio'])): ?>
            <?php foreach ($perguntas as $idxP => $pergunta): ?>
                <?php if ((string) ($pergunta['tipo'] ?? '') === 'discursiva') { continue; } ?>
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
