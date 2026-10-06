<?php
/**
 * Login no tema caderno. Mesmo formulário da V2 (resources/views/v2/pages/login.php):
 * POST em $loginAction com `login`, `senha`, `origem` e `redirect` (quando houver);
 * o _token de CSRF é injetado pelo View::render em todo form method="post".
 */

use App\Core\Helpers;

require BASE_PATH . '/resources/views/caderno/partials/ver-senha.php';

$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$accountCreated = isset($accountCreated) && is_array($accountCreated) ? $accountCreated : null;
$loginAction = isset($loginAction) ? (string) $loginAction : '/login';
$origemFlag = isset($origemFlag) && in_array($origemFlag, array('v2', 'v2_aluno'), true) ? (string) $origemFlag : 'v2';
$redirectSeguro = isset($redirectSeguro) ? (string) $redirectSeguro : '';
$registerHref = $authLinks['register'];
$forgotHref = $authLinks['forgot'];
$jaLogado = !empty($jaLogado);
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';
$loginValue = isset($old['login']) ? (string) $old['login'] : '';
$temErros = !empty($errors);
$mensagemOk = '';
if ($accountCreated) {
    $mensagemOk = 'Conta criada com sucesso. Agora acesse com seus dados para começar.';
} elseif (!empty($success)) {
    $mensagemOk = is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success;
}
?>
<div class="auth-cab">
  <h1 class="t2">Acesse sua conta</h1>
  <p class="lead">Entre com seu e-mail ou CPF e sua senha para continuar.</p>
</div>

<?php if ($jaLogado): ?>
<div class="postit auth-aviso" role="status">
  <b>Você já está autenticado.</b>
  <a class="link" href="<?= Helpers::e($areaHref) ?>">Ir para a sua área</a>
</div>
<?php endif; ?>

<?php if ($mensagemOk !== ''): ?>
<div class="postit ok auth-aviso" role="status" aria-live="polite"><?= Helpers::e($mensagemOk) ?></div>
<?php endif; ?>

<form class="auth-form" method="post" action="<?= Helpers::e($loginAction) ?>" novalidate>
  <input type="hidden" name="origem" value="<?= Helpers::e($origemFlag) ?>">
  <?php if ($redirectSeguro !== ''): ?>
  <input type="hidden" name="redirect" value="<?= Helpers::e($redirectSeguro) ?>">
  <?php endif; ?>

  <div class="campo<?= $temErros ? ' erro' : '' ?>">
    <label for="cad-login-login">E-mail ou CPF</label>
    <input type="text" id="cad-login-login" name="login" value="<?= Helpers::e($loginValue) ?>"
           placeholder="seuemail@exemplo.com ou 000.000.000-00"
           autocomplete="username" inputmode="text" required autofocus
           <?= $temErros ? 'aria-invalid="true" aria-describedby="cad-login-erros"' : '' ?>>
    <?php if ($temErros): ?>
    <p class="erro-msg" id="cad-login-erros" role="alert">
      <?php foreach ($errors as $i => $erro): ?><?= $i > 0 ? '<br>' : '' ?><?= Helpers::e((string) $erro) ?><?php endforeach; ?>
    </p>
    <?php endif; ?>
  </div>

  <div class="campo">
    <label for="cad-login-senha">Senha</label>
    <div class="campo-senha">
      <input type="password" id="cad-login-senha" name="senha" placeholder="Sua senha" autocomplete="current-password" required>
      <?= caderno_ver_senha('cad-login-senha') ?>
    </div>
  </div>

  <p class="auth-esqueci"><a class="link" href="<?= Helpers::e($forgotHref) ?>">Esqueci minha senha</a></p>

  <button type="submit" class="btn btn-bloco">Entrar <?= caderno_icone('seta-dir') ?></button>
</form>

<p class="auth-pe">Ainda não tem conta? <a class="link" href="<?= Helpers::e($registerHref) ?>">Criar conta</a></p>
