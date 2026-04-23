<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1><?php echo Helpers::e($perfil['usuario_nome']); ?></h1>
    <p>Detalhe do perfil fiscal do professor.</p>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>Tipo</dt><dd><?php echo Helpers::e($perfil['tipo_pessoa']); ?></dd>
        <dt>Documento</dt><dd><?php echo Helpers::e(!empty($perfil['cpf']) ? $perfil['cpf'] : $perfil['cnpj']); ?></dd>
        <dt>Razao social</dt><dd><?php echo Helpers::e($perfil['razao_social'] ?? ''); ?></dd>
        <dt>Nome fantasia</dt><dd><?php echo Helpers::e($perfil['nome_fantasia'] ?? ''); ?></dd>
        <dt>Aliquota</dt><dd><?php echo number_format((float) $perfil['aliquota_retencao'], 2, ',', '.'); ?>%</dd>
        <dt>Exige NF</dt><dd><?php echo !empty($perfil['exige_nota_fiscal']) ? 'Sim' : 'Nao'; ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($perfil['status']); ?></dd>
    </dl>
</section>

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
