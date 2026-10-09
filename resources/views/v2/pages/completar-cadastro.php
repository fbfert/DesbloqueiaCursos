<?php
/**
 * "Complete seu cadastro" no tema V2 (login-google). POST /conta/completar com
 * `cpf`, ou `acao=depois` para seguir sem informar.
 */

use App\Core\Helpers;

$errors = isset($errors) && is_array($errors) ? $errors : array();
$old = isset($old) && is_array($old) ? $old : array();
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$erroCpf = isset($errors['cpf']) ? (string) $errors['cpf'] : '';
$primeiroNome = trim((string) strtok(isset($usuarioNome) ? (string) $usuarioNome : '', ' '));
?>
<div class="v2-auth-split">
  <div class="v2-auth-card">
    <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-auth-logo"><img src="/v2/assets/img/logo-v2.svg" alt="Desbloqueia Cursos"></a>
    <h1 class="v2-h2 v2-center">Complete seu cadastro</h1>
    <p class="v2-muted v2-center v2-sm" style="margin-bottom:20px;"><?php echo $primeiroNome !== '' ? 'Boas-vindas, ' . Helpers::e(mb_convert_case($primeiroNome, MB_CASE_TITLE, 'UTF-8')) . '! ' : ''; ?>Informe seu CPF para podermos emitir seus certificados. Leva só um instante.</p>

    <?php if ($erroCpf !== ''): ?>
      <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle"></i><span><?php echo Helpers::e($erroCpf); ?></span></div>
    <?php endif; ?>

    <form method="post" action="/conta/completar" data-native-submit novalidate>
      <div class="v2-field<?php echo $erroCpf !== '' ? ' has-error' : ''; ?>">
        <label for="completar-cpf">CPF</label>
        <input type="text" id="completar-cpf" name="cpf" class="v2-input" value="<?php echo Helpers::e(isset($old['cpf']) ? (string) $old['cpf'] : ''); ?>"
               placeholder="000.000.000-00" maxlength="14" inputmode="numeric" data-mask-cpf autofocus aria-describedby="completar-cpf-ajuda">
        <div class="v2-sm v2-muted" id="completar-cpf-ajuda">Ele aparece no certificado e na validação pública. Depois de salvo, só o atendimento altera.</div>
      </div>
      <button type="submit" class="v2-btn v2-btn-primary v2-btn-block">Salvar e continuar <i class="ti ti-arrow-right"></i></button>
    </form>

    <form method="post" action="/conta/completar" class="v2-auth-foot">
      <input type="hidden" name="acao" value="depois">
      <button type="submit" class="v2-btn v2-btn-ghost">Fazer isso depois</button>
    </form>
  </div>
</div>
