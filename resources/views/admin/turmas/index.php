<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php $canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar'); ?>

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
                <?php endif; ?>
                <?php foreach ($turmas as $turma): ?>
                    <tr>
                        <td><?php echo Helpers::e($turma['nome']); ?></td>
                        <td><?php echo Helpers::e($turma['codigo']); ?></td>
                        <td><?php echo Helpers::e($turma['curso_nome']); ?></td>
                        <td><?php echo Helpers::e($turma['professor_responsavel_nome'] ?? '-'); ?></td>
                        <td><?php echo Helpers::e($turma['curso_modalidade']); ?></td>
                        <td><?php echo Helpers::e((string) $turma['data_inicio']); ?></td>
                        <td><?php echo Helpers::e((string) $turma['data_fim']); ?></td>
                        <td><?php echo Helpers::e($turma['status']); ?></td>
                        <td>
                            <div class="split-actions">
                                <a href="/admin/turmas/show?turma_id=<?php echo (int) $turma['id']; ?>">Ver</a>
                                <?php if ($canManage): ?>
                                    <a href="/admin/turmas/editar?turma_id=<?php echo (int) $turma['id']; ?>">Editar</a>
                                    <form method="post" action="/admin/turmas/status">
                                        <input type="hidden" name="id" value="<?php echo (int) $turma['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $turma['status'] === 'aberta' ? 'encerrada' : 'aberta'; ?>">
                                        <button type="submit"><?php echo $turma['status'] === 'aberta' ? 'Encerrar' : 'Abrir'; ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>
</div>

