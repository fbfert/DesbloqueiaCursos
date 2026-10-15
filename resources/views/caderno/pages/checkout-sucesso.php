<?php
/**
 * Checkout no tema caderno — pagamento concluído (CheckoutController::sucessoV2).
 * Variáveis: estado (aprovado | gratuito | analise | confirmando | cancelado), pedidoCodigo,
 * cursos, totalFormatado, proximasAcoes (titulo, conteudo), cursosHref, pedidosHref.
 * O carimbo é ornamento (aria-hidden); a situação está em texto no aviso e na ficha.
 */

use App\Core\Helpers;
use App\Support\V2Nav;

$estado = isset($estado) ? (string) $estado : 'confirmando';
$pedidoCodigo = isset($pedidoCodigo) ? (string) $pedidoCodigo : '';
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$totalFormatado = isset($totalFormatado) ? (string) $totalFormatado : '';
$proximasAcoes = isset($proximasAcoes) && is_array($proximasAcoes) ? $proximasAcoes : array();
$cursosHref = isset($cursosHref) ? (string) $cursosHref : V2Nav::ALUNO;
$pedidosHref = isset($pedidosHref) ? (string) $pedidosHref : V2Nav::PEDIDOS;

$liberado = $estado === 'aprovado' || $estado === 'gratuito';
$textos = array(
    'aprovado' => array('Inscrição confirmada', 'Pagamento aprovado.', 'Seu curso já está liberado na sua área.', 'Pago'),
    'gratuito' => array('Inscrição confirmada', 'Pedido gratuito confirmado.', 'O valor final ficou em R$ 0,00 e o curso já foi liberado, sem necessidade de comprovante.', 'Confirmado'),
    'analise' => array('Pedido recebido', 'Comprovante em análise.', 'Assim que a equipe aprovar o comprovante, o curso é liberado na sua área.', 'Em análise'),
    'confirmando' => array('Pedido recebido', 'Estamos confirmando o seu pagamento.', 'Isso costuma levar poucos minutos. Assim que a confirmação chegar, o curso é liberado na sua área e a situação do pedido muda em Meus pedidos.', 'Em confirmação'),
    'cancelado' => array('Pedido cancelado', 'Este pedido foi cancelado.', 'Nenhuma cobrança será feita por ele. Para se inscrever, faça um novo pedido pelo catálogo.', 'Cancelado'),
);
list($titulo, $avisoTitulo, $avisoTexto, $situacao) = isset($textos[$estado]) ? $textos[$estado] : $textos['confirmando'];
?>
<div class="ck">
  <header class="ck-cab">
    <h1 class="t2"><?= Helpers::e($titulo) ?></h1>
    <?php if ($pedidoCodigo !== ''): ?>
    <p class="lead">Pedido <b><?= Helpers::e($pedidoCodigo) ?></b></p>
    <?php endif; ?>
  </header>

  <div class="ck-grade ck-enviado">
    <div class="ck-corpo">
      <div class="ck-avisos" id="ck-avisos" tabindex="-1" aria-live="polite">
        <div class="postit <?= $liberado ? 'ok ' : '' ?>largo" role="status">
          <b><?= Helpers::e($avisoTitulo) ?></b>
          <?= Helpers::e($avisoTexto) ?>
        </div>
      </div>

      <?php if (!empty($proximasAcoes)): ?>
      <section class="ck-proximas" aria-labelledby="ck-proximas-titulo">
        <h2 class="t3" id="ck-proximas-titulo">Próximos passos</h2>
        <dl class="ficha-linhas">
          <?php foreach ($proximasAcoes as $acao): ?>
          <div><dt><?= Helpers::e((string) ($acao['titulo'] ?? '')) ?></dt><dd><?= nl2br(Helpers::e((string) ($acao['conteudo'] ?? ''))) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </section>
      <?php endif; ?>

      <div class="form-acoes">
        <?php if ($liberado): ?>
        <a class="btn" href="<?= Helpers::e($cursosHref) ?>">Ir para meus cursos <?= caderno_icone('seta-dir') ?></a>
        <?php else: ?>
        <a class="btn" href="<?= Helpers::e($pedidosHref) ?>">Acompanhar meus pedidos <?= caderno_icone('seta-dir') ?></a>
        <?php endif; ?>
      </div>
    </div>

    <aside class="ck-lado">
      <section class="comprovante-folha" aria-labelledby="ck-sucesso-dados"<?= $liberado ? ' data-carimbar' : '' ?>>
        <span class="grampeado-grampo" aria-hidden="true"></span>
        <h2 class="t3" id="ck-sucesso-dados">Dados do pedido</h2>
        <?php if (!empty($cursos)): ?>
        <ul class="comprovante-cursos">
          <?php foreach ($cursos as $nomeCurso): ?>
          <li><?= Helpers::e((string) $nomeCurso) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <dl class="ficha-linhas">
          <?php if ($pedidoCodigo !== ''): ?>
          <div><dt>Pedido</dt><dd><?= Helpers::e($pedidoCodigo) ?></dd></div>
          <?php endif; ?>
          <div><dt>Situação</dt><dd><span class="selo <?= $liberado ? 'verde' : 'tinta' ?>"><?= Helpers::e($situacao) ?></span></dd></div>
          <?php if ($totalFormatado !== ''): ?>
          <div class="ck-valor"><dt>Total</dt><dd><b><?= Helpers::e($totalFormatado) ?></b></dd></div>
          <?php endif; ?>
        </dl>
        <?php if ($liberado): ?>
        <div class="carimbo ret" aria-hidden="true">INSCRIÇÃO CONFIRMADA</div>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</div>
