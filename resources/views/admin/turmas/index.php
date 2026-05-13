<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php
$canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar');
$filters = isset($filters) && is_array($filters) ? $filters : array();
$statusAtual = isset($filters['status']) ? (string) $filters['status'] : '';
$qAtual = isset($filters['q']) ? (string) $filters['q'] : '';
$cursoAtual = isset($filters['curso_id']) ? (int) $filters['curso_id'] : 0;
$turmas = isset($turmas) && is_array($turmas) ? $turmas : array();
$turmasEncerradas = isset($turmas_encerradas) && is_array($turmas_encerradas) ? $turmas_encerradas : array();
$turmasExcluidas = isset($turmas_excluidas) && is_array($turmas_excluidas) ? $turmas_excluidas : array();
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$mostrarSecoes = !empty($mostrar_secoes);
$statusLabels = array(
    'planejada' => 'Planejada',
    'aberta' => 'Aberta',
    'encerrada' => 'Encerrada',
    'excluida' => 'Excluída',
);
$formatStatus = function ($status) use ($statusLabels) {
    $status = (string) $status;

    return isset($statusLabels[$status]) ? $statusLabels[$status] : $status;
};

$renderAcoes = function (array $turma, $canManage) {
    $html = '<div class="split-actions">';
    $html .= '<a href="/admin/turmas/show?turma_id=' . (int) $turma['id'] . '">Ver</a>';

    if ($canManage && empty($turma['deleted_at'])) {
        $html .= '<a href="/admin/turmas/editar?turma_id=' . (int) $turma['id'] . '">Editar</a>';
        $html .= '<form method="post" action="/admin/turmas/status" class="admin-form">';
        $html .= '<input type="hidden" name="id" value="' . (int) $turma['id'] . '">';
        $html .= '<input type="hidden" name="status" value="' . ($turma['status'] === 'aberta' ? 'encerrada' : 'aberta') . '">';
        $html .= '<button type="submit">' . ($turma['status'] === 'aberta' ? 'Encerrar' : 'Abrir') . '</button>';
        $html .= '</form>';
    }

    $html .= '</div>';

    return $html;
};
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Turmas</h1>
            <p class="admin-page__subtitle">Instâncias do catálogo para operação e acompanhamento.</p>
        </div>
        <?php if ($canManage): ?>
            <div class="admin-page__actions">
                <a class="button-link" href="/admin/turmas/criar">Nova turma</a>
            </div>
        <?php endif; ?>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card admin-turmas__filters-card">
        <form method="get" action="/admin/turmas" class="admin-filters">
            <div class="admin-filters__row">
                <label>Busca
                    <input type="text" name="q" value="<?php echo Helpers::e($qAtual); ?>" placeholder="Nome da turma ou curso">
                </label>
                <label>Curso
                    <select name="curso_id">
                        <option value="0">Todos os cursos</option>
                        <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo (int) $curso['id']; ?>" <?php echo $cursoAtual === (int) $curso['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($curso['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <option value="" <?php echo $statusAtual === '' ? 'selected' : ''; ?>>Padrão da tela</option>
                        <option value="ativas" <?php echo $statusAtual === 'ativas' ? 'selected' : ''; ?>>Todas ativas</option>
                        <option value="planejada" <?php echo $statusAtual === 'planejada' ? 'selected' : ''; ?>>Planejadas</option>
                        <option value="aberta" <?php echo $statusAtual === 'aberta' ? 'selected' : ''; ?>>Abertas</option>
                        <option value="encerrada" <?php echo $statusAtual === 'encerrada' ? 'selected' : ''; ?>>Encerradas</option>
                        <option value="excluida" <?php echo $statusAtual === 'excluida' ? 'selected' : ''; ?>>Excluídas</option>
                    </select>
                </label>
            </div>
            <div class="cta-group">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/turmas">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Turmas cadastradas</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Código</th>
                        <th>Curso</th>
                        <th>Professor</th>
                        <th>Modalidade</th>
                        <th>Início</th>
                        <th>Fim</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($turmas)): ?>
                        <tr><td colspan="9">Nenhuma turma cadastrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($turmas as $turma): ?>
                            <tr>
                                <td><?php echo Helpers::e($turma['nome']); ?></td>
                                <td><?php echo Helpers::e($turma['codigo']); ?></td>
                                <td><?php echo Helpers::e($turma['curso_nome']); ?></td>
                                <td><?php echo Helpers::e($turma['professor_responsavel_nome'] ?? '-'); ?></td>
                                <td><?php echo Helpers::e($turma['curso_modalidade']); ?></td>
                                <td><?php echo Helpers::e((string) $turma['data_inicio']); ?></td>
                                <td><?php echo Helpers::e((string) $turma['data_fim']); ?></td>
                                <td><?php echo Helpers::e($formatStatus($turma['status'])); ?></td>
                                <td><?php echo $renderAcoes($turma, $canManage); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($mostrarSecoes): ?>
        <section class="status-card admin-turmas__details-card">
            <details class="admin-turmas__details">
                <summary>
                    <h2 class="admin-section__title">Turmas encerradas (<?php echo count($turmasEncerradas); ?>)</h2>
                </summary>
                <div class="table-wrap admin-turmas__details-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Código</th>
                                <th>Curso</th>
                                <th>Professor</th>
                                <th>Modalidade</th>
                                <th>Início</th>
                                <th>Fim</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($turmasEncerradas)): ?>
                                <tr><td colspan="9">Nenhuma turma encerrada.</td></tr>
                            <?php else: ?>
                                <?php foreach ($turmasEncerradas as $turma): ?>
                                    <tr>
                                        <td><?php echo Helpers::e($turma['nome']); ?></td>
                                        <td><?php echo Helpers::e($turma['codigo']); ?></td>
                                        <td><?php echo Helpers::e($turma['curso_nome']); ?></td>
                                        <td><?php echo Helpers::e($turma['professor_responsavel_nome'] ?? '-'); ?></td>
                                        <td><?php echo Helpers::e($turma['curso_modalidade']); ?></td>
                                        <td><?php echo Helpers::e((string) $turma['data_inicio']); ?></td>
                                        <td><?php echo Helpers::e((string) $turma['data_fim']); ?></td>
                                        <td><?php echo Helpers::e($formatStatus($turma['status'])); ?></td>
                                        <td><?php echo $renderAcoes($turma, $canManage); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <section class="status-card admin-turmas__details-card">
            <details class="admin-turmas__details">
                <summary>
                    <h2 class="admin-section__title">Turmas excluídas (<?php echo count($turmasExcluidas); ?>)</h2>
                </summary>
                <div class="table-wrap admin-turmas__details-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Código</th>
                                <th>Curso</th>
                                <th>Professor</th>
                                <th>Modalidade</th>
                                <th>Início</th>
                                <th>Fim</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($turmasExcluidas)): ?>
                                <tr><td colspan="9">Nenhuma turma excluída.</td></tr>
                            <?php else: ?>
                                <?php foreach ($turmasExcluidas as $turma): ?>
                                    <tr>
                                        <td><?php echo Helpers::e($turma['nome']); ?></td>
                                        <td><?php echo Helpers::e($turma['codigo']); ?></td>
                                        <td><?php echo Helpers::e($turma['curso_nome']); ?></td>
                                        <td><?php echo Helpers::e($turma['professor_responsavel_nome'] ?? '-'); ?></td>
                                        <td><?php echo Helpers::e($turma['curso_modalidade']); ?></td>
                                        <td><?php echo Helpers::e((string) $turma['data_inicio']); ?></td>
                                        <td><?php echo Helpers::e((string) $turma['data_fim']); ?></td>
                                        <td><?php echo Helpers::e($formatStatus('excluida')); ?></td>
                                        <td><?php echo $renderAcoes($turma, $canManage); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
    <?php endif; ?>
</div>
