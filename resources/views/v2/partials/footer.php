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
        <a href="<?php echo Helpers::e($comoFuncionaSalaHref); ?>">Como funciona a Sala Virtual</a>
        <a href="<?php echo Helpers::e($quemSomosHref); ?>">Quem somos</a>
        <a href="<?php echo Helpers::e($ondeEstamosHref); ?>">Onde estamos</a>
        <a href="<?php echo Helpers::e($certificadosHref); ?>">Validar certificado</a>
      </div>
      <div class="v2-footer-col">
        <h4>Minha área</h4>
        <a href="<?php echo Helpers::e($areaHref); ?>">Acessar minha área</a>
        <a href="<?php echo Helpers::e($pedidosHref); ?>">Meus pedidos</a>
        <a href="<?php echo Helpers::e($contaHref); ?>">Minha conta</a>
      </div>
    </div>
    <div class="v2-footer-bottom">
      <span class="v2-footer-legal-text">2026 Desbloqueia Cursos | CP Educa Cursos LTDA, CNPJ 65.513.089/0001-35 | +55 49 991581411 | desbloqueiacursos@gmail.com</span>
      <nav class="v2-footer-legal" aria-label="Links legais">
        <a href="<?php echo Helpers::e($termosHref); ?>">Termos de uso</a>
        <a href="<?php echo Helpers::e($privacidadeHref); ?>">Política de privacidade</a>
        <a href="<?php echo Helpers::e($removaMeHref); ?>">Remova-me</a>
      </nav>
    </div>
  </div>
</footer>

<?php require BASE_PATH . '/resources/views/v2/partials/bottom-nav.php'; ?>
