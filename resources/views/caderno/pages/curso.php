<?php
/**
 * Página do curso no tema caderno. Recebe as mesmas variáveis da ficha V2
 * (V2\CursoController): curso (ou null), estadoIndisponivel, success e as de
 * navegação (do layout).
 *
 * Ordem: capa colada + título (com os mesmos view-transition-name do card:
 * capa-<id> e titulo-<id>) → ficha de inscrição (lateral fixa ≥ 900 px; no
 * celular, a ação fica na barra fixa acima da barra inferior) → seções da V2,
 * na mesma ordem e com os mesmos títulos, cada uma só quando tem conteúdo →
 * turmas abertas como fichas pautadas → professor como assinatura.
 *
 * Tudo funciona sem JS: a troca de turma é o mesmo link GET da V2
 * (?turma_id=); o módulo `curso` de caderno.js só evita o recarregamento e
 * desenha a caneta da trilha de módulos conforme a rolagem.
 */

use App\Core\Helpers;

$estado = isset($estadoIndisponivel) && is_array($estadoIndisponivel) ? $estadoIndisponivel : null;
$curso = isset($curso) && is_array($curso) ? $curso : null;
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : '/v2/catalogo/';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';

if (!$curso):
    // ---- Estado amigável (curso inexistente / indisponível / sem parâmetro) ----
    $cvTitulo = $estado ? (string) $estado['titulo'] : 'Curso não encontrado';
    $cvMensagem = $estado ? (string) $estado['mensagem'] : 'O curso solicitado não está disponível.';
?>
<nav class="caminho" aria-label="Caminho">
  <ol>
    <li><a href="<?= Helpers::e($homeHref) ?>">Início</a></li>
    <li><a href="<?= Helpers::e($catalogoHref) ?>">Cursos</a></li>
  </ol>
</nav>
<section class="curso-vazio">
  <p class="mao" aria-hidden="true">página em branco…</p>
  <h1 class="t2"><?= Helpers::e($cvTitulo) ?></h1>
  <p class="lead"><?= Helpers::e($cvMensagem) ?></p>
  <a class="btn" href="<?= Helpers::e($catalogoHref) ?>"><?= caderno_icone('seta-esq') ?>Voltar ao catálogo</a>
</section>
<?php
    return;
endif;

$cId = (int) $curso['id'];
$cTitulo = (string) $curso['titulo'];
$cCategoria = (string) $curso['categoria'];
$cThumb = !empty($curso['thumbnail']) ? (string) $curso['thumbnail'] : '';
$cProfessores = isset($curso['professores']) && is_array($curso['professores']) ? $curso['professores'] : array();
$cTurmas = isset($curso['turmas']) && is_array($curso['turmas']) ? $curso['turmas'] : array();
$cp = isset($curso['conteudo_programatico']) && is_array($curso['conteudo_programatico']) ? $curso['conteudo_programatico'] : array('tipo' => 'texto');
$cpTipo = isset($cp['tipo']) ? (string) $cp['tipo'] : 'texto';
$temConteudoProgramatico = !empty($cp['texto']) || !empty($cp['html']) || !empty($cp['modulos']);
$cCarga = (int) $curso['cargaHoraria'];
$cModalidade = (string) $curso['modalidade'];
$cVt = $cId > 0 ? (string) $cId : '';

// Ícone da modalidade (traço de caneta), pelo código do curso.
$cModIcone = 'sob-demanda';
$cModCodigo = isset($curso['modalidade_codigo']) ? (string) $curso['modalidade_codigo'] : '';
if ($cModCodigo === 'presencial') {
    $cModIcone = 'presencial';
} elseif ($cModCodigo === 'online_ao_vivo' || $cModCodigo === 'hibrido') {
    $cModIcone = 'ao-vivo';
}

// Turma da ficha / CTA: a selecionada ou a primeira (como na V2).
$turmaSelecionada = null;
foreach ($cTurmas as $t) {
    if (!empty($t['selecionada'])) {
        $turmaSelecionada = $t;
        break;
    }
}
if ($turmaSelecionada === null && !empty($cTurmas)) {
    $turmaSelecionada = $cTurmas[0];
}
$ctaHref = (string) $curso['cta_href'];
$turmaEscolhidaId = $turmaSelecionada ? (int) $turmaSelecionada['id'] : 0; // a ficha e a turma marcada concordam
$temTurmas = !empty($cTurmas);

