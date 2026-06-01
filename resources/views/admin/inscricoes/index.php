<?php use App\Core\Helpers; ?>
<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array('total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1);
$currentSortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : 'id';
$currentSortDir = isset($filters['sort_dir']) ? strtolower((string) $filters['sort_dir']) : 'desc';
$queryBase = array(
    'q' => isset($filters['q']) ? $filters['q'] : '',
    'status' => isset($filters['status']) ? $filters['status'] : '',
    'pedido_status' => isset($filters['pedido_status']) ? $filters['pedido_status'] : '',
    'curso_evento_id' => isset($filters['curso_evento_id']) ? $filters['curso_evento_id'] : '',
    'turma_id' => isset($filters['turma_id']) ? $filters['turma_id'] : '',
    'per_page' => isset($filters['per_page']) ? $filters['per_page'] : 20,
);

$formatarTelefonePagador = function ($telefone) {
    $telefone = preg_replace('/\D+/', '', (string) $telefone);

    if ($telefone === '') {
        return '';
    }

    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    }

    if (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }

    return $telefone;
};

if (!function_exists('inscricoesSortUrl')) {
    function inscricoesSortUrl($field, $currentSortBy, $currentSortDir, array $queryBase)
    {
        $nextDir = ($currentSortBy === $field && $currentSortDir === 'asc') ? 'desc' : 'asc';
        $params = array_merge($queryBase, array('sort_by' => $field, 'sort_dir' => $nextDir));
        return '/admin/inscricoes?' . http_build_query($params);
    }
}

if (!function_exists('inscricoesQuery')) {
    function inscricoesQuery(array $queryBase, $sortBy, $sortDir, $page = null)
    {
        $params = array_merge($queryBase, array(
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ));

        if ($page !== null) {
            $params['page'] = (int) $page;
        }

        return $params;
    }
}

$statusOptions = array(
    '' => 'Todos',
    'pendente' => 'Pendente',
    'com_pendencia' => 'Com pendência',
    'ativa' => 'Ativa',
    'em_andamento' => 'Em andamento',
    'cancelada' => 'Cancelada',
    'reprovada' => 'Reprovada',
    'concluida' => 'Concluída',
    'concluida_sem_certificado' => 'Concluída sem certificado',
    'certificado_emitido' => 'Certificado emitido',
);

$pedidoStatusOptions = array(
    '' => 'Todos',
    'pendente' => 'Pendente',
    'aguardando_comprovante' => 'Aguardando comprovante',
    'aguardando_reenvio' => 'Aguardando reenvio',
    'aprovado' => 'Aprovado',
    'pago' => 'Pago',
    'cancelado' => 'Cancelado',
    'reprovado' => 'Reprovado',
);

