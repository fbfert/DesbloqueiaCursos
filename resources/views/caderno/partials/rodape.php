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
      <?php foreach (array($quemSomosHref => 'Quem somos', $comoFuncionaSalaHref => 'Como funciona', $ondeEstamosHref => 'Onde estamos') as $rodHref => $rodRotulo): ?>
      <?php if (!isset($institucionaisPublicadas) || in_array($rodHref, $institucionaisPublicadas, true)): ?>
      <a href="<?= Helpers::e($rodHref) ?>"><?= Helpers::e($rodRotulo) ?></a>
      <?php endif; ?>
      <?php endforeach; ?>
      <a href="<?= Helpers::e($certificadosHref) ?>">Validar certificado</a>
      <?php if ($loggedIn): ?>
      <a href="<?= Helpers::e($pedidosHref) ?>">Meus pedidos</a>
      <a href="<?= Helpers::e($contaHref) ?>">Minha conta</a>
      <?php else: ?>
      <a href="<?= Helpers::e($registerHref) ?>">Criar conta</a>
      <?php endif; ?>
      <?php foreach (array($termosHref => 'Termos de uso', $privacidadeHref => 'Política de privacidade', $removaMeHref => 'Remova-me') as $rodHref => $rodRotulo): ?>
      <?php if (!isset($institucionaisPublicadas) || in_array($rodHref, $institucionaisPublicadas, true)): ?>
      <a href="<?= Helpers::e($rodHref) ?>"><?= Helpers::e($rodRotulo) ?></a>
      <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <p class="legal">2026 Desbloqueia Cursos | CP Educa Cursos LTDA, CNPJ 65.513.089/0001-35 | +55 49 991581411 | desbloqueiacursos@gmail.com</p>
  </div>
</footer>
