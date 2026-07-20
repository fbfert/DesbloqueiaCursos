<?php
use App\Core\Helpers;

$coursePalette = isset($coursePalette) && is_array($coursePalette) ? $coursePalette : array(
    array('icon' => 'ti-school', 'g1' => '#fff4ec', 'g2' => '#ffe4d3', 'cor' => '#cc5500'),
);
$estado = isset($estadoIndisponivel) && is_array($estadoIndisponivel) ? $estadoIndisponivel : null;
$curso = isset($curso) && is_array($curso) ? $curso : null;
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : '/v2/catalogo/';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
?>
<script>window.V2_DISABLE_AUTORENDER_CURSO = true;<?php if ($curso): ?> window.V2_CURSO_INTEGRADO = true;<?php endif; ?></script>

<?php if (!$curso): ?>
  <?php // ---- Estado amigável (curso inexistente / indisponível / sem parâmetro) ---- ?>
  <div class="v2-container v2-curso-area">
    <nav class="v2-breadcrumb" aria-label="Caminho">
      <a href="<?php echo Helpers::e($homeHref); ?>">Início</a>
      <i class="ti ti-chevron-right" aria-hidden="true"></i>
      <a href="<?php echo Helpers::e($catalogoHref); ?>">Cursos</a>
    </nav>
    <div class="v2-empty">
      <i class="ti ti-mood-empty"></i>
      <p><strong><?php echo Helpers::e($estado ? $estado['titulo'] : 'Curso não encontrado'); ?></strong></p>
      <p><?php echo Helpers::e($estado ? $estado['mensagem'] : 'O curso solicitado não está disponível.'); ?></p>
      <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-btn v2-btn-primary"><i class="ti ti-arrow-left"></i> Voltar ao catálogo</a>
    </div>
  </div>
  <?php return; ?>
<?php endif; ?>

<?php
// ---- Tema da capa (determinístico por curso; fallback neutro) ----
$themeIndex = $curso['id'] > 0 ? ($curso['id'] % count($coursePalette)) : 0;
$theme = $coursePalette[$themeIndex];
$titulo = (string) $curso['titulo'];
$categoria = (string) $curso['categoria'];
$thumb = !empty($curso['thumbnail']) ? (string) $curso['thumbnail'] : '';
$professores = isset($curso['professores']) && is_array($curso['professores']) ? $curso['professores'] : array();
$turmas = isset($curso['turmas']) && is_array($curso['turmas']) ? $curso['turmas'] : array();
$cp = isset($curso['conteudo_programatico']) && is_array($curso['conteudo_programatico']) ? $curso['conteudo_programatico'] : array('tipo' => 'texto');

$temConteudoProgramatico = !empty($cp['texto']) || !empty($cp['html']) || !empty($cp['modulos']);
$metaItens = array();
if ((int) $curso['cargaHoraria'] > 0) {
    $metaItens[] = '<span><i class="ti ti-clock"></i> ' . (int) $curso['cargaHoraria'] . ' horas</span>';
}
if ($curso['modalidade'] !== '') {
    $metaItens[] = '<span><i class="ti ti-device-desktop"></i> ' . Helpers::e((string) $curso['modalidade']) . '</span>';
}
if (count($turmas) > 0) {
    $metaItens[] = '<span><i class="ti ti-users"></i> ' . count($turmas) . (count($turmas) === 1 ? ' turma aberta' : ' turmas abertas') . '</span>';
}

// Turma selecionada para o card lateral / CTA.
$turmaSelecionada = null;
foreach ($turmas as $t) {
    if (!empty($t['selecionada'])) {
        $turmaSelecionada = $t;
        break;
    }
}
if ($turmaSelecionada === null && !empty($turmas)) {
    $turmaSelecionada = $turmas[0];
}
$ctaHref = (string) $curso['cta_href'];
?>

<?php if (!empty($success)): ?>
  <div class="v2-container" style="padding-top:14px;">
    <div class="v2-callout v2-callout-success"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e((string) $success); ?></span></div>
  </div>
<?php endif; ?>