$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$turmas = isset($turmas) && is_array($turmas) ? $turmas : array();
$page = isset($pagination['page']) ? (int) $pagination['page'] : 1;
$pages = isset($pagination['pages']) ? (int) $pagination['pages'] : 1;
$total = isset($pagination['total']) ? (int) $pagination['total'] : 0;
$perPage = isset($pagination['per_page']) ? (int) $pagination['per_page'] : 20;
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Inscrições</h1>
        <p class="admin-page__subtitle">Listagem administrativa de inscrições, com busca, filtros, ordenação e paginação.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card" style="margin-bottom:12px;">
    <form method="get" action="/admin/inscricoes" class="admin-filters">
        <div class="admin-filters__row">
            <label>Busca
                <input type="text" name="q" value="<?php echo Helpers::e((string) ($filters['q'] ?? '')); ?>" placeholder="Pedido, pagador, participante, CPF, curso ou turma">
            </label>
            <label>Status da inscrição
                <select name="status">
                    <?php $statusAtual = (string) ($filters['status'] ?? ''); ?>
                    <?php foreach ($statusOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo $statusAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Status do pedido
                <select name="pedido_status">
                    <?php $pedidoStatusAtual = (string) ($filters['pedido_status'] ?? ''); ?>
                    <?php foreach ($pedidoStatusOptions as $valor => $rotulo): ?>
                        <option value="<?php echo Helpers::e($valor); ?>" <?php echo $pedidoStatusAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="admin-filters__row" style="margin-top:12px;">
            <label>Curso
                <select name="curso_evento_id">
                    <?php $cursoAtual = (int) ($filters['curso_evento_id'] ?? 0); ?>
                    <option value="0">Todos</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?php echo (int) $curso['id']; ?>" <?php echo $cursoAtual === (int) $curso['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($curso['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Turma
                <select name="turma_id">
                    <?php $turmaAtual = (int) ($filters['turma_id'] ?? 0); ?>
                    <option value="0">Todas</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?php echo (int) $turma['id']; ?>" <?php echo $turmaAtual === (int) $turma['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e(($turma['curso_nome'] ?? '') . ' - ' . $turma['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Por página
                <select name="per_page">
                    <?php $perPageAtual = (int) ($filters['per_page'] ?? 20); ?>
                    <?php foreach (array(20, 50, 100) as $valor): ?>
                        <option value="<?php echo (int) $valor; ?>" <?php echo $perPageAtual === (int) $valor ? 'selected' : ''; ?>><?php echo (int) $valor; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="hidden" name="sort_by" value="<?php echo Helpers::e($currentSortBy); ?>">
            <input type="hidden" name="sort_dir" value="<?php echo Helpers::e($currentSortDir); ?>">
        </div>
        <div class="cta-group" style="margin-top:12px;">
            <button type="submit" class="button-link button-link--primary">Filtrar</button>
            <a class="button-link button-link--ghost" href="/admin/inscricoes">Limpar filtros</a>
        </div>
    </form>
</section>

<section class="status-card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
        <strong>Inscrições</strong>
        <small><?php echo $total > 0 ? ('Mostrando ' . $from . ' a ' . $to . ' de ' . $total . ' registros.') : 'Nenhum registro encontrado.'; ?></small>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('pedido_codigo', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Pedido</a></th>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('pagador_nome', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Pagador</a></th>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('participante_nome', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Participante</a></th>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('curso_nome', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Curso</a></th>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('turma_nome', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Turma</a></th>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('status', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Status</a></th>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('pedido_status', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Status do pedido</a></th>
                    <th><a href="<?php echo Helpers::e(inscricoesSortUrl('created_at', $currentSortBy, $currentSortDir, inscricoesQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Data</a></th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inscricoes)): ?>
                    <tr>
                        <td colspan="9">Nenhuma inscrição encontrada com os filtros aplicados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inscricoes as $inscricao): ?>
                        <tr>
                            <td><?php echo Helpers::e((string) $inscricao['pedido_codigo']); ?></td>
                        <td>
                            <?php echo Helpers::e((string) $inscricao['pagador_nome']); ?><br>
                            <small><?php echo Helpers::e((string) $inscricao['pagador_email']); ?></small>
                            <?php if (!empty($inscricao['pagador_telefone'])): ?>
                                <br><small><?php echo Helpers::e($formatarTelefonePagador((string) $inscricao['pagador_telefone'])); ?></small>
                            <?php endif; ?>
                        </td>
                            <td>
                                <?php echo Helpers::e((string) $inscricao['participante_nome']); ?><br>
                                <small><?php echo Helpers::e((string) $inscricao['participante_cpf']); ?></small>
                            </td>
                            <td><?php echo Helpers::e((string) $inscricao['curso_nome']); ?></td>
                            <td><?php echo Helpers::e((string) $inscricao['turma_nome']); ?></td>
                            <td><?php echo Helpers::e((string) $inscricao['status']); ?></td>
                            <td><?php echo Helpers::e((string) $inscricao['pedido_status']); ?></td>
                            <td><?php echo Helpers::e((string) $inscricao['created_at']); ?></td>
                            <td>
                                <a class="button-link button-link--ghost" href="/admin/pedidos/show?pedido_id=<?php echo (int) $inscricao['pedido_id']; ?>">Ver Pedido</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($pages > 1): ?>
    <section class="status-card" style="margin-top:12px;">
        <div class="cta-group" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <small>Página <?php echo (int) $page; ?> de <?php echo (int) $pages; ?>.</small>
            <div class="cta-group">
                <?php if ($page > 1): ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/inscricoes?' . http_build_query(inscricoesQuery($queryBase, $currentSortBy, $currentSortDir, $page - 1))); ?>">Anterior</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/inscricoes?' . http_build_query(inscricoesQuery($queryBase, $currentSortBy, $currentSortDir, $page + 1))); ?>">Próxima</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
</div>
