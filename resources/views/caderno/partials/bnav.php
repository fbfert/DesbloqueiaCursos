<?php
/**
 * Barra inferior do celular (some a partir de 900 px). Quatro destinos, como
 * no protótipo aprovado: Início, Cursos, Certificado e Entrar/Minha área.
 */

use App\Core\Helpers;

$bnavItens = array(
    array('inicio', $homeHref, 'inicio', 'Início'),
    array('cursos', $catalogoHref, 'cursos', 'Cursos'),
    array('certificado', $certificadosHref, 'certificado', 'Certificado'),
    $loggedIn
        ? array('area', $areaHref, 'usuario', 'Minha área')
        : array('entrar', $loginHref, 'usuario', 'Entrar'),
);
?>
<nav class="bnav" aria-label="Navegação rápida">
  <?php foreach ($bnavItens as $bnavItem): ?>
  <a href="<?= Helpers::e($bnavItem[1]) ?>"<?= $navAtual === $bnavItem[0] ? ' aria-current="page"' : '' ?>><?= caderno_icone($bnavItem[2]) ?><span><?= Helpers::e($bnavItem[3]) ?></span></a>
  <?php endforeach; ?>
</nav>
