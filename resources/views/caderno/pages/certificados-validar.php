<?php
/**
 * Validar certificado no tema caderno. Mesmas variáveis e campos da V2
 * (V2\CertificadoValidacaoController): certificado (nome_participante,
 * cpf_mascarado, curso_nome, codigo, pdf_url), codigo, cpf e erro
 * (tipo aviso|erro, mensagem). POST em /v2/certificados/validar/ com `codigo`
 * e `cpf` (opcional); o _token de CSRF é injetado pelo View::render.
 *
 * O carimbo "VÁLIDO" é só ornamento (aria-hidden): a validade está dita em
 * texto normal no título do resultado.
 */

use App\Core\Helpers;

$certificado = isset($certificado) && is_array($certificado) ? $certificado : null;
$erro = isset($erro) && is_array($erro) ? $erro : null;
$codigo = isset($codigo) ? (string) $codigo : '';
$cpf = isset($cpf) ? (string) $cpf : '';
$erroAviso = $erro && (isset($erro['tipo']) ? $erro['tipo'] : '') === 'aviso';
$temPdf = $certificado && trim((string) (isset($certificado['pdf_url']) ? $certificado['pdf_url'] : '')) !== '';
$certCampos = array(
    'Titular' => 'nome_participante',
    'CPF' => 'cpf_mascarado',
    'Curso' => 'curso_nome',
    'Código' => 'codigo',
);
?>
<section class="sec cert" aria-labelledby="cert-titulo">
  <p class="selo tinta">Validação pública</p>
  <h1 class="t1" id="cert-titulo">Validar certificado.</h1>
  <p class="lead">Qualquer pessoa pode confirmar a autenticidade do documento informando o código do certificado e, se desejar, o CPF.</p>

<?php if ($certificado): ?>
  <section class="cert-folha" aria-labelledby="cert-ok" data-carimbar>
    <div class="cert-resultado" role="status" aria-live="polite" id="cert-resultado" tabindex="-1">
      <h2 class="t2" id="cert-ok">Certificado válido</h2>
      <p>A autenticidade foi confirmada com os dados informados.</p>
    </div>
    <dl class="ficha-linhas">
      <?php foreach ($certCampos as $rotulo => $chave): ?>
      <div><dt><?= Helpers::e($rotulo) ?></dt><dd><?= Helpers::e((string) (isset($certificado[$chave]) ? $certificado[$chave] : '')) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php
    $linhas = array('Desbloqueia Cursos', 'VÁLIDO', 'certificado');
    $cor = 'verde';
    require BASE_PATH . '/resources/views/caderno/partials/carimbo.php';
    ?>
  </section>
  <div class="cert-acoes">
    <?php if ($temPdf): ?>
    <a class="btn" href="<?= Helpers::e((string) $certificado['pdf_url']) ?>"><?= caderno_icone('certificado') ?> Abrir certificado</a>
    <?php endif; ?>
    <a class="btn-sec" href="/v2/certificados/validar/">Validar outro certificado<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg></a>
  </div>

<?php else: ?>
  <?php if ($erro): ?>
  <div class="postit erro largo cert-aviso" role="alert" id="cert-erro" tabindex="-1">
    <strong><?= $erroAviso ? 'Atenção' : 'Não foi possível validar' ?></strong>
    <?= Helpers::e((string) (isset($erro['mensagem']) ? $erro['mensagem'] : '')) ?>
  </div>
  <?php endif; ?>

  <form class="cert-form" method="post" action="/v2/certificados/validar/" id="cert-validacao-form">
    <div class="campo">
      <label for="cert-codigo">Código do certificado</label>
      <input type="text" id="cert-codigo" name="codigo" value="<?= Helpers::e($codigo) ?>"
             required autocomplete="off" spellcheck="false" aria-describedby="cert-ajuda"
             placeholder="Código impresso no certificado">
    </div>
    <div class="campo">
      <label for="cert-cpf">CPF <span class="opcional">(opcional)</span></label>
      <input type="text" id="cert-cpf" name="cpf" value="<?= Helpers::e($cpf) ?>"
             autocomplete="off" inputmode="numeric" data-mask-cpf
             placeholder="Para conferir o titular">
    </div>
    <p class="ajuda cert-ajuda" id="cert-ajuda">A consulta confirma se o certificado foi emitido pela instituição, mantendo a autenticidade acessível a qualquer pessoa.</p>
    <div class="cert-acoes">
      <button type="submit" class="btn"><?= caderno_icone('certificado') ?> Validar certificado</button>
      <a class="btn-sec" href="<?= Helpers::e(isset($homeHref) ? (string) $homeHref : '/v2/') ?>">Voltar ao início<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg></a>
    </div>
  </form>
<?php endif; ?>
</section>
