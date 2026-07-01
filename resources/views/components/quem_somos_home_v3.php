<?php

use App\Core\Helpers;
use App\Core\Session;
use App\Services\FrontendModuloService;

$dbcQuemSomos = null;
try {
    $dbcQuemSomosLista = (new FrontendModuloService())->listarAtivosPorPosicao('quem_somos_v3', 1, array(
        'page_key' => 'home',
        'route' => '/',
        'area' => 'publica',
        'auth_state' => Session::get('usuario_id') !== null ? 'logged' : 'guest',
    ));
    $dbcQuemSomos = !empty($dbcQuemSomosLista) ? $dbcQuemSomosLista[0] : null;
} catch (\Throwable $exception) {
    $dbcQuemSomos = null;
}
?>
<?php if (!empty($dbcQuemSomos)): ?>
    <?php
        $dbcQuemSomosTitulo = !empty($dbcQuemSomos['titulo']) ? (string) $dbcQuemSomos['titulo'] : '';
        $dbcQuemSomosTexto = !empty($dbcQuemSomos['conteudo']) ? (string) $dbcQuemSomos['conteudo'] : (!empty($dbcQuemSomos['subtitulo']) ? (string) $dbcQuemSomos['subtitulo'] : '');
        $dbcQuemSomosImagem = !empty($dbcQuemSomos['imagem_caminho']) ? (string) $dbcQuemSomos['imagem_caminho'] : '';
        $dbcQuemSomosImagemAlt = !empty($dbcQuemSomos['imagem_alt']) ? (string) $dbcQuemSomos['imagem_alt'] : $dbcQuemSomosTitulo;
    ?>
<section class="dbc-section dbc-quem-somos">
    <div class="dbc-quem-somos__inner">
        <?php if ($dbcQuemSomosImagem !== ''): ?>
            <div class="dbc-quem-somos__media">
                <img src="<?php echo Helpers::e($dbcQuemSomosImagem); ?>" alt="<?php echo Helpers::e($dbcQuemSomosImagemAlt); ?>" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="dbc-quem-somos__content">
            <?php if ($dbcQuemSomosTitulo !== ''): ?>
                <h2 class="dbc-quem-somos__title"><?php echo Helpers::e($dbcQuemSomosTitulo); ?></h2>
            <?php endif; ?>
            <?php if ($dbcQuemSomosTexto !== ''): ?>
                <p class="dbc-quem-somos__text"><?php echo nl2br(Helpers::e($dbcQuemSomosTexto)); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
