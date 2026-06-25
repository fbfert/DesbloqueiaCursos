<?php
use App\Core\Helpers;

$chamadaPrincipalCapa = isset($chamadaPrincipalCapa) && is_array($chamadaPrincipalCapa) ? $chamadaPrincipalCapa : array();
$modulosCapaStatus = isset($modulosCapaStatus) && is_array($modulosCapaStatus) ? $modulosCapaStatus : array();
$topCursos = isset($topCursos) && is_array($topCursos) ? $topCursos : array();
$topCursosModulo = isset($topCursosModulo) && is_array($topCursosModulo) ? $topCursosModulo : array();
$topAvaliacoesModulo = isset($topAvaliacoesModulo) && is_array($topAvaliacoesModulo) ? $topAvaliacoesModulo : array();
$depoimentosModulo = isset($depoimentosModulo) && is_array($depoimentosModulo) ? $depoimentosModulo : array();
$depoimentosCapa = isset($depoimentosCapa) && is_array($depoimentosCapa) ? $depoimentosCapa : array();
$categoriasDestaque = isset($categoriasDestaque) && is_array($categoriasDestaque) ? $categoriasDestaque : array();
$postLoginChoiceModal = isset($postLoginChoiceModal) && is_array($postLoginChoiceModal) ? $postLoginChoiceModal : array();
$frontendTemplateRaw = isset($frontend_template) ? (string) $frontend_template : 'v1';
$frontendTemplate = in_array($frontendTemplateRaw, array('v2', 'v3', 'v4-claude'), true) ? $frontendTemplateRaw : 'v1';
$loggedIn = !empty($loggedIn);
$textoModulo = function (array $modulo) {
    if (!empty($modulo['conteudo'])) {
        return (string) $modulo['conteudo'];
    }
    if (!empty($modulo['subtitulo'])) {
        return (string) $modulo['subtitulo'];
    }
    return '';
};
$imagemModulo = function (array $modulo, $classe = 'module-public-image') {
    if (empty($modulo['imagem_caminho'])) {
        return;
    }
    $alt = !empty($modulo['imagem_alt']) ? (string) $modulo['imagem_alt'] : (!empty($modulo['titulo']) ? (string) $modulo['titulo'] : 'Imagem do módulo');
    echo '<div class="' . Helpers::e($classe) . '"><img src="' . Helpers::e((string) $modulo['imagem_caminho']) . '" alt="' . Helpers::e($alt) . '"></div>';
};

// ===== Helpers visuais do template V4 (dc-) =====
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$credibilidade = isset($credibilidade) && is_array($credibilidade) ? $credibilidade : array();
$usuarioNome = isset($usuarioNome) ? (string) $usuarioNome : '';

// Cores de thumb por índice (cicla)
$dcCores = array('#fff4ec', '#f0e8ff', '#d1faf5', '#eaf3de', '#faeeda', '#ffe4ea');
$dcCor = fn(int $i): string => $dcCores[$i % count($dcCores)];

// Cores de ícone por índice
$dcIconCores = array('#FF6A00', '#4B008E', '#007a6a', '#3B6D11', '#854F0B', '#c00030');
$dcIconCor = fn(int $i): string => $dcIconCores[$i % count($dcIconCores)];

// Ícones por categoria (fallback genérico)
$dcIcones = array('ti-chart-bar', 'ti-scale', 'ti-coin', 'ti-bulb', 'ti-speakerphone', 'ti-code');
$dcIcone = fn(int $i): string => $dcIcones[$i % count($dcIcones)];

// Preço (array; valor_efetivo é o valor final já com promoção)
$dcPreco = function (array $curso): string {
    $val = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : (isset($curso['valor']) ? (float) $curso['valor'] : 0.0);
    if ($val <= 0) {
        return 'Grátis';
    }
    return 'R$ ' . number_format($val, 2, ',', '.');
};

