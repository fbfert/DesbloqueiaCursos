<?php
/**
 * Redefinir senha no tema caderno. Mesmo formulário da V2
 * (resources/views/v2/pages/recuperar-senha-redefinir.php): POST em
 * $redefinirAction com `token`, `senha`, `senha_confirmacao` e `origem=v2`;
 * _token de CSRF injetado pelo View::render.
 */

use App\Core\Helpers;

require BASE_PATH . '/resources/views/caderno/partials/ver-senha.php';

$errors = isset($errors) && is_array($errors) ? $errors : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$token = isset($token) ? (string) $token : '';
$redefinirAction = isset($redefinirAction) ? (string) $redefinirAction : '/recuperar-senha/redefinir';
$loginHref = $authLinks['login'];

$tokenValue = isset($old['token']) && (string) $old['token'] !== '' ? (string) $old['token'] : $token;
$temErros = !empty($errors);
// Erros de campo ficam junto do campo; os demais (ex.: link inválido ou expirado) no aviso do topo.
$erroSenha = isset($errors['senha']) ? (string) $errors['senha'] : '';
$erroConf = isset($errors['senha_confirmacao']) ? (string) $errors['senha_confirmacao'] : '';
$errosGerais = array();
foreach ($errors as $chave => $msg) {
    if ($chave !== 'senha' && $chave !== 'senha_confirmacao') {
        $errosGerais[] = (string) $msg;
    }
}
?>
<div class="auth-cab">
  <h1 class="t2">Redefinir senha</h1>
  <p class="lead">Escolha uma nova senha para sua conta.</p>
</div>

<?php if ($errosGerais): ?>
<div class="postit erro auth-aviso" id="cad-red-erros" role="alert">
  <?php foreach ($errosGerais as $i => $msg): ?><?= $i > 0 ? '<br>' : '' ?><?= Helpers::e($msg) ?><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="postit ok auth-aviso" role="status" aria-live="polite"><?= Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success) ?></div>
<?php endif; ?>

<form class="auth-form" method="post" action="<?= Helpers::e($redefinirAction) ?>" novalidate>
  <input type="hidden" name="origem" value="v2">
  <input type="hidden" name="token" value="<?= Helpers::e($tokenValue) ?>">

  <div class="campo<?= $erroSenha !== '' ? ' erro' : '' ?>">
    <label for="cad-red-senha">Nova senha <span class="opcional">(mínimo 8 caracteres)</span></label>
    <div class="campo-senha">
      <input type="password" id="cad-red-senha" name="senha" minlength="8" autocomplete="new-password" required autofocus
             <?= $erroSenha !== '' ? 'aria-invalid="true" aria-describedby="e-cad-red-senha"' : ($errosGerais ? 'aria-describedby="cad-red-erros"' : '') ?>>
      <?= caderno_ver_senha('cad-red-senha') ?>
    </div>
    <?php if ($erroSenha !== ''): ?><p class="erro-msg" id="e-cad-red-senha"><?= Helpers::e($erroSenha) ?></p><?php endif; ?>
  </div>

  <div class="campo<?= $erroConf !== '' ? ' erro' : '' ?>">
    <label for="cad-red-senha-conf">Confirmar nova senha</label>
    <div class="campo-senha">
      <input type="password" id="cad-red-senha-conf" name="senha_confirmacao" minlength="8" autocomplete="new-password" required
             <?= $erroConf !== '' ? 'aria-invalid="true" aria-describedby="e-cad-red-conf"' : '' ?>>
      <?= caderno_ver_senha('cad-red-senha-conf') ?>
    </div>
    <?php if ($erroConf !== ''): ?><p class="erro-msg" id="e-cad-red-conf"><?= Helpers::e($erroConf) ?></p><?php endif; ?>
  </div>

  <button type="submit" class="btn btn-bloco">Salvar nova senha <?= caderno_icone('check') ?></button>
</form>

<p class="auth-pe"><a class="link auth-voltar" href="<?= Helpers::e($loginHref) ?>"><?= caderno_icone('seta-esq') ?>Voltar ao login</a></p>
