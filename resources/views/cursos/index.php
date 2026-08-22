<?php use App\Core\Helpers; ?>
<?php
if (!function_exists('curso_preco_publico_texto')) {
    function curso_preco_publico_texto(array $curso)
    {
        $valorEfetivo = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : (float) ($curso['valor'] ?? 0);
        return $valorEfetivo <= 0 ? 'Gratuito' : 'R$ ' . number_format($valorEfetivo, 2, ',', '.');
    }
}
?>

<?php
$frontendTemplateRaw = isset($frontend_template) ? (string) $frontend_template : 'v1';
$frontendTemplate = in_array($frontendTemplateRaw, array('v2', 'v4-claude'), true) ? $frontendTemplateRaw : 'v1';

// Dados de paginação (compartilhados entre o template v1 e o v4-claude).
$paginacao = isset($paginacao) && is_array($paginacao) ? $paginacao : array('total' => 0, 'pagina' => 1, 'por_pagina' => 12, 'total_paginas' => 1);
$catalogoPaginaAtual = (int) $paginacao['pagina'];
$catalogoTotalPaginas = (int) $paginacao['total_paginas'];
$catalogoTotalResultados = (int) $paginacao['total'];
$catalogoCategoriaSlug = isset($categoriaSelecionada['slug']) ? (string) $categoriaSelecionada['slug'] : (isset($categoriaSlugAtual) ? (string) $categoriaSlugAtual : '');
$catalogoBuscaAtual = isset($buscaAtual) ? trim((string) $buscaAtual) : '';

if (!function_exists('catalogoPaginaUrl')) {
    function catalogoPaginaUrl($pagina, $categoriaSlug, $busca)
    {
        $params = array();
        if ($categoriaSlug !== '') {
            $params['categoria'] = $categoriaSlug;
        }
        if ($busca !== '') {
            $params['busca'] = $busca;
        }
        if ($pagina > 1) {
            $params['pagina'] = $pagina;
        }

        return '/cursos' . ($params ? '?' . http_build_query($params) : '');
    }
}
?>
<?php if ($frontendTemplate === 'v4-claude'): ?>
    <?php require BASE_PATH . '/resources/views/v4-claude/catalogo.php'; ?>
<?php else: ?>
<?php $pageTitle = isset($page_title) && trim((string) $page_title) !== '' ? trim((string) $page_title) : 'Cursos e eventos'; ?>
<?php $categoriaSelecionada = isset($categoriaSelecionada) && is_array($categoriaSelecionada) ? $categoriaSelecionada : null; ?>
<?php $categoriasFiltro = isset($categoriasFiltro) && is_array($categoriasFiltro) ? $categoriasFiltro : array(); ?>
<?php $buscaAtual = isset($buscaAtual) ? trim((string) $buscaAtual) : ''; ?>
<?php $totalCursosExibidos = isset($cursos) && is_array($cursos) ? count($cursos) : 0; ?>
<?php $textoQuantidadeCursos = $totalCursosExibidos === 1 ? '1 curso' : $totalCursosExibidos . ' cursos'; ?>
<?php $filtroAtivo = !empty($buscaAtual) || !empty($categoriaSelecionada); ?>
<?php
$mensagemResultado = '';
if ($filtroAtivo) {
    $partesMensagem = array('Exibindo ' . $textoQuantidadeCursos);

    if (!empty($categoriaSelecionada['nome'])) {
        $partesMensagem[] = 'da categoria <strong>' . Helpers::e($categoriaSelecionada['nome']) . '</strong>';
    }

    if ($buscaAtual !== '') {
        $partesMensagem[] = 'para a busca <strong>' . Helpers::e($buscaAtual) . '</strong>';
    }

    $mensagemResultado = implode(' ', $partesMensagem) . '.';
}
?>

