<?php
use App\Core\Helpers;

$featuredCourses = isset($featuredCourses) && is_array($featuredCourses) ? $featuredCourses : array();
$topCourses = isset($topCourses) && is_array($topCourses) ? $topCourses : array();
$categories = isset($categories) && is_array($categories) ? $categories : array();
$heroStats = isset($heroStats) && is_array($heroStats) ? $heroStats : null;
$loggedIn = !empty($loggedIn);
$usuarioPrimeiroNome = isset($usuarioPrimeiroNome) ? (string) $usuarioPrimeiroNome : '';
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';
$loginHref = isset($loginHref) ? (string) $loginHref : '/login';
$registerHref = isset($registerHref) ? (string) $registerHref : '/cadastro';
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : '/cursos';
$categoriesHref = isset($categoriesHref) ? (string) $categoriesHref : '/categorias';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$coursePalette = isset($coursePalette) && is_array($coursePalette) ? $coursePalette : array();
$featuredCount = count($featuredCourses);
$categoriesCount = count($categories);
$topCount = count($topCourses);
$heroStatItems = array();

// Apenas apresentação: os cards de alunos e certificados exibem o total real + 750.
// O card de cursos mantém o valor real. Não altera dados no banco.
// Valores nulos/ausentes são tratados como zero antes da soma.
$totalAlunosReal = ($heroStats && isset($heroStats['alunos'])) ? (int) $heroStats['alunos'] : 0;
$totalCertificadosReal = ($heroStats && isset($heroStats['certificados'])) ? (int) $heroStats['certificados'] : 0;
$totalAlunosExibidos = max(0, $totalAlunosReal) + 750;
$totalCertificadosExibidos = max(0, $totalCertificadosReal) + 750;

if ($heroStats && (isset($heroStats['alunos']) || isset($heroStats['cursos']) || isset($heroStats['certificados']))) {
    if (!empty($heroStats['alunos'])) {
        $heroStatItems[] = array('valor' => number_format($totalAlunosExibidos, 0, ',', '.'), 'rotulo' => 'alunos');
    }
    if (!empty($heroStats['cursos'])) {
        $heroStatItems[] = array('valor' => number_format((int) $heroStats['cursos'], 0, ',', '.'), 'rotulo' => 'cursos');
    }
    if (!empty($heroStats['certificados'])) {
        $heroStatItems[] = array('valor' => number_format($totalCertificadosExibidos, 0, ',', '.'), 'rotulo' => 'certificados');
    }
} else {
    if ($featuredCount > 0) {
        $heroStatItems[] = array('valor' => number_format($featuredCount, 0, ',', '.'), 'rotulo' => 'destaques');
    }
    if ($categoriesCount > 0) {
        $heroStatItems[] = array('valor' => number_format($categoriesCount, 0, ',', '.'), 'rotulo' => 'categorias');
    }
    if ($topCount > 0) {
        $heroStatItems[] = array('valor' => number_format($topCount, 0, ',', '.'), 'rotulo' => 'top cursos');
    }
}
?>
<?php if (!empty($success)): ?>
  <div class="v2-container" style="padding-top:16px;">
    <div class="v2-callout v2-callout-success">
      <i class="ti ti-circle-check"></i>
      <span><?php echo Helpers::e((string) $success); ?></span>
    </div>
  </div>
<?php endif; ?>

