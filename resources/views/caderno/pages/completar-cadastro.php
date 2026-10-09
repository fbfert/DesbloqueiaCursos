<?php
/**
 * "Complete seu cadastro" no tema caderno (login-google). POST /conta/completar com
 * `cpf`, ou `acao=depois` para seguir sem informar. _token injetado pelo View::render.
 */

use App\Core\Helpers;

$errors = isset($errors) && is_array($errors) ? $errors : array();
$old = isset($old) && is_array($old) ? $old : array();
$erroCpf = isset($errors['cpf']) ? (string) $errors['cpf'] : '';
$primeiroNome = trim((string) strtok(isset($usuarioNome) ? (string) $usuarioNome : '', ' '));
?>
<div class="auth-cab">
  <h1 class="t2">Complete seu cadastro</h1>
  <p class="lead"><?= $primeiroNome !== '' ? 'Boas-vindas, ' . Helpers::e(mb_convert_case($primeiroNome, MB_CASE_TITLE, 'UTF-8')) . '! ' : '' ?>Informe seu CPF para podermos emitir seus certificados. Leva só um instante.</p>
</div>

<form class="auth-form" method="post" action="/conta/completar" novalidate>
  <div class="campo<?= $erroCpf !== '' ? ' erro' : '' ?>">
    <label for="completar-cpf">CPF</label>
    <input type="text" id="completar-cpf" name="cpf" value="<?= Helpers::e(isset($old['cpf']) ? (string) $old['cpf'] : '') ?>"
           placeholder="000.000.000-00" maxlength="14" inputmode="numeric" data-mask-cpf autofocus
           aria-describedby="<?= $erroCpf !== '' ? 'completar-cpf-erro' : 'completar-cpf-ajuda' ?>"<?= $erroCpf !== '' ? ' aria-invalid="true"' : '' ?>>
    <?php if ($erroCpf !== ''): ?>
    <p class="erro-msg" id="completar-cpf-erro" role="alert"><?= Helpers::e($erroCpf) ?></p>
    <?php else: ?>
    <p class="ajuda" id="completar-cpf-ajuda">Ele aparece no certificado e na validação pública. Depois de salvo, só o atendimento altera.</p>
    <?php endif; ?>
  </div>

  <button type="submit" class="btn btn-bloco">Salvar e continuar <?= caderno_icone('seta-dir') ?></button>
</form>

<form class="auth-form" method="post" action="/conta/completar">
  <input type="hidden" name="acao" value="depois">
  <p class="auth-esqueci" style="text-align:center"><button type="submit" class="link">Fazer isso depois</button></p>
</form>
