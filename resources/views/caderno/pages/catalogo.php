<?php
/**
 * Catálogo do tema caderno. Recebe as mesmas variáveis do catálogo V2
 * (V2\CatalogoController): cursos, totalCursos, chips, categoriaSelecionada,
 * tituloCategoria, modalidadesView, paginacao, estado, success e as de
 * navegação (do layout).
 *
 * Tudo funciona por GET sem JS: o form usa a mesma action e os mesmos nomes
 * de parâmetro da V2 (busca, categoria, modalidade, preco, carga, destaque,
 * ordem, pagina) e as divisórias são links. Sem JS, os filtros ficam inline
 * abaixo da busca; com JS (html.js), viram a folha que sobe.
 *
 * O módulo `catalogo` de caderno.js só filtra no navegador quando todos os
 * cursos já estão na página: data-busca-local (uma página e sem busca no
 * servidor) e data-categoria-local (uma página e sem categoria no servidor).
 * Fora disso, busca e divisórias vão ao servidor.
 */

use App\Core\Helpers;

$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$totalCursos = isset($totalCursos) ? (int) $totalCursos : count($cursos);
$chips = isset($chips) && is_array($chips) ? $chips : array();
$modalidadesView = isset($modalidadesView) && is_array($modalidadesView) ? $modalidadesView : array();
$paginacao = isset($paginacao) && is_array($paginacao) ? $paginacao : array();
$estado = isset($estado) && is_array($estado) ? $estado : array();
$tituloCategoria = isset($tituloCategoria) ? (string) $tituloCategoria : '';

$buscaAtual = isset($estado['busca']) ? (string) $estado['busca'] : '';
$categoriaAtual = isset($estado['categoria']) ? (string) $estado['categoria'] : '';
$modalidadeAtual = isset($estado['modalidade']) ? (string) $estado['modalidade'] : '';
$precoAtual = isset($estado['preco']) ? (string) $estado['preco'] : '';
$cargaAtual = isset($estado['carga']) ? (string) $estado['carga'] : '';
$destaqueAtual = !empty($estado['destaque']);
$ordemAtual = isset($estado['ordem']) ? (string) $estado['ordem'] : 'relevancia';

$catAcao = '/v2/catalogo/';
// O h1 é o nome da área ("Cursos de Cursos Formativos" soaria repetido); o <title> segue o do controller.
$catTitulo = $tituloCategoria !== '' ? ($tituloCategoria . '.') : 'Todos os cursos.';

$catPrecoOpcoes = array(
    '' => 'Todos',
    'gratis' => 'Gratuitos',
    'ate100' => 'Até R$ 100',
    '100a150' => 'De R$ 100 a R$ 150',
    'acima150' => 'Acima de R$ 150',
);
$catCargaOpcoes = array(
    '' => 'Qualquer carga',
    'ate10' => 'Até 10 horas',
    '11a30' => 'De 11 a 30 horas',
    'acima30' => 'Acima de 30 horas',
);
$catOrdemOpcoes = array(
    'relevancia' => 'Mais relevantes',
    'preco-asc' => 'Menor preço',
    'preco-desc' => 'Maior preço',
    'alfabetica' => 'Ordem alfabética',
);

$catFiltrosAtivos = ($modalidadeAtual !== '' ? 1 : 0) + ($precoAtual !== '' ? 1 : 0) + ($cargaAtual !== '' ? 1 : 0)
    + ($destaqueAtual ? 1 : 0) + ($ordemAtual !== 'relevancia' ? 1 : 0);
$catTemFiltros = $buscaAtual !== '' || $categoriaAtual !== '' || $catFiltrosAtivos > 0;

$catTotalPaginas = isset($paginacao['totalPaginas']) ? (int) $paginacao['totalPaginas'] : 1;
$catPaginaAtual = isset($paginacao['paginaAtual']) ? (int) $paginacao['paginaAtual'] : 1;
$catUmaPagina = $catTotalPaginas <= 1;
$catBuscaLocal = $catUmaPagina && $buscaAtual === '';
$catCategoriaLocal = $catUmaPagina && $categoriaAtual === '';

// Nome da categoria → slug (para a filtragem no navegador por divisória).
$catSlugPorNome = array();
foreach ($chips as $catChip) {
    if (!empty($catChip['slug']) && isset($catChip['nome'])) {
        $catSlugPorNome[(string) $catChip['nome']] = (string) $catChip['slug'];
    }
}

