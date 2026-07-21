<?php
use App\Core\Helpers;

$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$token = isset($token) ? (string) $token : '';
$redefinirAction = isset($redefinirAction) ? (string) $redefinirAction : '/recuperar-senha/redefinir';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$loginHref = isset($loginHref) ? (string) $loginHref : '/v2/login';

$tokenValue = isset($old['token']) && (string) $old['token'] !== '' ? (string) $old['token'] : $token;
$temErros = !empty($errors);
$describedBy = $temErros ? 'v2-red-erros' : null;
?>
<div class="v2-auth-split v2-auth-split-single">
  <div class="v2-auth-card">
    <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-auth-logo"><img src="/v2/assets/img/logo-v2.svg" alt="Desbloqueia Cursos"></a>
    <h1 class="v2-h2 v2-center">Redefinir senha</h1>
    <p class="v2-muted v2-center v2-sm" style="margin-bottom:20px;">Escolha uma nova senha para sua conta.</p>

    <?php if ($temErros): ?>
      <div class="v2-callout v2-callout-danger" id="v2-red-erros" role="alert" aria-live="assertive">
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
        <i class="ti ti-circle-check"></i>
        <span><?php echo Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo Helpers::e($redefinirAction); ?>" id="v2-redefinir-form" data-native-submit novalidate>
      <input type="hidden" name="origem" value="v2">
      <input type="hidden" name="token" value="<?php echo Helpers::e($tokenValue); ?>">

      <div class="v2-field">
        <label for="v2-red-senha">Nova senha</label>
        <input type="password" id="v2-red-senha" name="senha" class="v2-input" minlength="8" autocomplete="new-password" required autofocus aria-describedby="<?php echo Helpers::e((string) $describedBy); ?>">
      </div>
      <div class="v2-field">
        <label for="v2-red-senha-conf">Confirmar nova senha</label>
        <input type="password" id="v2-red-senha-conf" name="senha_confirmacao" class="v2-input" minlength="8" autocomplete="new-password" required>
      </div>

      <button type="submit" class="v2-btn v2-btn-primary v2-btn-block">Salvar nova senha <i class="ti ti-check"></i></button>
    </form>

    <div class="v2-auth-foot"><a href="<?php echo Helpers::e($loginHref); ?>"><i class="ti ti-arrow-left"></i> Voltar ao login</a></div>
  </div>
</div>