// Stats reais do hero (a partir de $credibilidade)
$statAlunos = isset($credibilidade['alunos']) ? (int) $credibilidade['alunos'] : 0;
$statCursos = isset($credibilidade['cursos']) ? (int) $credibilidade['cursos'] : 0;
$statCertificados = isset($credibilidade['certificados']) ? (int) $credibilidade['certificados'] : 0;
?>

<div class="front-page-stack home-page-stack">
    <?php require BASE_PATH . '/resources/views/components/orientacao_novo_usuario.php'; ?>

    <?php if ($loggedIn): ?>
        <?php require BASE_PATH . '/resources/views/partials/public/avisos.php'; ?>
    <?php endif; ?>

<div class="home-public-stack front-section-stack">
<?php if ($frontendTemplate === 'v4-claude'): ?>

<?php if (!empty($success)): ?>
<div class="dc-container">
  <div class="dc-callout dc-callout-success" style="margin-top:16px;">
    <i class="ti ti-circle-check"></i><span><?php echo Helpers::e($success); ?></span>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════ HERO ══════════════ -->
<section class="dc-hero">
  <div class="dc-container">
    <div class="dc-hero-inner">
      <div class="dc-hero-text">
        <div class="dc-hero-tag"><i class="ti ti-bolt"></i> Sua próxima fase começa aqui.</div>
        <h1 class="dc-hero-title">Quando aprende de verdade,<br><em>desbloqueia.</em></h1>
        <p class="dc-hero-sub">Cursos com certificado, quizzes e acompanhamento.<br>Online e presencial.</p>
        <div class="dc-hero-actions">
          <a href="/cursos" class="dc-btn dc-btn-primary"><i class="ti ti-search"></i> Explorar cursos</a>
          <?php if ($loggedIn): ?>
            <a href="/meus-cursos" class="dc-btn dc-btn-ghost">Minha área <i class="ti ti-arrow-right"></i></a>
          <?php else: ?>
            <a href="/cadastro" class="dc-btn dc-btn-ghost">Criar conta <i class="ti ti-arrow-right"></i></a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($statAlunos > 0 || $statCursos > 0 || $statCertificados > 0): ?>
      <div class="dc-stats">
        <?php if ($statAlunos > 0): ?>
        <div class="dc-stat"><div class="dc-stat-n"><?php echo number_format($statAlunos, 0, ',', '.'); ?>+</div><div class="dc-stat-l">alunos</div></div>
        <?php endif; ?>
        <?php if ($statCursos > 0): ?>
        <div class="dc-stat"><div class="dc-stat-n"><?php echo number_format($statCursos, 0, ',', '.'); ?></div><div class="dc-stat-l">cursos</div></div>
        <?php endif; ?>
        <?php if ($statCertificados > 0): ?>
        <div class="dc-stat"><div class="dc-stat-n"><?php echo number_format($statCertificados, 0, ',', '.'); ?></div><div class="dc-stat-l">certificados</div></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══════════════ CHIPS DE CATEGORIA ══════════════ -->
<?php if (!empty($categoriasDestaque)): ?>
<div class="dc-chips-row" role="list" aria-label="Filtrar por categoria">
  <a href="/cursos" class="dc-chip on" role="listitem">Todos</a>
  <?php foreach ($categoriasDestaque as $cat): ?>
    <a href="/categorias/<?php echo Helpers::e($cat['slug'] ?? ''); ?>/cursos" class="dc-chip" role="listitem"><?php echo Helpers::e($cat['nome'] ?? ''); ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════ EM DESTAQUE ══════════════ -->
