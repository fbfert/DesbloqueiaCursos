<?php use App\Core\Helpers; ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($perfil['usuario_nome']); ?></h1>
        <p class="admin-page__subtitle">Detalhe do perfil fiscal do professor.</p>
    </div>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>Tipo</dt><dd><?php echo Helpers::e($perfil['tipo_pessoa']); ?></dd>
        <dt>Documento</dt><dd><?php echo Helpers::e(!empty($perfil['cpf']) ? $perfil['cpf'] : $perfil['cnpj']); ?></dd>
        <dt>Razão social</dt><dd><?php echo Helpers::e($perfil['razao_social'] ?? ''); ?></dd>
        <dt>Nome fantasia</dt><dd><?php echo Helpers::e($perfil['nome_fantasia'] ?? ''); ?></dd>
        <dt>Alíquota</dt><dd><?php echo number_format((float) $perfil['aliquota_retencao'], 2, ',', '.'); ?>%</dd>
        <dt>Exige NF</dt><dd><?php echo !empty($perfil['exige_nota_fiscal']) ? 'Sim' : 'Não'; ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($perfil['status']); ?></dd>
    </dl>
</section>
</div>

<section class="status-card">
    <div class="split-actions">
        <a href="/admin/professores-fiscais/editar?perfil_id=<?php echo (int) $perfil['id']; ?>">Editar</a>
        <a href="/admin/professores-fiscais">Voltar</a>
    </div>
    <form method="post" action="/admin/professores-fiscais/excluir" class="admin-form" style="margin-top: 16px;">
        <input type="hidden" name="id" value="<?php echo (int) $perfil['id']; ?>">
        <label>
            Justificativa para lixeira
            <input type="text" name="justificativa" required>
        </label>
        <button type="submit">Remover perfil fiscal</button>
    </form>
</section>

