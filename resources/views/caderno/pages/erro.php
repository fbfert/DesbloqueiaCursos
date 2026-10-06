<?php
/**
 * Erro do tema caderno (404/403). Mesmas variáveis da V2 (V2ErrorPage):
 * erroTitulo, erroMensagem, erroIcone, loggedIn, areaHref. O status HTTP vem
 * do V2ErrorPage; esta view só desenha a folha arrancada.
 */

use App\Core\Helpers;
use App\Support\V2Nav;

$erroTitulo = isset($erroTitulo) ? (string) $erroTitulo : 'Página não encontrada';
$erroMensagem = isset($erroMensagem) ? (string) $erroMensagem : 'O conteúdo que você procura não está disponível.';
$erroIcone = isset($erroIcone) ? (string) $erroIcone : 'ti-error-404';
$loggedIn = !empty($loggedIn);
$areaHref = isset($areaHref) ? (string) $areaHref : V2Nav::ALUNO;
$catalogoHref = V2Nav::CATALOGO;
// A frase do caderno vale para "não encontrada"; outros erros (ex.: acesso negado) mantêm o título próprio.
$ehNaoEncontrada = $erroIcone === 'ti-error-404';
$h1 = $ehNaoEncontrada ? 'Esta página foi arrancada do caderno.' : $erroTitulo;
?>
<section class="sec erro-pag" aria-labelledby="erro-titulo">
  <div class="erro-sombra">
    <div class="erro-folha" role="alert">
      <h1 class="t1" id="erro-titulo"><?= Helpers::e($h1) ?></h1>
      <?php if ($ehNaoEncontrada): ?>
      <p class="t3"><?= Helpers::e($erroTitulo) ?></p>
      <?php endif; ?>
      <p class="lead"><?= Helpers::e($erroMensagem) ?></p>
      <div class="erro-acoes">
        <a class="btn" href="<?= Helpers::e($catalogoHref) ?>">Ver catálogo</a>
        <?php if ($loggedIn): ?>
        <a class="btn-sec" href="<?= Helpers::e($areaHref) ?>">Minha área<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg></a>
        <?php else: ?>
        <a class="btn-sec" href="<?= Helpers::e(V2Nav::HOME) ?>">Início<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg></a>
        <?php endif; ?>
      </div>
      <p class="mao erro-nota" aria-hidden="true">faltou uma folha aqui</p>
    </div>
  </div>
</section>
