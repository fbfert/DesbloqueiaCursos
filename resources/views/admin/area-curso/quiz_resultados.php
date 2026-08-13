<?php use App\Core\Helpers; ?>
<?php
$item       = isset($item) && is_array($item) ? $item : array();
$quiz       = isset($quiz) && is_array($quiz) ? $quiz : null;
$resultados = isset($resultados) && is_array($resultados) ? $resultados : array();
$cursoId    = isset($curso_id) ? (int) $curso_id : 0;
$turmaId    = isset($turma_id) ? (int) $turma_id : 0;
$itemId     = (int) ($item['id'] ?? 0);
$filtros    = isset($filtros) && is_array($filtros) ? $filtros : array();
$tentativas = isset($tentativas) && is_array($tentativas) ? $tentativas : array();
$pendentesDiscursiva = isset($pendentes_discursiva) ? (int) $pendentes_discursiva : 0;
$voltarUrl  = '/admin/area-curso?curso_id=' . $cursoId . '&aba=relatorios';

/** Formata segundos em h/min para a coluna de tempo utilizado. */
$formatarTempo = function ($segundos) {
    $segundos = (int) $segundos;
    if ($segundos <= 0) {
        return '-';
    }
    $horas   = (int) floor($segundos / 3600);
    $minutos = (int) floor(($segundos % 3600) / 60);
    if ($horas > 0) {
        return $horas . 'h' . str_pad((string) $minutos, 2, '0', STR_PAD_LEFT);
    }
    return $minutos . ' min';
};
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

    <?php if ($quiz && !empty($tentativas)): ?>
        <section class="status-card" style="margin-top:16px;">
            <header style="display:flex; gap:12px; flex-wrap:wrap; align-items:baseline; justify-content:space-between;">
                <h2 style="margin:0;">Tentativas em detalhe</h2>
                <?php if ($pendentesDiscursiva > 0): ?>
                    <a class="button-link button-link--ghost"
                       href="/admin/area-curso/conteudo/quiz/discursivas?item_id=<?php echo $itemId; ?>&amp;curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&amp;turma_id=' . $turmaId : ''; ?>">
                        <?php echo $pendentesDiscursiva; ?> discursiva(s) pendente(s)
                    </a>
                <?php endif; ?>
            </header>

            <div style="overflow-x:auto; margin-top:12px;">
                <table class="admin-table" style="width:100%; border-collapse:collapse; min-width:900px;">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Tent.</th>
                            <th>Situação</th>
                            <th>Objetivas</th>
                            <th>Nota geral</th>
                            <th>Desempenho por bloco</th>
                            <th>Tempo</th>
                            <th>Discursiva</th>
                            <th>Sorteio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tentativas as $tentativa): ?>
                            <?php
                            $desempenho = isset($tentativa['desempenho']) && is_array($tentativa['desempenho']) ? $tentativa['desempenho'] : null;
                            $emAndamento = (string) $tentativa['status'] === 'em_andamento';
                            ?>
                            <tr<?php echo !empty($tentativa['e_melhor']) ? ' style="background:rgba(34,197,94,0.06);"' : ''; ?>>
                                <td>
                                    <?php echo Helpers::e((string) ($tentativa['aluno_nome'] ?? '-')); ?>
                                    <?php if (!empty($tentativa['e_melhor'])): ?>
                                        <br><span class="pill pill--success" style="font-size:0.75em;">Melhor tentativa</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><?php echo (int) $tentativa['numero_tentativa']; ?></td>
                                <td>
                                    <?php if ($emAndamento): ?>
                                        <span class="pill pill--info">Em andamento</span>
                                    <?php elseif (!empty($tentativa['aprovado'])): ?>
                                        <span class="pill pill--success">Aprovado</span>
                                    <?php else: ?>
                                        <span class="pill pill--warning">Não aprovado</span>
                                    <?php endif; ?>
                                    <?php if (!empty($tentativa['encerrada_por_tempo'])): ?>
                                        <br><small class="muted">Encerrada por tempo</small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php echo (int) $tentativa['total_acertos']; ?> / <?php echo (int) $tentativa['total_objetivas']; ?>
                                </td>
                                <td style="text-align:center;">
                                    <strong><?php echo number_format((float) $tentativa['percentual'], 1); ?>%</strong>
                                </td>
                                <td>
                                    <?php if ($desempenho === null || empty($desempenho['blocos'])): ?>
                                        <span class="muted">-</span>
                                    <?php else: ?>
                                        <?php foreach ($desempenho['blocos'] as $bloco): ?>
                                            <small style="display:block;">
                                                <?php echo Helpers::e((string) $bloco['codigo']); ?>:
                                                <?php echo (int) $bloco['acertos']; ?>/<?php echo (int) $bloco['total']; ?>
                                                (<?php echo number_format((float) $bloco['percentual'], 0); ?>%)
                                            </small>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php echo Helpers::e($formatarTempo($tentativa['tempo_utilizado_segundos'] ?? 0)); ?>
                                    <?php if (!empty($tentativa['duracao_minutos'])): ?>
                                        <br><small class="muted">de <?php echo (int) $tentativa['duracao_minutos']; ?> min</small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php $statusDiscursiva = (string) ($tentativa['discursiva_status'] ?? 'nao_aplicavel'); ?>
                                    <?php if ($statusDiscursiva === 'pendente'): ?>
                                        <span class="pill pill--warning">Pendente</span>
                                    <?php elseif ($statusDiscursiva === 'corrigida'): ?>
                                        <span class="pill pill--success">Corrigida</span>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php echo (int) $tentativa['total_perguntas']; ?> questão(ões)
                                    <?php if (!empty($tentativa['sorteio_com_repeticao'])): ?>
                                        <br><span class="pill pill--warning" style="font-size:0.75em;" title="O banco não tinha questões inéditas suficientes">Com reaproveitamento</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
