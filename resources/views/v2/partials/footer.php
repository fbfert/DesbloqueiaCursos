<?php
use App\Core\Helpers;
use App\Support\V2Nav;

// Fase 2.13 — todos os destinos internos V2 (sem vazamento para o V1).
$homeHref = isset($homeHref) ? (string) $homeHref : V2Nav::HOME;
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : V2Nav::CATALOGO;
$categoriesHref = isset($categoriesHref) ? (string) $categoriesHref : V2Nav::CATEGORIAS;
$loginHref = isset($loginHref) ? (string) $loginHref : V2Nav::LOGIN;
$registerHref = isset($registerHref) ? (string) $registerHref : V2Nav::CADASTRO;
$areaHref = isset($areaHref) ? (string) $areaHref : V2Nav::ALUNO;
$certificadosHref = isset($certificadosHref) ? (string) $certificadosHref : V2Nav::CERTIFICADOS;
$sobreHref = isset($sobreHref) ? (string) $sobreHref : V2Nav::SOBRE;
$contatoHref = isset($contatoHref) ? (string) $contatoHref : V2Nav::CONTATO;
$comoFuncionaHref = isset($comoFuncionaHref) ? (string) $comoFuncionaHref : V2Nav::COMO_FUNCIONA;
// Institucionais canônicas (Fase 2.13B) — conteúdo real do backend.
$quemSomosHref = isset($quemSomosHref) ? (string) $quemSomosHref : V2Nav::QUEM_SOMOS;
$comoFuncionaSalaHref = isset($comoFuncionaSalaHref) ? (string) $comoFuncionaSalaHref : V2Nav::COMO_FUNCIONA_SALA;
$ondeEstamosHref = isset($ondeEstamosHref) ? (string) $ondeEstamosHref : V2Nav::ONDE_ESTAMOS;
$termosHref = isset($termosHref) ? (string) $termosHref : V2Nav::TERMOS;
$privacidadeHref = isset($privacidadeHref) ? (string) $privacidadeHref : V2Nav::PRIVACIDADE;
$removaMeHref = isset($removaMeHref) ? (string) $removaMeHref : V2Nav::REMOVA_ME;
$pedidosHref = isset($pedidosHref) ? (string) $pedidosHref : V2Nav::PEDIDOS;
$contaHref = isset($contaHref) ? (string) $contaHref : V2Nav::PERFIL;
?>
<footer class="v2-footer">
  <div class="v2-container">
    <div class="v2-footer-grid">
      <div class="v2-footer-col">
        <h4>Plataforma</h4>
        <a href="<?php echo Helpers::e($catalogoHref); ?>">Cursos</a>
        <a href="<?php echo Helpers::e($categoriesHref); ?>">Categorias</a>
        <a href="<?php echo Helpers::e($loginHref); ?>">Entrar</a>
        <a href="<?php echo Helpers::e($registerHref); ?>">Cadastrar</a>
      </div>
      <div class="v2-footer-col">
        <h4>Institucional</h4>
        <a href="<?php echo Helpers::e($quemSomosHref); ?>">Quem somos</a>
        <a href="<?php echo Helpers::e($ondeEstamosHref); ?>">Onde estamos</a>
        <a href="<?php echo Helpers::e($certificadosHref); ?>">Validar certificado</a>
        <a href="<?php echo Helpers::e($termosHref); ?>">Termos de Uso</a>
        <a href="<?php echo Helpers::e($privacidadeHref); ?>">Política de Privacidade</a>
        <a href="<?php echo Helpers::e($removaMeHref); ?>">Remova-me</a>
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
