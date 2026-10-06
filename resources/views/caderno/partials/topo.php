<?php
/**
 * Cabeçalho do tema caderno. Usa as variáveis de navegação preparadas pelo
 * layout (V2Nav::links) e $navAtual ('inicio', 'cursos', 'categorias',
 * 'certificado' ou ''). No celular, o menu é um <details>: abre e fecha sem JS.
 */

use App\Core\Csrf;
use App\Core\Helpers;

$navItens = array(
    'inicio' => array($homeHref, 'Início'),
    'cursos' => array($catalogoHref, 'Cursos'),
    'categorias' => array($categoriesHref, 'Categorias'),
    'certificado' => array($certificadosHref, 'Validar certificado'),
);
$navAtual = isset($navAtual) ? (string) $navAtual : '';
?>
<header class="topo">
  <div class="miolo">
    <a class="logo" href="<?= Helpers::e($homeHref) ?>" aria-label="Desbloqueia Cursos, página inicial">
      <svg width="22" height="24" viewBox="0 0 34 36" fill="none" stroke="#FF6A00" stroke-width="3" stroke-linecap="round" aria-hidden="true"><rect x="3" y="14" width="26" height="19" rx="4" fill="#FFF4EC"/><path d="M9 14v-4a7 7 0 0 1 14 0"/><circle cx="16" cy="23" r="2.6" fill="#FF6A00" stroke="none"/></svg>
      <span>Desbloqueia Cursos</span>
    </a>
    <nav class="nav" aria-label="Principal">
      <?php foreach ($navItens as $chave => $item): ?>
      <a href="<?= Helpers::e($item[0]) ?>"<?= $navAtual === $chave ? ' aria-current="page"' : '' ?>><?= Helpers::e($item[1]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="topo-acoes">
      <?php if ($loggedIn): ?>
      <a class="entrar" href="<?= Helpers::e($areaHref) ?>"><?= caderno_icone('usuario') ?><span><?= Helpers::e($usuarioPrimeiroNome !== '' ? $usuarioPrimeiroNome : 'Minha área') ?></span></a>
      <form class="sair" method="post" action="/v2/logout" data-native-submit>
        <?= Csrf::field() ?>
        <button type="submit">Sair</button>
      </form>
      <?php else: ?>
      <a class="entrar" href="<?= Helpers::e($loginHref) ?>">Entrar</a>
      <?php endif; ?>
      <details class="menu">
        <summary aria-label="Menu"><?= caderno_icone('menu', 'i-menu') ?><?= caderno_icone('fechar', 'i-fechar') ?></summary>
        <div class="menu-painel">
          <nav aria-label="Menu">
            <?php foreach ($navItens as $chave => $item): ?>
            <a href="<?= Helpers::e($item[0]) ?>"<?= $navAtual === $chave ? ' aria-current="page"' : '' ?>><?= Helpers::e($item[1]) ?></a>
            <?php endforeach; ?>
            <a href="<?= Helpers::e($sobreHref) ?>">Quem somos</a>
            <?php if ($loggedIn): ?>
            <a href="<?= Helpers::e($areaHref) ?>">Minha área</a>
            <a href="<?= Helpers::e($pedidosHref) ?>">Meus pedidos</a>
            <?php else: ?>
            <a href="<?= Helpers::e($loginHref) ?>">Entrar</a>
            <a href="<?= Helpers::e($registerHref) ?>">Criar conta</a>
            <?php endif; ?>
          </nav>
          <?php if ($loggedIn): ?>
          <form method="post" action="/v2/logout" data-native-submit>
            <?= Csrf::field() ?>
            <button type="submit">Sair da conta</button>
          </form>
          <?php endif; ?>
        </div>
      </details>
    </div>
  </div>
</header>
