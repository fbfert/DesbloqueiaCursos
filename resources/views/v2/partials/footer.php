<?php
use App\Core\Helpers;

$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : '/cursos';
$categoriesHref = isset($categoriesHref) ? (string) $categoriesHref : '/categorias';
$loginHref = isset($loginHref) ? (string) $loginHref : '/login';
$registerHref = isset($registerHref) ? (string) $registerHref : '/cadastro';
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';
?>
<footer class="v2-footer">
  <div class="v2-container">
    <div class="v2-footer-grid">
      <div class="v2-footer-brand">
        <span class="v2-h3" style="color:#fff">Desbloqueia <span style="color:#FF6A00">Cursos</span></span>
        <p>Quando aprende de verdade, desbloqueia. A Home V2 já usa dados reais do catálogo atual e mantém a experiência visual aprovada.</p>
      </div>
      <div class="v2-footer-col">
        <h4>Plataforma</h4>
        <a href="<?php echo Helpers::e($catalogoHref); ?>">Cursos</a>
        <a href="<?php echo Helpers::e($categoriesHref); ?>">Categorias</a>
        <a href="<?php echo Helpers::e($loginHref); ?>">Entrar</a>
        <a href="<?php echo Helpers::e($registerHref); ?>">Cadastrar</a>
      </div>
      <div class="v2-footer-col">
        <h4>Institucional</h4>
        <a href="/como-funciona">Como funciona</a>
        <a href="/sobre">Sobre</a>
        <a href="/contato">Contato</a>
        <a href="/v2/certificados/validar">Validar certificado</a>
      </div>
      <div class="v2-footer-col">
        <h4>Minha área</h4>
        <a href="<?php echo Helpers::e($areaHref); ?>">Acessar área atual</a>
        <a href="/meus-cursos">Meus cursos</a>
        <a href="/minha-conta">Minha conta</a>
      </div>
    </div>
    <div class="v2-footer-bottom">
      <span>© 2026 Desbloqueia Cursos</span>
      <span>Home V2 integrada com dados reais do backend</span>
    </div>
  </div>
</footer>

<?php require BASE_PATH . '/resources/views/v2/partials/bottom-nav.php'; ?>
