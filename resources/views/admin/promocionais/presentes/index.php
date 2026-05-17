<?php
$campanhas = isset($campanhas) && is_array($campanhas) ? $campanhas : array();
$filters = isset($filters) && is_array($filters) ? $filters : array();
$canManage = !empty($can_manage);
$canCancel = !empty($can_cancel);

$filtrosAtivosLista = array();
if (!empty($filters['q'])) {
    $filtrosAtivosLista[] = 'Título';
}
if (!empty((int) ($filters['curso_evento_id'] ?? 0))) {
    $filtrosAtivosLista[] = 'Curso';
}
if (!empty((int) ($filters['turma_id'] ?? 0))) {
    $filtrosAtivosLista[] = 'Turma';
}
if (!empty($filters['status'])) {
    $filtrosAtivosLista[] = 'Status';
}
if (!empty((int) ($filters['criado_por_usuario_id'] ?? 0))) {
    $filtrosAtivosLista[] = 'Criado por';
}
if (!empty($filters['de'])) {
    $filtrosAtivosLista[] = 'Data inicial';
}
if (!empty($filters['ate'])) {
    $filtrosAtivosLista[] = 'Data final';
}
if (!empty($filters['beneficiario'])) {
    $filtrosAtivosLista[] = 'Beneficiário';
}
$filtrosAtivos = !empty($filtrosAtivosLista);

