<?php

use App\Core\Helpers;
use App\Core\Session;
use App\Services\FrontendModuloService;

$dbcComoFuncionaPadrao = array(
    array('titulo' => 'Escolha seu curso', 'conteudo' => 'Navegue pelo catálogo e encontre o curso ideal para você ou sua equipe.'),
    array('titulo' => 'Faça sua inscrição', 'conteudo' => 'Processo simples e rápido, com suporte em cada etapa.'),
    array('titulo' => 'Estude no seu ritmo', 'conteudo' => 'Conteúdo online ou presencial, com material de apoio incluído.'),
    array('titulo' => 'Receba seu certificado', 'conteudo' => 'Certificado reconhecido emitido ao concluir o curso.'),
);

$dbcComoFuncionaPassos = array();
try {
    $dbcComoFuncionaPassos = (new FrontendModuloService())->listarAtivosPorPosicao('como_funciona_v3_passo', 4, array(
        'page_key' => 'home',
        'route' => '/',
        'area' => 'publica',
        'auth_state' => Session::get('usuario_id') !== null ? 'logged' : 'guest',
    ));
} catch (\Throwable $exception) {
    $dbcComoFuncionaPassos = array();
}

if (empty($dbcComoFuncionaPassos)) {
    $dbcComoFuncionaPassos = $dbcComoFuncionaPadrao;
}
?>
<section class="dbc-section dbc-como-funciona">
    <div class="dbc-section__header">
        <h2 class="dbc-section__title">Começar é simples</h2>
    </div>
    <div class="dbc-como-funciona__steps">
        <?php foreach ($dbcComoFuncionaPassos as $dbcIndex => $dbcPasso): ?>
            <?php
                $dbcPassoTitulo = !empty($dbcPasso['titulo']) ? (string) $dbcPasso['titulo'] : '';
                $dbcPassoTexto = !empty($dbcPasso['conteudo']) ? (string) $dbcPasso['conteudo'] : (!empty($dbcPasso['subtitulo']) ? (string) $dbcPasso['subtitulo'] : '');
            ?>
            <article class="dbc-step">
                <span class="dbc-step__number"><?php echo (int) $dbcIndex + 1; ?></span>
                <?php if ($dbcPassoTitulo !== ''): ?>
                    <h3 class="dbc-step__title"><?php echo Helpers::e($dbcPassoTitulo); ?></h3>
                <?php endif; ?>
                <?php if ($dbcPassoTexto !== ''): ?>
                    <p class="dbc-step__text"><?php echo Helpers::e($dbcPassoTexto); ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