<section class="v2-hero">
  <div class="v2-container">
    <div class="v2-hero-inner v2-hero-inner--no-visual">
      <div class="v2-hero-text">
        <span class="v2-hero-tag"><i class="ti ti-bolt"></i> Sua próxima fase começa aqui</span>
        <h1 class="v2-h1"><?php echo Helpers::e($heroTitulo); ?></h1>
        <p class="v2-hero-sub"><?php echo Helpers::e($heroSubtitulo); ?></p>

        <?php if ($loggedIn): ?>
          <div class="v2-callout v2-callout-info" style="margin-bottom:16px;">
            <i class="ti ti-user-check"></i>
            <span>Olá, <?php echo Helpers::e($usuarioPrimeiroNome !== '' ? $usuarioPrimeiroNome : 'aluno'); ?>. Acesse sua área atual e continue sem perder o contexto dos seus cursos.</span>
          </div>
        <?php endif; ?>

        <div class="v2-hero-actions">
          <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-btn v2-btn-primary"><i class="ti ti-search"></i> Explorar cursos</a>
          <?php if ($loggedIn): ?>
            <a href="<?php echo Helpers::e($areaHref); ?>" class="v2-btn v2-btn-ghost">Minha área <i class="ti ti-arrow-right"></i></a>
          <?php else: ?>
            <a href="<?php echo Helpers::e($loginHref); ?>" class="v2-btn v2-btn-ghost">Entrar <i class="ti ti-arrow-right"></i></a>
            <a href="<?php echo Helpers::e($registerHref); ?>" class="v2-btn v2-btn-ghost">Criar conta</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($heroStatItems)): ?>
      <div class="v2-stats">
        <?php foreach ($heroStatItems as $stat): ?>
          <div class="v2-stat">
            <div class="v2-stat-n"><?php echo Helpers::e($stat['valor']); ?><?php echo ($stat['rotulo'] === 'alunos' ? '+' : ''); ?></div>
            <div class="v2-stat-l"><?php echo Helpers::e($stat['rotulo']); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="v2-section">
  <div class="v2-container">
    <div class="v2-section-head">
      <h2 class="v2-h2">Em destaque</h2>
      <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-section-link">Ver todos <i class="ti ti-arrow-right"></i></a>
    </div>

    <?php if (!empty($featuredCourses)): ?>
      <div class="v2-grid">
        <?php foreach ($featuredCourses as $index => $curso): ?>
          <?php require BASE_PATH . '/resources/views/v2/partials/course-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="v2-empty">
        <i class="ti ti-search"></i>
        <p>Nenhum curso em destaque disponível no momento.</p>
        <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-btn v2-btn-primary">Explorar catálogo</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="v2-section v2-section-alt">
  <div class="v2-container">
    <div class="v2-section-head">
      <h2 class="v2-h2">Categorias</h2>
      <a href="<?php echo Helpers::e($categoriesHref); ?>" class="v2-section-link">Ver todas <i class="ti ti-arrow-right"></i></a>
    </div>

    <?php if (!empty($categories)): ?>
      <div class="v2-cats">
        <?php foreach ($categories as $index => $categoria): ?>
          <?php
          $theme = $coursePalette[$index % count($coursePalette)];
          $categoriaNome = isset($categoria['nome']) ? (string) $categoria['nome'] : '';
          $categoriaUrl = isset($categoria['url']) ? (string) $categoria['url'] : '/categorias';
          $categoriaThumb = !empty($categoria['thumbnail']) ? (string) $categoria['thumbnail'] : '';
          $totalCursos = isset($categoria['total_cursos']) ? (int) $categoria['total_cursos'] : 0;
          ?>
          <a href="<?php echo Helpers::e($categoriaUrl); ?>" class="v2-cat">
            <span class="v2-cat-ic" style="background:linear-gradient(135deg,<?php echo Helpers::e($theme['g1']); ?>,<?php echo Helpers::e($theme['g2']); ?>);">
              <?php if ($categoriaThumb !== ''): ?>
                <img src="<?php echo Helpers::e($categoriaThumb); ?>" alt="<?php echo Helpers::e($categoriaNome); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:14px;">
              <?php else: ?>
                <i class="ti <?php echo Helpers::e($theme['icon']); ?>" style="color:<?php echo Helpers::e($theme['cor']); ?>;"></i>
              <?php endif; ?>
            </span>
            <span class="v2-cat-nome"><?php echo Helpers::e($categoriaNome); ?></span>
            <span class="v2-cat-q"><?php echo number_format($totalCursos, 0, ',', '.'); ?> <?php echo $totalCursos === 1 ? 'curso' : 'cursos'; ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="v2-empty">
        <i class="ti ti-category"></i>
        <p>Nenhuma categoria disponível no momento.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="v2-section">
  <div class="v2-container">
    <div class="v2-section-head">
      <h2 class="v2-h2">Top cursos</h2>
      <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-section-link">Mais procurados <i class="ti ti-arrow-right"></i></a>
    </div>

    <?php if (!empty($topCourses)): ?>
      <div class="v2-top-courses-list">
        <?php foreach ($topCourses as $index => $curso): ?>
          <?php require BASE_PATH . '/resources/views/v2/partials/course-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="v2-empty">
        <i class="ti ti-star"></i>
        <p>Nenhum curso em evidência encontrado.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="v2-section">
  <div class="v2-container">
    <div class="v2-block v2-center" style="background:linear-gradient(135deg,#fff4ec,#f0e8ff);">
      <h2 class="v2-h2">Pronto para seguir para a próxima etapa?</h2>
      <p class="v2-muted" style="margin:8px 0 16px;">A Home V2 já está conectada ao catálogo real do sistema, sem tocar na home original.</p>
      <div class="v2-hero-actions" style="justify-content:center;">
        <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-btn v2-btn-primary">Explorar cursos</a>
        <?php if ($loggedIn): ?>
          <a href="<?php echo Helpers::e($areaHref); ?>" class="v2-btn v2-btn-ghost">Ir para minha área</a>
        <?php else: ?>
          <a href="<?php echo Helpers::e($registerHref); ?>" class="v2-btn v2-btn-ghost">Criar conta grátis</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