$statusBadgeMap = array(
    'ativo' => 'badge badge--status-aprovado',
    'cancelado' => 'badge badge--status-reprovado',
    'parcialmente_cancelado' => 'badge badge--status-pendente',
    'expirado' => 'badge badge--warn',
);
?>
<div class="admin-page admin-presentes-page">
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__content">
            <div>
                <h1 class="admin-page__title">Presentes</h1>
                <p class="admin-page__subtitle">Campanhas promocionais que concedem acesso gratuito a cursos com rastreabilidade, auditoria e cancelamento controlado.</p>
            </div>
            <?php if ($canManage): ?>
                <div class="admin-presentes-new-card">
                    <span class="admin-presentes-new-card__eyebrow">Nova campanha</span>
                    <h2 class="admin-presentes-new-card__title">Nova campanha de presente</h2>
                    <p class="admin-presentes-new-card__description">Conceda acesso gratuito a um curso para usuários selecionados, sem cobrança e sem geração de financeiro.</p>
                    <div class="admin-presentes-new-card__actions">
                        <a class="button-link button-link--primary" href="/admin/promocionais/presentes/criar">Criar nova campanha</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card admin-presentes-filters" id="admin-presentes-filters">
        <div class="admin-presentes-filters__header">
            <div>
                <div class="admin-presentes-filters__eyebrow">
                    <span>Filtros de busca</span>
                    <?php if ($filtrosAtivos): ?>
                        <span class="badge badge--warning">Filtros ativos</span>
                    <?php endif; ?>
                </div>
                <strong class="admin-presentes-filters__title">Localize campanhas por curso, turma, status, data ou beneficiário.</strong>
                <?php if ($filtrosAtivos): ?>
                    <p class="admin-presentes-filters__summary">Filtros ativos: <?php echo htmlspecialchars(implode(', ', $filtrosAtivosLista), ENT_QUOTES, 'UTF-8'); ?>.</p>
                <?php else: ?>
                    <p class="admin-presentes-filters__summary">Use os filtros para refinar a listagem de campanhas sem perder os dados preenchidos.</p>
                <?php endif; ?>
            </div>
            <button type="button" class="button-link admin-presentes-filters__toggle" id="admin-presentes-filters-toggle" aria-expanded="<?php echo $filtrosAtivos ? 'true' : 'false'; ?>" aria-controls="admin-presentes-filters-body">
                <?php echo $filtrosAtivos ? 'Ocultar filtros' : 'Mostrar filtros'; ?>
            </button>
        </div>

        <div class="admin-presentes-filters__body" id="admin-presentes-filters-body" <?php echo $filtrosAtivos ? '' : 'hidden'; ?>>
            <form method="get" action="/admin/promocionais/presentes" class="admin-filter-grid">
                <label>
                    <span>Título</span>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($filters['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar por nome da campanha">
                </label>
                <label>
                    <span>Curso</span>
                    <select name="curso_evento_id">
                        <option value="0">Todos</option>
                        <?php foreach ((isset($options['cursos']) ? $options['cursos'] : array()) as $curso): ?>
                            <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) ($filters['curso_evento_id'] ?? 0) === (int) $curso['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curso['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Turma</span>
                    <select name="turma_id">
                        <option value="0">Todas</option>
                        <?php foreach ((isset($options['turmas']) ? $options['turmas'] : array()) as $turma): ?>
                            <option value="<?php echo (int) $turma['id']; ?>" <?php echo (int) ($filters['turma_id'] ?? 0) === (int) $turma['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($turma['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="">Todos</option>
                        <?php foreach (array('ativo' => 'Ativo', 'cancelado' => 'Cancelado', 'parcialmente_cancelado' => 'Parcialmente cancelado', 'expirado' => 'Expirado') as $valor => $texto): ?>
                            <option value="<?php echo $valor; ?>" <?php echo (($filters['status'] ?? '') === $valor) ? 'selected' : ''; ?>><?php echo $texto; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Criado por</span>
                    <select name="criado_por_usuario_id">
                        <option value="0">Todos</option>
                        <?php foreach ((isset($options['usuarios']) ? $options['usuarios'] : array()) as $usuario): ?>
                            <option value="<?php echo (int) $usuario['id']; ?>" <?php echo (int) ($filters['criado_por_usuario_id'] ?? 0) === (int) $usuario['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>De</span>
                    <input type="date" name="de" value="<?php echo htmlspecialchars($filters['de'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>
                    <span>Até</span>
                    <input type="date" name="ate" value="<?php echo htmlspecialchars($filters['ate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>
                    <span>Beneficiário</span>
                    <input type="text" name="beneficiario" value="<?php echo htmlspecialchars($filters['beneficiario'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nome, e-mail ou CPF">
                </label>
                <div class="admin-filter-grid__actions">
                    <button type="submit" class="button-link button-link--primary">Filtrar</button>
                    <a class="button-link" href="/admin/promocionais/presentes">Limpar filtros</a>
                </div>
            </form>
        </div>
    </section>

    <section class="status-card">
        <div class="admin-page__content">
            <div>
                <strong>Histórico de campanhas</strong>
                <p class="muted">Use os filtros para localizar campanhas ativas, canceladas ou expiradas.</p>
            </div>
        </div>
        <div class="table-wrap" style="margin-top: 16px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Campanha</th>
                        <th>Curso</th>
                        <th>Turma</th>
                        <th>Beneficiados</th>
                        <th>Ativos</th>
                        <th>Cancelados</th>
                        <th>Prazo</th>
                        <th>Criado por</th>
                        <th>Criado em</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campanhas)): ?>
                        <tr>
                            <td colspan="12">Nenhuma campanha de presente encontrada.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($campanhas as $campanha): ?>
                        <tr>
                            <td><?php echo (int) $campanha['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($campanha['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <small class="muted"><?php echo htmlspecialchars($campanha['justificativa'], ENT_QUOTES, 'UTF-8'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($campanha['curso_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(!empty($campanha['turma_nome']) ? $campanha['turma_nome'] : 'Curso inteiro', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $campanha['total_beneficiarios']; ?></td>
                            <td><?php echo (int) $campanha['total_ativos']; ?></td>
                            <td><?php echo (int) $campanha['total_cancelados']; ?></td>
                            <td><?php echo htmlspecialchars(!empty($campanha['acesso_tipo']) && $campanha['acesso_tipo'] === 'dias' ? ((int) ($campanha['acesso_dias'] ?? 0) . ' dias') : (!empty($campanha['acesso_expira_em']) ? date('d/m/Y H:i', strtotime($campanha['acesso_expira_em'])) : 'Sem prazo'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($campanha['criado_por_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo !empty($campanha['criado_em']) ? date('d/m/Y H:i', strtotime($campanha['criado_em'])) : '-'; ?></td>
                            <td>
                                <?php
                                $status = (string) ($campanha['status'] ?? '');
                                $statusLabel = array(
                                    'ativo' => 'Ativo',
                                    'cancelado' => 'Cancelado',
                                    'parcialmente_cancelado' => 'Parcialmente cancelado',
                                    'expirado' => 'Expirado',
                                );
                                $statusClass = isset($statusBadgeMap[$status]) ? $statusBadgeMap[$status] : 'badge';
                                ?>
                                <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel[$status] ?? ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td>
                                <a href="/admin/promocionais/presentes/show?campanha_id=<?php echo (int) $campanha['id']; ?>">Visualizar</a>
                                <?php if ($canCancel): ?>
                                    <span aria-hidden="true"> | </span>
                                    <a href="/admin/promocionais/presentes/show?campanha_id=<?php echo (int) $campanha['id']; ?>#cancelar">Cancelar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(function () {
    var toggle = document.getElementById('admin-presentes-filters-toggle');
    var body = document.getElementById('admin-presentes-filters-body');

    if (!toggle || !body) {
        return;
    }

    toggle.addEventListener('click', function () {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        var nextExpanded = !expanded;

        toggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
        toggle.textContent = nextExpanded ? 'Ocultar filtros' : 'Mostrar filtros';

        if (nextExpanded) {
            body.removeAttribute('hidden');
        } else {
            body.setAttribute('hidden', 'hidden');
        }
    });
})();
</script>
