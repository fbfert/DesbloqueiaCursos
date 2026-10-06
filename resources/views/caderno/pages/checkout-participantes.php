<?php
/**
 * Checkout no tema caderno — etapa 2, participantes. Mesmas variáveis e o mesmo
 * formulário de resources/views/v2/pages/checkout-participantes.php:
 * POST /v2/checkout/participantes?pedido_id=N com, para cada vaga i,
 * participantes[i][pedido_item_id] (oculto), [nome], [cpf], [email] e
 * [telefone]; o _token de CSRF é injetado pelo View::render.
 * Cada participante é uma linha da "lista de chamada".
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$pedido = isset($pedido) && is_array($pedido) ? $pedido : array();
$quantidade = isset($quantidade) ? max(1, (int) $quantidade) : 1;
$participantePrefill = isset($participantePrefill) && is_array($participantePrefill) ? $participantePrefill : array();
$ckErros = caderno_ck_erros(isset($errors) ? $errors : array());
$ckErrosLista = $ckErros['lista'];
$success = isset($success) ? $success : null;
$loggedIn = !empty($loggedIn);
$pedidoId = (int) ($pedido['id'] ?? 0);
$pedidoItemId = !empty($pedido['itens'][0]['id']) ? (int) $pedido['itens'][0]['id'] : '';
$etapaAtual = 'participantes';
?>
<div class="ck">
  <header class="ck-cab">
    <h1 class="t2">Participantes</h1>
    <p class="lead">Quem vai estudar? Preencha uma linha para cada vaga do pedido <b><?= Helpers::e((string) ($pedido['codigo'] ?? '')) ?></b>.</p>
  </header>

  <?php require BASE_PATH . '/resources/views/caderno/partials/checklist-checkout.php'; ?>
  <?php require BASE_PATH . '/resources/views/caderno/partials/checkout-avisos.php'; ?>

  <div class="ck-grade">
    <aside class="ck-lado" aria-labelledby="cp-resumo">
      <div class="ck-curso">
        <h2 class="ficha-tit" id="cp-resumo">Resumo do pedido</h2>
        <dl class="ficha-linhas">
          <div><dt>Pagador</dt><dd><?= Helpers::e((string) ($pedido['pagador_nome'] ?? '')) ?></dd></div>
          <div><dt>Situação</dt><dd><?= Helpers::e(caderno_ck_status($pedido['status'] ?? '')) ?></dd></div>
          <div><dt>Vagas</dt><dd><?= $quantidade ?></dd></div>
          <div class="ck-valor"><dt>Total</dt><dd><b><?= Helpers::e(caderno_ck_dinheiro($pedido['total'] ?? 0)) ?></b></dd></div>
        </dl>
      </div>
    </aside>

    <?php if (!$loggedIn): ?>
    <section class="ck-estado" aria-labelledby="cp-entrar">
      <h2 class="t3" id="cp-entrar">Entre para concluir</h2>
      <p class="ck-texto">O cadastro dos participantes continua disponível depois do login.</p>
      <div class="form-acoes">
        <a class="btn" href="/v2/login?origem=v2_aluno">Entrar</a>
        <a class="btn-sec" href="/v2/cadastro">Criar conta<?= caderno_ck_contorno() ?></a>
      </div>
    </section>
    <?php else: ?>
    <form class="ck-form" method="post" action="/v2/checkout/participantes?pedido_id=<?= $pedidoId ?>" data-native-submit data-ck-envio id="v2-checkout-participantes-form">
      <ol class="chamada-lista">
      <?php for ($i = 0; $i < $quantidade; $i++):
          $nome = $i === 0 ? (string) ($pedido['pagador_nome'] ?? '') : '';
          $cpf = $i === 0 && !empty($pedido['pagador_cpf']) ? (string) $pedido['pagador_cpf'] : '';
          $email = $i === 0 ? (string) ($pedido['pagador_email'] ?? '') : '';
          $tel = $i === 0 && !empty($pedido['pagador_telefone']) ? (string) $pedido['pagador_telefone'] : '';
          if ($i === 0 && !empty($participantePrefill)) {
              $nome = isset($participantePrefill['nome']) && $participantePrefill['nome'] !== '' ? (string) $participantePrefill['nome'] : $nome;
              if (isset($participantePrefill['cpf']) && trim((string) $participantePrefill['cpf']) !== '') { $cpf = (string) $participantePrefill['cpf']; }
              $email = isset($participantePrefill['email']) && $participantePrefill['email'] !== '' ? (string) $participantePrefill['email'] : $email;
              if (isset($participantePrefill['telefone']) && trim((string) $participantePrefill['telefone']) !== '') { $tel = (string) $participantePrefill['telefone']; }
          }
          $base = 'participantes[' . $i . ']';
          $eNome = caderno_ck_campo($ckErros['campos'], $base . '[nome]', 'cp-nome-' . $i);
          $eCpf = caderno_ck_campo($ckErros['campos'], $base . '[cpf]', 'cp-cpf-' . $i);
          $eEmail = caderno_ck_campo($ckErros['campos'], $base . '[email]', 'cp-email-' . $i);
      ?>
        <li>
          <fieldset class="ck-bloco chamada">
            <legend class="t3"><span class="chamada-n" aria-hidden="true"><?= $i + 1 ?></span>Participante <?= $i + 1 ?></legend>
            <input type="hidden" name="<?= $base ?>[pedido_item_id]" value="<?= Helpers::e((string) $pedidoItemId) ?>">
            <div class="campo<?= $eNome['classe'] ?>">
              <label for="cp-nome-<?= $i ?>">Nome</label>
              <input type="text" id="cp-nome-<?= $i ?>" name="<?= $base ?>[nome]" value="<?= Helpers::e($nome) ?>" autocomplete="off"<?= $eNome['attrs'] ?>>
              <?= $eNome['msg'] ?>
            </div>
            <div class="ck-par2">
              <div class="campo<?= $eCpf['classe'] ?>">
                <label for="cp-cpf-<?= $i ?>">CPF</label>
                <input type="text" id="cp-cpf-<?= $i ?>" name="<?= $base ?>[cpf]" value="<?= Helpers::e($cpf) ?>" placeholder="000.000.000-00" inputmode="numeric" data-mask-cpf autocomplete="off"<?= $eCpf['attrs'] ?>>
                <?= $eCpf['msg'] ?>
              </div>
              <div class="campo">
                <label for="cp-tel-<?= $i ?>">WhatsApp</label>
                <input type="text" id="cp-tel-<?= $i ?>" name="<?= $base ?>[telefone]" value="<?= Helpers::e($tel) ?>" inputmode="tel" autocomplete="off">
              </div>
            </div>
            <div class="campo<?= $eEmail['classe'] ?>">
              <label for="cp-email-<?= $i ?>">E-mail</label>
              <input type="email" id="cp-email-<?= $i ?>" name="<?= $base ?>[email]" value="<?= Helpers::e($email) ?>" autocomplete="off"<?= $eEmail['attrs'] ?>>
              <?= $eEmail['msg'] ?>
            </div>
          </fieldset>
        </li>
      <?php endfor; ?>
      </ol>

      <div class="form-acoes">
        <button type="submit" class="btn" data-checkout-btn data-loading-label="Salvando…">Salvar participantes <?= caderno_icone('seta-dir') ?></button>
        <a class="btn-sec" href="/v2/checkout/resumo?pedido_id=<?= $pedidoId ?>">Ver resumo<?= caderno_ck_contorno() ?></a>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
