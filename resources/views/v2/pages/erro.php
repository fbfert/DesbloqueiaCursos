<?php
use App\Core\Helpers;
use App\Support\V2Nav;

$erroTitulo = isset($erroTitulo) ? (string) $erroTitulo : 'Página não encontrada';
$erroMensagem = isset($erroMensagem) ? (string) $erroMensagem : 'O conteúdo que você procura não está disponível.';
$erroIcone = isset($erroIcone) ? (string) $erroIcone : 'ti-error-404';
$loggedIn = !empty($loggedIn);
$areaHref = isset($areaHref) ? (string) $areaHref : V2Nav::ALUNO;
$catalogoHref = V2Nav::CATALOGO;
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container" style="padding-top:40px;padding-bottom:48px;">
  <div class="v2-empty" role="alert" aria-live="polite" style="max-width:560px;margin:0 auto;text-align:center;">
    <i class="ti <?php echo Helpers::e($erroIcone); ?>" aria-hidden="true" style="font-size:44px;"></i>
    <p><strong><?php echo Helpers::e($erroTitulo); ?></strong></p>
    <p class="v2-muted"><?php echo Helpers::e($erroMensagem); ?></p>
    <div class="v2-quiz-actions" style="justify-content:center;margin-top:14px;">
      <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e($catalogoHref); ?>"><i class="ti ti-compass"></i> Ver catálogo</a>
      <?php if ($loggedIn): ?>
        <a class="v2-btn v2-btn-ghost" href="<?php echo Helpers::e($areaHref); ?>"><i class="ti ti-user-circle"></i> Minha área</a>
      <?php else: ?>
        <a class="v2-btn v2-btn-ghost" href="<?php echo Helpers::e(V2Nav::HOME); ?>"><i class="ti ti-home"></i> Início</a>
      <?php endif; ?>
    </div>
  </div>
</section>
