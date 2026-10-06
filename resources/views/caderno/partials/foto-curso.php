<?php
/**
 * Foto colada: card de curso do tema caderno.
 *
 * Espera:
 *   $curso   item no formato de `featuredCourses`/`cursos` da V2 (id, titulo,
 *            thumbnail, categoria, cargaHoraria, modalidade, professor,
 *            total_turmas_abertas, preco, precoOriginal, descontoPromocional, url);
 *   $vtNome  opcional: sufixo dos nomes de view transition (padrão: id do curso);
 *            false desliga os nomes (ex.: o mesmo curso duas vezes na tela).
 *
 * A capa leva `view-transition-name: capa-<id>` e o título `titulo-<id>`, os
 * mesmos da página do curso. O link é o da ficha V2, como no card da V2.
 * Emite só o <a class="foto">; quem inclui decide o contêiner (li da .grade).
 */

use App\Core\Helpers;

if (!function_exists('caderno_preco')) {
    /** "R$ 199" para valores inteiros, "R$ 49,90" com centavos, "Grátis" para zero. */
    function caderno_preco($valor): string
    {
        $valor = (float) $valor;
        if ($valor <= 0) {
            return 'Grátis';
        }
        $casas = abs($valor - round($valor)) < 0.005 ? 0 : 2;

        return 'R$ ' . number_format($valor, $casas, ',', '.');
    }
}

$fcCurso = isset($curso) && is_array($curso) ? $curso : array();
$fcId = isset($fcCurso['id']) ? (int) $fcCurso['id'] : 0;
$fcTitulo = isset($fcCurso['titulo']) ? (string) $fcCurso['titulo'] : '';
$fcThumb = isset($fcCurso['thumbnail']) ? trim((string) $fcCurso['thumbnail']) : '';
$fcCategoria = isset($fcCurso['categoria']) ? trim((string) $fcCurso['categoria']) : '';
// Mesma URL do card V2: ficha V2 pelo id; sem id, a URL pública do item.
$fcUrl = $fcId > 0
    ? '/v2/curso/?curso_id=' . $fcId
    : (isset($fcCurso['url']) ? (string) $fcCurso['url'] : '/v2/catalogo/');

$fcVt = isset($vtNome) ? $vtNome : null;
$fcVtSufixo = $fcVt === false ? '' : (is_string($fcVt) && $fcVt !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '', $fcVt) : ($fcId > 0 ? (string) $fcId : ''));
$fcVtCapa = $fcVtSufixo !== '' ? ' style="view-transition-name: capa-' . Helpers::e($fcVtSufixo) . '"' : '';
$fcVtTitulo = $fcVtSufixo !== '' ? ' style="view-transition-name: titulo-' . Helpers::e($fcVtSufixo) . '"' : '';

$fcMeta = array();
$fcCarga = isset($fcCurso['cargaHoraria']) ? (int) $fcCurso['cargaHoraria'] : 0;
if ($fcCarga > 0) {
    $fcMeta[] = $fcCarga . 'h';
}
$fcModalidade = isset($fcCurso['modalidade']) ? trim((string) $fcCurso['modalidade']) : '';
if ($fcModalidade !== '') {
    $fcMeta[] = mb_strtolower($fcModalidade, 'UTF-8');
}
$fcProfessor = isset($fcCurso['professor']) ? trim((string) $fcCurso['professor']) : '';
$fcTurmas = isset($fcCurso['total_turmas_abertas']) ? (int) $fcCurso['total_turmas_abertas'] : 0;
if ($fcProfessor !== '') {
    $fcMeta[] = $fcProfessor;
} elseif ($fcTurmas > 0) {
    $fcMeta[] = number_format($fcTurmas, 0, ',', '.') . ($fcTurmas === 1 ? ' turma' : ' turmas');
}

$fcPreco = isset($fcCurso['preco']) ? (float) $fcCurso['preco'] : 0.0;
$fcPrecoOriginal = isset($fcCurso['precoOriginal']) ? (float) $fcCurso['precoOriginal'] : 0.0;
$fcDesconto = isset($fcCurso['descontoPromocional']) && is_array($fcCurso['descontoPromocional']) ? $fcCurso['descontoPromocional'] : null;
$fcPercentual = $fcDesconto && !empty($fcDesconto['desconto_percentual']) ? (int) round((float) $fcDesconto['desconto_percentual']) : 0;
?>
<a class="foto" href="<?= Helpers::e($fcUrl) ?>">
  <?php if ($fcThumb !== ''): ?>
  <img src="<?= Helpers::e($fcThumb) ?>" alt="" width="640" height="360" loading="lazy" decoding="async"<?= $fcVtCapa ?>>
  <?php else: ?>
  <span class="capa-vazia"<?= $fcVtCapa ?>><?= caderno_icone('cursos') ?></span>
  <?php endif; ?>
  <?php if ($fcCategoria !== ''): ?>
  <div class="cat"><?= Helpers::e($fcCategoria) ?></div>
  <?php endif; ?>
  <h3<?= $fcVtTitulo ?>><?= Helpers::e($fcTitulo) ?></h3>
  <?php if (!empty($fcMeta)): ?>
  <div class="meta"><?= Helpers::e(implode(' · ', $fcMeta)) ?></div>
  <?php endif; ?>
  <div class="preco">
    <b><?= Helpers::e(caderno_preco($fcPreco)) ?></b>
    <?php if ($fcPrecoOriginal > $fcPreco && $fcPreco > 0): ?>
    <s><span class="vh">de </span><?= Helpers::e(caderno_preco($fcPrecoOriginal)) ?></s>
    <?php endif; ?>
    <?php if ($fcPercentual > 0): ?>
    <span class="off"><span aria-hidden="true">−<?= $fcPercentual ?>%</span><span class="vh"><?= $fcPercentual ?>% de desconto</span></span>
    <?php endif; ?>
  </div>
</a>
