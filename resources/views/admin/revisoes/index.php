<?php
use App\Core\Helpers;
use App\Services\RevisaoComentarioService;

/**
 * Fila de triagem dos apontamentos de revisão (openspec/changes/fila-revisao-admin).
 *
 * Espera no escopo: $itens, $truncado, $filtros, $cursos.
 * Todo texto vindo do revisor ou do conteúdo é escapado: nada aqui é HTML.
 */
$itens = isset($itens) ? $itens : array();
$cursos = isset($cursos) ? $cursos : array();
$filtros = isset($filtros) ? $filtros : RevisaoComentarioService::normalizarFiltrosFila(array());

$dataHora = function ($valor) {
    return $valor ? date('d/m/Y H:i', strtotime($valor)) : '—';
};
?>
<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Revisões</h1>
        <p class="admin-page__subtitle">Apontamentos dos revisores sobre o conteúdo dos cursos. Os abertos e mais graves aparecem primeiro.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="get" action="/admin/revisoes" class="admin-filters">
        <div class="admin-filters__row">
            <label>Curso
                <select name="curso_id">
                    <option value="0">Todos</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) $filtros['curso_evento_id'] === (int) $curso['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($curso['nome']); ?> (<?php echo (int) $curso['total']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Severidade
                <select name="severidade">
                    <option value="">Todas</option>
                    <?php foreach (RevisaoComentarioService::SEVERIDADES as $severidade): ?>
                        <option value="<?php echo Helpers::e($severidade); ?>" <?php echo $filtros['severidade'] === $severidade ? 'selected' : ''; ?>>
                            <?php echo Helpers::e(RevisaoComentarioService::rotuloSeveridade($severidade)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Situação
                <select name="status">
                    <option value="">Todas</option>
                    <?php foreach (RevisaoComentarioService::STATUS as $status): ?>
                        <option value="<?php echo Helpers::e($status); ?>" <?php echo $filtros['status'] === $status ? 'selected' : ''; ?>>
                            <?php echo Helpers::e(RevisaoComentarioService::rotuloStatus($status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="cta-group admin-mt-8">
            <button type="submit" class="button-link button-link--primary">Filtrar</button>
            <a class="button-link button-link--ghost" href="/admin/revisoes">Limpar filtros</a>
        </div>
    </form>
</section>

<?php if ($truncado): ?>
    <div class="alert alert-warning">
        Mostrando os <?php echo (int) RevisaoComentarioService::LIMITE_FILA; ?> primeiros apontamentos. Use os filtros para ver os demais.
    </div>
<?php endif; ?>

<section class="status-card">
    <?php if (empty($itens)): ?>
        <p class="muted">Nenhum apontamento encontrado com estes filtros.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Severidade</th>
                        <th>Curso e alvo</th>
                        <th>Apontamento</th>
                        <th>Situação</th>
                        <th>Triagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $c): ?>
                        <tr>
                            <td>
                                <span class="badge<?php echo $c['severidade'] === 'erro' ? ' badge--danger' : ''; ?>">
                                    <?php echo Helpers::e(RevisaoComentarioService::rotuloSeveridade($c['severidade'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo Helpers::e($c['curso_nome'] ?? 'Curso removido'); ?></strong><br>
                                <span class="muted"><?php echo Helpers::e(RevisaoComentarioService::rotuloAlvoTipo($c['alvo_tipo'])); ?>:</span>
                                <?php if ($c['alvo_removido']): ?>
                                    <em class="muted"><?php echo Helpers::e($c['alvo_descricao']); ?></em>
                                <?php else: ?>
                                    <?php echo Helpers::e($c['alvo_descricao']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['trecho'])): ?>
                                    <blockquote class="muted"><?php echo Helpers::e($c['trecho']); ?></blockquote>
                                <?php endif; ?>
                                <?php echo nl2br(Helpers::e($c['comentario'])); ?>
                                <p class="muted">
                                    <?php echo Helpers::e($c['autor_nome'] ?? '—'); ?> · <?php echo Helpers::e($dataHora($c['created_at'])); ?>
                                </p>
                            </td>
                            <td><?php echo Helpers::e(RevisaoComentarioService::rotuloStatus($c['status'])); ?></td>
                            <td>
                                <?php if ($c['status'] === 'aberto'): ?>
                                    <form method="post" action="/admin/revisoes/triar" class="admin-form">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="comentario_id" value="<?php echo (int) $c['id']; ?>">
                                        <input type="hidden" name="filtro_curso_id" value="<?php echo (int) $filtros['curso_evento_id']; ?>">
                                        <input type="hidden" name="filtro_severidade" value="<?php echo Helpers::e($filtros['severidade']); ?>">
                                        <input type="hidden" name="filtro_status" value="<?php echo Helpers::e($filtros['status']); ?>">
                                        <label>Resposta ao revisor
                                            <textarea name="resposta" rows="3" placeholder="Obrigatória para recusar"></textarea>
                                        </label>
                                        <div class="cta-group admin-mt-8">
                                            <button type="submit" name="status" value="aceito" class="button-link button-link--primary">Aceitar</button>
                                            <button type="submit" name="status" value="resolvido" class="button-link button-link--ghost">Marcar como resolvido</button>
                                            <button type="submit" name="status" value="recusado" class="button-link button-link--danger">Recusar</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <p class="muted">
                                        <?php echo Helpers::e($c['triado_por_nome'] ?? '—'); ?> · <?php echo Helpers::e($dataHora($c['triado_em'])); ?>
                                    </p>
                                    <?php if (!empty($c['resposta'])): ?>
                                        <p><strong>Resposta:</strong> <?php echo nl2br(Helpers::e($c['resposta'])); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>
