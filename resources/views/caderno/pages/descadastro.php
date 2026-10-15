<?php
/**
 * Descadastro dos lembretes de recuperação de pedido (link dos e-mails), no tema
 * caderno. Variável de PedidoRecuperacaoController::descadastrar: ok. (A mensagem
 * de falha do service, "Link inválido ou expirado.", já está no texto do aviso.)
 */

use App\Core\Helpers;
use App\Support\V2Nav;

$ok = !empty($ok);
?>
<div class="auth-cab">
  <h1 class="t2">Lembretes de pedido</h1>
  <p class="lead">Você pode parar de receber os lembretes de pedidos não concluídos quando quiser.</p>
</div>

<?php if ($ok): ?>
<div class="postit ok auth-aviso" role="status">
  <b>Pronto.</b>
  Você não vai mais receber lembretes de pedidos não concluídos.
</div>
<?php else: ?>
<div class="postit erro auth-aviso" role="alert">
  <b>Não foi possível confirmar o descadastro.</b>
  O link pode ter expirado ou ser inválido.
</div>
<?php endif; ?>

<p class="auth-pe"><a class="link" href="<?= Helpers::e(V2Nav::HOME) ?>">Voltar ao site</a></p>
