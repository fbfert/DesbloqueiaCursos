<?php
/**
 * Recuperar senha no tema caderno. Mesmo formulário da V2
 * (resources/views/v2/pages/recuperar-senha.php): POST em $recuperarAction com
 * `login` e `origem=v2`; _token injetado pelo View::render. A resposta do
 * sistema continua neutra (não confirma se a conta existe).
 */

use App\Core\Helpers;

$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$recuperarAction = isset($recuperarAction) ? (string) $recuperarAction : '/recuperar-senha';
$loginHref = $authLinks['login'];

$loginValue = isset($old['login']) ? (string) $old['login'] : '';
$temErros = !empty($errors);
?>
<div class="auth-cab">
  <h1 class="t2">Recuperar senha</h1>
  <p class="lead">Informe seu e-mail ou CPF. Se houver uma conta, enviaremos um link de recuperação.</p>
</div>

<?php if (!empty($success)): ?>
<div class="postit ok auth-aviso" role="status" aria-live="polite"><?= Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success) ?></div>
<?php endif; ?>

<form class="auth-form" method="post" action="<?= Helpers::e($recuperarAction) ?>" novalidate>
  <input type="hidden" name="origem" value="v2">

  <div class="campo<?= $temErros ? ' erro' : '' ?>">
    <label for="cad-rec-login">E-mail ou CPF</label>
    <input type="text" id="cad-rec-login" name="login" value="<?= Helpers::e($loginValue) ?>"
           placeholder="seuemail@exemplo.com ou 000.000.000-00"
           autocomplete="username" required autofocus
           <?= $temErros ? 'aria-invalid="true" aria-describedby="cad-rec-erros"' : '' ?>>
    <?php if ($temErros): ?>
    <p class="erro-msg" id="cad-rec-erros" role="alert">
      <?php foreach ($errors as $i => $erro): ?><?= $i > 0 ? '<br>' : '' ?><?= Helpers::e((string) $erro) ?><?php endforeach; ?>
    </p>
    <?php endif; ?>
  </div>

  <button type="submit" class="btn btn-bloco auth-enviar">Enviar link de recuperação</button>
</form>

<p class="auth-pe"><a class="link auth-voltar" href="<?= Helpers::e($loginHref) ?>"><?= caderno_icone('seta-esq') ?>Voltar ao login</a></p>
