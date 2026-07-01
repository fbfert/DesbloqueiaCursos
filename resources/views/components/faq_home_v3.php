<?php

use App\Core\Helpers;
use App\Core\Session;
use App\Services\FrontendModuloService;

$dbcFaqPadrao = array(
    array('titulo' => 'Os cursos têm certificado?', 'conteudo' => 'Sim, todos os cursos emitem certificado ao concluir.'),
    array('titulo' => 'Posso fazer o curso online?', 'conteudo' => 'Sim, oferecemos modalidades online, presencial e híbrida.'),
    array('titulo' => 'Como faço minha inscrição?', 'conteudo' => 'Escolha o curso, clique em "Ver curso" e siga os passos de inscrição.'),
    array('titulo' => 'Há suporte durante o curso?', 'conteudo' => 'Sim, nossa equipe está disponível para tirar dúvidas durante todo o curso.'),
);

$dbcFaqItens = array();
try {
    $dbcFaqItens = (new FrontendModuloService())->listarAtivosPorPosicao('faq_v3_item', 12, array(
        'page_key' => 'home',
        'route' => '/',
        'area' => 'publica',
        'auth_state' => Session::get('usuario_id') !== null ? 'logged' : 'guest',
    ));
} catch (\Throwable $exception) {
    $dbcFaqItens = array();
}

if (empty($dbcFaqItens)) {
    $dbcFaqItens = $dbcFaqPadrao;
}
?>
<section class="dbc-section dbc-faq">
    <div class="dbc-section__header">
        <h2 class="dbc-section__title">Perguntas frequentes</h2>
    </div>
    <div class="dbc-faq__list">
        <?php foreach ($dbcFaqItens as $dbcItem): ?>
            <?php
                $dbcPergunta = !empty($dbcItem['titulo']) ? (string) $dbcItem['titulo'] : '';
                $dbcResposta = !empty($dbcItem['conteudo']) ? (string) $dbcItem['conteudo'] : (!empty($dbcItem['subtitulo']) ? (string) $dbcItem['subtitulo'] : '');
            ?>
            <?php if ($dbcPergunta !== '' && $dbcResposta !== ''): ?>
            <details class="dbc-faq-item">
                <summary class="dbc-faq-item__question">
                    <span><?php echo Helpers::e($dbcPergunta); ?></span>
                    <svg class="dbc-faq-item__icon" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true">
                        <path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/>
                    </svg>
                </summary>
                <p class="dbc-faq-item__answer"><?php echo nl2br(Helpers::e($dbcResposta)); ?></p>
            </details>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
