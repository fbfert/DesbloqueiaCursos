<?php use App\Core\Helpers; ?>
<?php
// Variáveis disponibilizadas pelo HomeController
$cursos         = isset($cursos) && is_array($cursos) ? $cursos : array();
$categoriasDestaque = isset($categoriasDestaque) && is_array($categoriasDestaque) ? $categoriasDestaque : array();
$topCursos      = isset($topCursos) && is_array($topCursos) ? $topCursos : array();
$loggedIn       = !empty($loggedIn);

$dcCatCores = array(
    '#FF6A00', '#E100FF', '#4B008E', '#00E6D2', '#FF4D6D',
    '#0EA5E9', '#7C3AED', '#059669', '#DC2626', '#D97706',
);

$dcThumbCor = function ($idx) use ($dcCatCores) {
    return $dcCatCores[$idx % count($dcCatCores)];
};

$dcPreco = function ($curso) {
    $v = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : (float) ($curso['valor'] ?? 0);
    return $v <= 0 ? 'Grátis' : 'R$ ' . number_format($v, 2, ',', '.');
};

$dcIcone = function ($cat) {
    $emojis = array('📚', '💻', '🎨', '🧠', '⚡', '🏅', '🌱', '🔧', '📊', '🎯');
    return $emojis[crc32((string) ($cat['nome'] ?? '')) % count($emojis)];
};
?>

<!-- ──────────── TOPBAR ──────────── -->
<div class="dc-topbar">
    <a href="/" class="dc-topbar__brand" aria-label="Página inicial">
        <?php if (!empty($institucional['logo_caminho'])): ?>
            <img src="<?php echo Helpers::e((string) $institucional['logo_caminho']); ?>" alt="Logo" class="dc-topbar__logo">
        <?php else: ?>
            <span class="dc-topbar__logo-fallback">Desbloqueia</span>
        <?php endif; ?>
    </a>

    <div class="dc-topbar__actions">
        <button type="button" class="dc-topbar__btn" data-dc-search-toggle aria-label="Buscar" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>

        <?php if ($loggedIn): ?>
            <a href="/meus-cursos" class="dc-topbar__btn" aria-label="Meus cursos">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
        <?php else: ?>
            <a href="/login" class="dc-btn dc-btn-primary" style="width:auto;padding:8px 16px;font-size:13px;">Entrar</a>
        <?php endif; ?>
    </div>
</div>

<!-- Busca expandível -->
<div data-dc-search-box aria-hidden="true" style="display:none;padding:10px 16px;background:#fff;border-bottom:1px solid var(--dc-border);">
    <form action="/cursos" method="get" class="dc-search-bar" role="search">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="search" name="busca" placeholder="Buscar cursos, eventos..." autocomplete="off" aria-label="Buscar">
        <button type="button" data-dc-search-close style="background:none;border:none;cursor:pointer;padding:4px;color:var(--dc-muted);">✕</button>
    </form>
</div>

<!-- HERO -->
<section class="dc-hero">
    <div class="dc-hero-tag">⚡ Sua próxima fase começa aqui.</div>
    <h1 class="dc-hero-title">
        Quando aprende de verdade, <em>desbloqueia.</em>
    </h1>
    <p class="dc-hero-sub">
        Cursos com certificado, quizzes e acompanhamento. Online e presencial.
    </p>
    <a href="/cursos" class="dc-btn dc-btn-primary dc-hero-cta">
        Explorar cursos
    </a>
    <?php if (!empty($credibilidade)): ?>
    <div class="dc-stats">
        <div class="dc-stat">
            <div class="dc-stat-n"><?= (int) $credibilidade['alunos'] ?>+</div>
            <div class="dc-stat-l">alunos</div>
        </div>
        <div class="dc-stat">
            <div class="dc-stat-n"><?= (int) $credibilidade['cursos'] ?></div>
            <div class="dc-stat-l">cursos</div>
        </div>
        <div class="dc-stat">
            <div class="dc-stat-n">100%</div>
            <div class="dc-stat-l">certificado</div>
        </div>
        <div class="dc-stat">
            <div class="dc-stat-n">4,9★</div>
            <div class="dc-stat-l">avaliação</div>
        </div>
    </div>
    <?php endif; ?>
</section>

<!-- CHIPS DE CATEGORIA -->
<?php if (!empty($categoriasDestaque)): ?>
<div class="dc-cats-scroll">
    <a href="/cursos" class="dc-chip dc-chip--active">Todos</a>
    <?php foreach ($categoriasDestaque as $cat): ?>
        <a href="/cursos?categoria=<?= (int) ($cat['id'] ?? 0) ?>" class="dc-chip">
            <?= Helpers::e((string) ($cat['nome'] ?? '')) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- EM DESTAQUE -->
