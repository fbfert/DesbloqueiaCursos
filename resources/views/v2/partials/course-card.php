<?php
use App\Core\Helpers;

$curso = isset($curso) && is_array($curso) ? $curso : array();
$index = isset($index) ? (int) $index : 0;
$coursePalette = isset($coursePalette) && is_array($coursePalette) ? $coursePalette : array(
    array('icon' => 'ti-scale', 'g1' => '#fff4ec', 'g2' => '#ffe4d3', 'cor' => '#cc5500'),
    array('icon' => 'ti-speakerphone', 'g1' => '#f0e8ff', 'g2' => '#e4d6ff', 'cor' => '#4B008E'),
    array('icon' => 'ti-chart-bar', 'g1' => '#d1faf5', 'g2' => '#b8f2ea', 'cor' => '#007a6a'),
    array('icon' => 'ti-device-laptop', 'g1' => '#e3f0ff', 'g2' => '#cfe4ff', 'cor' => '#1d4ed8'),
    array('icon' => 'ti-microphone', 'g1' => '#ffe9f0', 'g2' => '#ffd6e3', 'cor' => '#c00057'),
    array('icon' => 'ti-briefcase', 'g1' => '#eef7df', 'g2' => '#dcefc0', 'cor' => '#3b6d11'),
);
$theme = $coursePalette[$index % count($coursePalette)];
$title = isset($curso['titulo']) ? (string) $curso['titulo'] : '';
$thumb = isset($curso['thumbnail']) ? trim((string) $curso['thumbnail']) : '';
// Fase 2.3: cards V2 apontam para a Ficha de Curso V2 com dados reais.
// Usa o id real do curso; fallback para a URL pública atual se o id faltar.
$cursoIdCard = isset($curso['id']) ? (int) $curso['id'] : 0;
$url = $cursoIdCard > 0
    ? '/v2/curso/?curso_id=' . $cursoIdCard
    : (isset($curso['url']) ? (string) $curso['url'] : '/v2/catalogo/');
$valor = isset($curso['preco']) ? (float) $curso['preco'] : 0.0;
$valorOriginal = isset($curso['precoOriginal']) ? (float) $curso['precoOriginal'] : 0.0;
$professor = isset($curso['professor']) ? trim((string) $curso['professor']) : '';
$modalidade = isset($curso['modalidade']) ? (string) $curso['modalidade'] : '';
$cargaHoraria = isset($curso['cargaHoraria']) ? (int) $curso['cargaHoraria'] : 0;
$turmasAbertas = isset($curso['total_turmas_abertas']) ? (int) $curso['total_turmas_abertas'] : 0;
$alunos = isset($curso['alunos']) ? (int) $curso['alunos'] : 0;
$ehTop = !empty($curso['eh_top']);
$destaque = !empty($curso['destaque']);
$novo = !empty($curso['novo']);
$desconto = isset($curso['descontoPromocional']) && is_array($curso['descontoPromocional']) ? $curso['descontoPromocional'] : null;
$badges = array();
if ($destaque) {
    $badges[] = '<span class="v2-badge v2-badge-destaque"><i class="ti ti-flame"></i> Destaque</span>';
}
if ($ehTop) {
    $badges[] = '<span class="v2-badge v2-badge-novo">Top</span>';
}
if ($novo) {
    $badges[] = '<span class="v2-badge v2-badge-novo">Novo</span>';
}
if ($desconto && !empty($desconto['desconto_percentual'])) {
    $badges[] = '<span class="v2-badge v2-badge-gratis">Promoção</span>';
}
$meta = array();
if ($cargaHoraria > 0) {
    $meta[] = '<span><i class="ti ti-clock"></i>' . (int) $cargaHoraria . 'h</span>';
}
if ($modalidade !== '') {
    $meta[] = '<span><i class="ti ti-device-desktop"></i>' . Helpers::e($modalidade) . '</span>';
}
if ($turmasAbertas > 0) {
    $meta[] = '<span><i class="ti ti-users"></i>' . number_format($turmasAbertas, 0, ',', '.') . ' turma' . ($turmasAbertas === 1 ? '' : 's') . '</span>';
}
if ($professor !== '') {
    $meta[] = '<span><i class="ti ti-user"></i>' . Helpers::e($professor) . '</span>';
}
if ($alunos > 0) {
    $meta[] = '<span><i class="ti ti-chart-bar"></i>' . number_format($alunos, 0, ',', '.') . ' alunos</span>';
}
$priceHtml = '';
if ($valor > 0) {
    $priceHtml = '<span class="v2-price">R$ ' . number_format($valor, 2, ',', '.') . '</span>';
    if ($valorOriginal > $valor) {
        $priceHtml = '<span class="v2-price">R$ ' . number_format($valor, 2, ',', '.') . '</span>'
            . '<small><del>R$ ' . number_format($valorOriginal, 2, ',', '.') . '</del></small>';
    }
} else {
    $priceHtml = '<span class="v2-price">Grátis</span>';
}
?>
<a href="<?php echo Helpers::e($url); ?>" class="v2-card" aria-label="<?php echo Helpers::e($title); ?>">
  <div class="v2-thumb" style="background:linear-gradient(135deg,<?php echo Helpers::e($theme['g1']); ?>,<?php echo Helpers::e($theme['g2']); ?>);">
    <?php if (!empty($badges)): ?>
      <div class="v2-thumb-badges">
        <?php echo implode('', $badges); ?>
      </div>
    <?php endif; ?>
    <?php if ($thumb !== ''): ?>
      <img src="<?php echo Helpers::e($thumb); ?>" alt="<?php echo Helpers::e($title); ?>" style="width:100%;height:100%;object-fit:cover;">
    <?php else: ?>
      <i class="ti <?php echo Helpers::e($theme['icon']); ?>" style="color:<?php echo Helpers::e($theme['cor']); ?>;"></i>
    <?php endif; ?>
  </div>
  <div class="v2-card-body">
    <?php if (!empty($curso['categoria'])): ?>
      <span class="v2-card-cat"><?php echo Helpers::e((string) $curso['categoria']); ?></span>
    <?php endif; ?>
    <h3 class="v2-card-title"><?php echo Helpers::e($title); ?></h3>
    <?php if (!empty($curso['descricaoCurta'])): ?>
      <p class="v2-muted v2-sm" style="margin:0;"><?php echo Helpers::e((string) $curso['descricaoCurta']); ?></p>
    <?php endif; ?>
    <div class="v2-card-meta">
      <?php echo implode('', $meta); ?>
    </div>
    <div class="v2-card-foot">
      <?php echo $priceHtml; ?>
      <span class="v2-btn v2-btn-outline v2-btn-sm">Ver curso</span>
    </div>
  </div>
</a>
