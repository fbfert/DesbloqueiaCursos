<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$usuarios = isset($usuarios) && is_array($usuarios) ? $usuarios : array();
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$turmas = isset($turmas) && is_array($turmas) ? $turmas : array();
$perfis = isset($perfis) && is_array($perfis) ? $perfis : array();
$emailModelo = isset($email_modelo) && is_array($email_modelo) ? $email_modelo : array();
$modelosEmail = isset($modelos_email) && is_array($modelos_email) ? $modelos_email : array();
$old = isset($old) && is_array($old) ? $old : array();
$selecionados = isset($selecionados) && is_array($selecionados) ? $selecionados : array();
$selecionadosIds = isset($selecionados_ids) && is_array($selecionados_ids) ? array_map('intval', $selecionados_ids) : array();
$selecionadosLookup = array_fill_keys($selecionadosIds, true);
$page = max(1, (int) ($page ?? 1));
$perPage = max(5, (int) ($per_page ?? 20));
$total = (int) ($total ?? 0);
$totalPages = max(1, (int) ceil($total / max(1, $perPage)));

$currentFilters = array(
    'nome' => $filters['nome'] ?? '',
    'cidade' => $filters['cidade'] ?? '',
    'data_inicio' => $filters['data_inicio'] ?? '',
    'data_fim' => $filters['data_fim'] ?? '',
    'perfil' => $filters['perfil'] ?? '',
    'curso_evento_id' => (int) ($filters['curso_evento_id'] ?? 0),
    'compras_tipo' => $filters['compras_tipo'] ?? '',
);

$currentQuery = http_build_query(array_merge($currentFilters, array(
    'page' => $page,
    'per_page' => $perPage,
)));
$selectionQuery = $currentQuery !== '' ? '?' . $currentQuery : '';