<?php if (!empty($cursos)): ?>
<div class="dc-container">
    <div class="dc-section">
        <div class="dc-section-header">
            <h2 class="dc-section-title">Em destaque</h2>
            <a href="/cursos" class="dc-section-link">Ver todos →</a>
        </div>
        <div class="dc-course-list dc-fadein">
            <?php foreach (array_slice($cursos, 0, 6) as $i => $curso): ?>
                <?php
                    $nomeCat = $curso['categoria_nome'] ?? (isset($curso['categoria']['nome']) ? $curso['categoria']['nome'] : '');
                    $emojis  = array('📚', '💻', '🎨', '🧠', '⚡', '🏅', '🌱', '🔧', '📊', '🎯');
                    $icon    = $emojis[crc32($nomeCat) % count($emojis)];
                    $preco   = $dcPreco($curso);
                ?>
                <a href="/cursos/detalhe?curso_id=<?= (int) ($curso['id'] ?? 0) ?>" class="dc-card">
                    <div class="dc-card-body">
                        <div class="dc-thumb" style="background:<?= Helpers::e($dcThumbCor($i)) ?>20;">
                            <span style="font-size:1.5rem;" aria-hidden="true"><?= $icon ?></span>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="dc-card-cat"><?= Helpers::e($nomeCat ?: 'Curso') ?></div>
                            <div class="dc-card-title"><?= Helpers::e((string) ($curso['nome'] ?? '')) ?></div>
                            <div class="dc-card-foot">
                                <div class="dc-card-meta">
                                    <?php if (!empty($curso['carga_horaria'])): ?>
                                        <span><?= Helpers::e((string) $curso['carga_horaria']) ?>h</span>
                                    <?php endif; ?>
                                    <span>🏅 Cert.</span>
                                </div>
                                <div class="dc-price">
                                    <?php if ($preco === 'Grátis'): ?>
                                        <span class="dc-price-free">Gratuito</span>
                                    <?php else: ?>
                                        <span class="dc-price-val"><?= Helpers::e($preco) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CATEGORIAS -->
<?php if (!empty($categoriasDestaque)): ?>
<div class="dc-container">
    <div class="dc-section">
        <div class="dc-section-header">
            <h2 class="dc-section-title">Categorias</h2>
            <a href="/cursos" class="dc-section-link">Ver todas →</a>
        </div>
        <div class="dc-cats-grid">
            <?php foreach ($categoriasDestaque as $i => $cat): ?>
                <a href="/cursos?categoria=<?= (int) ($cat['id'] ?? 0) ?>" class="dc-cat-card">
                    <div class="dc-cat-thumb" style="background:<?= Helpers::e($dcThumbCor($i)) ?>20;">
                        <span style="font-size:1.6rem;" aria-hidden="true"><?= $dcIcone($cat) ?></span>
                    </div>
                    <span class="dc-cat-nome"><?= Helpers::e((string) ($cat['nome'] ?? '')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TOP CURSOS -->
<?php if (!empty($topCursos)): ?>
<div style="background:var(--dc-surface);padding:4px 0 20px;">
    <div class="dc-container">
        <div class="dc-section">
            <div class="dc-section-header">
                <h2 class="dc-section-title">🔥 Mais vendidos</h2>
                <a href="/cursos" class="dc-section-link">Ver mais →</a>
            </div>
            <div class="dc-scroll-row">
                <?php foreach ($topCursos as $i => $curso): ?>
                    <?php
                        $n      = $curso['categoria_nome'] ?? (isset($curso['categoria']['nome']) ? $curso['categoria']['nome'] : '');
                        $emojis = array('📚', '💻', '🎨', '🧠', '⚡', '🏅', '🌱', '🔧', '📊', '🎯');
                    ?>
                    <a href="/cursos/detalhe?curso_id=<?= (int) ($curso['id'] ?? 0) ?>" class="dc-card dc-card-compact">
                        <div class="dc-thumb dc-thumb--lg" style="background:<?= Helpers::e($dcThumbCor($i + 3)) ?>20;">
                            <span style="font-size:2rem;" aria-hidden="true"><?= $emojis[crc32($n) % count($emojis)] ?></span>
                        </div>
                        <div class="dc-card-body">
                            <div class="dc-card-title"><?= Helpers::e((string) ($curso['nome'] ?? '')) ?></div>
                            <div class="dc-price-val"><?= Helpers::e($dcPreco($curso)) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CTA FINAL -->
<?php if (!$loggedIn): ?>
<div class="dc-container">
    <div class="dc-section">
        <div style="text-align:center;padding:24px 16px;background:var(--dc-gradient);border-radius:var(--dc-radius);color:#fff;">
            <h2 style="font-family:var(--dc-font-title);font-weight:800;font-size:1.35rem;margin:0 0 10px;">
                Pronto para desbloquear seu potencial?
            </h2>
            <p style="opacity:.9;margin:0 0 20px;font-size:.9rem;">Crie sua conta grátis e comece hoje.</p>
            <a href="/cadastro" class="dc-btn" style="background:#fff;color:var(--dc-laranja);width:auto;">Criar conta grátis</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- BOTTOM NAV -->
<?php require BASE_PATH . '/resources/views/v4-claude/_bottom_nav.php'; ?>