<div class="v2-container v2-curso-area">
  <nav class="v2-breadcrumb" aria-label="Caminho">
    <a href="<?php echo Helpers::e($homeHref); ?>">Início</a>
    <i class="ti ti-chevron-right" aria-hidden="true"></i>
    <a href="<?php echo Helpers::e($catalogoHref); ?>">Cursos</a>
    <?php if ($categoria !== ''): ?>
      <i class="ti ti-chevron-right" aria-hidden="true"></i>
      <a href="<?php echo Helpers::e((string) $curso['categoria_catalogo_href']); ?>"><?php echo Helpers::e($categoria); ?></a>
    <?php endif; ?>
    <i class="ti ti-chevron-right" aria-hidden="true"></i>
    <span aria-current="page"><?php echo Helpers::e($titulo); ?></span>
  </nav>

  <div class="v2-course" id="v2-curso-integrado" data-cta-base="/v2/checkout/inscricao?curso_id=<?php echo (int) $curso['id']; ?>">
    <div class="v2-course-main">
      <!-- Capa real ou fallback neutro -->
      <div class="v2-cover" style="background:linear-gradient(135deg,<?php echo Helpers::e($theme['g1']); ?>,<?php echo Helpers::e($theme['g2']); ?>);">
        <?php if ($thumb !== ''): ?>
          <img src="<?php echo Helpers::e($thumb); ?>" alt="<?php echo Helpers::e($titulo); ?>" style="width:100%;height:auto;display:block;">
        <?php else: ?>
          <i class="ti <?php echo Helpers::e($theme['icon']); ?>" style="color:<?php echo Helpers::e($theme['cor']); ?>;"></i>
        <?php endif; ?>
      </div>

      <?php if ($categoria !== ''): ?>
        <span class="v2-card-cat"><?php echo Helpers::e($categoria); ?></span>
      <?php endif; ?>
      <h1 class="v2-h1" style="margin:6px 0;"><?php echo Helpers::e($titulo); ?></h1>
      <?php if (!empty($curso['resumo'])): ?>
        <p class="v2-curso-resumo v2-muted"><?php echo Helpers::e((string) $curso['resumo']); ?></p>
      <?php endif; ?>

      <?php if (!empty($metaItens)): ?>
        <div class="v2-course-meta"><?php echo implode('', $metaItens); ?></div>
      <?php endif; ?>

      <!-- O que você vai aprender (objetivos específicos reais) -->
      <?php if (!empty($curso['objetivos_especificos'])): ?>
        <div class="v2-block">
          <h2 class="v2-h3" style="margin-bottom:12px;">O que você vai aprender</h2>
          <ul class="v2-learn">
            <?php foreach ($curso['objetivos_especificos'] as $obj): ?>
              <li><i class="ti ti-circle-check-filled"></i> <?php echo Helpers::e((string) $obj); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Sobre o curso -->
      <?php if (!empty($curso['descricao'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Sobre o curso</h2>
          <div class="v2-muted v2-richtext"><?php echo Helpers::renderSafeHtml((string) $curso['descricao']); ?></div>
        </div>
      <?php endif; ?>

      <!-- Objetivo geral -->
      <?php if (!empty($curso['objetivo_geral'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Objetivo geral</h2>
          <div class="v2-muted v2-richtext"><?php echo Helpers::renderSafeHtml((string) $curso['objetivo_geral']); ?></div>
        </div>
      <?php endif; ?>

      <!-- Público-alvo -->
      <?php if (!empty($curso['publico_alvo'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Público-alvo</h2>
          <div class="v2-muted v2-richtext"><?php echo Helpers::renderSafeHtml((string) $curso['publico_alvo']); ?></div>
        </div>
      <?php endif; ?>

      <!-- Pré-requisitos -->
      <?php if (!empty($curso['pre_requisitos_texto']) || !empty($curso['pre_requisitos_itens'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Pré-requisitos</h2>
          <?php if (!empty($curso['pre_requisitos_texto'])): ?>
            <div class="v2-muted v2-richtext"><?php echo Helpers::renderSafeHtml((string) $curso['pre_requisitos_texto']); ?></div>
          <?php endif; ?>
          <?php if (!empty($curso['pre_requisitos_itens'])): ?>
            <ul class="v2-learn">
              <?php foreach ($curso['pre_requisitos_itens'] as $item): ?>
                <li><i class="ti ti-point-filled"></i> <?php echo Helpers::e((string) $item); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Ementa -->
      <?php if (!empty($curso['ementa'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Ementa</h2>
          <div class="v2-muted v2-richtext"><?php echo Helpers::renderSafeHtml((string) $curso['ementa']); ?></div>
        </div>
      <?php endif; ?>

      <!-- Conteúdo programático (texto / html sanitizado / módulos) -->
      <?php if ($temConteudoProgramatico): ?>
        <div class="v2-block">
          <h2 class="v2-h3" style="margin-bottom:12px;">Conteúdo programático</h2>
          <?php if (($cp['tipo'] ?? 'texto') === 'modulos' && !empty($cp['modulos'])): ?>
            <?php foreach ($cp['modulos'] as $idx => $modulo): ?>
              <?php if (!is_array($modulo)) { continue; } ?>
              <?php $aberto = $idx === 0; $paneId = 'v2-cp-pane-' . (int) $idx; ?>
              <div class="v2-acc<?php echo $aberto ? ' is-open' : ''; ?>">
                <button type="button" class="v2-acc-head" aria-expanded="<?php echo $aberto ? 'true' : 'false'; ?>" aria-controls="<?php echo $paneId; ?>">
                  <span class="v2-mod-num"><?php echo (int) $idx + 1; ?></span>
                  <span class="v2-acc-head-info">
                    <b><?php echo Helpers::e((string) ($modulo['titulo'] ?? 'Módulo ' . ((int) $idx + 1))); ?></b>
                    <?php $qtdItens = !empty($modulo['itens']) && is_array($modulo['itens']) ? count($modulo['itens']) : 0; ?>
                    <small><?php echo $qtdItens; ?> <?php echo $qtdItens === 1 ? 'tópico' : 'tópicos'; ?></small>
                  </span>
                  <i class="ti ti-chevron-down v2-acc-chev" aria-hidden="true"></i>
                </button>
                <div class="v2-acc-body" id="<?php echo $paneId; ?>">
                  <?php if (!empty($modulo['itens']) && is_array($modulo['itens'])): ?>
                    <?php foreach ($modulo['itens'] as $item): ?>
                      <?php $item = trim((string) $item); ?>
                      <?php if ($item !== ''): ?>
                        <div class="v2-acc-aula">
                          <i class="ti ti-point v2-acc-aula-ic" aria-hidden="true"></i>
                          <span class="v2-acc-aula-label"><?php echo Helpers::e($item); ?></span>
                        </div>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php elseif (($cp['tipo'] ?? 'texto') === 'html' && !empty($cp['html'])): ?>
            <div class="v2-curso-prose"><?php echo (string) $cp['html']; // já sanitizado pelo CursoService ?></div>
          <?php elseif (!empty($cp['texto'])): ?>
            <p class="v2-muted"><?php echo nl2br(Helpers::e((string) $cp['texto'])); ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Metodologia -->
      <?php if (!empty($curso['metodologia'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Metodologia</h2>
          <div class="v2-muted v2-richtext"><?php echo Helpers::renderSafeHtml((string) $curso['metodologia']); ?></div>
        </div>
      <?php endif; ?>

      <!-- Produto final -->
      <?php if (!empty($curso['produto_final'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Produto final</h2>
          <ul class="v2-learn">
            <?php foreach ($curso['produto_final'] as $pf): ?>
              <li><i class="ti ti-circle-check-filled"></i> <?php echo Helpers::e((string) $pf); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Avaliação -->
      <?php if (!empty($curso['avaliacao'])): ?>
        <div class="v2-block v2-curso-prose">
          <h2 class="v2-h3" style="margin-bottom:8px;">Avaliação</h2>
          <div class="v2-muted v2-richtext"><?php echo Helpers::renderSafeHtml((string) $curso['avaliacao']); ?></div>
        </div>
      <?php endif; ?>

      <!-- Turmas abertas reais (selecionáveis por link/GET) -->
      <div class="v2-block" id="v2-curso-turmas">
        <h2 class="v2-h3" style="margin-bottom:12px;">Turmas abertas</h2>
        <?php if (empty($turmas)): ?>
          <p class="v2-muted">Não há turma aberta no momento para inscrição pública. Este curso está publicado, mas ainda sem turma aberta.</p>
        <?php else: ?>
          <?php foreach ($turmas as $t): ?>
            <a href="<?php echo Helpers::e((string) $t['ficha_href']); ?>#v2-curso-turmas"
               class="v2-turma v2-turma-sel<?php echo !empty($t['selecionada']) ? ' is-sel' : ''; ?>"
               data-turma-id="<?php echo (int) $t['id']; ?>"
               data-turma-nome="<?php echo Helpers::e((string) $t['nome']); ?>"
               data-cta-href="<?php echo Helpers::e((string) $t['inscricao_href']); ?>">
              <span class="v2-turma-radio"><span class="v2-turma-radio-dot" aria-hidden="true"></span></span>
              <span class="v2-turma-body">
                <span class="v2-turma-top">
                  <span class="v2-turma-nome"><?php echo Helpers::e((string) $t['nome']); ?></span>
                  <?php if ($t['vagas'] !== null): ?>
                    <span class="v2-badge v2-badge-destaque"><?php echo (int) $t['vagas']; ?> <?php echo (int) $t['vagas'] === 1 ? 'vaga' : 'vagas'; ?></span>
                  <?php else: ?>
                    <span class="v2-badge v2-badge-gratis">Vagas abertas</span>
                  <?php endif; ?>
                </span>
                <span class="v2-turma-meta">
                  <?php if ($t['codigo'] !== ''): ?><span><i class="ti ti-hash"></i> <?php echo Helpers::e((string) $t['codigo']); ?></span><?php endif; ?>
                  <?php if ($t['data_inicio'] !== ''): ?><span><i class="ti ti-calendar"></i> Início <?php echo Helpers::e((string) $t['data_inicio']); ?></span><?php endif; ?>
                  <?php if ($t['data_fim'] !== ''): ?><span><i class="ti ti-calendar-check"></i> Fim <?php echo Helpers::e((string) $t['data_fim']); ?></span><?php endif; ?>
                  <?php if ($t['local'] !== ''): ?><span><i class="ti ti-map-pin"></i> <?php echo Helpers::e((string) $t['local']); ?></span><?php endif; ?>
                </span>
                <span class="v2-turma-preco"><?php echo Helpers::e((string) $curso['precoFormatado']); ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Professor responsável real -->
      <?php if (!empty($professores)): ?>
        <div class="v2-block">
          <h2 class="v2-h3" style="margin-bottom:12px;"><?php echo count($professores) > 1 ? 'Professores responsáveis' : 'Professor responsável'; ?></h2>
          <?php foreach ($professores as $prof): ?>
            <?php $prof = (string) $prof; $inicial = function_exists('mb_substr') ? mb_substr($prof, 0, 1, 'UTF-8') : substr($prof, 0, 1); ?>
            <div class="v2-instrutor" style="margin-bottom:10px;">
              <span class="v2-instrutor-av" aria-hidden="true"><?php echo Helpers::e($inicial); ?></span>
              <div class="v2-instrutor-body">
                <b><?php echo Helpers::e($prof); ?></b>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Card lateral (sticky no desktop amplo) -->
    <aside class="v2-course-aside">
      <div class="v2-cta-card">
        <div class="v2-cta-price" id="v2-curso-price">
          <?php echo Helpers::e((string) $curso['precoFormatado']); ?>
        </div>
        <?php if (!empty($curso['precoOriginalFormatado'])): ?>
          <p class="v2-muted v2-sm" style="margin-bottom:6px;text-decoration:line-through;"><?php echo Helpers::e((string) $curso['precoOriginalFormatado']); ?></p>
        <?php endif; ?>
        <?php if (!empty($curso['desconto']['desconto_percentual'])): ?>
          <p class="v2-sm" style="margin-bottom:12px;color:var(--v2-laranja);font-weight:700;">
            <?php echo (int) round((float) $curso['desconto']['desconto_percentual']); ?>% de desconto
          </p>
        <?php endif; ?>

        <?php if ($turmaSelecionada): ?>
          <div class="v2-cta-turma v2-sm"><i class="ti ti-users"></i> Turma: <b id="v2-curso-turma-nome"><?php echo Helpers::e((string) $turmaSelecionada['nome']); ?></b></div>
        <?php endif; ?>

        <?php if (!empty($turmas)): ?>
          <a href="<?php echo Helpers::e($ctaHref); ?>" class="v2-btn v2-btn-primary v2-btn-block" id="v2-curso-cta">Quero desbloquear <i class="ti ti-arrow-right"></i></a>
        <?php else: ?>
          <a href="<?php echo Helpers::e($catalogoHref); ?>" class="v2-btn v2-btn-ghost v2-btn-block">Ver outros cursos</a>
        <?php endif; ?>

        <ul class="v2-cta-list">
          <?php if ((int) $curso['cargaHoraria'] > 0): ?><li><i class="ti ti-clock"></i> <?php echo (int) $curso['cargaHoraria']; ?> horas de carga</li><?php endif; ?>
          <?php if ($curso['modalidade'] !== ''): ?><li><i class="ti ti-device-desktop"></i> <?php echo Helpers::e((string) $curso['modalidade']); ?></li><?php endif; ?>
          <li><i class="ti ti-certificate"></i> Certificado de conclusão</li>
          <li><i class="ti ti-school"></i> Ambiente de aprendizagem</li>
        </ul>
      </div>
    </aside>
  </div>
</div>

<!-- CTA fixa mobile (acima da bottom navigation) -->
<?php if (!empty($turmas)): ?>
  <div class="v2-cta-fixed" id="v2-curso-ctafixed">
    <div>
      <div class="v2-price" id="v2-curso-price-m"><?php echo Helpers::e((string) $curso['precoFormatado']); ?></div>
      <?php if ($turmaSelecionada): ?>
        <div class="v2-muted v2-sm" style="margin-top:-2px;">Turma: <span id="v2-curso-turma-nome-m"><?php echo Helpers::e((string) $turmaSelecionada['nome']); ?></span></div>
      <?php endif; ?>
    </div>
    <a href="<?php echo Helpers::e($ctaHref); ?>" class="v2-btn v2-btn-primary" id="v2-curso-cta-m">Quero desbloquear</a>
  </div>
<?php endif; ?>
