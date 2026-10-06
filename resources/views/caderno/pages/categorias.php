<?php
/**
 * Categorias do tema caderno. Recebe as mesmas variáveis da página V2
 * (V2\CategoriasController): categorias (nome, thumbnail, total_cursos, url,
 * descricao), coursePalette, loggedIn, usuarioNome e as de navegação.
 *
 * Estante completa: uma lombada por categoria, com altura proporcional à
 * quantidade de cursos, cada uma levando ao catálogo filtrado. Abaixo, a mesma
 * lista em texto (nome, contagem e descrição, quando houver).
 */

use App\Core\Helpers;

$categorias = isset($categorias) && is_array($categorias) ? $categorias : array();

$catMax = 0;
$catTotalCursos = 0;
foreach ($categorias as $catItem) {
    $catQt = isset($catItem['total_cursos']) ? (int) $catItem['total_cursos'] : 0;
    $catMax = max($catMax, $catQt);
    $catTotalCursos += $catQt;
}
?>
<section class="sec cats-topo" aria-labelledby="cats-titulo">
  <h1 class="t1" id="cats-titulo">Categorias.</h1>
  <p class="lead">Cada área é um caderno na estante. Puxe um para ver os cursos dele.</p>
</section>

<?php if (!empty($categorias)): ?>
<section class="cats-estante" aria-label="Estante de categorias">
  <div class="estante-rolo cheia">
    <ul class="estante grande" data-cena="estante">
      <?php foreach ($categorias as $indice => $categoria): ?>
      <?php
      $alturaLomb = 230 + ($catMax > 0 ? (int) round(((int) $categoria['total_cursos']) / $catMax * 150) : 0);
      require BASE_PATH . '/resources/views/caderno/partials/lombada.php';
      ?>
      <?php endforeach; ?>
    </ul>
  </div>
  <p class="mao cats-nota" aria-hidden="true">← puxe um caderno</p>
</section>

<section class="sec" aria-labelledby="cats-lista-titulo">
  <h2 class="t2" id="cats-lista-titulo">Todas as áreas.</h2>
  <ul class="cats-lista">
    <?php foreach ($categorias as $categoria): ?>
    <?php
    $catNome = isset($categoria['nome']) ? (string) $categoria['nome'] : '';
    $catQt = isset($categoria['total_cursos']) ? (int) $categoria['total_cursos'] : 0;
    $catDesc = isset($categoria['descricao']) ? trim((string) $categoria['descricao']) : '';
    ?>
    <li>
      <a class="cats-nome" href="<?= Helpers::e((string) $categoria['url']) ?>"><?= Helpers::e($catNome) ?></a>
      <span class="cats-qt"><?= number_format($catQt, 0, ',', '.') ?> <?= $catQt === 1 ? 'curso' : 'cursos' ?></span>
      <?php if ($catDesc !== ''): ?>
      <p><?= Helpers::e($catDesc) ?></p>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php else: ?>
<section class="sec vazio">
  <p class="mao" aria-hidden="true">estante vazia por enquanto</p>
  <p class="lead">Nenhuma categoria disponível no momento.</p>
  <a class="btn" href="<?= Helpers::e($catalogoHref) ?>">Ver todos os cursos</a>
</section>
<?php endif; ?>
