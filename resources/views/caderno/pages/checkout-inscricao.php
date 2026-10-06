<?php
/**
 * Checkout no tema caderno — etapa 1, inscrição. Mesmas variáveis e o mesmo
 * formulário de resources/views/v2/pages/checkout-inscricao.php:
 * POST /v2/checkout/inscricao com curso_evento_id, turma_id (ocultos),
 * pagador_nome, pagador_cpf, pagador_email, pagador_telefone, pagador_estado,
 * pagador_cidade, tipo_pedido (propria|terceiros|lote), quantidade e
 * observacoes_publicas; o _token de CSRF é injetado pelo View::render.
 * Estados da V2 mantidos: matriculado, pendente de pagamento, pedido anterior
 * inativo, visitante sem login e formulário.
 *
 * O tipo do pedido vira três opções marcáveis (mesmo name e mesmos valores do
 * select da V2). Com JS, a quantidade só aparece para terceiros/lote; sem JS
 * ela fica sempre visível, como na V2 (o servidor ignora a quantidade na
 * compra própria).
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$curso = isset($curso) && is_array($curso) ? $curso : array();
$cursoThumb = !empty($curso['thumbnail']) ? (string) $curso['thumbnail'] : '';
$pagadorPrefill = isset($pagadorPrefill) && is_array($pagadorPrefill) ? $pagadorPrefill : array();
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array();
$ckErros = caderno_ck_erros(isset($errors) ? $errors : array());
$ckErrosLista = $ckErros['lista'];
$success = isset($success) ? $success : null;
$situacao = isset($situacaoInscricao) && is_array($situacaoInscricao) ? $situacaoInscricao : array();
$statusFluxo = isset($situacao['status_fluxo']) ? (string) $situacao['status_fluxo'] : 'nao_inscrito';
$loggedIn = !empty($loggedIn);
$usuarioNome = isset($usuarioNome) ? (string) $usuarioNome : '';
$usuarioEmail = isset($usuarioEmail) ? (string) $usuarioEmail : '';
$continuarPagamentoUrl = isset($continuarPagamentoUrl) ? (string) $continuarPagamentoUrl : '';
$etapaAtual = 'inscricao';

$pf = function ($chave, $fallback = '') use ($pagadorPrefill, $oldInput) {
    if (isset($oldInput['pagador_' . $chave]) && (string) $oldInput['pagador_' . $chave] !== '') {
        return (string) $oldInput['pagador_' . $chave];
    }
    return isset($pagadorPrefill[$chave]) && (string) $pagadorPrefill[$chave] !== '' ? (string) $pagadorPrefill[$chave] : $fallback;
};
$turmaSel = isset($curso['turma_selecionada']) && is_array($curso['turma_selecionada']) ? $curso['turma_selecionada'] : array();
$estadoAtual = strtoupper((string) $pf('estado'));
$tipoPedidoAtual = isset($oldInput['tipo_pedido']) ? (string) $oldInput['tipo_pedido'] : 'propria';
if (!in_array($tipoPedidoAtual, array('propria', 'terceiros', 'lote'), true)) {
    $tipoPedidoAtual = 'propria';
}
$quantidadeAtual = isset($oldInput['quantidade']) ? (int) $oldInput['quantidade'] : 1;
$observacoesAtual = isset($oldInput['observacoes_publicas']) ? (string) $oldInput['observacoes_publicas'] : '';
$mostrarForm = $loggedIn && !in_array($statusFluxo, array('matriculado', 'pendente_pagamento'), true);
$cursoNome = (string) ($curso['nome'] ?? '');
$cursoId = (int) ($curso['id'] ?? 0);

$cNome = caderno_ck_campo($ckErros['campos'], 'pagador_nome', 'ci-nome');
$cCpf = caderno_ck_campo($ckErros['campos'], 'pagador_cpf', 'ci-cpf', 'ci-cpf-ajuda');
$cEmail = caderno_ck_campo($ckErros['campos'], 'pagador_email', 'ci-email');
$cQtd = caderno_ck_campo($ckErros['campos'], 'quantidade', 'ci-qtd', 'ci-qtd-ajuda');
$tipos = array(
    'propria' => array('Para mim', 'Uma vaga no seu nome.'),
    'terceiros' => array('Para outra pessoa', 'Você paga e informa quem vai estudar.'),
    'lote' => array('Em lote', 'Várias vagas de uma vez, para uma turma ou equipe.'),
);
?>
<div class="ck">
  <header class="ck-cab">
    <h1 class="t2">Inscrição</h1>
    <?php if ($cursoNome !== ''): ?>
    <p class="lead"><?= Helpers::e($cursoNome) ?></p>
    <?php endif; ?>
  </header>

  <?php require BASE_PATH . '/resources/views/caderno/partials/checklist-checkout.php'; ?>
  <?php require BASE_PATH . '/resources/views/caderno/partials/checkout-avisos.php'; ?>

  <?php if ($statusFluxo === 'matriculado'):
      $acessarCursoUrl = !empty($situacao['inscricao_id']) && !empty($situacao['curso_id'])
          ? '/v2/aula/?inscricao_id=' . (int) $situacao['inscricao_id'] . '&curso_id=' . (int) $situacao['curso_id'] . '&turma_id=' . (int) ($situacao['turma_id'] ?? 0)
          : '/v2/aluno/';
  ?>
  <div class="ck-estado">
    <div class="postit ok largo" role="status"><b>Você já está matriculado neste curso.</b>Acompanhe o conteúdo na sua área do aluno.</div>
    <div class="form-acoes"><a class="btn" href="<?= Helpers::e($acessarCursoUrl) ?>">Acessar curso <?= caderno_icone('seta-dir') ?></a></div>
  </div>
  <?php elseif ($statusFluxo === 'pendente_pagamento'): ?>
  <div class="ck-estado">
    <div class="postit largo" role="status"><b>Você já tem uma inscrição pendente neste curso.</b>Continue o pagamento para concluir sua matrícula.</div>
    <?php if ($continuarPagamentoUrl !== ''): ?>
    <div class="form-acoes"><a class="btn" href="<?= Helpers::e($continuarPagamentoUrl) ?>">Continuar pagamento <?= caderno_icone('seta-dir') ?></a></div>
    <?php endif; ?>
  </div>
  <?php elseif (in_array($statusFluxo, array('cancelado', 'expirado', 'falhou', 'reprovado'), true)): ?>
  <div class="postit largo ck-aviso" role="status">Você pode se inscrever de novo. O pedido anterior não está ativo e não impede uma nova inscrição.</div>
  <?php endif; ?>

  <?php if (!$loggedIn): ?>
  <section class="ck-estado" aria-labelledby="ci-entrar">
    <h2 class="t3" id="ci-entrar">Entre para continuar</h2>
    <p class="ck-texto">Você pode revisar o curso, mas precisa entrar na conta para iniciar a inscrição.</p>
    <div class="form-acoes">
      <a class="btn" href="/v2/login?origem=v2_aluno">Entrar</a>
      <a class="btn-sec" href="/v2/cadastro">Criar conta<?= caderno_ck_contorno() ?></a>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($mostrarForm): ?>
  <div class="ck-grade">
    <aside class="ck-lado" aria-labelledby="ci-resumo">
      <div class="ck-curso">
        <?php if ($cursoThumb !== ''): ?>
        <img src="<?= Helpers::e($cursoThumb) ?>" alt="" width="640" height="360" decoding="async">
        <?php endif; ?>
        <h2 class="ficha-tit" id="ci-resumo">Resumo da inscrição</h2>
        <dl class="ficha-linhas">
          <div><dt>Curso</dt><dd><?= Helpers::e($cursoNome) ?></dd></div>
          <?php if (!empty($turmaSel['nome'])): ?>
          <div><dt>Turma</dt><dd><?= Helpers::e((string) $turmaSel['nome']) ?></dd></div>
          <div><dt>Situação da turma</dt><dd><?= Helpers::e(ucfirst((string) ($turmaSel['status'] ?? ''))) ?></dd></div>
          <?php endif; ?>
          <div class="ck-valor"><dt>Valor</dt><dd>
            <?php if (!empty($curso['desconto_promocional'])): ?>
            <s><span class="vh">de </span><?= Helpers::e(caderno_ck_dinheiro($curso['valor'] ?? 0)) ?></s>
            <b><span class="vh">por </span><?= Helpers::e(caderno_ck_dinheiro($curso['valor_efetivo'] ?? 0)) ?></b>
            <?php else: ?>
            <b><?= Helpers::e(caderno_ck_dinheiro($curso['valor_efetivo'] ?? ($curso['valor'] ?? 0))) ?></b>
            <?php endif; ?>
          </dd></div>
        </dl>
      </div>
    </aside>

    <form class="ck-form" method="post" action="/v2/checkout/inscricao" data-native-submit data-ck-envio id="v2-checkout-inscricao-form">
      <input type="hidden" name="curso_evento_id" value="<?= $cursoId ?>">
      <input type="hidden" name="turma_id" value="<?= !empty($turmaSel['id']) ? (int) $turmaSel['id'] : '' ?>">

      <fieldset class="ck-bloco ck-tipo">
        <legend class="t3">Para quem é a inscrição?</legend>
        <div class="ck-opcoes">
          <?php foreach ($tipos as $valor => $rotulo): ?>
          <label class="ck-opcao">
            <input type="radio" name="tipo_pedido" value="<?= Helpers::e($valor) ?>"<?= $tipoPedidoAtual === $valor ? ' checked' : '' ?> data-ck-tipo>
            <span><b><?= Helpers::e($rotulo[0]) ?></b><small><?= Helpers::e($rotulo[1]) ?></small></span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="campo campo-qtd<?= $cQtd['classe'] ?>" data-ck-qtd>
          <label for="ci-qtd">Quantidade de vagas</label>
          <input type="number" id="ci-qtd" name="quantidade" min="1" value="<?= (int) max(1, $quantidadeAtual) ?>" inputmode="numeric"<?= $cQtd['attrs'] ?>>
          <p class="ajuda" id="ci-qtd-ajuda">Vale só para outra pessoa ou em lote. Para você, é sempre uma vaga.</p>
          <?= $cQtd['msg'] ?>
        </div>
      </fieldset>

      <fieldset class="ck-bloco">
        <legend class="t3">Quem paga</legend>
        <div class="campo<?= $cNome['classe'] ?>">
          <label for="ci-nome">Nome do pagador</label>
          <input type="text" id="ci-nome" name="pagador_nome" value="<?= Helpers::e($pf('nome', $usuarioNome)) ?>" autocomplete="name"<?= $cNome['attrs'] ?>>
          <?= $cNome['msg'] ?>
        </div>
        <div class="campo<?= $cCpf['classe'] ?>">
          <label for="ci-cpf">CPF do pagador</label>
          <input type="text" id="ci-cpf" name="pagador_cpf" value="<?= Helpers::e($pf('cpf')) ?>" placeholder="000.000.000-00" inputmode="numeric" data-mask-cpf autocomplete="off" required<?= $cCpf['attrs'] ?>>
          <p class="ajuda" id="ci-cpf-ajuda">Obrigatório.</p>
          <?= $cCpf['msg'] ?>
        </div>
        <div class="campo<?= $cEmail['classe'] ?>">
          <label for="ci-email">E-mail do pagador</label>
          <input type="email" id="ci-email" name="pagador_email" value="<?= Helpers::e($pf('email', $usuarioEmail)) ?>" autocomplete="email"<?= $cEmail['attrs'] ?>>
          <?= $cEmail['msg'] ?>
        </div>
        <div class="campo">
          <label for="ci-tel">WhatsApp <span class="opcional">(opcional)</span></label>
          <input type="text" id="ci-tel" name="pagador_telefone" value="<?= Helpers::e($pf('telefone')) ?>" inputmode="tel" autocomplete="tel">
        </div>
        <div class="ck-par">
          <div class="campo ck-uf">
            <label for="ci-estado">Estado</label>
            <select id="ci-estado" name="pagador_estado">
              <option value="">Selecione</option>
              <?php foreach (array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO') as $uf): ?>
              <option value="<?= Helpers::e($uf) ?>"<?= $estadoAtual === $uf ? ' selected' : '' ?>><?= Helpers::e($uf) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="ci-cidade">Cidade</label>
            <input type="text" id="ci-cidade" name="pagador_cidade" value="<?= Helpers::e($pf('cidade')) ?>" autocomplete="address-level2">
          </div>
        </div>
        <div class="campo">
          <label for="ci-obs">Comentários sobre a inscrição <span class="opcional">(opcional)</span></label>
          <textarea id="ci-obs" name="observacoes_publicas" rows="3"><?= Helpers::e($observacoesAtual) ?></textarea>
        </div>
      </fieldset>

      <div class="form-acoes">
        <button type="submit" class="btn" data-checkout-btn data-loading-label="Avançando…">Avançar <?= caderno_icone('seta-dir') ?></button>
        <a class="btn-sec" href="/v2/curso/?curso_id=<?= $cursoId ?>">Voltar ao curso<?= caderno_ck_contorno() ?></a>
      </div>
      <p class="ck-nota">Nada é cobrado aqui: você confere o resumo antes de escolher como pagar.</p>
    </form>
  </div>
  <?php endif; ?>
</div>
