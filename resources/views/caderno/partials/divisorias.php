<?php
/**
 * Divisórias de fichário: filtro de categoria do catálogo.
 *
 * Espera:
 *   $chips  itens de `chips` do CatalogoController V2 (nome, slug, url, ativo
 *           e, opcionalmente, total);
 *   $divMostrarTotais  opcional (padrão true): false esconde os contadores,
 *           que são totais da categoria e não refletem busca nem filtros.
 *
 * Cada divisória é um link GET (funciona sem JS); a ativa leva
 * aria-current="page". No celular as abas rolam na horizontal sobre a base
 * violeta; a partir de 1100 px viram divisórias verticais na borda direita da
 * folha. Cores com texto branco AA (as mesmas famílias das lombadas da home);
 * "Todos" é sempre violeta.
 */

use App\Core\Helpers;

$divChips = isset($chips) && is_array($chips) ? $chips : array();
$divMostrarTotais = isset($divMostrarTotais) ? (bool) $divMostrarTotais : true;
if (empty($divChips)) {
    return;
}

$divCores = array('#2F5D50', '#A33B2B', '#1F3FA8', '#A8641A', '#3B3550', '#6B4E2E');
$divSomaTotal = 0;
$divTotaisConhecidos = true;
foreach ($divChips as $divChip) {
    if (isset($divChip['slug']) && (string) $divChip['slug'] !== '') {
        if (isset($divChip['total']) && $divChip['total'] !== null) {
            $divSomaTotal += (int) $divChip['total'];
        } else {
            $divTotaisConhecidos = false;
        }
    }
}
$divPos = 0;
?>
<nav class="abas" aria-label="Categorias" data-abas>
  <?php foreach ($divChips as $divChip):
      $divSlug = isset($divChip['slug']) ? (string) $divChip['slug'] : '';
      $divNome = isset($divChip['nome']) ? (string) $divChip['nome'] : '';
      $divAtiva = !empty($divChip['ativo']);
      if ($divSlug === '') {
          $divCor = '#22104A';
          $divTotal = $divTotaisConhecidos ? $divSomaTotal : null;
      } else {
          $divCor = $divCores[$divPos % count($divCores)];
          $divPos++;
          $divTotal = isset($divChip['total']) && $divChip['total'] !== null ? (int) $divChip['total'] : null;
      }
  ?>
  <a class="aba" href="<?= Helpers::e((string) $divChip['url']) ?>" style="--cor:<?= Helpers::e($divCor) ?>" data-cat="<?= Helpers::e($divSlug) ?>" data-nome="<?= Helpers::e($divNome) ?>"<?= $divAtiva ? ' aria-current="page"' : '' ?>><?= Helpers::e($divNome) ?><?php if ($divMostrarTotais && $divTotal !== null): ?><span class="qt"><span class="vh">, </span><?= number_format($divTotal, 0, ',', '.') ?><span class="vh"> <?= $divTotal === 1 ? 'curso' : 'cursos' ?></span></span><?php endif; ?></a>
  <?php endforeach; ?>
</nav>
<div class="abas-base" aria-hidden="true"></div>