<div class="front-section-stack<?php echo $frontendTemplate === 'v2' ? ' dbc-v2-course-page' : ''; ?>">
    <?php if ($frontendTemplate === 'v2'): ?>
    <section class="dbc-v2-hero dbc-v2-hero--compact front-section">
        <div class="dbc-v2-hero__content">
            <span class="dbc-v2-hero__eyebrow">Catálogo público</span>
            <h1 class="dbc-v2-gradient-text">Encontre sua próxima formação e desbloqueie seu avanço.</h1>
            <p>Conheça cursos com turmas abertas, apresentação clara e acesso rápido para quem já é aluno.</p>
            <div class="dbc-v2-hero__actions">
                <a class="dbc-v2-btn dbc-v2-btn--primary" href="/cursos">Explorar cursos</a>
                <a class="dbc-v2-btn dbc-v2-btn--secondary" href="<?php echo !empty($loggedIn) ? '/minha-pagina' : '/login'; ?>"><?php echo !empty($loggedIn) ? 'Minha Página' : 'Entrar'; ?></a>
            </div>
        </div>
        <div class="dbc-v2-hero__visual" aria-hidden="true">
            <div class="dbc-v2-course-preview">
                <span class="dbc-v2-course-preview__chip">Turmas abertas</span>
                <strong>Catálogo otimizado para celular</strong>
                <span>CTA dinâmico para matrícula ou acesso direto.</span>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="page-header front-card front-section courses-page-header">
        <?php if (!empty($categoriaSelecionada['slug'])): ?>
            <a href="/categorias" class="page-header__back-link">← Ver todas as categorias</a>
        <?php endif; ?>
        <h1><?php echo Helpers::e($pageTitle); ?></h1>
        <form class="courses-search-form" method="get" action="/cursos" role="search">
            <?php if (!empty($categoriaSelecionada['slug'])): ?>
                <input type="hidden" name="categoria" value="<?php echo Helpers::e($categoriaSelecionada['slug']); ?>">
            <?php endif; ?>

            <label class="sr-only" for="busca-cursos">Pesquisar cursos</label>

            <input
                id="busca-cursos"
                class="courses-search-form__input"
                type="search"
                name="busca"
                value="<?php echo Helpers::e($buscaAtual); ?>"
                placeholder="Pesquisar por curso ou palavra-chave"
                maxlength="80"
            >

            <button class="courses-search-form__button" type="submit">Pesquisar</button>

            <?php if (!empty($buscaAtual) || !empty($categoriaSelecionada)): ?>
                <a class="courses-search-form__clear" href="/cursos">Limpar</a>
            <?php endif; ?>
        </form>
        <?php if (!empty($categoriasFiltro)): ?>
            <nav class="courses-category-filter courses-category-filter--inside-header" aria-label="Filtrar cursos por categoria">
                <a class="courses-category-filter__item<?php echo empty($categoriaSelecionada) ? ' is-active' : ''; ?>" href="/cursos<?php echo !empty($buscaAtual) ? '?busca=' . urlencode($buscaAtual) : ''; ?>">Todos</a>
                <?php foreach ($categoriasFiltro as $categoria): ?>
                    <?php
                        $categoriaId = isset($categoria['id']) ? (int) $categoria['id'] : 0;
                        $categoriaSlug = isset($categoria['slug']) ? (string) $categoria['slug'] : '';
                        $categoriaNome = isset($categoria['nome']) ? (string) $categoria['nome'] : '';
                    ?>
                    <a
                        class="courses-category-filter__item<?php echo !empty($categoriaSelecionada) && (int) $categoriaSelecionada['id'] === $categoriaId ? ' is-active' : ''; ?>"
                        href="/cursos?categoria=<?php echo urlencode($categoriaSlug); ?><?php echo !empty($buscaAtual) ? '&busca=' . urlencode($buscaAtual) : ''; ?>"
                    >
                        <?php echo Helpers::e($categoriaNome); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </section>

    <?php if (!empty($buscaAtual)): ?>
    <p class="courses-active-search">
        Resultado da busca por <strong><?php echo Helpers::e($buscaAtual); ?></strong>.
    </p>
    <?php endif; ?>

    <?php if ($filtroAtivo): ?>
    <section class="courses-result-card" aria-live="polite">
        <div class="courses-result-card__text">
            <?php if ($totalCursosExibidos === 0): ?>
                Nenhum curso encontrado para este filtro.
            <?php else: ?>
                <?php echo $mensagemResultado; ?>
            <?php endif; ?>
        </div>
        <a class="courses-result-card__clear" href="/cursos">Limpar filtro</a>
    </section>
    <?php endif; ?>

    <?php if (empty($loggedIn)): ?>
    <section class="notice notice--info front-card front-section">
        <strong>Você pode conhecer os cursos antes de criar sua conta.</strong>
        <p>O cadastro será necessário apenas para comprar, acessar conteúdos ou acompanhar sua inscrição.</p>
    </section>
    <?php endif; ?>

    <section class="card-grid front-card-grid front-section<?php echo $frontendTemplate === 'v2' ? ' dbc-v2-course-grid' : ''; ?>">
        <?php if (empty($cursos)): ?>
            <article class="status-card front-card">
                <strong>Nenhum curso ativo</strong>
                <span><?php echo !empty($buscaAtual) || !empty($categoriaSelecionada) ? 'Nenhum curso encontrado para os filtros informados.' : 'O catálogo ainda não possui itens públicos disponíveis.'; ?></span>
            </article>
        <?php else: ?>
            <?php foreach ($cursos as $curso): ?>
                <article class="course-card front-card<?php echo $frontendTemplate === 'v2' ? ' dbc-v2-course-card' : ''; ?>">
                    <?php if (!empty($curso['thumbnail'])): ?>
                        <div class="course-card__image">
                            <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome']); ?>">
                        </div>
                    <?php else: ?>
                        <div class="course-card__image course-card__image--placeholder" style="min-height:180px;background:linear-gradient(135deg,#f3f4f6,#e5e7eb);display:flex;align-items:center;justify-content:center;">
                            <span aria-hidden="true" style="width:56px;height:56px;border-radius:14px;background:rgba(17,24,39,.12);display:block;"></span>
                        </div>
                    <?php endif; ?>
                    <div class="course-card__media">
                        <strong><?php echo Helpers::e($curso['nome']); ?></strong>
                        <span><?php echo Helpers::e($curso['categoria_nome'] ?: 'Sem categoria'); ?></span>
                    </div>
                    <div class="course-card__body">
                        <div class="pill-row">
                            <span class="pill"><?php echo Helpers::e($curso['tipo']); ?></span>
                            <span class="pill"><?php echo Helpers::e(Helpers::modalidadeCurso($curso['modalidade'])); ?></span>
                            <?php if (!empty($curso['em_promocao'])): ?>
                                <span class="pill pill--alert">Promoção</span>
                            <?php endif; ?>
                        </div>
                        <p><?php echo Helpers::e($curso['descricao_curta'] ?: 'Descrição resumida em breve.'); ?></p>
                        <div class="course-card__meta">
                            <?php if (!empty($curso['desconto_promocional'])): ?>
                                <?php $precoEfetivo = (float) $curso['valor_efetivo']; ?>
                                <div>
                                    <?php if ($precoEfetivo > 0): ?>
                                        <span class="muted" style="font-size:12px;text-decoration:line-through;">R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></span>
                                    <?php endif; ?>
                                    <strong style="display:block;"><?php echo $precoEfetivo <= 0 ? 'Gratuito' : 'R$ ' . number_format($precoEfetivo, 2, ',', '.'); ?></strong>
                                </div>
                            <?php else: ?>
                                <strong><?php echo curso_preco_publico_texto($curso); ?></strong>
                            <?php endif; ?>
                        </div>
                        <div class="cta-group<?php echo $frontendTemplate === 'v2' ? ' dbc-v2-course-card__cta' : ''; ?>">
                            <a class="button-link button-link--ghost" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">Ver detalhes</a>
                            <?php if (!empty($curso['cta_aluno']['href'])): ?>
                                <a class="<?php echo Helpers::e($curso['cta_aluno']['classe']); ?>" href="<?php echo Helpers::e($curso['cta_aluno']['href']); ?>">
                                    <?php echo Helpers::e($curso['cta_aluno']['label']); ?>
                                </a>
                            <?php elseif (!empty($curso['turmas_abertas'][0]['id'])): ?>
                                <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $curso['turmas_abertas'][0]['id']; ?>">Inscreva-se já</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($catalogoTotalPaginas > 1): ?>
    <section class="courses-pagination front-section" aria-label="Navegação entre páginas de cursos">
        <div class="cta-group" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <small><?php echo $catalogoTotalResultados; ?> curso<?php echo $catalogoTotalResultados === 1 ? '' : 's'; ?> no total — página <?php echo $catalogoPaginaAtual; ?> de <?php echo $catalogoTotalPaginas; ?>.</small>
            <div class="cta-group">
                <?php if ($catalogoPaginaAtual > 1): ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e(catalogoPaginaUrl($catalogoPaginaAtual - 1, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>">Anterior</a>
                <?php endif; ?>
                <?php
                    $catalogoPaginaInicio = max(1, $catalogoPaginaAtual - 2);
                    $catalogoPaginaFim = min($catalogoTotalPaginas, $catalogoPaginaAtual + 2);
                ?>
                <?php if ($catalogoPaginaInicio > 1): ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e(catalogoPaginaUrl(1, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>">1</a>
                    <?php if ($catalogoPaginaInicio > 2): ?><span class="muted">…</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($catalogoPaginaLoop = $catalogoPaginaInicio; $catalogoPaginaLoop <= $catalogoPaginaFim; $catalogoPaginaLoop++): ?>
                    <a class="button-link<?php echo $catalogoPaginaLoop === $catalogoPaginaAtual ? ' button-link--primary' : ' button-link--ghost'; ?>" href="<?php echo Helpers::e(catalogoPaginaUrl($catalogoPaginaLoop, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>"><?php echo $catalogoPaginaLoop; ?></a>
                <?php endfor; ?>
                <?php if ($catalogoPaginaFim < $catalogoTotalPaginas): ?>
                    <?php if ($catalogoPaginaFim < $catalogoTotalPaginas - 1): ?><span class="muted">…</span><?php endif; ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e(catalogoPaginaUrl($catalogoTotalPaginas, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>"><?php echo $catalogoTotalPaginas; ?></a>
                <?php endif; ?>
                <?php if ($catalogoPaginaAtual < $catalogoTotalPaginas): ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e(catalogoPaginaUrl($catalogoPaginaAtual + 1, $catalogoCategoriaSlug, $catalogoBuscaAtual)); ?>">Próxima</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($frontendTemplate === 'v2'): ?>
    <section class="dbc-v2-final-cta front-section">
        <div class="dbc-v2-final-cta__content">
            <strong>Pronto para começar?</strong>
            <span>Escolha um curso com turma aberta e entre direto na experiência pública do portal.</span>
        </div>
        <div class="dbc-v2-final-cta__actions">
            <a class="dbc-v2-btn dbc-v2-btn--primary" href="/cursos">Continuar explorando</a>
            <a class="dbc-v2-btn dbc-v2-btn--secondary" href="<?php echo !empty($loggedIn) ? '/minha-pagina' : '/login'; ?>"><?php echo !empty($loggedIn) ? 'Minha Página' : 'Entrar'; ?></a>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php endif; ?>

