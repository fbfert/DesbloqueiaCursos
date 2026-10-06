<?php
/**
 * Rodapé de papelão: o caderno apoiado na mesa. Mesmos destinos do rodapé V2.
 */

use App\Core\Helpers;
?>
<footer class="rodape">
  <div class="miolo">
    <div>
      <p class="marca">Desbloqueia Cursos</p>
      <p class="lema">Quando aprende de verdade, desbloqueia.</p>
    </div>
    <nav aria-label="Rodapé">
      <a href="<?= Helpers::e($catalogoHref) ?>">Cursos</a>
      <a href="<?= Helpers::e($categoriesHref) ?>">Categorias</a>
      <a href="<?= Helpers::e($quemSomosHref) ?>">Quem somos</a>
      <a href="<?= Helpers::e($comoFuncionaSalaHref) ?>">Como funciona</a>
      <a href="<?= Helpers::e($ondeEstamosHref) ?>">Onde estamos</a>
      <a href="<?= Helpers::e($certificadosHref) ?>">Validar certificado</a>
      <?php if ($loggedIn): ?>
      <a href="<?= Helpers::e($pedidosHref) ?>">Meus pedidos</a>
      <a href="<?= Helpers::e($contaHref) ?>">Minha conta</a>
      <?php else: ?>
      <a href="<?= Helpers::e($registerHref) ?>">Criar conta</a>
      <?php endif; ?>
      <a href="<?= Helpers::e($termosHref) ?>">Termos de uso</a>
      <a href="<?= Helpers::e($privacidadeHref) ?>">Política de privacidade</a>
      <a href="<?= Helpers::e($removaMeHref) ?>">Remova-me</a>
    </nav>
    <p class="legal">2026 Desbloqueia Cursos | CP Educa Cursos LTDA, CNPJ 65.513.089/0001-35 | +55 49 991581411 | desbloqueiacursos@gmail.com</p>
  </div>
</footer>
