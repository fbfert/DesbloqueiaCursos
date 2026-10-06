<?php
/**
 * Home do tema caderno. Recebe as mesmas variáveis da home V2
 * (V2\HomeController): featuredCourses, topCourses, categories, heroStats,
 * heroTitulo, heroSubtitulo, success e as de navegação (do layout).
 *
 * Ordem: abertura com a trilha → estante de categorias → até 6 em destaque →
 * o que você leva → mais procurados → chamada final. Seção sem dados não sai.
 */

use App\Core\Helpers;

$featuredCourses = isset($featuredCourses) && is_array($featuredCourses) ? array_slice($featuredCourses, 0, 6) : array();
$topCourses = isset($topCourses) && is_array($topCourses) ? $topCourses : array();
$categories = isset($categories) && is_array($categories) ? $categories : array();
$heroStats = isset($heroStats) && is_array($heroStats) ? $heroStats : null;
$heroTitulo = isset($heroTitulo) ? (string) $heroTitulo : '';
$heroSubtitulo = isset($heroSubtitulo) ? trim((string) $heroSubtitulo) : '';

// Números da abertura: a mesma regra de apresentação da home V2 (alunos e
// certificados exibem o total real + 750; cursos, o valor real).
$homeNumeros = array();
if ($heroStats && (isset($heroStats['alunos']) || isset($heroStats['cursos']) || isset($heroStats['certificados']))) {
    if (!empty($heroStats['alunos'])) {
        $homeNumeros[] = array('n' => max(0, (int) $heroStats['alunos']) + 750, 'rotulo' => 'alunos', 'mais' => true);
    }
    if (!empty($heroStats['cursos'])) {
        $homeNumeros[] = array('n' => (int) $heroStats['cursos'], 'rotulo' => 'cursos', 'mais' => false);
    }
    if (!empty($heroStats['certificados'])) {
        $homeNumeros[] = array('n' => max(0, (int) $heroStats['certificados']) + 750, 'rotulo' => 'certificados emitidos', 'mais' => false);
    }
} else {
    if (!empty($featuredCourses)) {
        $homeNumeros[] = array('n' => count($featuredCourses), 'rotulo' => 'destaques', 'mais' => false);
    }
    if (!empty($categories)) {
        $homeNumeros[] = array('n' => count($categories), 'rotulo' => 'categorias', 'mais' => false);
    }
    if (!empty($topCourses)) {
        $homeNumeros[] = array('n' => count($topCourses), 'rotulo' => 'mais procurados', 'mais' => false);
    }
}

// Marca-texto em "de verdade" quando o título tem a expressão.
$homeTituloHtml = Helpers::e($heroTitulo);
$homeTituloHtml = preg_replace('/de verdade/u', '<span class="marcado">de verdade</span>', $homeTituloHtml, 1);

// Ranking: barra proporcional ao maior número de alunos.
$homeMaxAlunos = 0;
foreach ($topCourses as $topCurso) {
    $homeMaxAlunos = max($homeMaxAlunos, isset($topCurso['alunos']) ? (int) $topCurso['alunos'] : 0);
}

// Frase do lado da estante (desktop) a partir dos nomes reais.
$homeNomesCategorias = array();
foreach (array_slice($categories, 0, 5) as $cat) {
    if (!empty($cat['nome'])) {
        $homeNomesCategorias[] = (string) $cat['nome'];
    }
}
$homeFraseEstante = '';
if (!empty($homeNomesCategorias)) {
    $ultimo = array_pop($homeNomesCategorias);
    $homeFraseEstante = (empty($homeNomesCategorias) ? $ultimo : implode(', ', $homeNomesCategorias) . ' e ' . $ultimo)
        . ': cada área com cursos práticos e certificado.';
}

$homeContorno = '<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg>';
?>
<?php if (!empty($success)): ?>
<div class="postit ok largo home-aviso" role="status"><?= Helpers::e((string) $success) ?></div>
<?php endif; ?>

<section class="hero" aria-labelledby="home-titulo">
  <div class="hero-texto">
    <h1 class="t1" id="home-titulo"><?= $homeTituloHtml ?></h1>
    <?php if ($heroSubtitulo !== ''): ?>
    <p class="lead"><?= Helpers::e($heroSubtitulo) ?></p>
    <?php elseif ($loggedIn): ?>
    <p class="lead">Seus cursos, aulas e certificados ficam na sua área.</p>
    <?php endif; ?>
    <div class="hero-acoes">
      <a class="btn" href="<?= Helpers::e($catalogoHref) ?>">Escolher meu curso</a>
      <?php if ($loggedIn): ?>
      <a class="btn-sec" href="<?= Helpers::e($areaHref) ?>">Ir para minha área<?= $homeContorno ?></a>
      <?php else: ?>
      <span class="mao" aria-hidden="true"><span class="seta-lado">←</span><span class="seta-cima">↑</span> comece por aqui</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($homeNumeros)): ?>
    <dl class="numeros">
      <?php foreach ($homeNumeros as $numero): ?>
      <div><dt class="vh"><?= Helpers::e($numero['rotulo']) ?></dt><dd><b><span data-n="<?= (int) $numero['n'] ?>"><?= number_format((int) $numero['n'], 0, ',', '.') ?></span><?= $numero['mais'] ? '+' : '' ?></b><small aria-hidden="true"><?= Helpers::e($numero['rotulo']) ?></small></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php endif; ?>
  </div>
  <?php require BASE_PATH . '/resources/views/caderno/partials/trilha-hero.php'; ?>
</section>

