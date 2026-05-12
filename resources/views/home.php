<?php
use App\Core\Helpers;

$chamadaPrincipalCapa = isset($chamadaPrincipalCapa) && is_array($chamadaPrincipalCapa) ? $chamadaPrincipalCapa : array();
$modulosCapaStatus = isset($modulosCapaStatus) && is_array($modulosCapaStatus) ? $modulosCapaStatus : array();
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

<?php if (!empty($chamadaPrincipalCapa)): ?>
<section class="hero hero--public">
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
    <div class="hero__panel">
        <div class="hero-stat">
            <strong><?php echo count($cursos); ?></strong>
            <span>Cursos em destaque agora</span>
        </div>
        <div class="hero-stat">
            <strong>100%</strong>
            <span>Fluxo público separado do admin e da área do professor</span>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($success)): ?>
<section class="status-card">
    <strong>Estado</strong>
    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
</section>
<?php endif; ?>

<?php if (!empty($usuarioNome)): ?>
<section class="status-card">
    <strong>Conta ativa</strong>
    <span><?php echo htmlspecialchars($usuarioNome, ENT_QUOTES, 'UTF-8'); ?></span>
    <div style="margin-top: 12px;">
        <a class="button-link button-link--ghost" href="/logout">Sair</a>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($modulosCapaStatus)): ?>
<section class="status-grid" aria-label="Módulos da capa">
    <?php foreach ($modulosCapaStatus as $moduloCapa): ?>
        <?php $textoStatus = $textoModulo($moduloCapa); ?>
        <article class="status-card">
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

<section class="status-card home-destaques-card">
    <header class="home-destaques-card__header">
        <h2>Destaques</h2>
    </header>

    <div class="card-grid card-grid--inside">
        <?php if (empty($cursos)): ?>
            <article class="status-card">
                <strong>Sem cursos públicos</strong>
                <span>O catálogo ainda está vazio.</span>
            </article>
        <?php else: ?>
            <?php foreach ($cursos as $curso): ?>
                <article class="course-card">
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