<?php if (!empty($cursos)): ?>
<section class="dc-section">
  <div class="dc-container">
    <div class="dc-section-header">
      <h2 class="dc-section-title">Em destaque</h2>
      <a href="/cursos" class="dc-section-link">Ver todos <i class="ti ti-arrow-right"></i></a>
    </div>
    <div class="dc-course-list">
      <?php foreach ($cursos as $i => $curso): ?>
      <a href="/cursos/detalhe?curso_id=<?php echo (int) ($curso['id'] ?? 0); ?>" class="dc-card" aria-label="<?php echo Helpers::e($curso['nome'] ?? ''); ?>">
        <div class="dc-thumb" style="background:<?php echo $dcCor((int) $i); ?>;">
          <?php if (!empty($curso['thumbnail'])): ?>
            <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome'] ?? ''); ?>" class="dc-thumb-img">
          <?php else: ?>
            <i class="ti <?php echo $dcIcone((int) $i); ?>" style="color:<?php echo $dcIconCor((int) $i); ?>;"></i>
          <?php endif; ?>
        </div>
        <div class="dc-card-body">
          <?php if (!empty($curso['destaque'])): ?>
            <span class="dc-badge dc-badge-destaque"><i class="ti ti-flame"></i> Destaque</span>
          <?php endif; ?>
          <?php if (!empty($curso['categoria_nome'])): ?>
            <div class="dc-card-cat"><?php echo Helpers::e($curso['categoria_nome']); ?></div>
          <?php endif; ?>
          <div class="dc-card-title"><?php echo Helpers::e($curso['nome'] ?? ''); ?></div>
          <div class="dc-card-foot">
            <div class="dc-card-meta">
              <?php if (!empty($curso['carga_horaria'])): ?>
                <span><i class="ti ti-clock"></i><?php echo (int) $curso['carga_horaria']; ?>h</span>
              <?php endif; ?>
              <?php if (!empty($curso['certificado_previsto'])): ?>
                <span><i class="ti ti-certificate"></i>Cert.</span>
              <?php endif; ?>
            </div>
            <?php $preco = $dcPreco($curso); ?>
            <?php if ($preco === 'Grátis'): ?>
              <span class="dc-price-free">Gratuito</span>
            <?php else: ?>
              <span class="dc-price-val"><?php echo Helpers::e($preco); ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════ CATEGORIAS ══════════════ -->
<?php if (!empty($categoriasDestaque)): ?>
<section class="dc-section dc-section-alt">
  <div class="dc-container">
    <div class="dc-section-header">
      <h2 class="dc-section-title">Categorias</h2>
      <a href="/categorias" class="dc-section-link">Ver todas <i class="ti ti-arrow-right"></i></a>
    </div>
    <div class="dc-cats-grid">
      <?php foreach ($categoriasDestaque as $i => $cat): ?>
      <a href="/categorias/<?php echo Helpers::e($cat['slug'] ?? ''); ?>/cursos" class="dc-cat-card">
        <div class="dc-cat-thumb" style="background:<?php echo $dcCor((int) $i); ?>;">
          <?php if (!empty($cat['thumbnail'])): ?>
            <img src="<?php echo Helpers::e($cat['thumbnail']); ?>" alt="<?php echo Helpers::e($cat['nome'] ?? ''); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
          <?php else: ?>
            <i class="ti <?php echo $dcIcone((int) $i); ?>" style="color:<?php echo $dcIconCor((int) $i); ?>;font-size:26px;"></i>
          <?php endif; ?>
        </div>
        <span class="dc-cat-nome"><?php echo Helpers::e($cat['nome'] ?? ''); ?></span>
        <?php if (!empty($cat['total_cursos'])): $totalCat = (int) $cat['total_cursos']; ?>
          <span class="dc-cat-count"><?php echo $totalCat . ' ' . ($totalCat === 1 ? 'curso' : 'cursos'); ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════ TOP CURSOS (scroll horizontal) ══════════════ -->
