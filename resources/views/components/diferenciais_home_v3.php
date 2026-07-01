<?php

use App\Core\Helpers;
use App\Core\Session;
use App\Services\FrontendModuloService;

$dbcDiferenciaisIcones = array(
    '<path d="M3 4h18a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-6.5l1.5 3h-8l1.5-3H3a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm1 2v9h16V6H4z"/>',
    '<path d="M19 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4l-1 4 4-2 4 2-1-4h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 12H5V5h14v10z"/><path d="M8 8h8v1.5H8zM8 11h5v1.5H8z"/>',
    '<path d="M12 2a8 8 0 0 0-8 8v6a3 3 0 0 0 3 3h1v-7H6v-2a6 6 0 1 1 12 0v2h-2v7h1a3 3 0 0 0 3-3v-6a8 8 0 0 0-8-8z"/>',
    '<path d="M3 5h2v2H3V5zm4 0h14v2H7V5zM3 11h2v2H3v-2zm4 0h14v2H7v-2zM3 17h2v2H3v-2zm4 0h14v2H7v-2z"/>',
);

$dbcDiferenciaisPadrao = array(
    array('titulo' => 'Online e presencial', 'conteudo' => 'Escolha a modalidade que se adapta à sua rotina.'),
    array('titulo' => 'Certificado incluso', 'conteudo' => 'Todos os cursos emitem certificado ao concluir.'),
    array('titulo' => 'Suporte ao aluno', 'conteudo' => 'Equipe disponível para tirar dúvidas durante o curso.'),
    array('titulo' => 'Cursos práticos', 'conteudo' => 'Conteúdo aplicável direto ao mercado de trabalho.'),
);

$dbcDiferenciaisItens = array();
try {
    $dbcDiferenciaisItens = (new FrontendModuloService())->listarAtivosPorPosicao('diferenciais_v3_item', 4, array(
        'page_key' => 'home',
        'route' => '/',
        'area' => 'publica',
        'auth_state' => Session::get('usuario_id') !== null ? 'logged' : 'guest',
    ));
} catch (\Throwable $exception) {
    $dbcDiferenciaisItens = array();
}

if (empty($dbcDiferenciaisItens)) {
    $dbcDiferenciaisItens = $dbcDiferenciaisPadrao;
}
?>
<section class="dbc-section dbc-diferenciais">
    <div class="dbc-section__header">
        <h2 class="dbc-section__title">Diferenciais</h2>
    </div>
    <div class="dbc-diferenciais__grid">
        <?php foreach ($dbcDiferenciaisItens as $dbcIndex => $dbcItem): ?>
            <?php
                $dbcItemTitulo = !empty($dbcItem['titulo']) ? (string) $dbcItem['titulo'] : '';
                $dbcItemTexto = !empty($dbcItem['conteudo']) ? (string) $dbcItem['conteudo'] : (!empty($dbcItem['subtitulo']) ? (string) $dbcItem['subtitulo'] : '');
                $dbcIcone = isset($dbcDiferenciaisIcones[$dbcIndex % count($dbcDiferenciaisIcones)]) ? $dbcDiferenciaisIcones[$dbcIndex % count($dbcDiferenciaisIcones)] : $dbcDiferenciaisIcones[0];
            ?>
            <article class="dbc-diferencial">
                <svg class="dbc-diferencial__icon" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true"><?php echo $dbcIcone; ?></svg>
                <?php if ($dbcItemTitulo !== ''): ?>
                    <h3 class="dbc-diferencial__title"><?php echo Helpers::e($dbcItemTitulo); ?></h3>
                <?php endif; ?>
                <?php if ($dbcItemTexto !== ''): ?>
                    <p class="dbc-diferencial__text"><?php echo Helpers::e($dbcItemTexto); ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
