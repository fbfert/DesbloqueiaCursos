<?php
/**
 * Certificado na versão online, no tema caderno. As páginas do certificado são
 * HTML autocontido, desenhado para impressão: ficam em iframe srcdoc, isoladas do
 * CSS do tema (como na versão legada). PDF só para quem tem acesso ($canSeePdf).
 */

use App\Core\Helpers;

$certificado = isset($certificado) && is_array($certificado) ? $certificado : array();
$versaoOnline = isset($versaoOnline) && is_array($versaoOnline) ? $versaoOnline : array();
$paginaPrimeiraHtml = isset($versaoOnline['pagina_primeira_html']) ? (string) $versaoOnline['pagina_primeira_html'] : '';
$paginaSegundaHtml = isset($versaoOnline['pagina_segunda_html']) ? (string) $versaoOnline['pagina_segunda_html'] : '';
$codigo = isset($certificado['codigo']) ? (string) $certificado['codigo'] : '';
$pdfUrl = !empty($certificado['pdf_url']) ? (string) $certificado['pdf_url'] : '/certificados/pdf?codigo=' . rawurlencode($codigo);
$validacaoUrl = '/v2/certificados/validar?codigo=' . rawurlencode($codigo);
$canSeePdf = !empty($canSeePdf);

// O HTML do certificado usa caminhos relativos: a <base> aponta para a raiz do site.
$baseHref = Helpers::url('/');
if ($baseHref !== '') {
    $baseTag = '<base href="' . Helpers::e($baseHref) . '">';
    foreach (array('paginaPrimeiraHtml', 'paginaSegundaHtml') as $variavel) {
        if ($$variavel !== '' && stripos($$variavel, '<base ') === false) {
            $$variavel = str_replace('<meta charset="utf-8">', '<meta charset="utf-8">' . $baseTag, $$variavel);
        }
    }
}
?>
<section class="sec cert-online" aria-labelledby="cert-online-titulo">
  <header class="cert-online-cab">
    <h1 class="t2" id="cert-online-titulo">Certificado</h1>
    <?php if (!empty($certificado['nome_participante'])): ?>
    <p class="lead"><?= Helpers::e((string) $certificado['nome_participante']) ?><?php if (!empty($certificado['curso_nome'])): ?> — <?= Helpers::e((string) $certificado['curso_nome']) ?><?php endif; ?></p>
    <?php endif; ?>
    <p class="cert-online-codigo">Código <b><?= Helpers::e($codigo) ?></b></p>
  </header>

  <div class="cert-online-acoes" aria-label="Ações do certificado">
    <?php if ($canSeePdf): ?>
    <a class="btn" href="<?= Helpers::e($pdfUrl) ?>" target="_blank" rel="noopener">Baixar PDF <?= caderno_icone('seta-dir') ?></a>
    <?php endif; ?>
    <button type="button" class="btn-sec" data-imprimir-certificado hidden>Imprimir<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg></button>
    <a class="link" href="<?= Helpers::e($validacaoUrl) ?>">Ver a validação deste certificado</a>
  </div>

  <div class="cert-online-pagina">
    <iframe class="cert-online-frame" title="Certificado — primeira página" srcdoc="<?= Helpers::e($paginaPrimeiraHtml) ?>" loading="eager"></iframe>
  </div>
  <?php if (trim($paginaSegundaHtml) !== ''): ?>
  <div class="cert-online-pagina">
    <iframe class="cert-online-frame cert-online-frame-2" title="Certificado — segunda página, conteúdo programático" srcdoc="<?= Helpers::e($paginaSegundaHtml) ?>" loading="lazy"></iframe>
  </div>
  <?php endif; ?>

  <p class="cert-online-nota">Para imprimir com fidelidade, prefira o PDF oficial do certificado.</p>
</section>
<script>
(function () {
  var b = document.querySelector('[data-imprimir-certificado]');
  if (!b) { return; }
  b.hidden = false;
  b.addEventListener('click', function () { window.print(); });
})();
</script>
