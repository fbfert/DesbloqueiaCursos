<?php
use App\Core\Helpers;

$certificado = isset($certificado) && is_array($certificado) ? $certificado : null;
$erro = isset($erro) && is_array($erro) ? $erro : null;
$codigo = isset($codigo) ? (string) $codigo : '';
$cpf = isset($cpf) ? (string) $cpf : '';
$erroTipo = $erro && ($erro['tipo'] ?? '') === 'aviso' ? 'aviso' : 'erro';
$temPdf = $certificado && trim((string) ($certificado['pdf_url'] ?? '')) !== '';
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-cert-validar">
  <header class="v2-cert-validar-hero">
    <span class="v2-badge v2-badge-novo">Validação pública</span>
    <h1 class="v2-h2" style="margin:8px 0;">Validar certificado</h1>
    <p class="v2-muted">Qualquer pessoa pode confirmar a autenticidade do documento informando o código do certificado e, se desejar, o CPF.</p>
  </header>

  <?php if ($certificado): ?>
    <section class="v2-cert-result is-ok" role="status" aria-live="polite" id="v2-cert-resultado" tabindex="-1">
      <div class="v2-cert-result-head">
        <span class="v2-cert-seal"><i class="ti ti-rosette-discount-check-filled" aria-hidden="true"></i></span>
        <div>
          <strong>Certificado válido</strong>
          <p class="v2-muted v2-sm" style="margin:2px 0 0;">A autenticidade foi confirmada com os dados informados.</p>
        </div>
      </div>
      <div class="v2-cert-result-grid">
        <div>
          <span class="v2-muted v2-sm">Titular</span>
          <b><?php echo Helpers::e((string) ($certificado['nome_participante'] ?? '')); ?></b>
        </div>
        <div>
          <span class="v2-muted v2-sm">CPF</span>
          <b><?php echo Helpers::e((string) ($certificado['cpf_mascarado'] ?? '')); ?></b>
        </div>
        <div>
          <span class="v2-muted v2-sm">Curso</span>
          <b><?php echo Helpers::e((string) ($certificado['curso_nome'] ?? '')); ?></b>
        </div>
        <div>
          <span class="v2-muted v2-sm">Código</span>
          <b><?php echo Helpers::e((string) ($certificado['codigo'] ?? '')); ?></b>
        </div>
      </div>
      <div class="v2-quiz-actions">
        <?php if ($temPdf): ?>
          <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) $certificado['pdf_url']); ?>"><i class="ti ti-file-certificate"></i> Abrir certificado</a>
        <?php endif; ?>
        <a class="v2-btn v2-btn-ghost" href="/v2/certificados/validar"><i class="ti ti-refresh"></i> Validar outro certificado</a>
      </div>
    </section>

  <?php else: ?>
    <?php if ($erro): ?>
      <div class="v2-callout <?php echo $erroTipo === 'aviso' ? 'v2-callout-warning' : 'v2-callout-danger'; ?>" role="alert" aria-live="assertive" id="v2-cert-erro" tabindex="-1">
        <i class="ti <?php echo $erroTipo === 'aviso' ? 'ti-alert-triangle' : 'ti-circle-x'; ?>" aria-hidden="true"></i>
        <span>
          <strong><?php echo $erroTipo === 'aviso' ? 'Atenção' : 'Não foi possível validar'; ?>:</strong>
          <?php echo Helpers::e((string) ($erro['mensagem'] ?? '')); ?>
        </span>
      </div>
    <?php endif; ?>

    <section class="v2-block">
      <form method="post" action="/v2/certificados/validar" class="v2-cert-form" id="v2-cert-validacao-form" data-native-submit>
        <div class="v2-field">
          <label for="v2-cert-codigo">Código do certificado</label>
          <input type="text" id="v2-cert-codigo" name="codigo" class="v2-input"
                 value="<?php echo Helpers::e($codigo); ?>"
                 required autocomplete="off" spellcheck="false"
                 aria-describedby="v2-cert-ajuda"
                 placeholder="Informe o código impresso no certificado">
        </div>
        <div class="v2-field">
          <label for="v2-cert-cpf">CPF <span class="v2-muted v2-sm">(opcional)</span></label>
          <input type="text" id="v2-cert-cpf" name="cpf" class="v2-input"
                 value="<?php echo Helpers::e($cpf); ?>"
                 autocomplete="off" inputmode="numeric" data-mask-cpf
                 placeholder="Somente se quiser conferir o titular">
        </div>
        <p class="v2-muted v2-sm" id="v2-cert-ajuda">A consulta confirma se o certificado foi emitido pela instituição, mantendo a autenticidade acessível a qualquer pessoa.</p>
        <div class="v2-quiz-actions">
          <button type="submit" class="v2-btn v2-btn-primary" data-cert-btn data-loading-label="Consultando…"><i class="ti ti-shield-check"></i> Validar certificado</button>
          <a class="v2-btn v2-btn-ghost" href="/v2/"><i class="ti ti-arrow-left"></i> Voltar ao início</a>
        </div>
      </form>
    </section>
  <?php endif; ?>
</section>
