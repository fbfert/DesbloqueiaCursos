<?php
/**
 * Checkout rápido no tema caderno — tela única de compra (GET /comprar).
 * Mesmas variáveis de resources/views/checkout-rapido/comprar.php (curso,
 * turma, valor, prefill, loggedIn, textoLgpd, pollingSegundos) e a mesma
 * marcação que /assets/js/checkout-rapido.js usa: ids cr-*, classes
 * .cr-botao-texto e .cr-spinner no botão, data-polling no script e
 * <body data-autenticado> (marcado abaixo, antes do script, porque o <body>
 * é do layout). O formulário é enviado por fetch para POST /comprar/pix
 * (curso_evento_id, turma_id, _token, email, whatsapp, cpf) e o pagamento é
 * acompanhado por GET /comprar/status — nada disso mudou em relação à V1.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$curso = isset($curso) && is_array($curso) ? $curso : array();
$turma = isset($turma) && is_array($turma) ? $turma : array();
$cursoNome = (string) ($curso['nome'] ?? 'Curso');
$cursoId = (int) ($curso['id'] ?? 0);
$turmaId = (int) ($turma['id'] ?? 0);
$valor = (float) ($valor ?? 0);
$valorFormatado = $valor <= 0 ? 'Gratuito' : 'R$ ' . number_format($valor, 2, ',', '.');
$cargaHoraria = !empty($curso['carga_horaria']) ? (int) $curso['carga_horaria'] : 0;
$temCertificado = !empty($curso['certificado_previsto']);
$thumb = !empty($curso['thumbnail']) ? (string) $curso['thumbnail'] : '';
$pollingSegundos = (int) ($pollingSegundos ?? 3);
$prefill = isset($prefill) && is_array($prefill) ? $prefill : array('email' => '', 'whatsapp' => '', 'cpf' => '');
$prefill = array_merge(array('email' => '', 'whatsapp' => '', 'cpf' => ''), $prefill);

$valorDe = null;
if (!empty($curso['desconto_promocional']) && !empty($curso['valor'])) {
    $valorDe = 'R$ ' . number_format((float) $curso['valor'], 2, ',', '.');
}
?>
<script>document.body.setAttribute('data-autenticado', '<?= !empty($loggedIn) ? '1' : '0' ?>');</script>
<div class="ck cr">
  <header class="ck-cab">
    <p class="mao cr-eyebrow">Inscrição</p>
    <h1 class="t2" id="cr-curso-nome"><?= Helpers::e($cursoNome) ?></h1>
  </header>

  <div class="ck-grade">
    <aside class="ck-lado" aria-labelledby="cr-resumo">
      <div class="ck-curso">
        <?php if ($thumb !== ''): ?>
        <img src="<?= Helpers::e($thumb) ?>" alt="" width="640" height="360" decoding="async">
        <?php endif; ?>
        <h2 class="ficha-tit" id="cr-resumo">Sua vaga</h2>
        <ul class="cr-beneficios">
          <?php if ($cargaHoraria > 0): ?><li><?= caderno_icone('relogio') ?><?= $cargaHoraria ?> horas de conteúdo</li><?php endif; ?>
          <?php if ($temCertificado): ?><li><?= caderno_icone('certificado') ?>Certificado incluso</li><?php endif; ?>
          <li><?= caderno_icone('check') ?>Acesso imediato após o pagamento</li>
        </ul>
        <dl class="ficha-linhas">
          <div class="ck-valor"><dt>Valor</dt><dd>
            <?php if ($valorDe !== null): ?><s><span class="vh">de </span><?= Helpers::e($valorDe) ?></s><?php endif; ?>
            <b><?php if ($valorDe !== null): ?><span class="vh">por </span><?php endif; ?><?= Helpers::e($valorFormatado) ?></b>
          </dd></div>
        </dl>
      </div>
    </aside>

    <div class="ck-corpo">
      <!-- ETAPA 1 — formulário -->
      <section class="cr-etapa" id="cr-etapa-form" aria-labelledby="cr-form-titulo">
        <h2 class="t3" id="cr-form-titulo">Só isso e sua vaga está garantida</h2>

        <p class="postit erro largo cr-alerta" id="cr-erro-geral" role="alert" hidden></p>

        <form id="cr-form" class="ck-form" novalidate>
          <input type="hidden" name="curso_evento_id" value="<?= $cursoId ?>">
          <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
          <input type="hidden" name="_token" value="<?= Helpers::e($csrfToken ?? '') ?>">

          <div class="campo">
            <label for="cr-email">Seu e-mail</label>
            <input type="email" id="cr-email" name="email" inputmode="email" autocomplete="email"
                   autocapitalize="off" spellcheck="false" required
                   placeholder="voce@email.com"
                   value="<?= Helpers::e($prefill['email']) ?>"
                   aria-describedby="cr-email-erro">
            <p class="erro-msg" id="cr-email-erro" role="alert" hidden></p>
          </div>

          <div class="campo">
            <label for="cr-whatsapp">Seu WhatsApp</label>
            <input type="tel" id="cr-whatsapp" name="whatsapp" inputmode="numeric" autocomplete="tel-national"
                   required maxlength="16" placeholder="(11) 98888-7777"
                   value="<?= Helpers::e($prefill['whatsapp']) ?>"
                   aria-describedby="cr-whatsapp-erro">
            <p class="erro-msg" id="cr-whatsapp-erro" role="alert" hidden></p>
          </div>

          <div class="campo">
            <label for="cr-cpf">Seu CPF</label>
            <input type="text" id="cr-cpf" name="cpf" inputmode="numeric" autocomplete="off"
                   required maxlength="14" placeholder="000.000.000-00"
                   value="<?= Helpers::e($prefill['cpf']) ?>"
                   aria-describedby="cr-cpf-ajuda cr-cpf-erro">
            <p class="ajuda" id="cr-cpf-ajuda">Exigido pelo banco para emitir o Pix e pelo seu certificado.</p>
            <p class="erro-msg" id="cr-cpf-erro" role="alert" hidden></p>
          </div>

          <div class="form-acoes">
            <button type="submit" class="btn btn-bloco cr-botao" id="cr-enviar">
              <span class="cr-botao-texto">Gerar Pix e garantir minha vaga</span>
              <span class="cr-spinner" aria-hidden="true" hidden></span>
            </button>
          </div>

          <?php if (trim((string) ($textoLgpd ?? '')) !== ''): ?>
          <p class="ck-nota cr-lgpd"><?= Helpers::e($textoLgpd) ?></p>
          <?php endif; ?>
          <p class="ck-nota cr-lgpd">
            Ao continuar você concorda com os
            <a class="link" href="/v2/termos-de-uso" target="_blank" rel="noopener">termos de uso</a> e a
            <a class="link" href="/v2/politica-de-privacidade" target="_blank" rel="noopener">política de privacidade</a>.
            Para excluir seus dados, escreva para
            <a class="link" href="/v2/onde-estamos">nosso contato</a>.
          </p>
        </form>
      </section>

      <!-- ETAPA 2 — Pix -->
      <section class="cr-etapa" id="cr-etapa-pix" aria-labelledby="cr-pix-titulo" hidden>
        <h2 class="t3" id="cr-pix-titulo">Pague o Pix para liberar seu acesso</h2>

        <dl class="ficha-linhas">
          <div class="ck-valor"><dt>Valor</dt><dd><b id="cr-pix-valor"><?= Helpers::e($valorFormatado) ?></b></dd></div>
        </dl>

        <div class="cr-qr-area">
          <img class="cr-qr" id="cr-qr" src="" alt="QR Code do Pix" hidden>
          <p class="cr-qr-placeholder" id="cr-qr-placeholder">Gerando seu Pix…</p>
        </div>

        <div class="campo cr-copiacola">
          <label for="cr-copiacola">Ou copie o código Pix</label>
          <div class="cr-copiacola-linha">
            <input type="text" id="cr-copiacola" readonly value="" aria-label="Código Pix copia e cola">
            <button type="button" class="btn-sec cr-copiar" id="cr-copiar"><?= caderno_icone('copiar') ?>Copiar<?= caderno_ck_contorno() ?></button>
          </div>
          <p class="ajuda cr-copiado" id="cr-copiado" role="status" hidden>Código copiado.</p>
        </div>

        <p class="ck-texto cr-validade" id="cr-validade" hidden>
          Este Pix expira em <strong id="cr-contador">--:--</strong>.
        </p>

        <div class="postit largo cr-aguardando" id="cr-aguardando" role="status">
          <span class="cr-pulso" aria-hidden="true"></span>
          Aguardando o pagamento. Assim que cair, esta tela libera seu curso sozinha.
        </div>

        <div class="postit erro largo cr-expirado" id="cr-expirado" hidden>
          <b>Este Pix expirou.</b>
          <div class="form-acoes">
            <button type="button" class="btn" id="cr-novo-pix">Gerar um novo Pix</button>
          </div>
        </div>

        <p class="postit erro largo cr-alerta" id="cr-pix-erro" role="alert" hidden></p>
      </section>

      <!-- ETAPA 3 — pago -->
      <section class="cr-etapa" id="cr-etapa-pago" aria-labelledby="cr-pago-titulo" hidden>
        <div class="postit ok largo" role="status">
          <b id="cr-pago-titulo"><?= caderno_icone('check') ?> Pagamento confirmado</b>
          Sua vaga está garantida. Estamos abrindo seu curso…
        </div>
        <div class="form-acoes">
          <a class="btn" id="cr-ir-curso" href="/v2/aluno/">Entrar no curso</a>
        </div>
      </section>
    </div>
  </div>
</div>
<script src="/assets/js/checkout-rapido.js?v=20260820b" defer
        data-polling="<?= $pollingSegundos ?>"></script>