<?php if (!empty($topCursos)): ?>
<section class="dc-section">
  <div class="dc-container">
    <div class="dc-section-header"><h2 class="dc-section-title">Top cursos</h2></div>
  </div>
  <div class="dc-scroll-wrap">
    <div class="dc-scroll-row">
      <?php foreach ($topCursos as $i => $curso): ?>
      <a href="/cursos/detalhe?curso_id=<?php echo (int) ($curso['id'] ?? 0); ?>" class="dc-card dc-card-scroll">
        <div class="dc-thumb" style="background:<?php echo $dcCor((int) $i); ?>;">
          <?php if (!empty($curso['thumbnail'])): ?>
            <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="" class="dc-thumb-img">
          <?php else: ?>
            <i class="ti ti-star" style="color:<?php echo $dcIconCor((int) $i); ?>;"></i>
          <?php endif; ?>
        </div>
        <div class="dc-card-body">
          <div class="dc-card-title"><?php echo Helpers::e($curso['nome'] ?? ''); ?></div>
          <div class="dc-price-val"><?php echo Helpers::e($dcPreco($curso)); ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php else: ?>
<?php if ($frontendTemplate === 'v3'): ?>
    <?php require BASE_PATH . '/resources/views/components/hero_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/credibilidade_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/destaques_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/categorias_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/como_funciona_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/diferenciais_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/quem_somos_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/depoimentos_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/faq_home_v3.php'; ?>
    <?php require BASE_PATH . '/resources/views/components/cta_final_home_v3.php'; ?>
<?php endif; ?>
<?php if (!empty($success)): ?>
<section class="status-card front-card front-section">
    <strong>Estado</strong>
    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
</section>
<?php endif; ?>

<section class="status-card front-card home-destaques-card front-section">
    <header class="home-destaques-card__header">
        <h2>Destaques</h2>
    </header>

    <div class="destaques-grid home-destaques-grid front-card-grid">
        <?php if (empty($cursos)): ?>
            <article class="status-card front-card">
                <strong>Sem cursos públicos</strong>
                <span>O catálogo ainda está vazio.</span>
            </article>
        <?php else: ?>
            <?php foreach ($cursos as $curso): ?>
                <article class="course-card course-card--image-only home-destaques-grid__card front-card">
                    <?php if (!empty($curso['thumbnail'])): ?>
                        <div class="course-card__image course-card__image--only">
                            <a class="course-card__image-link" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>" aria-label="Ver detalhes do curso <?php echo Helpers::e($curso['nome']); ?>">
                                <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome']); ?>">
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="course-card__image course-card__image--only">
                            <a class="course-card__image-link" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>" aria-label="Ver detalhes do curso <?php echo Helpers::e($curso['nome']); ?>">
                                <span class="home-top-course-card__placeholder" aria-hidden="true"></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<div class="home-destaques-card__footer">
        <a class="button-link" href="/cursos">Ver todos os cursos</a>
    </div>
</section>

<section class="status-card front-card home-categorias-card front-section">
    <header class="home-destaques-card__header">
        <h2>Categorias</h2>
    </header>

    <div class="destaques-grid home-categorias-grid front-card-grid">
        <?php if (empty($categoriasDestaque)): ?>
            <article class="status-card front-card">
                <strong>Nenhuma categoria pública disponível</strong>
                <span>As categorias ativas com cursos públicos aparecerão aqui.</span>
            </article>
        <?php else: ?>
            <?php foreach ($categoriasDestaque as $categoria): ?>
                <?php $categoriaNome = isset($categoria['nome']) ? (string) $categoria['nome'] : ''; ?>
                <?php $categoriaSlug = isset($categoria['slug']) ? (string) $categoria['slug'] : ''; ?>
                <article class="category-card category-card--image-only home-categorias-grid__card front-card">
                    <a class="category-card__image-link" href="/categorias/<?php echo Helpers::e($categoriaSlug); ?>/cursos" aria-label="Ver cursos da categoria <?php echo Helpers::e($categoriaNome); ?>">
                        <?php if (!empty($categoria['thumbnail'])): ?>
                            <img src="<?php echo Helpers::e($categoria['thumbnail']); ?>" alt="<?php echo Helpers::e($categoriaNome); ?>" loading="lazy">
                        <?php else: ?>
                            <span class="category-card__placeholder" aria-hidden="true"></span>
                        <?php endif; ?>
                    </a>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="home-destaques-card__footer">
        <a class="button-link" href="/categorias">Mais categorias</a>
    </div>
