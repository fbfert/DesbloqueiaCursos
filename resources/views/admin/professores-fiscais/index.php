<?php use App\Core\Helpers; ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Professores fiscais</h1>
        <p class="admin-page__subtitle">Operação fiscal dos professores e retenções.</p>
    </div>
    <div class="admin-page__actions">
        <a class="button-link" href="/admin/professores-fiscais/criar">Novo perfil fiscal</a>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Perfis fiscais</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Professor</th>
                    <th>Tipo</th>
                    <th>Documento</th>
                    <th>Alíquota</th>
                    <th>Exige NF</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($professores_fiscal)): ?>
                    <tr><td colspan="7">Nenhum perfil fiscal cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($professores_fiscal as $perfil): ?>
                    <tr>
                        <td><?php echo Helpers::e($perfil['usuario_nome']); ?></td>
                        <td><?php echo Helpers::e($perfil['tipo_pessoa']); ?></td>
                        <td><?php echo Helpers::e(!empty($perfil['cpf']) ? $perfil['cpf'] : $perfil['cnpj']); ?></td>
                        <td><?php echo number_format((float) $perfil['aliquota_retencao'], 2, ',', '.'); ?>%</td>
                        <td><?php echo !empty($perfil['exige_nota_fiscal']) ? 'Sim' : 'Não'; ?></td>
                        <td><?php echo Helpers::e($perfil['status']); ?></td>
                        <td>
                            <div class="split-actions">
                                <a class="button-link button-link--ghost" href="/admin/professores-fiscais/show?perfil_id=<?php echo (int) $perfil['id']; ?>">Ver</a>
                                <a class="button-link button-link--ghost" href="/admin/professores-fiscais/editar?perfil_id=<?php echo (int) $perfil['id']; ?>">Editar</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