<?php if (!empty($categories)): ?>
<section class="sec" aria-labelledby="home-estante">
  <div class="sec-cab"><div><h2 class="t2 rv" id="home-estante">Escolha o seu caderno.</h2><p class="rv">Cada área é um caderno na estante. Puxe um para ver os cursos dele.</p></div></div>
  <div class="estante-rolo">
    <ul class="estante" data-cena="estante">
      <?php foreach ($categories as $indice => $categoria): ?>
      <?php require BASE_PATH . '/resources/views/caderno/partials/lombada.php'; ?>
      <?php endforeach; ?>
    </ul>
    <div class="estante-lado">
      <p class="mao" aria-hidden="true">← puxe um caderno</p>
      <?php if ($homeFraseEstante !== ''): ?>
      <p><?= Helpers::e($homeFraseEstante) ?></p>
      <?php endif; ?>
      <a class="link" href="<?= Helpers::e($categoriesHref) ?>">Ver todas as categorias</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredCourses)): ?>
<section class="sec" aria-labelledby="home-destaque">
  <div class="sec-cab"><h2 class="t2 rv" id="home-destaque">Em destaque.</h2><a class="btn-sec rv" href="<?= Helpers::e($catalogoHref) ?>">Ver todos os cursos<?= $homeContorno ?></a></div>
  <ul class="grade">
    <?php foreach ($featuredCourses as $curso): ?>
    <li class="rv"><?php require BASE_PATH . '/resources/views/caderno/partials/foto-curso.php'; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="sec" aria-labelledby="home-provas">
  <h2 class="t2 rv" id="home-provas">O que você leva de cada curso.</h2>
  <div class="provas">
    <div class="prova rv">
      <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false" fill="none" stroke="#1F3FA8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15c14-1.2 30-.8 44 .3 1 9 .8 19-.2 28-14 .9-30 1-43.6-.2-.9-9-1-19-.2-28.1"/><path d="M27 24.5l12 6.4-12 6.6z" fill="#FFE9D6"/><path d="M22 52c7-.6 13-.5 20 .2M32 44v8"/></svg>
      <h3>Aula ao vivo ou no seu horário</h3>
      <p>Turmas on-line com professor em tempo real, ou cursos sob demanda para assistir quando der.</p>
    </div>
    <div class="prova rv">
      <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false" fill="none" stroke="#1F3FA8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 8.5c11-.6 23-.4 34 .3.8 15.7.7 31.5-.2 47.2-11.3.6-22.6.5-33.8-.2-.8-15.8-.8-31.5 0-47.3"/><path d="M22 21l3 3 6-7M22 33l3 3 6-7M22 45l3 3 6-7"/><path d="M36 21.5c3.5-.2 7-.2 8 0M36 33.5c3.5-.2 7 0 8 .1M36 45.5c3-.2 6-.1 8 0"/></svg>
      <h3>Simulado no formato oficial</h3>
      <p>Na PND e na OAB você treina com questões no mesmo formato da prova, com mais de uma tentativa.</p>
    </div>
    <div class="prova rv">
      <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false" fill="none" stroke="#FF6A00" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="32" cy="27" r="16.5"/><circle cx="32" cy="27" r="10.5" stroke-dasharray="3 3"/><path d="M23 41l-4 15 7-4 4 7 3-13M41 41l4 15-7-4-4 7-3-13"/></svg>
      <h3>Certificado com validação pública</h3>
      <p>Código e QR Code que qualquer pessoa confere no site, para usar em progressão, estágio ou currículo.</p>
    </div>
  </div>
</section>

<?php if (!empty($topCourses) && $homeMaxAlunos > 0): ?>
<section class="sec" aria-labelledby="home-ranking">
  <h2 class="t2 rv" id="home-ranking">Os mais procurados.</h2>
  <ol class="ranking" data-cena="ranking">
    <?php foreach ($topCourses as $posicao => $topCurso): ?>
    <?php
    $topId = isset($topCurso['id']) ? (int) $topCurso['id'] : 0;
    $topUrl = $topId > 0 ? '/v2/curso/?curso_id=' . $topId : (isset($topCurso['url']) ? (string) $topCurso['url'] : '/v2/catalogo/');
    $topAlunos = isset($topCurso['alunos']) ? (int) $topCurso['alunos'] : 0;
    $topLargura = max(4, (int) round($topAlunos / $homeMaxAlunos * 100));
    ?>
    <li class="rv"><span class="n" aria-hidden="true"><?= (int) $posicao + 1 ?></span><div><a href="<?= Helpers::e($topUrl) ?>"><?= Helpers::e(isset($topCurso['titulo']) ? (string) $topCurso['titulo'] : '') ?></a><span class="barra" style="--w:<?= $topLargura ?>%" aria-hidden="true"></span></div><span class="qt"><?= number_format($topAlunos, 0, ',', '.') ?> <?= $topAlunos === 1 ? 'aluno' : 'alunos' ?></span></li>
    <?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>

<section class="chamada" aria-labelledby="home-chamada">
  <h2 class="t2 rv" id="home-chamada">Pronto para virar a página?</h2>
  <p class="lead rv">Escolha um curso, faça a inscrição em poucos passos e pague por PIX.</p>
  <div class="acoes rv">
    <a class="btn" href="<?= Helpers::e($catalogoHref) ?>">Explorar cursos</a>
    <?php if ($loggedIn): ?>
    <a class="btn-sec" href="<?= Helpers::e($areaHref) ?>">Ir para minha área<?= $homeContorno ?></a>
    <?php else: ?>
    <a class="btn-sec" href="<?= Helpers::e($registerHref) ?>">Criar conta grátis<?= $homeContorno ?></a>
    <?php endif; ?>
  </div>
</section>
