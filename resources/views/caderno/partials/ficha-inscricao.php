<?php
/**
 * Ficha de inscrição da página do curso: preço atual, preço original
 * riscado, desconto, turma e o botão "Desbloquear" (mesmo destino da V2,
 * `cta_href`). Lateral `position: sticky` a partir de 900 px.
 *
 * No celular o botão da ficha some e a ação fica na barra fixa logo abaixo
 * no HTML (acima da barra inferior): em cada largura existe um só
 * "Desbloquear" na ordem de foco. Sem turma aberta não há botão de compra:
 * a ficha leva ao catálogo e não existe barra fixa.
 *
 * Espera, da página: $curso, $turmaSelecionada, $temTurmas, $ctaHref,
 * $catalogoHref, $cCarga, $cModalidade, $cModIcone.
 */

use App\Core\Helpers;

$fiPreco = (string) $curso['precoFormatado'];
$fiOriginal = !empty($curso['precoOriginalFormatado']) ? (string) $curso['precoOriginalFormatado'] : '';
$fiPercentual = !empty($curso['desconto']['desconto_percentual']) ? (int) round((float) $curso['desconto']['desconto_percentual']) : 0;
$fiTurmaNome = $turmaSelecionada ? (string) $turmaSelecionada['nome'] : '';
$fiTurmaInicio = $turmaSelecionada && !empty($turmaSelecionada['data_inicio']) ? (string) $turmaSelecionada['data_inicio'] : '';
?>
<aside class="ficha-lado" aria-labelledby="ficha-tit">
  <div class="ficha<?= $temTurmas ? '' : ' sem-compra' ?>">
    <h2 class="ficha-tit" id="ficha-tit">Ficha de inscrição</h2>
    <?php if ($fiPercentual > 0): ?>
    <div class="carimbo ret ficha-carimbo" aria-hidden="true">−<?= $fiPercentual ?>%</div>
    <?php endif; ?>
    <p class="ficha-preco">
      <b data-preco><?= Helpers::e($fiPreco) ?></b>
      <?php if ($fiOriginal !== ''): ?>
      <s><span class="vh">de </span><?= Helpers::e($fiOriginal) ?></s>
      <?php endif; ?>
    </p>
    <?php if ($fiPercentual > 0): ?>
    <p class="selo laranja ficha-off"><?= $fiPercentual ?>% de desconto</p>
    <?php endif; ?>

    <?php if ($temTurmas): ?>
    <dl class="ficha-linhas">
      <?php if ($fiTurmaNome !== ''): ?>
      <div><dt>Turma</dt><dd data-turma-nome><?= Helpers::e($fiTurmaNome) ?></dd></div>
      <?php endif; ?>
      <div<?= $fiTurmaInicio === '' ? ' hidden' : '' ?> data-turma-inicio-linha><dt>Início</dt><dd data-turma-inicio><?= Helpers::e($fiTurmaInicio) ?></dd></div>
    </dl>
    <a class="btn btn-laranja btn-bloco ficha-cta" href="<?= Helpers::e($ctaHref) ?>" data-cta>Desbloquear <?= caderno_icone('seta-dir') ?></a>
    <?php else: ?>
    <p class="ficha-sem">Sem turma aberta para inscrição no momento.</p>
    <a class="btn btn-bloco" href="<?= Helpers::e($catalogoHref) ?>">Ver outros cursos</a>
    <?php endif; ?>

    <ul class="ficha-inclui">
      <?php if ($cCarga > 0): ?><li><?= caderno_icone('relogio') ?><?= $cCarga ?> horas de carga</li><?php endif; ?>
      <?php if ($cModalidade !== ''): ?><li><?= caderno_icone($cModIcone) ?><?= Helpers::e($cModalidade) ?></li><?php endif; ?>
      <li><?= caderno_icone('certificado') ?>Certificado de conclusão</li>
      <li><?= caderno_icone('cursos') ?>Ambiente de aprendizagem</li>
    </ul>
  </div>
</aside>
<?php if ($temTurmas): ?>
<div class="barra-compra">
  <p class="barra-preco">
    <b><?= Helpers::e($fiPreco) ?></b>
    <?php if ($fiOriginal !== ''): ?><s><span class="vh">de </span><?= Helpers::e($fiOriginal) ?></s><?php endif; ?>
    <?php if ($fiTurmaNome !== ''): ?><small>Turma: <span data-turma-nome><?= Helpers::e($fiTurmaNome) ?></span></small><?php endif; ?>
  </p>
  <a class="btn btn-laranja" href="<?= Helpers::e($ctaHref) ?>" data-cta>Desbloquear</a>
</div>
<?php endif; ?>
