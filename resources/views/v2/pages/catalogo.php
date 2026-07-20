<?php
use App\Core\Helpers;

$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$totalCursos = isset($totalCursos) ? (int) $totalCursos : count($cursos);
$chips = isset($chips) && is_array($chips) ? $chips : array();
$modalidadesView = isset($modalidadesView) && is_array($modalidadesView) ? $modalidadesView : array();
$paginacao = isset($paginacao) && is_array($paginacao) ? $paginacao : array();
$estado = isset($estado) && is_array($estado) ? $estado : array();
$coursePalette = isset($coursePalette) && is_array($coursePalette) ? $coursePalette : array();
$tituloCategoria = isset($tituloCategoria) ? (string) $tituloCategoria : '';

$buscaAtual = isset($estado['busca']) ? (string) $estado['busca'] : '';
$categoriaAtual = isset($estado['categoria']) ? (string) $estado['categoria'] : '';
$modalidadeAtual = isset($estado['modalidade']) ? (string) $estado['modalidade'] : '';
$precoAtual = isset($estado['preco']) ? (string) $estado['preco'] : '';
$cargaAtual = isset($estado['carga']) ? (string) $estado['carga'] : '';
$destaqueAtual = !empty($estado['destaque']);
$ordemAtual = isset($estado['ordem']) ? (string) $estado['ordem'] : 'relevancia';

$tituloPagina = $tituloCategoria !== '' ? ('Cursos de ' . $tituloCategoria) : 'Todos os cursos';

$precoOpcoes = array(
    '' => 'Todos',
    'gratis' => 'Gratuitos',
    'ate100' => 'Até R$ 100',
    '100a150' => 'De R$ 100 a R$ 150',
    'acima150' => 'Acima de R$ 150',
);
$cargaOpcoes = array(
    '' => 'Qualquer carga',
    'ate10' => 'Até 10 horas',
    '11a30' => 'De 11 a 30 horas',
    'acima30' => 'Acima de 30 horas',
);
$ordemOpcoes = array(
    'relevancia' => 'Mais relevantes',
    'preco-asc' => 'Menor preço',
    'preco-desc' => 'Maior preço',
    'alfabetica' => 'Ordem alfabética',
);

$temFiltrosAtivos = $buscaAtual !== '' || $categoriaAtual !== '' || $modalidadeAtual !== ''
    || $precoAtual !== '' || $cargaAtual !== '' || $destaqueAtual || $ordemAtual !== 'relevancia';
?>
<script>window.V2_DISABLE_AUTORENDER_CATALOGO = true; window.V2_CATALOGO_INTEGRADO = true;</script>

<?php if (!empty($success)): ?>
  <div class="v2-container" style="padding-top:16px;">
    <div class="v2-callout v2-callout-success">
      <i class="ti ti-circle-check"></i>
      <span><?php echo Helpers::e((string) $success); ?></span>
    </div>
  </div>
<?php endif; ?>