</section>

    <?php if (!empty($topCursosModulo)): ?>
<section class="status-card front-card home-extra-card home-top-cursos-card front-section">
    <header class="home-extra-card__header">
        <div>
            <?php $imagemModulo($topCursosModulo, 'module-public-image module-public-image--section'); ?>
            <?php if (!empty($topCursosModulo['titulo'])): ?>
                <h2><?php echo Helpers::e($topCursosModulo['titulo']); ?></h2>
            <?php endif; ?>
        </div>
    </header>

    <?php if (empty($topCursos)): ?>
        <article class="home-empty-state front-card">
            <strong>Aguardando cursos em destaque</strong>
            <span>Quando houver cursos publicados com turmas abertas, os cinco destaques aparecerão aqui automaticamente.</span>
        </article>
    <?php else: ?>
        <div class="home-top-courses-grid front-card-grid" aria-label="Top 5 cursos em destaque">
            <?php foreach ($topCursos as $cursoTop): ?>
                <article class="home-top-course-card home-top-course-card--image-only front-card">
                    <a
                        class="home-top-course-card__link"
                        href="/cursos/detalhe?curso_id=<?php echo (int) $cursoTop['id']; ?>"
                        aria-label="Ver curso <?php echo Helpers::e($cursoTop['nome']); ?>"
                    >
                        <?php if (!empty($cursoTop['thumbnail'])): ?>
                            <img src="<?php echo Helpers::e($cursoTop['thumbnail']); ?>" alt="<?php echo Helpers::e($cursoTop['nome']); ?>">
                        <?php else: ?>
                            <span class="home-top-course-card__placeholder" aria-hidden="true"></span>
                        <?php endif; ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!$loggedIn && !empty($modulosCapaStatus)): ?>
<section class="status-grid front-card-grid front-section" aria-label="Módulos da capa">
    <?php foreach ($modulosCapaStatus as $moduloCapa): ?>
        <?php $textoStatus = $textoModulo($moduloCapa); ?>
        <article class="status-card front-card">
            <?php $imagemModulo($moduloCapa, 'module-public-image module-public-image--card'); ?>
            <?php if (!empty($moduloCapa['titulo'])): ?>
                <strong><?php echo Helpers::e($moduloCapa['titulo']); ?></strong>
            <?php endif; ?>
            <?php if ($textoStatus !== ''): ?>
                <span><?php echo nl2br(Helpers::e($textoStatus)); ?></span>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($topAvaliacoesModulo)): ?>
<section class="status-card front-card home-extra-card home-avaliacoes-card front-section">
    <header class="home-extra-card__header">
        <div>
            <?php $imagemModulo($topAvaliacoesModulo, 'module-public-image module-public-image--section'); ?>
            <?php if (!empty($topAvaliacoesModulo['titulo'])): ?>
                <h2><?php echo Helpers::e($topAvaliacoesModulo['titulo']); ?></h2>
            <?php endif; ?>
            <?php $textoTopAvaliacoes = $textoModulo($topAvaliacoesModulo); ?>
            <?php if ($textoTopAvaliacoes !== ''): ?>
                <p><?php echo nl2br(Helpers::e($textoTopAvaliacoes)); ?></p>
            <?php endif; ?>
        </div>
    </header>

    <article class="home-empty-state home-empty-state--soft front-card">
        <strong>Módulo preparado para avaliações</strong>
        <span>Assim que o sistema tiver avaliações públicas consolidadas, esta área poderá exibir automaticamente os cinco cursos com melhor nota.</span>
    </article>
</section>
<?php endif; ?>

