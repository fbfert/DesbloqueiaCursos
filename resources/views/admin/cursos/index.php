<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php $canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar'); ?>
<?php
$cursosAtivos = array();
$cursosInativos = array();
foreach ($cursos ?? array() as $cursoItem) {
    if (($cursoItem['status'] ?? '') === 'ativo') {
        $cursosAtivos[] = $cursoItem;
        continue;
    }
    $cursosInativos[] = $cursoItem;
}
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Cursos e eventos</h1>
            <p class="admin-page__subtitle">Operação administrativa do catálogo principal.</p>
        </div>
        <?php if ($canManage): ?>
            <div class="admin-page__actions">
                <a class="button-link" href="/admin/cursos/criar">Novo curso/evento</a>
            </div>
        <?php endif; ?>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Cursos e eventos ativos</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Professor</th>
                    <th>Tipo</th>
                    <th>Modalidade</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cursosAtivos)): ?>
                    <tr><td colspan="8">Nenhum curso/evento cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($cursosAtivos as $curso): ?>
                    <tr>
                        <td><?php echo Helpers::e($curso['nome']); ?></td>
                        <td><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></td>
                        <td><?php echo Helpers::e($curso['professor_responsavel']['nome'] ?? '-'); ?></td>
                        <td><?php echo Helpers::e($curso['tipo']); ?></td>
                        <td><?php echo Helpers::e($curso['modalidade']); ?></td>
                        <td>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></td>
                        <td><?php echo Helpers::e($curso['status']); ?></td>
                        <td>
                            <div class="split-actions">
                                <a href="/admin/cursos/show?curso_id=<?php echo (int) $curso['id']; ?>">Ver</a>
                                <?php if ($canManage): ?>
                                    <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
                                    <form method="post" action="/admin/cursos/status" class="admin-form">
                                        <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $curso['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>">
                                        <button type="submit"><?php echo $curso['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?></button>
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

    <section class="admin-section" style="margin-top:16px;">
        <details>
            <summary style="cursor:pointer;font-weight:700;">Cursos e eventos inativos (<?php echo count($cursosInativos); ?>)</summary>
            <div class="table-wrap" style="margin-top:12px;">
                <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Professor</th>
                        <th>Tipo</th>
                        <th>Modalidade</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursosInativos)): ?>
                        <tr><td colspan="8">Nenhum curso/evento inativo.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cursosInativos as $curso): ?>
                        <tr>
                            <td><?php echo Helpers::e($curso['nome']); ?></td>
                            <td><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></td>
                            <td><?php echo Helpers::e($curso['professor_responsavel']['nome'] ?? '-'); ?></td>
                            <td><?php echo Helpers::e($curso['tipo']); ?></td>
                            <td><?php echo Helpers::e($curso['modalidade']); ?></td>
                            <td>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></td>
                            <td><?php echo Helpers::e($curso['status']); ?></td>
                            <td>
                                <div class="split-actions">
                                    <a href="/admin/cursos/show?curso_id=<?php echo (int) $curso['id']; ?>">Ver</a>
                                    <?php if ($canManage): ?>
                                        <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
                                        <form method="post" action="/admin/cursos/status" class="admin-form">
                                            <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $curso['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>">
                                            <button type="submit"><?php echo $curso['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        </details>
    </section>
</div>