<form method="get" action="/v2/catalogo/" id="v2c-form" data-native-submit>
  <?php // Preserva a categoria ativa (escolhida via chips) ao enviar busca/filtros/ordenação ?>
  <input type="hidden" name="categoria" value="<?php echo Helpers::e($categoriaAtual); ?>">

  <!-- Busca -->
  <div class="v2-searchbar">
    <div class="v2-container">
      <div class="v2-search-row">
        <div class="v2-search-wrap">
          <i class="ti ti-search"></i>
          <input type="search" name="busca" id="v2c-search" class="v2-input v2-search-input"
                 value="<?php echo Helpers::e($buscaAtual); ?>"
                 placeholder="Buscar cursos por nome, categoria ou tema…" aria-label="Buscar cursos" autocomplete="off">
        </div>
        <button type="submit" class="v2-btn v2-btn-primary v2-btn-sm v2-hide-mobile" aria-label="Buscar">
          <i class="ti ti-search"></i> Buscar
        </button>
        <button type="button" class="v2-btn v2-btn-ghost v2-btn-filters" data-open-filters-c aria-label="Abrir filtros">
          <i class="ti ti-adjustments-horizontal"></i><span class="v2-hide-mobile">Filtros</span>
        </button>
      </div>
    </div>
  </div>

  <div class="v2-container">
    <!-- Cabeçalho do catálogo -->
    <nav class="v2-breadcrumb" aria-label="Caminho">
      <a href="/v2/">Início</a>
      <i class="ti ti-chevron-right" aria-hidden="true"></i>
      <span aria-current="page">Cursos</span>
    </nav>
    <header class="v2-catalog-header">
      <h1 class="v2-h1"><?php echo Helpers::e($tituloPagina); ?></h1>
      <p class="v2-muted">Encontre o curso ideal para desenvolver novas competências. Apenas cursos ativos e com turmas abertas.</p>
    </header>

    <!-- Chips de categoria (navegação por GET, funciona sem JavaScript) -->
    <?php if (!empty($chips)): ?>
      <div class="v2-chips v2-chips-wrap" role="list" aria-label="Categorias">
        <?php foreach ($chips as $chip): ?>
          <a href="<?php echo Helpers::e((string) $chip['url']); ?>"
             class="v2-chip<?php echo !empty($chip['ativo']) ? ' is-on' : ''; ?>"
             role="listitem"<?php echo !empty($chip['ativo']) ? ' aria-current="true"' : ''; ?>><?php echo Helpers::e((string) $chip['nome']); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="v2-catalog">
      <!-- Filtros (sidebar desktop / bottom-sheet mobile) -->
      <div class="v2-overlay" id="v2c-overlay" data-close-filters-c></div>
      <aside class="v2-filters" id="v2c-filters" aria-label="Filtros">
        <div class="v2-filters-head">
          <strong>Filtros</strong>
          <button type="button" class="v2-iconbtn" data-close-filters-c aria-label="Fechar filtros"><i class="ti ti-x"></i></button>
        </div>

        <div class="v2-fgroup">
          <div class="v2-flabel">Modalidade</div>
          <label class="v2-fopt"><input type="radio" name="modalidade" value=""<?php echo $modalidadeAtual === '' ? ' checked' : ''; ?>> Todas</label>
          <?php foreach ($modalidadesView as $mod): ?>
            <label class="v2-fopt">
              <input type="radio" name="modalidade" value="<?php echo Helpers::e((string) $mod['codigo']); ?>"<?php echo !empty($mod['ativo']) ? ' checked' : ''; ?>>
              <?php echo Helpers::e((string) $mod['label']); ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="v2-fgroup">
          <div class="v2-flabel">Faixa de preço</div>
          <?php foreach ($precoOpcoes as $valor => $rotulo): ?>
            <label class="v2-fopt">
              <input type="radio" name="preco" value="<?php echo Helpers::e((string) $valor); ?>"<?php echo $precoAtual === (string) $valor ? ' checked' : ''; ?>>
              <?php echo Helpers::e($rotulo); ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="v2-fgroup">
          <div class="v2-flabel">Carga horária</div>
          <?php foreach ($cargaOpcoes as $valor => $rotulo): ?>
            <label class="v2-fopt">
              <input type="radio" name="carga" value="<?php echo Helpers::e((string) $valor); ?>"<?php echo $cargaAtual === (string) $valor ? ' checked' : ''; ?>>
              <?php echo Helpers::e($rotulo); ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="v2-fgroup">
          <div class="v2-flabel">Destaques</div>
          <label class="v2-fopt"><input type="checkbox" name="destaque" value="1"<?php echo $destaqueAtual ? ' checked' : ''; ?>> Somente cursos em destaque</label>
        </div>

        <button type="submit" class="v2-btn v2-btn-primary v2-btn-block">Ver resultados</button>
        <a href="/v2/catalogo/" class="v2-btn v2-btn-ghost v2-btn-block" style="margin-top:8px;"><i class="ti ti-rotate"></i> Limpar filtros</a>
      </aside>

      <!-- Lista -->
      <div class="v2-catalog-main">
        <div class="v2-catalog-bar">
          <span class="v2-muted v2-sm" id="v2c-count">
            <?php echo number_format($totalCursos, 0, ',', '.'); ?>
            <?php echo $totalCursos === 1 ? 'curso encontrado' : 'cursos encontrados'; ?>
          </span>
          <label class="v2-sm v2-muted" style="display:flex;align-items:center;gap:8px;">
            Ordenar
            <select name="ordem" id="v2c-order" class="v2-select" style="width:auto;min-width:170px;" aria-label="Ordenar cursos">
              <?php foreach ($ordemOpcoes as $valor => $rotulo): ?>
                <option value="<?php echo Helpers::e((string) $valor); ?>"<?php echo $ordemAtual === (string) $valor ? ' selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="v2-btn v2-btn-ghost v2-btn-sm" id="v2c-order-apply" aria-label="Aplicar ordenação"><i class="ti ti-check"></i></button>
          </label>
        </div>

        <?php if (!empty($cursos)): ?>
          <div class="v2-grid v2-grid-catalogo" id="v2c-grid">
            <?php foreach ($cursos as $index => $curso): ?>
              <?php $hideBadges = true; ?>
              <?php require BASE_PATH . '/resources/views/v2/partials/course-card.php'; ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="v2-empty" id="v2c-empty">
            <i class="ti ti-search-off"></i>
            <p>
              <?php if ($temFiltrosAtivos): ?>
                Nenhum curso encontrado para a busca e os filtros selecionados.
              <?php else: ?>
                Nenhum curso disponível no momento.
              <?php endif; ?>
            </p>
            <?php if ($temFiltrosAtivos): ?>
              <a href="/v2/catalogo/" class="v2-btn v2-btn-outline"><i class="ti ti-rotate"></i> Limpar filtros</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($paginacao['paginas']) && count($paginacao['paginas']) > 1): ?>
          <nav class="v2-pagination" id="v2c-pag" aria-label="Paginação">
            <?php if (!empty($paginacao['anterior'])): ?>
              <a href="<?php echo Helpers::e((string) $paginacao['anterior']); ?>" class="v2-page" aria-label="Página anterior" rel="prev"><i class="ti ti-chevron-left"></i></a>
            <?php endif; ?>
            <?php foreach ($paginacao['paginas'] as $pg): ?>
              <a href="<?php echo Helpers::e((string) $pg['url']); ?>"
                 class="v2-page<?php echo !empty($pg['ativa']) ? ' is-on' : ''; ?>"
                 <?php echo !empty($pg['ativa']) ? 'aria-current="page"' : ''; ?>><?php echo (int) $pg['numero']; ?></a>
            <?php endforeach; ?>
            <?php if (!empty($paginacao['proxima'])): ?>
              <a href="<?php echo Helpers::e((string) $paginacao['proxima']); ?>" class="v2-page" aria-label="Próxima página" rel="next"><i class="ti ti-chevron-right"></i></a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>
