<?php
/**
 * Sair da conta no tema caderno (mudança tema-caderno-unico). Confirmar envia
 * POST para /v2/logout (o _token é injetado pelo View::render); cancelar volta à
 * área do perfil ($cancelUrl).
 */

use App\Core\Helpers;
use App\Support\V2Nav;

$cancelUrl = isset($cancelUrl) && (string) $cancelUrl !== '' ? (string) $cancelUrl : V2Nav::ALUNO;
$primeiro = isset($usuarioPrimeiroNome) ? (string) $usuarioPrimeiroNome : '';
?>
<div class="auth-cab">
  <h1 class="t2">Sair da conta</h1>
  <p class="lead"><?= $primeiro !== '' ? Helpers::e($primeiro) . ', você' : 'Você' ?> está prestes a sair. Para voltar, é só entrar de novo com seu e-mail, CPF ou conta Google.</p>
</div>

<form class="auth-form" method="post" action="/v2/logout">
  <button type="submit" class="btn btn-bloco">Sair da conta <?= caderno_icone('seta-dir') ?></button>
</form>

<p class="auth-pe"><a class="link" href="<?= Helpers::e($cancelUrl) ?>">Cancelar e continuar conectado</a></p>
