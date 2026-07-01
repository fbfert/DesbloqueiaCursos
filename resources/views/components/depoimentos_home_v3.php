<?php

use App\Core\Helpers;

$depoimentosCapa = isset($depoimentosCapa) && is_array($depoimentosCapa) ? $depoimentosCapa : array();
?>
<?php if (!empty($depoimentosCapa)): ?>
<section class="dbc-section dbc-depoimentos">
    <div class="dbc-section__header">
        <h2 class="dbc-section__title">O que dizem nossos alunos</h2>
    </div>
    <div class="dbc-depoimentos__track">
        <?php foreach ($depoimentosCapa as $depoimento): ?>
            <?php
                $dbcQuote = !empty($depoimento['conteudo']) ? (string) $depoimento['conteudo'] : (!empty($depoimento['subtitulo']) ? (string) $depoimento['subtitulo'] : '');
                $dbcAutor = !empty($depoimento['titulo']) ? (string) $depoimento['titulo'] : '';
                $dbcPapel = !empty($depoimento['conteudo']) && !empty($depoimento['subtitulo']) ? (string) $depoimento['subtitulo'] : '';
            ?>
            <article class="dbc-testimonial">
                <?php if ($dbcQuote !== ''): ?>
                    <p class="dbc-testimonial__quote">&ldquo;<?php echo nl2br(Helpers::e($dbcQuote)); ?>&rdquo;</p>
                <?php endif; ?>
                <div class="dbc-testimonial__footer">
                    <?php if (!empty($depoimento['imagem_caminho'])): ?>
                        <img class="dbc-testimonial__avatar" src="<?php echo Helpers::e($depoimento['imagem_caminho']); ?>" alt="<?php echo Helpers::e(!empty($depoimento['imagem_alt']) ? (string) $depoimento['imagem_alt'] : $dbcAutor); ?>" loading="lazy">
                    <?php endif; ?>
                    <div>
                        <?php if ($dbcAutor !== ''): ?>
                            <div class="dbc-testimonial__author"><?php echo Helpers::e($dbcAutor); ?></div>
                        <?php endif; ?>
                        <?php if ($dbcPapel !== ''): ?>
                            <div class="dbc-testimonial__role"><?php echo Helpers::e($dbcPapel); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
