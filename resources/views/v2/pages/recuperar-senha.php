<?php
use App\Core\Helpers;

$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$recuperarAction = isset($recuperarAction) ? (string) $recuperarAction : '/recuperar-senha';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$loginHref = isset($loginHref) ? (string) $loginHref : '/v2/login';

$loginValue = isset($old['login']) ? (string) $old['login'] : '';
$temErros = !empty($errors);
$describedBy = $temErros ? 'v2-rec-erros' : 'v2-rec-ajuda';
?>
<div class="v2-auth-split v2-auth-split-single">
  <div class="v2-auth-card">
    <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-auth-logo"><img src="/v2/assets/img/logo-v2.svg" alt="Desbloqueia Cursos"></a>
    <h1 class="v2-h2 v2-center">Recuperar senha</h1>
    <p class="v2-muted v2-center v2-sm" style="margin-bottom:20px;">Informe seu e-mail ou CPF. Se houver uma conta, enviaremos um link de recuperação.</p>

    <?php if ($temErros): ?>
      <div class="v2-callout v2-callout-danger" id="v2-rec-erros" role="alert" aria-live="assertive">
        <i class="ti ti-alert-triangle"></i>
        <span>
          <?php foreach ($errors as $erro): ?>
            <?php echo Helpers::e((string) $erro); ?><br>
          <?php endforeach; ?>
        </span>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status" aria-live="polite">
        <i class="ti ti-mail-check"></i>
        <span><?php echo Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo Helpers::e($recuperarAction); ?>" id="v2-recuperar-real" data-native-submit novalidate>
      <input type="hidden" name="origem" value="v2">

      <div class="v2-field<?php echo $temErros ? ' has-error' : ''; ?>">
        <label for="v2-rec-login">E-mail ou CPF</label>
        <input type="text" id="v2-rec-login" name="login" class="v2-input"
               value="<?php echo Helpers::e($loginValue); ?>"
               placeholder="seuemail@exemplo.com ou 000.000.000-00"
               autocomplete="username" aria-describedby="<?php echo Helpers::e($describedBy); ?>"
               required autofocus>
        <div class="v2-field-error" id="v2-rec-ajuda"></div>
      </div>

      <button type="submit" class="v2-btn v2-btn-primary v2-btn-block">Enviar link de recuperação <i class="ti ti-send"></i></button>
    </form>

    <div class="v2-auth-foot"><a href="<?php echo Helpers::e($loginHref); ?>"><i class="ti ti-arrow-left"></i> Voltar ao login</a></div>
  </div>
</div>
