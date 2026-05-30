<?php
use App\Core\Helpers;

$chamadaPrincipalCapa = isset($chamadaPrincipalCapa) && is_array($chamadaPrincipalCapa) ? $chamadaPrincipalCapa : array();
$modulosCapaStatus = isset($modulosCapaStatus) && is_array($modulosCapaStatus) ? $modulosCapaStatus : array();
$topCursos = isset($topCursos) && is_array($topCursos) ? $topCursos : array();
$topCursosModulo = isset($topCursosModulo) && is_array($topCursosModulo) ? $topCursosModulo : array();
$topAvaliacoesModulo = isset($topAvaliacoesModulo) && is_array($topAvaliacoesModulo) ? $topAvaliacoesModulo : array();
$depoimentosModulo = isset($depoimentosModulo) && is_array($depoimentosModulo) ? $depoimentosModulo : array();
$depoimentosCapa = isset($depoimentosCapa) && is_array($depoimentosCapa) ? $depoimentosCapa : array();
$postLoginChoiceModal = isset($postLoginChoiceModal) && is_array($postLoginChoiceModal) ? $postLoginChoiceModal : array();
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
?>

<div class="front-page-stack home-page-stack">
<?php if ($loggedIn): ?>
<section class="home-greeting front-section">
    <strong><?php echo Helpers::e('Bem-vindo' . (!empty($usuarioNome) ? ' ' . $usuarioNome : ' usuário')); ?></strong>
</section>
<?php endif; ?>

<?php if ($loggedIn): ?>
    <?php require BASE_PATH . '/resources/views/partials/public/avisos.php'; ?>
<?php endif; ?>

<?php if (!empty($chamadaPrincipalCapa)): ?>
<section class="hero hero--public front-section">
    <div class="hero__content">
        <?php $imagemModulo($chamadaPrincipalCapa, 'module-public-image module-public-image--hero'); ?>
        <?php if (!empty($chamadaPrincipalCapa['titulo'])): ?>
            <h1><?php echo Helpers::e($chamadaPrincipalCapa['titulo']); ?></h1>
        <?php endif; ?>
        <?php $textoChamada = $textoModulo($chamadaPrincipalCapa); ?>
        <?php if ($textoChamada !== ''): ?>
            <p><?php echo nl2br(Helpers::e($textoChamada)); ?></p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<div class="home-public-stack front-section-stack">
<?php if (!empty($success)): ?>
<section class="status-card front-card front-section">
    <strong>Estado</strong>
    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
</section>
<?php endif; ?>

<?php if (!empty($modulosCapaStatus)): ?>
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
                <article class="course-card home-destaques-grid__card front-card">
                    <?php if (!empty($curso['thumbnail'])): ?>
                        <div class="course-card__image">
                            <a href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">
                                <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome']); ?>">
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="course-card__media">
                        <strong><?php echo Helpers::e($curso['nome']); ?></strong>
                        <span><?php echo Helpers::e($curso['categoria_nome'] ?: 'Sem categoria'); ?></span>
                    </div>
                    <div class="course-card__body">
                        <div class="pill-row">
                            <span class="pill"><?php echo Helpers::e($curso['tipo']); ?></span>
                            <span class="pill"><?php echo Helpers::e($curso['modalidade']); ?></span>
                            <span class="pill"><?php echo (int) $curso['total_turmas_abertas']; ?> turma(s) aberta(s)</span>
                        </div>
                        <p><?php echo Helpers::e($curso['descricao_curta']); ?></p>
                        <div class="cta-group">
                            <a class="button-link button-link--ghost" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">Ver detalhes</a>
                            <?php if (!empty($curso['turmas_abertas'][0]['id'])): ?>
                                <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $curso['turmas_abertas'][0]['id']; ?>">Inscreva-se já</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="home-destaques-card__footer">
        <a class="button-link" href="https://polorainbow.com.br/cursos">Ver todos os cursos</a>
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
        <ol class="home-ranking-list front-card-list" aria-label="Top 5 cursos em destaque">
            <?php foreach ($topCursos as $indice => $cursoTop): ?>
                <li class="home-ranking-item front-card">
                    <span class="home-ranking-item__position">#<?php echo (int) ($indice + 1); ?></span>
                    <?php if (!empty($cursoTop['thumbnail'])): ?>
                        <a class="home-ranking-item__image" href="/cursos/detalhe?curso_id=<?php echo (int) $cursoTop['id']; ?>">
                            <img src="<?php echo Helpers::e($cursoTop['thumbnail']); ?>" alt="<?php echo Helpers::e($cursoTop['nome']); ?>">
                        </a>
                    <?php else: ?>
                        <span class="home-ranking-item__image home-ranking-item__image--empty" aria-hidden="true"></span>
                    <?php endif; ?>
                    <div class="home-ranking-item__content">
                        <strong><?php echo Helpers::e($cursoTop['nome']); ?></strong>
                        <span><?php echo Helpers::e($cursoTop['categoria_nome'] ?: 'Sem categoria'); ?></span>
                        <div class="pill-row">
                            <span class="pill"><?php echo Helpers::e('Modalidade: ' . (ucfirst(trim((string) ($cursoTop['modalidade'] ?? ''))) !== '' ? ucfirst(trim((string) ($cursoTop['modalidade'] ?? ''))) : '-')); ?></span>
                            <span class="pill"><?php echo (int) ($cursoTop['total_turmas_abertas'] ?? 0) > 0 ? ((int) $cursoTop['total_turmas_abertas'] . ' turma(s) aberta(s)') : 'Nenhuma turma aberta no momento'; ?></span>
                        </div>
                    </div>
                    <a class="button-link button-link--ghost" href="/cursos/detalhe?curso_id=<?php echo (int) $cursoTop['id']; ?>">Ver curso</a>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
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