$buildPageUrl = function ($targetPage) use ($currentFilters, $perPage) {
    $query = $currentFilters;
    $query['page'] = max(1, (int) $targetPage);
    $query['per_page'] = $perPage;
    return '/admin/promocionais/presentes/criar?' . http_build_query($query);
};
?>
<div class="admin-page">
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__content">
            <div>
                <h1 class="admin-page__title">Novo presente</h1>
                <p class="admin-page__subtitle">Filtre usuários ativos, selecione beneficiários e depois configure a campanha.</p>
            </div>
            <div class="admin-page__actions">
                <a class="button-link" href="/admin/promocionais/presentes">Voltar</a>
            </div>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <form method="get" action="/admin/promocionais/presentes/criar" class="admin-filter-grid">
            <label>
                <span>Buscar por nome</span>
                <input type="text" name="nome" placeholder="Digite o nome do usuário" value="<?php echo htmlspecialchars($currentFilters['nome'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                <span>Cidade</span>
                <input type="text" name="cidade" placeholder="Digite a cidade" value="<?php echo htmlspecialchars($currentFilters['cidade'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                <span>Cadastro de</span>
                <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($currentFilters['data_inicio'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                <span>Cadastro até</span>
                <input type="date" name="data_fim" value="<?php echo htmlspecialchars($currentFilters['data_fim'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                <span>Perfil</span>
                <select name="perfil">
                    <option value="">Todos</option>
                    <?php foreach ($perfis as $perfil): ?>
                        <option value="<?php echo htmlspecialchars($perfil['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($currentFilters['perfil'] === ($perfil['slug'] ?? '')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($perfil['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Curso comprado</span>
                <select name="curso_evento_id">
                    <option value="0">Qualquer ou nenhum</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) $currentFilters['curso_evento_id'] === (int) $curso['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($curso['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Compras</span>
                <select name="compras_tipo">
                    <option value="">Todos</option>
                    <option value="qualquer" <?php echo $currentFilters['compras_tipo'] === 'qualquer' ? 'selected' : ''; ?>>Já comprou algum curso</option>
                    <option value="nenhuma" <?php echo $currentFilters['compras_tipo'] === 'nenhuma' ? 'selected' : ''; ?>>Nunca comprou curso</option>
                </select>
            </label>
            <div class="admin-filter-grid__actions">
                <button type="submit" class="button-link button-link--primary">Filtrar usuários</button>
                <a class="button-link" href="/admin/promocionais/presentes/criar">Limpar</a>
            </div>
        </form>
    </section>

    <section class="status-card">
        <div class="admin-page__content">
            <div>
                <strong>Usuários filtrados</strong>
                <p class="muted"><?php echo (int) $total; ?> usuário(s) encontrado(s). Selecione os que deseja conceder como presente.</p>
            </div>
        </div>

        <form method="post" action="/admin/promocionais/presentes/selecionar<?php echo htmlspecialchars($selectionQuery, ENT_QUOTES, 'UTF-8'); ?>" id="presentes-selection-form">
            <?php echo $csrfField; ?>
            <div class="table-wrap" style="margin-top: 16px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selecionar_visiveis"></th>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Cidade</th>
                            <th>Perfil</th>
                            <th>Cadastro</th>
                            <th>Status</th>
                            <th>Compras</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="9">Nenhum usuário encontrado com os filtros atuais.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <input type="checkbox"
                                           name="usuarios_selecionados[]"
                                           value="<?php echo (int) $usuario['id']; ?>"
                                           <?php echo isset($selecionadosLookup[(int) $usuario['id']]) ? 'checked' : ''; ?>>
                                </td>
                                <td><?php echo (int) $usuario['id']; ?></td>
                                <td><?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['cidade'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['perfis'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo !empty($usuario['created_at']) ? date('d/m/Y H:i', strtotime($usuario['created_at'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($usuario['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if (!empty($usuario['cursos_comprados'])): ?>
                                        <?php echo htmlspecialchars($usuario['cursos_comprados'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php else: ?>
                                        <span class="muted">Nunca comprou curso</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-pagination" style="margin-top: 16px;">
                <a class="button-link" href="<?php echo htmlspecialchars($buildPageUrl(max(1, $page - 1)), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $page <= 1 ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>Anterior</a>
                <span class="muted">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                <a class="button-link" href="<?php echo htmlspecialchars($buildPageUrl(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $page >= $totalPages ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>Próxima</a>
            </div>
        </form>
    </section>

    <section class="status-card">
        <div class="admin-page__content">
            <div>
                <strong>Usuários selecionados</strong>
                <p class="muted"><?php echo (int) count($selecionados); ?> usuário(s) já salvos na seleção. A lista permanece entre buscas e filtros.</p>
            </div>
            <div class="admin-page__actions">
                <form method="post" action="/admin/promocionais/presentes/selecionar-limpar<?php echo htmlspecialchars($selectionQuery, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $csrfField; ?>
                    <button type="submit" class="button-link">Limpar seleção</button>
                </form>
            </div>
        </div>

        <?php if (!empty($selecionados)): ?>
            <div class="table-wrap" style="margin-top: 16px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Cidade</th>
                            <th>Perfil</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($selecionados as $usuario): ?>
                            <tr>
                                <td><?php echo (int) $usuario['id']; ?></td>
                                <td><?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['cidade'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['perfis'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <form method="post" action="/admin/promocionais/presentes/selecionado-remover<?php echo htmlspecialchars($selectionQuery, ENT_QUOTES, 'UTF-8'); ?>" style="margin: 0;">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="usuario_id" value="<?php echo (int) $usuario['id']; ?>">
                                        <button type="submit" class="button-link button-link--small button-link--ghost">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-warning-box" style="margin-top: 16px;">
                Nenhum usuário foi selecionado ainda. Use a lista acima para montar a campanha.
            </div>
        <?php endif; ?>
        <div class="admin-page__actions" style="margin-top: 16px; flex-wrap: wrap;">
            <button type="submit" class="button-link button-link--primary" form="presentes-selection-form">Selecionar estes usuários</button>
            <?php if (!empty($selecionadosIds)): ?>
                <a class="button-link button-link--primary" href="/admin/promocionais/presentes/configurar">Ir para configuração da campanha</a>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
(function () {
    var marcarVisiveis = document.getElementById('selecionar_visiveis');
    var form = document.getElementById('presentes-selection-form');

    if (marcarVisiveis && form) {
        marcarVisiveis.addEventListener('change', function () {
            var checks = form.querySelectorAll('input[name="usuarios_selecionados[]"]');
            checks.forEach(function (checkbox) {
                checkbox.checked = marcarVisiveis.checked;
            });
        });
    }
})();
</script>
