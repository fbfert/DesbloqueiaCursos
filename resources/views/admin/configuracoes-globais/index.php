<section class="hero">
    <h1>Configuracoes globais</h1>
    <p>Regras institucionais, visuais, de seguranca, certificados, e-mail e financeiro.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <div class="grid-2">
        <a class="card-link" href="/admin/configuracoes-globais/certificados">Certificados</a>
        <a class="card-link" href="/admin/configuracoes-globais/frontend">Frontend</a>
        <a class="card-link" href="/admin/configuracoes-globais/financeiro">Financeiro</a>
        <a class="card-link" href="/admin/configuracoes-globais/seguranca">Seguranca</a>
    </div>
</section>

<section class="status-card">
    <strong>Institucional</strong>
    <form method="post" action="/admin/configuracoes-globais" class="form-grid">
        <label>
            Nome fantasia
            <input type="text" name="nome_fantasia" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['nome_fantasia']) ? $configuracoes['institucional']['nome_fantasia'] : 'Polo Rainbow'), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Razao social
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
            Telefone
            <input type="text" name="telefone" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['telefone']) ? $configuracoes['institucional']['telefone'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Logo
            <input type="text" name="logo_caminho" value="<?php echo htmlspecialchars((string) (isset($configuracoes['institucional']['logo_caminho']) ? $configuracoes['institucional']['logo_caminho'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <button type="submit">Salvar institucional</button>
    </form>
</section>
