<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Certificado online</h1>
    <p><?php echo Helpers::e($certificado['codigo']); ?></p>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?php echo Helpers::e($certificado['nome_participante']); ?></h2>
            <p>Versao online com validacao publica.</p>
        </div>
    </div>

    <div class="card-grid">
        <article class="status-card">
            <strong>CPF</strong>
            <span><?php echo Helpers::e($certificado['cpf_mascarado']); ?></span>
        </article>
        <article class="status-card">
            <strong>Curso</strong>
            <span><?php echo Helpers::e($certificado['curso_nome']); ?></span>
        </article>
        <article class="status-card">
            <strong>Status</strong>
            <span><?php echo Helpers::e($certificado['status']); ?></span>
        </article>
    </div>

    <div class="pill-row" style="margin-top:16px;">
        <a class="pill" href="<?php echo Helpers::e($certificado['pdf_url']); ?>">Abrir PDF</a>
        <a class="pill" href="<?php echo Helpers::e($certificado['validacao_url']); ?>">Validar novamente</a>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>QR Code</h2>
            <p>O QR codifica o codigo do certificado.</p>
        </div>
    </div>
    <div style="max-width:240px;">
        <img src="<?php echo Helpers::e($certificado['qr_svg']); ?>" alt="QR Code do certificado" style="width:100%;height:auto;">
    </div>
</section>