<?php if (!empty($depoimentosModulo)): ?>
<section class="status-card front-card home-extra-card home-depoimentos-card front-section">
    <header class="home-extra-card__header">
        <div>
            <?php $imagemModulo($depoimentosModulo, 'module-public-image module-public-image--section'); ?>
            <?php if (!empty($depoimentosModulo['titulo'])): ?>
                <h2><?php echo Helpers::e($depoimentosModulo['titulo']); ?></h2>
            <?php endif; ?>
            <?php $textoDepoimentos = $textoModulo($depoimentosModulo); ?>
            <?php if ($textoDepoimentos !== ''): ?>
                <p><?php echo nl2br(Helpers::e($textoDepoimentos)); ?></p>
            <?php endif; ?>
        </div>
    </header>

    <div class="testimonial-slider" aria-label="Depoimentos">
        <?php if (empty($depoimentosCapa)): ?>
            <article class="testimonial-slide testimonial-slide--empty front-card">
                <strong>Depoimentos em preparação</strong>
                <span>Cadastre módulos ativos com posição <code>depoimentos_capa_item</code> para alimentar este carrossel.</span>
            </article>
        <?php else: ?>
            <?php foreach ($depoimentosCapa as $depoimento): ?>
                <?php $textoDepoimento = $textoModulo($depoimento); ?>
                <article class="testimonial-slide front-card">
                    <?php $imagemModulo($depoimento, 'module-public-image module-public-image--testimonial'); ?>
                    <?php if ($textoDepoimento !== ''): ?>
                        <p>“<?php echo nl2br(Helpers::e($textoDepoimento)); ?>”</p>
                    <?php endif; ?>
                    <?php if (!empty($depoimento['titulo'])): ?>
                        <strong><?php echo Helpers::e($depoimento['titulo']); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($depoimento['subtitulo'])): ?>
                        <span><?php echo Helpers::e($depoimento['subtitulo']); ?></span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($frontendTemplate === 'v2'): ?>
<section class="dbc-v2-final-cta front-section">
    <div class="dbc-v2-final-cta__content">
        <strong>Aprenda no seu ritmo, com uma experiência que valoriza cada conquista.</strong>
        <span>Explore o catálogo, encontre uma turma aberta e siga para a sala virtual sem distrações.</span>
    </div>
    <div class="dbc-v2-final-cta__actions">
        <a class="dbc-v2-btn dbc-v2-btn--primary" href="/cursos">Explorar cursos</a>
        <a class="dbc-v2-btn dbc-v2-btn--secondary" href="<?php echo $loggedIn ? '/minha-pagina' : '/login'; ?>"><?php echo $loggedIn ? 'Minha Página' : 'Entrar'; ?></a>
    </div>
</section>
<?php endif; ?>

<?php endif; /* fim da ramificação de template (v4-claude | demais) */ ?>
</div>
</div>
<?php if (!empty($postLoginChoiceModal)): ?>
<div class="post-login-modal" id="post-login-choice-modal" role="dialog" aria-modal="true" aria-labelledby="post-login-choice-modal-title">
    <div class="post-login-modal__backdrop" data-post-login-modal-close></div>
    <div class="post-login-modal__dialog" tabindex="-1">
        <button type="button" class="post-login-modal__close" aria-label="Fechar" data-post-login-modal-close>&times;</button>
        <h2 id="post-login-choice-modal-title">Você deseja ir para Minha Página ou Catálogo de Cursos?</h2>
        <p>Escolha para onde deseja seguir agora.</p>
        <div class="post-login-modal__actions">
            <a class="button-link" href="/minha-pagina">Minha Página</a>
            <a class="button-link button-link--ghost" href="/cursos">Catálogo de Cursos</a>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('post-login-choice-modal');
    if (!modal) {
        return;
    }

    var dialog = modal.querySelector('.post-login-modal__dialog');
    var focusTarget = modal.querySelector('.post-login-modal__actions a');
    var closeButtons = modal.querySelectorAll('[data-post-login-modal-close]');

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.classList.remove('has-post-login-modal');
    }

    for (var i = 0; i < closeButtons.length; i++) {
        closeButtons[i].addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    document.body.classList.add('has-post-login-modal');
    modal.classList.add('is-open');

    if (dialog && dialog.focus) {
        dialog.focus();
    } else if (focusTarget && focusTarget.focus) {
        focusTarget.focus();
    }
})();
</script>
<?php endif; ?>