// Resumo: "12 cursos em Neurociência para "cérebro" · página 1 de 2".
$catResumo = number_format($totalCursos, 0, ',', '.') . ($totalCursos === 1 ? ' curso' : ' cursos');
if ($tituloCategoria !== '') {
    $catResumo .= ' em ' . $tituloCategoria;
}
if ($buscaAtual !== '') {
    $catResumo .= ' para “' . $buscaAtual . '”';
}
if (!$catUmaPagina) {
    $catResumo .= ' · página ' . $catPaginaAtual . ' de ' . $catTotalPaginas;
}

// Números de página: primeira, última e vizinhas da atual; o resto vira "…".
$catPaginas = array();
if (!empty($paginacao['paginas']) && count($paginacao['paginas']) > 1) {
    $catUltimo = 0;
    foreach ($paginacao['paginas'] as $catPg) {
        $catN = (int) $catPg['numero'];
        if ($catN !== 1 && $catN !== $catTotalPaginas && abs($catN - $catPaginaAtual) > 1) {
            continue;
        }
        if ($catUltimo && $catN - $catUltimo > 1) {
            $catPaginas[] = null;
        }
        $catPaginas[] = $catPg;
        $catUltimo = $catN;
    }
}

$catOpcao = function ($tipo, $nome, $valor, $rotulo, $marcado) {
    return '<label class="opcao"><input type="' . $tipo . '" class="vh" name="' . Helpers::e($nome) . '" value="' . Helpers::e((string) $valor) . '"'
        . ($marcado ? ' checked' : '') . '><span class="chip">' . Helpers::e($rotulo) . '</span></label>';
};
?>
<?php if (!empty($success)): ?>
<div class="postit ok cat-aviso" role="status"><?= Helpers::e((string) $success) ?></div>
<?php endif; ?>

<h1 class="t1 cat-titulo" id="cat-titulo"><?= Helpers::e($catTitulo) ?></h1>

<form class="catalogo" method="get" action="<?= Helpers::e($catAcao) ?>" role="search" aria-label="Cursos" id="cat-form"
      data-busca-local="<?= $catBuscaLocal ? '1' : '0' ?>" data-categoria-local="<?= $catCategoriaLocal ? '1' : '0' ?>">
  <input type="hidden" name="categoria" value="<?= Helpers::e($categoriaAtual) ?>" data-campo-categoria>

  <div class="busca">
    <label for="cat-busca"><?= caderno_icone('busca') ?><span class="vh">Buscar cursos</span></label>
    <input id="cat-busca" type="search" name="busca" value="<?= Helpers::e($buscaAtual) ?>" maxlength="80"
           placeholder="O que você quer aprender?" autocomplete="off" enterkeyhint="search">
    <button type="submit" class="busca-ir"><?= caderno_icone('seta-dir') ?><span class="vh">Buscar</span></button>
  </div>

  <?php
  // Contadores das divisórias são totais da categoria: só aparecem sem busca e sem filtros.
  $divMostrarTotais = $buscaAtual === '' && $modalidadeAtual === '' && $precoAtual === '' && $cargaAtual === '' && !$destaqueAtual;
  require BASE_PATH . '/resources/views/caderno/partials/divisorias.php';
  ?>

  <button class="filtros-btn" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="cat-filtros" data-abrir-filtros>
    <?= caderno_icone('filtro') ?>Filtrar e ordenar<?php if ($catFiltrosAtivos > 0): ?><span class="filtros-qt"><?= $catFiltrosAtivos ?><span class="vh"> <?= $catFiltrosAtivos === 1 ? 'filtro ativo' : 'filtros ativos' ?></span></span><?php endif; ?>
  </button>

  <div class="filtros sheet" id="cat-filtros" data-filtros>
    <div class="fundo" data-fechar></div>
    <div class="papel" aria-labelledby="cat-filtros-tit">
      <div class="papel-cab">
        <h2 id="cat-filtros-tit">Filtrar e ordenar</h2>
        <button type="button" class="papel-fechar" data-fechar><?= caderno_icone('fechar') ?><span class="vh">Fechar filtros</span></button>
      </div>

      <fieldset>
        <legend>Modalidade</legend>
        <div class="chips">
          <?= $catOpcao('radio', 'modalidade', '', 'Todas', $modalidadeAtual === '') ?>
          <?php foreach ($modalidadesView as $catMod): ?>
          <?= $catOpcao('radio', 'modalidade', (string) $catMod['codigo'], (string) $catMod['label'], !empty($catMod['ativo'])) ?>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Faixa de preço</legend>
        <div class="chips">
          <?php foreach ($catPrecoOpcoes as $catValor => $catRotulo): ?>
          <?= $catOpcao('radio', 'preco', (string) $catValor, $catRotulo, $precoAtual === (string) $catValor) ?>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Carga horária</legend>
        <div class="chips">
          <?php foreach ($catCargaOpcoes as $catValor => $catRotulo): ?>
          <?= $catOpcao('radio', 'carga', (string) $catValor, $catRotulo, $cargaAtual === (string) $catValor) ?>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Ordenar por</legend>
        <div class="chips">
          <?php foreach ($catOrdemOpcoes as $catValor => $catRotulo): ?>
          <?= $catOpcao('radio', 'ordem', (string) $catValor, $catRotulo, $ordemAtual === (string) $catValor) ?>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset>
        <legend>Destaques</legend>
        <div class="chips">
          <?= $catOpcao('checkbox', 'destaque', '1', 'Só cursos em destaque', $destaqueAtual) ?>
        </div>
      </fieldset>

      <div class="papel-acoes">
        <button type="submit" class="btn">Ver cursos</button>
        <a class="link" href="<?= Helpers::e($catAcao) ?>">Limpar filtros</a>
      </div>
    </div>
  </div>
