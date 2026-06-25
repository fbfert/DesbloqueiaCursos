<?php use App\Core\Helpers; ?>
<?php
// Login (template v4-claude). Incluído por auth/login.php. Campos reais: login, senha.
$errors = isset($errors) && is_array($errors) ? $errors : array();
$old = isset($old) && is_array($old) ? $old : array();
$accountCreated = isset($accountCreated) && is_array($accountCreated) ? $accountCreated : null;
?>

<div class="dc-auth-wrap">
  <div class="dc-auth-card">

    <a href="/" class="dc-auth-logo">Desbloqueia&nbsp;<strong>Cursos</strong></a>

    <h1 class="dc-auth-titulo">Bem-vindo de volta</h1>
    <p class="dc-text-muted dc-text-sm" style="text-align:center;margin-bottom:20px;">Entre com sua conta para continuar aprendendo.</p>

    <?php if (!empty($errors)): ?>
      <div class="dc-callout dc-callout-danger">
        <i class="ti ti-alert-circle"></i>
        <span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span>
      </div>
    <?php endif; ?>

    <?php if ($accountCreated): ?>
      <div class="dc-callout dc-callout-success">
        <i class="ti ti-circle-check"></i>
        <span>Conta criada com sucesso. Acesse com seus dados para começar.</span>
      </div>
    <?php endif; ?>

    <form method="post" action="/login">
      <?php echo $csrfField; ?>

      <div class="dc-field">
        <label for="dc-login-login">E-mail ou CPF</label>
        <input type="text" id="dc-login-login" name="login" class="dc-input"
               placeholder="seuemail@exemplo.com ou 000.000.000-00"
               value="<?php echo Helpers::e(isset($old['login']) ? (string) $old['login'] : ''); ?>"
               autocomplete="username" required>
      </div>

      <div class="dc-field">
        <label for="dc-login-senha">Senha</label>
        <div class="dc-input-wrap">
          <input type="password" id="dc-login-senha" name="senha" class="dc-input"
                 placeholder="Sua senha" autocomplete="current-password" required>
          <button type="button" class="dc-input-eye" data-dc-toggle-pass="#dc-login-senha" aria-label="Mostrar ou ocultar senha">
            <i class="ti ti-eye"></i>
          </button>
        </div>
      </div>

      <div style="text-align:right;margin-bottom:16px;">
        <a href="/recuperar-senha" class="dc-text-sm">Esqueci minha senha</a>
      </div>

      <button type="submit" class="dc-btn dc-btn-primary dc-btn-block">Entrar</button>
    </form>

    <div class="dc-auth-footer">
      Não tem conta? <a href="/cadastro">Criar conta</a>
    </div>

  </div>
</div>
