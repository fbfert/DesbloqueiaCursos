<?php use App\Core\Helpers; ?>
<?php
$item       = isset($item) && is_array($item) ? $item : array();
$quiz       = isset($quiz) && is_array($quiz) ? $quiz : null;
$resultados = isset($resultados) && is_array($resultados) ? $resultados : array();
$cursoId    = isset($curso_id) ? (int) $curso_id : 0;
$turmaId    = isset($turma_id) ? (int) $turma_id : 0;
$itemId     = (int) ($item['id'] ?? 0);
$filtros    = isset($filtros) && is_array($filtros) ? $filtros : array();
$voltarUrl  = '/admin/area-curso?curso_id=' . $cursoId . '&aba=relatorios';
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Resultados do Quiz</h1>
            <p class="admin-page__subtitle"><?php echo Helpers::e((string) ($item['titulo'] ?? '')); ?></p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($voltarUrl); ?>">Voltar</a>
            <?php if ($quiz): ?>
                <a class="button-link button-link--ghost" href="/admin/area-curso/conteudo/quiz/perguntas?item_id=<?php echo $itemId; ?>&curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . $turmaId : ''; ?>">
                    Editar perguntas
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card" style="margin-bottom:16px;">
        <form method="get" action="/admin/area-curso/conteudo/quiz/resultados" class="form-grid">
            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <?php if ($turmaId > 0): ?>
                <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
            <?php endif; ?>
            <label>
                Aluno ID
                <input type="number" name="aluno_id" value="<?php echo (int) ($filtros['aluno_id'] ?? 0); ?>">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach (array('em_andamento' => 'Em andamento', 'enviada' => 'Enviada', 'corrigida' => 'Corrigida', 'cancelada' => 'Cancelada') as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo (string) ($filtros['status'] ?? '') === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Aprovação
                <select name="aprovado">
                    <option value="">Todos</option>
                    <option value="1" <?php echo (string) ($filtros['aprovado'] ?? '') === '1' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="0" <?php echo (string) ($filtros['aprovado'] ?? '') === '0' ? 'selected' : ''; ?>>Não aprovado</option>
                </select>
            </label>
            <div style="align-self:end;">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
            </div>
        </form>
    </section>

    <?php if (!$quiz): ?>
        <section class="status-card"><p>Quiz não configurado.</p></section>
    <?php elseif (empty($resultados)): ?>
        <section class="status-card"><p class="muted">Nenhuma tentativa registrada ainda.</p></section>
    <?php else: ?>
        <section class="status-card">
            <table class="admin-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>E-mail</th>
                        <th>Tentativas</th>
                        <th>Melhor %</th>
                        <th>Último %</th>
                        <th>Situação</th>
                        <th>Última em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $linha): ?>
                        <tr>
                            <td><?php echo Helpers::e((string) ($linha['aluno_nome'] ?? '-')); ?></td>
                            <td><?php echo Helpers::e((string) ($linha['aluno_email'] ?? '-')); ?></td>
                            <td style="text-align:center;"><?php echo (int) ($linha['total_tentativas'] ?? 0); ?></td>
                            <td style="text-align:center;"><?php echo number_format((float) ($linha['melhor_percentual'] ?? 0), 1); ?>%</td>
                            <td style="text-align:center;"><?php echo number_format((float) ($linha['ultimo_percentual'] ?? 0), 1); ?>%</td>
                            <td>
                                <?php if (!empty($linha['aprovado'])): ?>
                                    <span class="pill pill--success">Aprovado</span>
                                <?php else: ?>
                                    <span class="pill pill--warning">Não aprovado</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($linha['ultima_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $linha['ultima_em']))) : '-'; ?></td>
                            <td>
                                <?php if (!empty($linha['inscricao_id'])): ?>
                                    <form method="post" action="/admin/area-curso/conteudo/quiz/reset-aluno"
                                          onsubmit="return confirm('Redefinir o progresso deste aluno no quiz? O histórico de tentativas será preservado.');"
                                          style="display:inline;">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                        <input type="hidden" name="aluno_id" value="<?php echo (int) ($linha['aluno_id'] ?? 0); ?>">
                                        <input type="hidden" name="inscricao_id" value="<?php echo (int) ($linha['inscricao_id'] ?? 0); ?>">
                                        <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                        <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                                        <button type="submit" class="button-link button-link--ghost" style="font-size:0.8em;">Redefinir</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</div>