</form>

<p class="resumo" id="cat-resumo" aria-live="polite"><?= Helpers::e($catResumo) ?></p>

<ul class="grade" id="cat-grade"<?= empty($cursos) ? ' hidden' : '' ?>>
  <?php foreach ($cursos as $curso):
      $catCursoNome = isset($curso['categoria']) ? (string) $curso['categoria'] : '';
      $catCursoSlug = isset($catSlugPorNome[$catCursoNome]) ? $catSlugPorNome[$catCursoNome] : '';
      $catCursoTexto = trim((isset($curso['titulo']) ? (string) $curso['titulo'] : '') . ' ' . $catCursoNome . ' ' . (isset($curso['descricaoCurta']) ? (string) $curso['descricaoCurta'] : ''));
  ?>
  <li data-cat="<?= Helpers::e($catCursoSlug) ?>" data-texto="<?= Helpers::e($catCursoTexto) ?>"><?php require BASE_PATH . '/resources/views/caderno/partials/foto-curso.php'; ?></li>
  <?php endforeach; ?>
</ul>

<div class="vazio" id="cat-vazio"<?= empty($cursos) ? '' : ' hidden' ?>>
  <p class="mao" aria-hidden="true">Nada nesta página…</p>
  <p class="lead">
    <?php if ($catTemFiltros || !empty($cursos)): ?>
    Não achamos curso com essa busca ou esses filtros. Tente outra palavra ou veja todas as áreas.
    <?php else: ?>
    Nenhum curso disponível no momento. Volte em breve: novas turmas abrem sempre.
    <?php endif; ?>
  </p>
  <?php if ($catTemFiltros || !empty($cursos)): ?>
  <a class="btn-sec" href="<?= Helpers::e($catAcao) ?>">Ver todos os cursos<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg></a>
  <?php endif; ?>
</div>

<?php if (!empty($catPaginas)): ?>
<nav class="paginas" aria-label="Páginas do catálogo">
  <?php if (!empty($paginacao['anterior'])): ?>
  <a class="link pag-seta" href="<?= Helpers::e((string) $paginacao['anterior']) ?>" rel="prev"><?= caderno_icone('seta-esq') ?>anterior</a>
  <?php endif; ?>
  <?php foreach ($catPaginas as $catPg): ?>
  <?php if ($catPg === null): ?>
  <span aria-hidden="true">…</span>
  <?php else: ?>
  <a href="<?= Helpers::e((string) $catPg['url']) ?>"<?= !empty($catPg['ativa']) ? ' aria-current="page"' : '' ?>><span class="vh">Página </span><?= (int) $catPg['numero'] ?></a>
  <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!empty($paginacao['proxima'])): ?>
  <a class="link pag-seta" href="<?= Helpers::e((string) $paginacao['proxima']) ?>" rel="next">próxima página<?= caderno_icone('seta-dir') ?></a>
  <?php endif; ?>
</nav>
<?php endif; ?>
