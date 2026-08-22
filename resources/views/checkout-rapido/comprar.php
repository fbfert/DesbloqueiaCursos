<?php

use App\Core\Helpers;

/**
 * Checkout rapido — tela unica.
 *
 * Renderizada sem o layout do portal ($useLayout = false): nada de menu,
 * rodape ou link de saida. A unica acao da pagina e comprar.
 */

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

$valorDe = null;
if (!empty($curso['desconto_promocional']) && !empty($curso['valor'])) {
    $valorDe = 'R$ ' . number_format((float) $curso['valor'], 2, ',', '.');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo Helpers::e($cursoNome); ?> · Garantir minha vaga</title>
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?php echo Helpers::e($csrfToken ?? ''); ?>">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/checkout-rapido.css?v=20260820b">
</head>
<body data-autenticado="<?php echo !empty($loggedIn) ? '1' : '0'; ?>">

<main class="cr" role="main">

  <section class="cr-produto" aria-labelledby="cr-curso-nome">
    <?php if ($thumb !== ''): ?>
      <img class="cr-thumb" src="<?php echo Helpers::e($thumb); ?>" alt="">
    <?php endif; ?>
    <div class="cr-produto-texto">
      <p class="cr-eyebrow">Inscrição</p>
      <h1 class="cr-curso-nome" id="cr-curso-nome"><?php echo Helpers::e($cursoNome); ?></h1>
      <ul class="cr-beneficios">
        <?php if ($cargaHoraria > 0): ?><li><?php echo $cargaHoraria; ?> horas de conteúdo</li><?php endif; ?>
        <?php if ($temCertificado): ?><li>Certificado incluso</li><?php endif; ?>
        <li>Acesso imediato após o pagamento</li>
      </ul>
      <p class="cr-preco">
        <?php if ($valorDe !== null): ?><span class="cr-preco-de"><?php echo Helpers::e($valorDe); ?></span><?php endif; ?>
        <strong><?php echo Helpers::e($valorFormatado); ?></strong>
      </p>
    </div>
  </section>

  <!-- ETAPA 1 — formulario -->
  <section class="cr-etapa" id="cr-etapa-form" aria-labelledby="cr-form-titulo">
    <h2 class="cr-h2" id="cr-form-titulo">Só isso e sua vaga está garantida</h2>

    <p class="cr-alerta cr-alerta-erro" id="cr-erro-geral" role="alert" hidden></p>

    <form id="cr-form" novalidate>
      <input type="hidden" name="curso_evento_id" value="<?php echo $cursoId; ?>">
      <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
      <input type="hidden" name="_token" value="<?php echo Helpers::e($csrfToken ?? ''); ?>">

      <div class="cr-campo">
        <label for="cr-email">Seu e-mail</label>
        <input type="email" id="cr-email" name="email" inputmode="email" autocomplete="email"
               autocapitalize="off" spellcheck="false" required
               placeholder="voce@email.com"
               value="<?php echo Helpers::e($prefill['email']); ?>"
               aria-describedby="cr-email-erro">
        <p class="cr-erro" id="cr-email-erro" role="alert" hidden></p>
      </div>

      <div class="cr-campo">
        <label for="cr-whatsapp">Seu WhatsApp</label>
        <input type="tel" id="cr-whatsapp" name="whatsapp" inputmode="numeric" autocomplete="tel-national"
               required maxlength="16" placeholder="(11) 98888-7777"
               value="<?php echo Helpers::e($prefill['whatsapp']); ?>"
               aria-describedby="cr-whatsapp-erro">
        <p class="cr-erro" id="cr-whatsapp-erro" role="alert" hidden></p>
      </div>

      <div class="cr-campo">
        <label for="cr-cpf">Seu CPF</label>
        <input type="text" id="cr-cpf" name="cpf" inputmode="numeric" autocomplete="off"
               required maxlength="14" placeholder="000.000.000-00"
               value="<?php echo Helpers::e($prefill['cpf']); ?>"
               aria-describedby="cr-cpf-ajuda cr-cpf-erro">
        <p class="cr-ajuda" id="cr-cpf-ajuda">Exigido pelo banco para emitir o Pix e pelo seu certificado.</p>
        <p class="cr-erro" id="cr-cpf-erro" role="alert" hidden></p>
      </div>

      <button type="submit" class="cr-botao" id="cr-enviar">
        <span class="cr-botao-texto">Gerar Pix e garantir minha vaga</span>
        <span class="cr-spinner" aria-hidden="true" hidden></span>
      </button>

      <p class="cr-lgpd"><?php echo Helpers::e($textoLgpd ?? ''); ?></p>
      <p class="cr-lgpd cr-lgpd-links">
        Ao continuar você concorda com os
        <a href="/termos-de-uso" target="_blank" rel="noopener">termos de uso</a> e a
        <a href="/politica-de-privacidade" target="_blank" rel="noopener">política de privacidade</a>.
        Para excluir seus dados, escreva para
        <a href="/contato">nosso contato</a>.
      </p>
    </form>
  </section>

  <!-- ETAPA 2 — Pix -->
  <section class="cr-etapa" id="cr-etapa-pix" aria-labelledby="cr-pix-titulo" hidden>
    <h2 class="cr-h2" id="cr-pix-titulo">Pague o Pix para liberar seu acesso</h2>

    <div class="cr-pix-valor">
      <span>Valor</span>
      <strong id="cr-pix-valor"><?php echo Helpers::e($valorFormatado); ?></strong>
    </div>

    <div class="cr-qr-area">
      <img class="cr-qr" id="cr-qr" src="" alt="QR Code do Pix" hidden>
      <p class="cr-qr-placeholder" id="cr-qr-placeholder">Gerando seu Pix…</p>
    </div>

    <label class="cr-copiacola-label" for="cr-copiacola">Ou copie o código Pix</label>
    <div class="cr-copiacola">
      <input type="text" id="cr-copiacola" readonly value="" aria-label="Código Pix copia e cola">
      <button type="button" class="cr-copiar" id="cr-copiar">Copiar</button>
    </div>
    <p class="cr-copiado" id="cr-copiado" role="status" hidden>Código copiado.</p>

    <p class="cr-validade" id="cr-validade" hidden>
      Este Pix expira em <strong id="cr-contador">--:--</strong>.
    </p>

    <div class="cr-aguardando" id="cr-aguardando" role="status">
      <span class="cr-pulso" aria-hidden="true"></span>
      Aguardando o pagamento. Assim que cair, esta tela libera seu curso sozinha.
    </div>

    <div class="cr-expirado" id="cr-expirado" hidden>
      <p>Este Pix expirou.</p>
      <button type="button" class="cr-botao cr-botao-secundario" id="cr-novo-pix">Gerar um novo Pix</button>
    </div>

    <p class="cr-alerta cr-alerta-erro" id="cr-pix-erro" role="alert" hidden></p>
  </section>

  <!-- ETAPA 3 — pago -->
  <section class="cr-etapa cr-sucesso" id="cr-etapa-pago" aria-labelledby="cr-pago-titulo" hidden>
    <div class="cr-check" aria-hidden="true">✓</div>
    <h2 class="cr-h2" id="cr-pago-titulo">Pagamento confirmado</h2>
    <p>Sua vaga está garantida. Estamos abrindo seu curso…</p>
    <p><a class="cr-botao cr-botao-secundario" id="cr-ir-curso" href="/aluno/meus-cursos">Entrar no curso</a></p>
  </section>

</main>

<script src="/assets/js/checkout-rapido.js?v=20260820b" defer
        data-polling="<?php echo $pollingSegundos; ?>"></script>
</body>
</html>
