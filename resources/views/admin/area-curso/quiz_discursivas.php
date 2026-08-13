<?php use App\Core\Helpers; ?>
<?php
$item      = isset($item) && is_array($item) ? $item : array();
$quiz      = isset($quiz) && is_array($quiz) ? $quiz : null;
$fila      = isset($fila) && is_array($fila) ? $fila : array();
$pendentes = isset($pendentes) ? (int) $pendentes : 0;
$statusF   = isset($status) ? (string) $status : 'pendente';
$cursoId   = isset($curso_id) ? (int) $curso_id : 0;
$turmaId   = isset($turma_id) ? (int) $turma_id : 0;
$itemId    = (int) ($item['id'] ?? 0);

$sufixoUrl = '&curso_id=' . $cursoId . ($turmaId > 0 ? '&turma_id=' . $turmaId : '');
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Correção das discursivas</h1>
            <p class="admin-page__subtitle"><?php echo Helpers::e((string) ($item['titulo'] ?? '')); ?></p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost" href="/admin/area-curso?curso_id=<?php echo $cursoId; ?>&amp;aba=relatorios">Voltar</a>
            <a class="button-link button-link--ghost" href="/admin/area-curso/conteudo/quiz/resultados?item_id=<?php echo $itemId . $sufixoUrl; ?>">Resultados</a>
            <a class="button-link button-link--ghost" href="/admin/area-curso/conteudo/quiz/blocos?item_id=<?php echo $itemId . $sufixoUrl; ?>">Blocos</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card" style="margin-bottom:16px;">
        <p style="margin:0 0 12px;">
            <strong><?php echo $pendentes; ?></strong> discursiva(s) aguardando correção.
            A nota da discursiva é informativa: não altera o percentual objetivo, a aprovação nem a aptidão ao certificado.
        </p>

        <form method="get" action="/admin/area-curso/conteudo/quiz/discursivas" class="form-grid">
            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <?php if ($turmaId > 0): ?>
                <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
            <?php endif; ?>
            <label>
                Situação
                <select name="status">
                    <?php foreach (array('pendente' => 'Pendentes', 'corrigida' => 'Corrigidas', 'todas' => 'Todas') as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo $statusF === $valor ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="align-self:end;">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
            </div>
        </form>
    </section>

    <?php if (!$quiz): ?>
        <section class="status-card"><p>Quiz não configurado.</p></section>
    <?php elseif (empty($fila)): ?>
        <section class="status-card"><p class="muted">Nenhuma questão discursiva nesta situação.</p></section>
    <?php else: ?>
        <?php foreach ($fila as $registro): ?>
            <?php
            $corrigida  = (string) $registro['status'] === 'corrigida';
            $notaMaxima = (float) $registro['nota_maxima'];
            ?>
            <section class="status-card" style="margin-bottom:16px;">
                <header style="display:flex; gap:12px; flex-wrap:wrap; align-items:baseline; justify-content:space-between;">
                    <div>
                        <strong><?php echo Helpers::e((string) ($registro['aluno_nome'] ?? '-')); ?></strong>
                        <span class="muted"><?php echo Helpers::e((string) ($registro['aluno_email'] ?? '')); ?></span>
                        <br>
                        <small class="muted">
                            Tentativa <?php echo (int) $registro['numero_tentativa']; ?>
                            <?php if (!empty($registro['enviada_em'])): ?>
                                · enviada em <?php echo Helpers::e(date('d/m/Y H:i', strtotime((string) $registro['enviada_em']))); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <span class="pill <?php echo $corrigida ? 'pill--success' : 'pill--warning'; ?>">
                        <?php echo $corrigida ? 'Corrigida' : 'Pendente'; ?>
                    </span>
                </header>

                <div style="margin:12px 0;">
                    <p style="font-weight:600; margin:0 0 4px;">Enunciado</p>
                    <p style="margin:0;"><?php echo nl2br(Helpers::e((string) ($registro['pergunta_enunciado'] ?? ''))); ?></p>
                </div>

                <div style="margin:12px 0;">
                    <p style="font-weight:600; margin:0 0 4px;">Resposta do aluno</p>
                    <div style="white-space:pre-wrap; padding:12px; border:1px solid var(--color-border, #e5e7eb); border-radius:6px; background:var(--color-surface-muted, #f9fafb);"><?php
                        echo Helpers::e((string) ($registro['resposta_texto'] ?? '(sem resposta registrada)'));
                    ?></div>
                </div>

                <form method="post" action="/admin/area-curso/conteudo/quiz/discursiva/corrigir" class="form-grid">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                    <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                    <input type="hidden" name="correcao_id" value="<?php echo (int) $registro['id']; ?>">

                    <label>
                        Nota (0 a <?php echo Helpers::e(rtrim(rtrim(number_format($notaMaxima, 2, ',', '.'), '0'), ',')); ?>) *
                        <input type="number" name="nota" min="0" max="<?php echo $notaMaxima; ?>" step="0.01" required
                               value="<?php echo $registro['nota'] !== null ? Helpers::e((string) (float) $registro['nota']) : ''; ?>">
                    </label>

                    <label style="grid-column: 1 / -1;">
                        Rubrica / critério aplicado
                        <textarea name="rubrica" rows="2"><?php echo Helpers::e((string) ($registro['rubrica'] ?? '')); ?></textarea>
                    </label>

                    <label style="grid-column: 1 / -1;">
                        Feedback para o aluno
                        <textarea name="feedback" rows="4"><?php echo Helpers::e((string) ($registro['feedback'] ?? '')); ?></textarea>
                    </label>

                    <div class="cta-group" style="grid-column: 1 / -1;">
                        <button type="submit" class="button-link button-link--primary">
                            <?php echo $corrigida ? 'Atualizar correção' : 'Registrar correção'; ?>
                        </button>
                        <?php if ($corrigida && !empty($registro['corrigida_em'])): ?>
                            <span class="muted" style="align-self:center;">
                                Corrigida em <?php echo Helpers::e(date('d/m/Y H:i', strtotime((string) $registro['corrigida_em']))); ?>
                                <?php if (!empty($registro['corretor_nome'])): ?>
                                    por <?php echo Helpers::e((string) $registro['corretor_nome']); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
