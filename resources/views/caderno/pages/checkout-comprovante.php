<?php
/**
 * Checkout no tema caderno — etapa 5, comprovante PIX. Mesmas variáveis e os
 * mesmos estados de resources/views/v2/pages/checkout-comprovante.php
 * (pago, bloqueado, em análise, enviar/reenviar, fechado) e o mesmo
 * formulário: POST multipart em $enviarUrl com pedido_id (oculto),
 * comprovante (arquivo), valor_informado e, quando exigido, motivo_reenvio;
 * o _token de CSRF é injetado pelo View::render.
 *
 * A chave PIX fica num post-it com o botão de copiar (o botão só aparece com
 * JS; sem JS, a chave é um campo somente leitura, selecionável). A área de
 * envio é uma folha "grampeada" que contém o <input type="file"> de verdade:
 * funciona sem JS; com JS, aceita arrastar e soltar e mostra o arquivo escolhido.
 * Os carimbos são ornamento (aria-hidden); a informação está no texto ao lado.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$ckErros = caderno_ck_erros(isset($errors) ? $errors : array());
$ckErrosLista = $ckErros['lista'];
$success = isset($success) ? $success : null;

$canSeePix = !empty($canSeePix);
$pedidoPago = !empty($pedidoPago);
$pedidoBloqueado = !empty($pedidoBloqueado);
$comprovanteAguardando = !empty($comprovanteAguardando);
$podeEnviar = !empty($podeEnviarComprovante);
$exigeMotivoReenvio = !empty($exigeMotivoReenvio);
$estadoReenvio = !empty($estadoReenvio);

$pixKey = isset($pixKey) ? (string) $pixKey : '';
$enviarUrl = isset($enviarUrl) ? (string) $enviarUrl : '/v2/checkout/comprovante/enviar';
$pagamentoUrl = isset($pagamentoUrl) ? (string) $pagamentoUrl : '';

$pedidoId = (int) ($pedido['id'] ?? 0);
$comprovantes = isset($pedido['comprovantes']) && is_array($pedido['comprovantes']) ? $pedido['comprovantes'] : array();
$totalNumerico = (float) ($pedido['total'] ?? 0);
$valorPrefill = number_format($totalNumerico, 2, '.', '');
$statusLabel = caderno_ck_status($pedido['status'] ?? '');
$comprovanteStatusLabels = array(
    'pendente' => 'Em análise',
    'aprovado' => 'Aprovado',
    'reprovado' => 'Devolvido para reenvio',
);
$mostrarChave = $podeEnviar && $canSeePix && $pixKey !== '';

$slotCupom = '';
$reciboDobravel = true;
$reciboTitulo = 'Resumo do pedido';
$etapaAtual = 'comprovante';
?>
<div class="ck">
  <header class="ck-cab">
    <h1 class="t2">Comprovante PIX</h1>
    <p class="lead">Pedido <b><?= Helpers::e((string) ($pedido['codigo'] ?? '')) ?></b>, total de <b><?= Helpers::e(caderno_ck_dinheiro($totalNumerico)) ?></b>.</p>
  </header>

  <?php require BASE_PATH . '/resources/views/caderno/partials/checklist-checkout.php'; ?>
  <?php require BASE_PATH . '/resources/views/caderno/partials/checkout-avisos.php'; ?>

  <div class="ck-grade">
    <aside class="ck-lado ck-lado-recibo">
      <?php require BASE_PATH . '/resources/views/caderno/partials/recibo.php'; ?>
    </aside>

    <div class="ck-corpo">
      <?php if ($mostrarChave): ?>
      <section class="postit ck-pix" aria-labelledby="cc-pix" data-pix-key-block>
        <h2 class="ck-pix-tit" id="cc-pix"><?= caderno_icone('pix') ?>1. Faça o PIX para esta chave</h2>
        <label class="vh" for="cc-pixkey">Chave PIX</label>
        <input class="ck-pix-chave" type="text" id="cc-pixkey" value="<?= Helpers::e($pixKey) ?>" readonly data-pix-key-input>
        <div class="ck-pix-acoes">
          <button type="button" class="btn ck-copiar" data-pix-copy hidden><?= caderno_icone('copiar') ?>Copiar chave</button>
          <span class="ck-pix-retorno" aria-live="polite" data-pix-feedback></span>
        </div>
        <p>Valor do PIX: <span class="ck-forte"><?= Helpers::e(caderno_ck_dinheiro($totalNumerico)) ?></span>. Depois, anexe o comprovante abaixo: a equipe confere e libera o curso.</p>
      </section>
      <?php endif; ?>

      <section class="ck-bloco" aria-labelledby="cc-acao">
        <h2 class="t3" id="cc-acao"><?= $mostrarChave ? '2. Envie o comprovante' : 'Comprovante' ?></h2>

        <?php if ($pedidoPago): ?>
        <div class="ck-carimbado">
          <div class="carimbo ret verde" aria-hidden="true">PAGAMENTO CONFIRMADO</div>
          <div class="postit ok largo" role="status"><b>Pagamento confirmado.</b>Este pedido já está pago: não há comprovante a enviar. O curso será liberado na sua área do aluno.</div>
        </div>
        <div class="form-acoes"><a class="btn" href="/v2/aluno">Ir para minha área <?= caderno_icone('seta-dir') ?></a></div>

        <?php elseif ($pedidoBloqueado): ?>
        <div class="postit erro largo" role="status"><b>Pedido indisponível.</b>Este pedido não aceita envio de comprovante no momento (situação: <?= Helpers::e($statusLabel) ?>). Faça uma nova inscrição se precisar de outro pedido.</div>
        <div class="form-acoes"><a class="btn-sec" href="/v2/catalogo/">Ver catálogo<?= caderno_ck_contorno() ?></a></div>

        <?php elseif ($comprovanteAguardando): ?>
        <div class="ck-carimbado">
          <div class="carimbo ret tinta" aria-hidden="true">COMPROVANTE EM ANÁLISE</div>
          <div class="postit largo" role="status"><b>Comprovante em análise.</b>Recebemos seu comprovante e ele aguarda aprovação. Assim que for confirmado, o curso é liberado na sua área.</div>
        </div>
        <?php if (!empty($comprovantes)): ?>
        <h3 class="ck-sub">Envios feitos</h3>
        <ol class="ck-envios">
          <?php foreach ($comprovantes as $indice => $comprovante):
              $cs = strtolower(trim((string) ($comprovante['status'] ?? '')));
          ?>
          <li><b>Envio <?= (int) ($indice + 1) ?></b><span><?= Helpers::e(isset($comprovanteStatusLabels[$cs]) ? $comprovanteStatusLabels[$cs] : ($cs !== '' ? ucfirst($cs) : '—')) ?></span></li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>
        <div class="form-acoes">
          <a class="btn-sec" href="/v2/aluno">Minha área<?= caderno_ck_contorno() ?></a>
          <a class="btn-sec" href="/v2/aluno/?aba=pedidos">Meus pedidos<?= caderno_ck_contorno() ?></a>
        </div>

        <?php elseif ($podeEnviar): ?>
        <?php if ($estadoReenvio): ?>
        <?php
        $postit = array(
            'titulo' => 'Precisamos de um novo comprovante.',
            'texto' => 'Anexe o arquivo correto e conte, logo abaixo, o motivo do reenvio.',
            'classe' => 'largo ck-aviso',
            'role' => 'status',
        );
        require BASE_PATH . '/resources/views/caderno/partials/postit.php';
        ?>
        <?php endif; ?>

        <form method="post" action="<?= Helpers::e($enviarUrl) ?>" enctype="multipart/form-data" class="ck-form" data-native-submit data-v2-single-submit data-ck-envio novalidate>
          <input type="hidden" name="pedido_id" value="<?= $pedidoId ?>">

          <div class="grampeado" data-grampeado>
            <span class="grampeado-grampo" aria-hidden="true"></span>
            <label class="grampeado-rotulo" for="cc-arquivo">
              <b>Arquivo do comprovante</b>
              <span class="grampeado-dica">Toque para escolher<span class="grampeado-arrastar"> ou arraste o arquivo para cá</span>.</span>
            </label>
            <input type="file" id="cc-arquivo" name="comprovante" accept=".pdf,.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp,application/pdf" aria-describedby="cc-arquivo-ajuda" required>
            <p class="grampeado-nome" data-arquivo-nome aria-live="polite"></p>
            <p class="ajuda" id="cc-arquivo-ajuda">PDF, JPG, PNG ou WEBP, com até 10 MB. A conferência final é feita no servidor.</p>
          </div>

          <div class="campo">
            <label for="cc-valor">Valor pago</label>
            <input type="text" id="cc-valor" name="valor_informado" value="<?= Helpers::e($valorPrefill) ?>" inputmode="decimal" autocomplete="off">
          </div>

          <?php if ($exigeMotivoReenvio): ?>
          <div class="campo">
            <label for="cc-motivo">Motivo do reenvio</label>
            <textarea id="cc-motivo" name="motivo_reenvio" rows="4" placeholder="Explique por que está reenviando o comprovante" aria-describedby="cc-motivo-ajuda"></textarea>
            <p class="ajuda" id="cc-motivo-ajuda">Obrigatório quando já existe um comprovante enviado para este pedido.</p>
          </div>
          <?php endif; ?>

          <div class="form-acoes">
            <button type="submit" class="btn" data-checkout-btn data-loading-label="Enviando…">Enviar comprovante <?= caderno_icone('seta-dir') ?></button>
          </div>
        </form>

        <?php else: ?>
        <div class="postit largo" role="status">No momento este pedido não está aberto para envio de comprovante. Acompanhe o andamento na sua área do aluno.</div>
        <div class="form-acoes"><a class="btn-sec" href="/v2/aluno/?aba=pedidos">Meus pedidos<?= caderno_ck_contorno() ?></a></div>
        <?php endif; ?>
      </section>

      <?php if ($pagamentoUrl !== ''): ?>
      <p class="ck-voltar"><a class="link" href="<?= Helpers::e($pagamentoUrl) ?>"><?= caderno_icone('seta-esq') ?>Voltar ao pagamento</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>
