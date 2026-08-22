<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Configurações globais</h1>
        <p class="admin-page__subtitle">Regras institucionais, visuais, de segurança, certificados, e-mail e financeiro.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <div class="grid-2">
        <a class="card-link" href="/admin/configuracoes-globais/certificados">Certificados</a>
        <a class="card-link" href="/admin/configuracoes-globais/frontend">Frontend</a>
        <a class="card-link" href="/admin/configuracoes-globais/financeiro">Financeiro</a>
        <a class="card-link" href="/admin/configuracoes-globais/seguranca">Segurança</a>
    </div>
</section>

<section class="status-card">
    <strong>Institucional</strong>
    <form method="post" action="/admin/configuracoes-globais" class="form-grid" enctype="multipart/form-data">
        <label>
            Nome fantasia
            <input type="text" name="nome_fantasia" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['nome_fantasia']) ? $configuracoes['institucional']['nome_fantasia'] : 'Desbloqueia Cursos'), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Razão social
            <input type="text" name="razao_social" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['razao_social']) ? $configuracoes['institucional']['razao_social'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            CNPJ
            <input type="text" name="cnpj" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['cnpj']) ? $configuracoes['institucional']['cnpj'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Cidade
            <input type="text" name="cidade" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['cidade']) ? $configuracoes['institucional']['cidade'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            UF
            <input type="text" name="uf" maxlength="2" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['uf']) ? $configuracoes['institucional']['uf'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            E-mail institucional
            <input type="email" name="email_institucional" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['email_institucional']) ? $configuracoes['institucional']['email_institucional'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            E-mail financeiro
            <input type="email" name="email_financeiro" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['email_financeiro']) ? $configuracoes['institucional']['email_financeiro'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            E-mail suporte
            <input type="email" name="email_suporte" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['email_suporte']) ? $configuracoes['institucional']['email_suporte'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            E-mail certificados
            <input type="email" name="email_certificados" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['email_certificados']) ? $configuracoes['institucional']['email_certificados'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            E-mail avaliador pedagógico (trabalhos)
            <input type="email" name="email_avaliador_pedagogico" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['email_avaliador_pedagogico']) ? $configuracoes['institucional']['email_avaliador_pedagogico'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="field-hint">Recebe um aviso sempre que um aluno enviar uma nova avaliação textual pendente de correção.</span>
        </label>
        <label>
            Telefone
            <input type="text" name="telefone" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['telefone']) ? $configuracoes['institucional']['telefone'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Logo
            <input type="text" name="logo_caminho" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['logo_caminho']) ? $configuracoes['institucional']['logo_caminho'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            <small>Informe um caminho público ou envie uma nova imagem abaixo. Esta logo também é usada como fallback nos certificados quando não houver imagem própria.</small>
            <div class="configuracoes-globais-upload-preview">
                <div class="configuracoes-globais-upload-preview__label">Logo institucional atual</div>
                <?php if (!empty($configuracoes['institucional']['logo_caminho'])): ?>
                    <img
                        class="configuracoes-globais-upload-preview__image"
                        src="<?php echo htmlspecialchars((string) $configuracoes['institucional']['logo_caminho'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Pré-visualização da logo"
                    >
                <?php else: ?>
                    <span class="configuracoes-globais-upload-preview__empty">Nenhuma logo definida.</span>
                <?php endif; ?>
            </div>
        </label>
        <label>
            Favicon do site
            <input type="text" name="favicon_caminho" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['favicon_caminho']) ? $configuracoes['institucional']['favicon_caminho'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            <small>Ícone exibido na aba do navegador e em atalhos do site.</small>
            <?php if (!empty($configuracoes['institucional']['favicon_url'])): ?>
                <div style="margin-top:10px;">
                    <div class="muted" style="margin-bottom:6px;">Favicon atual</div>
                    <img
                        src="<?php echo htmlspecialchars((string) $configuracoes['institucional']['favicon_url'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Pré-visualização do favicon"
                        style="width:32px;height:32px;display:block;image-rendering:auto;"
                    >
                </div>
            <?php else: ?>
                <small class="muted">Nenhum favicon personalizado definido.</small>
            <?php endif; ?>
        </label>
        <label>
            Enviar novo favicon
            <input type="file" name="favicon_upload" accept=".ico,.png,image/png,image/x-icon,image/vnd.microsoft.icon">
            <small>Formatos permitidos: ICO e PNG. Tamanho máximo: 1 MB.</small>
        </label>
        <label>
            Enviar nova logo
            <input type="file" name="logo_upload" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
            <small>Formatos permitidos: JPG, PNG, WEBP ou GIF. Recomendado: PNG com fundo transparente.</small>
        </label>
        <?php $cancel_url = '/admin/configuracoes-globais'; ?>
        <?php $showSaveAndNew = false; ?>
        <?php $showSaveAndExit = false; ?>
        <?php $showSaveAsCopy = false; ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>