// Seções textuais da V2, na mesma ordem (o conteúdo programático entra na posição dele).
$secaoRica = function ($chave) use ($curso) {
    return !empty($curso[$chave]) ? Helpers::renderSafeHtml((string) $curso[$chave]) : '';
};
?>
<?php if (!empty($success)): ?>
<div class="postit ok largo curso-aviso" role="status"><?= Helpers::e((string) $success) ?></div>
<?php endif; ?>

<nav class="caminho" aria-label="Caminho">
  <ol>
    <li><a href="<?= Helpers::e($homeHref) ?>">Início</a></li>
    <li><a href="<?= Helpers::e($catalogoHref) ?>">Cursos</a></li>
    <?php if ($cCategoria !== ''): ?>
    <li><a href="<?= Helpers::e((string) $curso['categoria_catalogo_href']) ?>"><?= Helpers::e($cCategoria) ?></a></li>
    <?php endif; ?>
    <li class="atual"><span aria-current="page"><?= Helpers::e($cTitulo) ?></span></li>
  </ol>
</nav>

<div class="curso" id="curso" data-curso="<?= $cId ?>">
  <header class="curso-cab">
    <div class="capa-colada">
      <?php if ($cThumb !== ''): ?>
      <img src="<?= Helpers::e($cThumb) ?>" alt="" width="1280" height="720" fetchpriority="high" decoding="async"<?= $cVt !== '' ? ' style="view-transition-name: capa-' . $cVt . '"' : '' ?>>
      <?php else: ?>
      <span class="capa-vazia"<?= $cVt !== '' ? ' style="view-transition-name: capa-' . $cVt . '"' : '' ?>><?= caderno_icone('cursos') ?></span>
      <?php endif; ?>
    </div>
    <?php if ($cCategoria !== ''): ?>
    <p class="curso-cat"><?= Helpers::e($cCategoria) ?></p>
    <?php endif; ?>
    <h1 class="curso-titulo"<?= $cVt !== '' ? ' style="view-transition-name: titulo-' . $cVt . '"' : '' ?>><?= Helpers::e($cTitulo) ?></h1>
    <?php if (!empty($curso['resumo'])): ?>
    <p class="lead curso-resumo"><?= Helpers::e((string) $curso['resumo']) ?></p>
    <?php endif; ?>
    <ul class="curso-meta">
      <?php if ($cCarga > 0): ?><li><?= caderno_icone('relogio') ?><?= $cCarga ?> horas</li><?php endif; ?>
      <?php if ($cModalidade !== ''): ?><li><?= caderno_icone($cModIcone) ?><?= Helpers::e($cModalidade) ?></li><?php endif; ?>
      <?php if ($temTurmas): ?><li><?= caderno_icone('vagas') ?><?= count($cTurmas) ?> <?= count($cTurmas) === 1 ? 'turma aberta' : 'turmas abertas' ?></li><?php endif; ?>
    </ul>
  </header>

  <?php require __DIR__ . '/../partials/ficha-inscricao.php'; ?>

  <div class="curso-corpo">
    <?php if (!empty($curso['objetivos_especificos'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-aprender">
      <h2 class="sec-tit" id="sec-aprender"><span class="sublinhado">O que você vai aprender</span></h2>
      <ul class="lista-check">
        <?php foreach ($curso['objetivos_especificos'] as $obj): ?>
        <li><?= caderno_icone('check') ?><span><?= Helpers::e((string) $obj) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['descricao'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-sobre">
      <h2 class="sec-tit" id="sec-sobre"><span class="sublinhado">Sobre o curso</span></h2>
      <div class="prosa"><?= $secaoRica('descricao') ?></div>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['objetivo_geral'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-objetivo">
      <h2 class="sec-tit" id="sec-objetivo"><span class="sublinhado">Objetivo geral</span></h2>
      <div class="prosa"><?= $secaoRica('objetivo_geral') ?></div>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['publico_alvo'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-publico">
      <h2 class="sec-tit" id="sec-publico"><span class="sublinhado">Público-alvo</span></h2>
      <div class="prosa"><?= $secaoRica('publico_alvo') ?></div>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['pre_requisitos_texto']) || !empty($curso['pre_requisitos_itens'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-pre">
      <h2 class="sec-tit" id="sec-pre"><span class="sublinhado">Pré-requisitos</span></h2>
      <?php if (!empty($curso['pre_requisitos_texto'])): ?>
      <div class="prosa"><?= $secaoRica('pre_requisitos_texto') ?></div>
      <?php endif; ?>
      <?php if (!empty($curso['pre_requisitos_itens'])): ?>
      <ul class="lista-ponto">
        <?php foreach ($curso['pre_requisitos_itens'] as $item): ?>
        <li><?= Helpers::e((string) $item) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['ementa'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-ementa">
      <h2 class="sec-tit" id="sec-ementa"><span class="sublinhado">Ementa</span></h2>
      <div class="prosa"><?= $secaoRica('ementa') ?></div>
    </section>
    <?php endif; ?>

    <?php if ($temConteudoProgramatico): ?>
    <section class="curso-sec" aria-labelledby="sec-conteudo">
      <h2 class="sec-tit" id="sec-conteudo"><span class="sublinhado">Conteúdo programático</span></h2>
      <?php if ($cpTipo === 'modulos' && !empty($cp['modulos'])): ?>
        <?php $modulos = $cp['modulos']; require __DIR__ . '/../partials/trilha-modulos.php'; ?>
      <?php elseif ($cpTipo === 'html' && !empty($cp['html'])): ?>
      <div class="prosa"><?= (string) $cp['html'] // já sanitizado pelo CursoService ?></div>
      <?php elseif (!empty($cp['texto'])): ?>
      <p class="prosa"><?= nl2br(Helpers::e((string) $cp['texto'])) ?></p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['metodologia'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-metodologia">
      <h2 class="sec-tit" id="sec-metodologia"><span class="sublinhado">Metodologia</span></h2>
      <div class="prosa"><?= $secaoRica('metodologia') ?></div>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['produto_final'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-produto">
      <h2 class="sec-tit" id="sec-produto"><span class="sublinhado">Produto final</span></h2>
      <ul class="lista-check">
        <?php foreach ($curso['produto_final'] as $pf): ?>
        <li><?= caderno_icone('check') ?><span><?= Helpers::e((string) $pf) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($curso['avaliacao'])): ?>
    <section class="curso-sec rv" aria-labelledby="sec-avaliacao">
      <h2 class="sec-tit" id="sec-avaliacao"><span class="sublinhado">Avaliação</span></h2>
      <div class="prosa"><?= $secaoRica('avaliacao') ?></div>
    </section>
    <?php endif; ?>

    <section class="curso-sec" id="turmas" aria-labelledby="sec-turmas">
      <h2 class="sec-tit" id="sec-turmas"><span class="sublinhado">Turmas abertas</span></h2>
      <?php if (!$temTurmas): ?>
      <div class="postit sem-turma">Não há turma aberta no momento para inscrição pública. Este curso está publicado, mas ainda sem turma aberta.</div>
      <?php else: ?>
      <?php if (count($cTurmas) > 1): ?>
      <p class="turmas-dica">Toque numa turma para escolher; a inscrição segue com a turma marcada.</p>
      <?php endif; ?>
      <ul class="turmas">
        <?php foreach ($cTurmas as $turma): ?>
        <li><?php require __DIR__ . '/../partials/turma-ficha.php'; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <?php if (!empty($cProfessores)): ?>
    <section class="curso-sec" aria-labelledby="sec-prof">
      <h2 class="sec-tit" id="sec-prof"><span class="sublinhado"><?= count($cProfessores) > 1 ? 'Professores responsáveis' : 'Professor responsável' ?></span></h2>
      <ul class="assinaturas">
        <?php foreach ($cProfessores as $prof): ?>
        <li class="assinatura">
          <span class="assinatura-nome"><?= Helpers::e((string) $prof) ?></span>
          <svg class="assinatura-traco" viewBox="0 0 300 16" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M2 10 C 60 7, 120 12, 180 8.5 S 268 9, 298 6.5"/></svg>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>
</div>
