<?php use App\Core\Helpers; ?>
<?php
$certificado = isset($certificado) && is_array($certificado) ? $certificado : array();
$versaoOnline = isset($versaoOnline) && is_array($versaoOnline) ? $versaoOnline : array();
$paginaPrimeiraHtml = isset($versaoOnline['pagina_primeira_html']) ? (string) $versaoOnline['pagina_primeira_html'] : '';
$paginaSegundaHtml = isset($versaoOnline['pagina_segunda_html']) ? (string) $versaoOnline['pagina_segunda_html'] : '';
$temSegundaPagina = trim($paginaSegundaHtml) !== '';
$codigoCertificado = isset($certificado['codigo']) ? (string) $certificado['codigo'] : '';
$pdfUrl = !empty($certificado['pdf_url']) ? (string) $certificado['pdf_url'] : '/certificados/pdf?codigo=' . urlencode($codigoCertificado);
$validacaoUrl = !empty($certificado['validacao_url']) ? (string) $certificado['validacao_url'] : '/certificados/validar?codigo=' . urlencode($codigoCertificado);
$baseHref = Helpers::url('/');

if ($baseHref !== '') {
    $baseTag = '<base href="' . Helpers::e($baseHref) . '">';
    if ($paginaPrimeiraHtml !== '' && stripos($paginaPrimeiraHtml, '<base ') === false) {
        $paginaPrimeiraHtml = str_replace('<meta charset="utf-8">', '<meta charset="utf-8">' . $baseTag, $paginaPrimeiraHtml);
    }
    if ($paginaSegundaHtml !== '' && stripos($paginaSegundaHtml, '<base ') === false) {
        $paginaSegundaHtml = str_replace('<meta charset="utf-8">', '<meta charset="utf-8">' . $baseTag, $paginaSegundaHtml);
    }
}
?>

<style>
    .certificados-versao-online-page .certificados-versao-online-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        justify-content: center;
    }

    .certificados-versao-online-page .certificados-versao-online-toolbar .pill {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .certificados-versao-online-page .pill--secondary {
        border: 1px solid rgba(124, 58, 237, 0.25);
        background: rgba(255, 255, 255, 0.9);
        color: #6d28d9;
    }

    .certificados-versao-online-page .pill--secondary:hover,
    .certificados-versao-online-page .pill--secondary:focus-visible {
        background: rgba(245, 243, 255, 0.98);
        border-color: rgba(124, 58, 237, 0.4);
        color: #5b21b6;
    }

    .certificados-versao-online-page .certificados-versao-online-frame {
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 20px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 18px 55px rgba(15, 23, 42, 0.09);
    }

    .certificados-versao-online-page .certificados-versao-online-iframe {
        width: 100%;
        height: 860px;
        border: 0;
        display: block;
        background: #fff;
    }

    .certificados-versao-online-page .certificados-versao-online-iframe--segunda {
        height: 980px;
    }

    .certificados-versao-online-page .certificados-versao-online-obs {
        max-width: 820px;
        margin: 0 auto;
        text-align: center;
        color: #475569;
    }

    @media (max-width: 780px) {
        .certificados-versao-online-page .certificados-versao-online-iframe,
        .certificados-versao-online-page .certificados-versao-online-iframe--segunda {
            height: 720px;
        }
    }

    @media print {
        .certificados-versao-online-toolbar,
        .certificados-versao-online-obs {
            display: none !important;
        }

        .certificados-versao-online-page .certificados-versao-online-frame {
            box-shadow: none;
            border: 0;
        }

        .certificados-versao-online-page .certificados-versao-online-iframe,
        .certificados-versao-online-page .certificados-versao-online-iframe--segunda {
            height: 1000px;
        }
    }
</style>

<div class="front-section-stack certificados-versao-online-page">
    <section class="page-header front-section certificados-versao-online-hero">
        <span class="certificados-validacao-badge">Versão online pública</span>
        <h1>Certificado em HTML</h1>
        <p>Visualização pública do certificado renderizada com os mesmos dados e placeholders do documento emitido.</p>
    </section>

    <section class="front-section certificados-versao-online-toolbar" aria-label="Ações do certificado">
        <a class="pill" href="<?php echo Helpers::e($pdfUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir PDF</a>
        <button type="button" class="pill pill--secondary" data-certificados-versao-online-print>Imprimir certificado</button>
        <a class="pill pill--secondary" href="<?php echo Helpers::e($validacaoUrl); ?>">Voltar para validação</a>
    </section>

    <section class="panel front-section">
        <div class="panel-header">
            <div>
                <h2>Primeira página</h2>
                <p>Renderização pública do template do certificado.</p>
            </div>
        </div>
        <div class="certificados-versao-online-frame">
            <iframe
                class="certificados-versao-online-iframe"
                title="Certificado - primeira página"
                srcdoc="<?php echo Helpers::e($paginaPrimeiraHtml); ?>"
                loading="eager"
            ></iframe>
        </div>
    </section>

    <?php if ($temSegundaPagina): ?>
        <section class="panel front-section">
            <div class="panel-header">
                <div>
                    <h2>Segunda página</h2>
                    <p>Conteúdo programático e informações complementares.</p>
                </div>
            </div>
            <div class="certificados-versao-online-frame">
                <iframe
                    class="certificados-versao-online-iframe certificados-versao-online-iframe--segunda"
                    title="Certificado - segunda página"
                    srcdoc="<?php echo Helpers::e($paginaSegundaHtml); ?>"
                    loading="lazy"
                ></iframe>
            </div>
        </section>
    <?php endif; ?>

    <section class="front-section certificados-versao-online-obs">
        <p>Para uma experiência de impressão mais fiel, use a ação <strong>Imprimir certificado</strong> ou o PDF oficial do documento.</p>
    </section>
</div>

<script>
(function () {
    var button = document.querySelector('[data-certificados-versao-online-print]');
    if (!button) {
        return;
    }

    button.addEventListener('click', function () {
        window.print();
    });
})();
</script>
