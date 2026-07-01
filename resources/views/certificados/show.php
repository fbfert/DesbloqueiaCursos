<?php use App\Core\Helpers; ?>

<div class="front-section-stack">
    <?php if (!empty($blockedMessage)): ?>
        <section class="auth-message auth-message-error front-section">
            <p><?php echo Helpers::e($blockedMessage); ?></p>
        </section>
    <?php endif; ?>

    <?php $configCertificados = isset($configCertificados) && is_array($configCertificados) ? $configCertificados : array(); ?>
    <?php if (empty($certificado)): ?>
        <section class="panel front-section">
            <p class="muted">A consulta pública deste certificado está indisponível no momento.</p>
        </section>
    <?php else: ?>
    <section class="page-header front-section">
        <h1>Certificado online</h1>
        <p><?php echo Helpers::e($certificado['codigo']); ?></p>
    </section>

    <div class="front-card-section front-section">
        <section class="panel front-card">
        <div class="panel-header">
            <div>
                <h2><?php echo Helpers::e($certificado['nome_participante']); ?></h2>
                <p>Versão online com validação pública.</p>
            </div>
        </div>

        <div class="card-grid front-card-grid">
            <article class="status-card front-card">
                <strong>CPF</strong>
                <span><?php echo Helpers::e($certificado['cpf_mascarado']); ?></span>
            </article>
            <article class="status-card front-card">
                <strong>Curso</strong>
                <span><?php echo Helpers::e($certificado['curso_nome']); ?></span>
            </article>
            <article class="status-card front-card">
                <strong>Status</strong>
                <span><?php echo Helpers::e($certificado['status']); ?></span>
            </article>
        </div>

        <div class="pill-row" style="margin-top:16px;">
            <?php if (!empty($canSeePdf) && !empty($configCertificados['certificados_permitir_download'])): ?>
                <a class="pill" href="<?php echo Helpers::e($certificado['pdf_url']); ?>">Abrir PDF</a>
            <?php endif; ?>
            <a class="pill" href="<?php echo Helpers::e($certificado['validacao_url']); ?>">Validar novamente</a>
        </div>
        </section>

        <section class="panel front-card">
        <div class="panel-header">
            <div>
                <h2>QR Code</h2>
                <p>O QR codifica o código do certificado.</p>
            </div>
        </div>
        <div style="max-width:240px;">
            <img src="<?php echo Helpers::e($certificado['qr_svg']); ?>" alt="QR Code do certificado" style="width:100%;height:auto;">
        </div>
        </section>
    <?php endif; ?>
    </div>
</div>
