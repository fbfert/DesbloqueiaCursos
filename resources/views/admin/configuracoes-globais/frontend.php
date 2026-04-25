<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Configurações de frontend</h1>
        <p class="admin-page__subtitle">Template visual e identidade da interface.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="/admin/configuracoes-globais/frontend" class="form-grid">
        <label>
            Template visual do portal
            <input type="text" name="template_visual_portal" value="<?php echo htmlspecialchars((string) (isset($configuracao['template_visual_portal']) ? $configuracao['template_visual_portal'] : 'padrao'), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Cor primária
            <input type="text" name="cor_primaria" value="<?php echo htmlspecialchars((string) (isset($configuracao['cor_primaria']) ? $configuracao['cor_primaria'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Cor secundária
            <input type="text" name="cor_secundaria" value="<?php echo htmlspecialchars((string) (isset($configuracao['cor_secundaria']) ? $configuracao['cor_secundaria'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Logo
            <input type="text" name="logo_caminho" value="<?php echo htmlspecialchars((string) (isset($configuracao['logo_caminho']) ? $configuracao['logo_caminho'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Banner
            <input type="text" name="banner_caminho" value="<?php echo htmlspecialchars((string) (isset($configuracao['banner_caminho']) ? $configuracao['banner_caminho'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Descrição da home
            <textarea name="descricao_home" rows="5"><?php echo htmlspecialchars((string) (isset($configuracao['descricao_home']) ? $configuracao['descricao_home'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <button type="submit">Salvar frontend</button>
    </form>
</section>
</div>

