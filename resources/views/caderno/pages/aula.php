<?php
/**
 * Aula no tema caderno ("Página do caderno"). Mesmos dados, ramos de estado,
 * formulário e endpoint de resources/views/v2/pages/aula.php (AulaController V2):
 *   - estado() (200 "Selecione um curso" / 404 "Conteúdo indisponível");
 *   - item inacessível, visão geral (sem item, com conteúdo), "Conteúdo a caminho";
 *   - conclusão: POST /v2/aula/concluir com inscricao_id, curso_id, turma_id,
 *     modulo_id, item_id e acao (marcar|desmarcar); _token injetado pelo
 *     View::render. Texto e HTML (auto_leitura) concluem sozinhos: o form leva
 *     data-autoconcluir e o módulo `aula` de caderno-aluno.js faz requestSubmit()
 *     após 150 ms; sem JS, o botão é o fallback;
 *   - feedback em #v2-aula-feedback (tabindex -1, aria-live), focado pelo JS.
 * Desktop (>= 900 px): sumário na coluna lateral; celular: sumário em <details>
 * no topo e barra de estudo fixa (anterior / concluir / próxima).
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$estado = isset($estado) && is_array($estado) ? $estado : null;
$cabecalho = isset($cabecalho) && is_array($cabecalho) ? $cabecalho : array();
$arvore = isset($arvore) && is_array($arvore) ? $arvore : array();
$navegacao = isset($navegacao) && is_array($navegacao) ? $navegacao : array('anterior' => null, 'proximo' => null);
$item = isset($item) && is_array($item) ? $item : null;
$itemInacessivel = !empty($itemInacessivel);
$temConteudo = !empty($temConteudo);
$alunoHref = isset($alunoHref) ? (string) $alunoHref : '/v2/aluno/';
$formCtx = isset($formCtx) && is_array($formCtx) ? $formCtx : array();
$errors = isset($errors) && is_array($errors) ? array_values(array_filter($errors, 'is_scalar')) : array();
$success = isset($success) ? $success : null;
$concluirAction = isset($formCtx['concluir_action']) ? (string) $formCtx['concluir_action'] : '/v2/aula/concluir';
$overviewUrl = isset($overviewUrl) ? (string) $overviewUrl : $alunoHref;
$mostrarVisaoGeral = !$itemInacessivel && !$item && $temConteudo;
$successTexto = !empty($success) ? (is_array($success) ? (string) ($success['message'] ?? '') : (string) $success) : '';

if ($estado): ?>
<div class="aula">
  <div class="vazio aula-estado">
    <p class="mao"><?= Helpers::e((string) $estado['titulo']) ?></p>
    <p class="lead"><?= Helpers::e((string) $estado['mensagem']) ?></p>
    <a class="btn" href="<?= Helpers::e($alunoHref) ?>"><?= caderno_icone('seta-esq') ?> Voltar à minha área</a>
  </div>
</div>
<?php return; endif;

$cursoNome = (string) ($cabecalho['curso_nome'] ?? '');
$progresso = max(0, min(100, (int) ($cabecalho['progresso'] ?? 0)));

// Contagem do sumário e o primeiro item ainda não concluído (atalho da visão geral).
$smTotalItens = 0;
$smFeitosItens = 0;
$proximoPendente = null;
foreach ($arvore as $smModulo) {
    foreach ((isset($smModulo['itens']) && is_array($smModulo['itens']) ? $smModulo['itens'] : array()) as $smItem) {
        if (!empty($smItem['etiqueta'])) {
            continue;
        }
        $smTotalItens++;
        if (!empty($smItem['concluido'])) {
            $smFeitosItens++;
        } elseif ($proximoPendente === null) {
            $proximoPendente = $smItem;
        }
    }
}
$smResumo = $smFeitosItens . ' de ' . $smTotalItens . ($smTotalItens === 1 ? ' concluído' : ' concluídos');
// ✓ à caneta: só logo depois de concluir com sucesso (redirect com flash).
$abConcluiu = $item && !empty($item['concluido']) && $successTexto !== '' && empty($errors);
?>
<div class="aula<?= $item || $itemInacessivel ? ' aula-com-item' : '' ?>">
  <header class="aula-cab">
    <nav class="migalha" aria-label="Caminho">
      <a href="<?= Helpers::e($alunoHref) ?>">Minha área</a><span aria-hidden="true">›</span>
      <?php if ($item || $itemInacessivel): ?>
      <a href="<?= Helpers::e($overviewUrl) ?>"><?= Helpers::e($cursoNome !== '' ? $cursoNome : 'Curso') ?></a>
      <?php else: ?>
      <span aria-current="page"><?= Helpers::e($cursoNome !== '' ? $cursoNome : 'Curso') ?></span>
      <?php endif; ?>
    </nav>
    <div class="al-prog aula-prog" style="--p:<?= $progresso ?>">
      <svg viewBox="0 0 200 14" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path class="al-prog-base" pathLength="100" d="M2 8C40 5 70 10.5 102 7.5S162 5 198 8"/>
        <?php if ($progresso > 0): ?><path class="al-prog-tinta" pathLength="100" stroke-dasharray="<?= $progresso ?> 100" d="M2 8C40 5 70 10.5 102 7.5S162 5 198 8"/><?php endif; ?>
      </svg>
      <p><b><?= $progresso ?>%</b> <span>do curso</span></p>
    </div>
  </header>

  <?php if (!$item && !$itemInacessivel): ?>
  <?php if ($mostrarVisaoGeral): ?>
  <section class="aula-geral" aria-labelledby="aula-titulo">
    <?php if (!empty($cabecalho['turma_nome'])): ?><p class="al-ola"><?= Helpers::e((string) $cabecalho['turma_nome']) ?></p><?php endif; ?>
    <h1 class="t2" id="aula-titulo"><?= Helpers::e($cursoNome !== '' ? $cursoNome : 'Conteúdo do curso') ?></h1>
    <p class="lead">Escolha um módulo para continuar seus estudos.</p>
    <?php if ($proximoPendente): ?>
    <p class="aula-continuar"><a class="btn" href="<?= Helpers::e((string) $proximoPendente['url']) ?>"><?= $smFeitosItens > 0 ? 'Continuar' : 'Começar' ?>: <?= Helpers::e((string) $proximoPendente['titulo']) ?> <?= caderno_icone('seta-dir') ?></a></p>
    <?php endif; ?>
    <div class="sumario aula-indice">
      <p class="sumario-tit">Sumário <span><?= Helpers::e($smResumo) ?></span></p>
      <?php require BASE_PATH . '/resources/views/caderno/partials/aula-sumario.php'; ?>
    </div>
  </section>
  <?php else: ?>
  <div class="vazio aula-estado">
    <p class="mao">Conteúdo a caminho</p>
    <p class="lead">Este curso ainda não tem conteúdo publicado disponível para você.</p>
    <a class="btn-sec" href="<?= Helpers::e($alunoHref) ?>"><?= caderno_icone('seta-esq') ?> Voltar à minha área<?= caderno_ck_contorno() ?></a>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="aula-grade">
    <details class="sumario" id="sumario" data-sumario>
      <summary><span class="sumario-tit">Sumário <span><?= Helpers::e($smResumo) ?></span></span></summary>
      <?php require BASE_PATH . '/resources/views/caderno/partials/aula-sumario.php'; ?>
    </details>

    <?php if ($itemInacessivel): ?>
    <div class="vazio aula-estado">
      <p class="mao"><?= caderno_icone('cadeado') ?> Conteúdo indisponível</p>
      <p class="lead">Este conteúdo não está disponível para você no momento.</p>
      <a class="btn-sec" href="<?= Helpers::e($alunoHref) ?>"><?= caderno_icone('seta-esq') ?> Voltar à minha área<?= caderno_ck_contorno() ?></a>
    </div>
    <?php else: ?>
    <article class="aula-pag" aria-labelledby="aula-titulo">
      <p class="aula-kicker"><span class="fita"><?= Helpers::e((string) $item['tipo_label']) ?></span><?php if ((string) $item['modulo_titulo'] !== ''): ?> <span><?= Helpers::e((string) $item['modulo_titulo']) ?></span><?php endif; ?></p>
      <h1 class="aula-tit" id="aula-titulo"><?= Helpers::e((string) $item['titulo']) ?></h1>

      <div id="v2-aula-feedback" class="aula-aviso" tabindex="-1" aria-live="assertive" data-aviso-foco>
        <?php if ($successTexto !== ''): ?>
        <p class="aula-ok-msg" role="status"><?= Helpers::e($successTexto) ?></p>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
        <div class="postit erro largo" role="alert"><?php foreach ($errors as $auI => $auErro): ?><?= $auI > 0 ? '<br>' : '' ?><?= Helpers::e((string) $auErro) ?><?php endforeach; ?></div>
        <?php endif; ?>
      </div>

      <?php require BASE_PATH . '/resources/views/caderno/partials/aula-conteudo.php'; ?>

      <?php
      // Campos ocultos do formulário de conclusão (localizadores; a autorização é
      // refeita no servidor). CSRF é injetado automaticamente.
      $auHidden = ''
          . '<input type="hidden" name="inscricao_id" value="' . (int) ($formCtx['inscricao_id'] ?? 0) . '">'
          . '<input type="hidden" name="curso_id" value="' . (int) ($formCtx['curso_id'] ?? 0) . '">'
          . '<input type="hidden" name="turma_id" value="' . (int) ($formCtx['turma_id'] ?? 0) . '">'
          . '<input type="hidden" name="modulo_id" value="' . (int) ($formCtx['modulo_id'] ?? 0) . '">'
          . '<input type="hidden" name="item_id" value="' . (int) $item['id'] . '">';
      ?>
      <?php if (!empty($item['concluido'])): ?>
      <div class="aula-fim feito">
        <p class="aula-feito"><svg viewBox="0 0 28 24" aria-hidden="true" focusable="false"><path<?= $abConcluiu ? ' data-ok' : '' ?> d="M3 13.5c2.2 1.7 4.2 4 6 6.6C13 13 17.6 7 25 2.5"/></svg>Concluído</p>
        <?php if (!empty($item['pode_concluir'])): ?>
        <form method="post" action="<?= Helpers::e($concluirAction) ?>" data-native-submit class="aula-desmarcar">
          <?= $auHidden ?>
          <input type="hidden" name="acao" value="desmarcar">
          <button type="submit" class="link" data-complete-btn>Desmarcar conclusão</button>
        </form>
        <?php endif; ?>
      </div>
      <?php elseif (!empty($item['pode_concluir'])): ?>
      <form method="post" action="<?= Helpers::e($concluirAction) ?>" data-native-submit id="aula-concluir" class="aula-fim"<?= !empty($item['auto_leitura']) ? ' data-autoconcluir' : '' ?>>
        <?= $auHidden ?>
        <input type="hidden" name="acao" value="marcar">
        <button type="submit" class="btn" data-complete-btn data-loading-label="Concluindo…"><?= caderno_icone('check') ?> <?= !empty($item['auto_leitura']) ? 'Concluir leitura' : 'Marcar como concluído' ?></button>
        <?php if (!empty($item['auto_leitura'])): ?>
        <noscript><p class="aula-nota">Clique para registrar a leitura deste conteúdo.</p></noscript>
        <?php endif; ?>
      </form>
      <?php endif; ?>

      <?php if (!empty($navegacao['anterior']) || !empty($navegacao['proximo'])): ?>
      <nav class="aula-nav" aria-label="Anterior e próxima">
        <?php if (!empty($navegacao['anterior'])): ?>
        <a class="aula-nav-ant" href="<?= Helpers::e((string) $navegacao['anterior']['url']) ?>"><small><?= caderno_icone('seta-esq') ?> Anterior</small><span><?= Helpers::e((string) ($navegacao['anterior']['label'] ?? '')) ?></span></a>
        <?php endif; ?>
        <?php if (!empty($navegacao['proximo'])): ?>
        <a class="aula-nav-prox" href="<?= Helpers::e((string) $navegacao['proximo']['url']) ?>"><small>Próxima <?= caderno_icone('seta-dir') ?></small><span><?= Helpers::e((string) ($navegacao['proximo']['label'] ?? '')) ?></span></a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    </article>
    <?php require BASE_PATH . '/resources/views/caderno/partials/aula-barra.php'; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
