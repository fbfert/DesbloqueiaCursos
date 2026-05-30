<div class="admin-page admin-config-frontend">
<section class="admin-page__header admin-config-frontend__header">
    <div>
        <h1 class="admin-page__title">Configurações de frontend</h1>
        <p class="admin-page__subtitle">Ajuste identidade visual, conteúdo da capa e volume de destaques públicos.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="admin-config-frontend__layout">
    <section class="status-card admin-config-frontend__main">
        <div class="panel-header">
            <h2>Identidade e capa</h2>
            <span class="muted">Ajustes publicados no portal</span>
        </div>
        <form method="post" action="/admin/configuracoes-globais/frontend" class="form-grid admin-config-frontend__form">
            <?php echo $csrfField; ?>
            <label class="full">
                Template visual do portal
                <input type="text" name="template_visual_portal" value="<?php echo htmlspecialchars((string) (isset($configuracao['template_visual_portal']) ? $configuracao['template_visual_portal'] : 'padrao'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="full">
                Espaçamento entre cards do frontend
                <input type="text" name="frontend_card_gap" value="<?php echo htmlspecialchars((string) (isset($configuracao['frontend_card_gap']) && trim((string) $configuracao['frontend_card_gap']) !== '' ? $configuracao['frontend_card_gap'] : 'clamp(16px, 2vw, 24px)'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="clamp(16px, 2vw, 24px)">
                <small>Informe um valor CSS válido, como 16px, 1rem, 24px ou clamp(16px, 2vw, 24px).</small>
            </label>
            <label class="full">
                Espaçamento vertical entre seções do frontend
                <input type="text" name="frontend_section_gap" value="<?php echo htmlspecialchars((string) (isset($configuracao['frontend_section_gap']) && trim((string) $configuracao['frontend_section_gap']) !== '' ? $configuracao['frontend_section_gap'] : 'clamp(24px, 3vw, 40px)'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="clamp(24px, 3vw, 40px)">
                <small>Informe um valor CSS válido para controlar o espaço vertical entre blocos e seções do frontend público. Exemplos: 24px, 2rem ou clamp(24px, 3vw, 40px).</small>
            </label>
            <label>
                Cor primária
                <input type="text" name="cor_primaria" value="<?php echo htmlspecialchars((string) (isset($configuracao['cor_primaria']) ? $configuracao['cor_primaria'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                Cor secundária
                <input type="text" name="cor_secundaria" value="<?php echo htmlspecialchars((string) (isset($configuracao['cor_secundaria']) ? $configuracao['cor_secundaria'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="full">
                Logo
                <input type="text" name="logo_caminho" value="<?php echo htmlspecialchars((string) (isset($configuracao['logo_caminho']) ? $configuracao['logo_caminho'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="full">
                Banner
                <input type="text" name="banner_caminho" value="<?php echo htmlspecialchars((string) (isset($configuracao['banner_caminho']) ? $configuracao['banner_caminho'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="full">
                Descrição da home
                <textarea name="descricao_home" rows="6"><?php echo htmlspecialchars((string) (isset($configuracao['descricao_home']) ? $configuracao['descricao_home'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>
            <label class="admin-config-frontend__limit">
                Quantidade de destaques na capa
                <input type="number" name="home_destaques_limite" min="1" max="12" step="1" value="<?php echo htmlspecialchars((string) (isset($configuracao['home_destaques_limite']) && (int) $configuracao['home_destaques_limite'] > 0 ? (int) $configuracao['home_destaques_limite'] : 6), ENT_QUOTES, 'UTF-8'); ?>">
                <small>Faixa recomendada entre 1 e 12. O padrão é 6.</small>
            </label>
            <div class="admin-config-frontend__actions full">
                <?php $cancelUrl = '/admin/configuracoes-globais/frontend'; ?>
                <?php $showSaveAndNew = false; ?>
                <?php $showSaveAndExit = false; ?>
                <?php $showSaveAsCopy = false; ?>
                <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
            </div>
        </form>
    </section>

    <aside class="status-card admin-config-frontend__sidebar">
        <div class="panel-header">
            <h2>Resumo</h2>
        </div>
        <div class="admin-config-frontend__summary">
            <div>
                <strong>Limite na capa</strong>
                <span><?php echo (int) (isset($configuracao['home_destaques_limite']) && (int) $configuracao['home_destaques_limite'] > 0 ? $configuracao['home_destaques_limite'] : 6); ?> destaque(s)</span>
            </div>
            <div>
                <strong>Espaçamento entre cards</strong>
                <span><?php echo htmlspecialchars((string) (isset($configuracao['frontend_card_gap']) && trim((string) $configuracao['frontend_card_gap']) !== '' ? $configuracao['frontend_card_gap'] : 'clamp(16px, 2vw, 24px)'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div>
                <strong>Espaçamento entre seções</strong>
                <span><?php echo htmlspecialchars((string) (isset($configuracao['frontend_section_gap']) && trim((string) $configuracao['frontend_section_gap']) !== '' ? $configuracao['frontend_section_gap'] : 'clamp(24px, 3vw, 40px)'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div>
                <strong>Template</strong>
                <span><?php echo htmlspecialchars((string) (isset($configuracao['template_visual_portal']) ? $configuracao['template_visual_portal'] : 'padrao'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div>
                <strong>Cores</strong>
                <span><?php echo htmlspecialchars((string) (isset($configuracao['cor_primaria']) ? $configuracao['cor_primaria'] : 'N/A'), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string) (isset($configuracao['cor_secundaria']) ? $configuracao['cor_secundaria'] : 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div>
                <strong>Conteúdo da home</strong>
                <span>A capa continua consumindo o catálogo ativo do backend e respeita o limite configurado aqui.</span>
            </div>
        </div>
    </aside>
</section>
</div>

