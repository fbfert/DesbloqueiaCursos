<?php use App\Core\Helpers; ?>
<?php
$resultadoOk = !empty($resultado) && !empty($resultado['ok']);
$mensagemResultado = null;
$mensagemResultadoTipo = 'error';

if (!empty($resultado) && empty($resultado['ok'])) {
    $mensagensAmigaveis = array(
        'Certificado nao encontrado.' => 'Não localizamos um certificado com os dados informados. Confira o código e tente novamente.',
        'CPF nao confere com o certificado.' => 'O CPF informado não confere com o certificado.',
        'Certificado nao esta ativo para validacao.' => 'Este certificado ainda não está ativo para validação pública.',
        'A validacao publica esta temporariamente indisponivel.' => 'A validação pública está temporariamente indisponível.',
        'A validação pública está temporariamente indisponível.' => 'A validação pública está temporariamente indisponível.',
    );

    $mensagemOriginal = isset($resultado['message']) ? trim((string) $resultado['message']) : 'Certificado inválido.';
    $mensagemResultado = isset($mensagensAmigaveis[$mensagemOriginal]) ? $mensagensAmigaveis[$mensagemOriginal] : $mensagemOriginal;

    if (strpos($mensagemOriginal, 'CPF') !== false) {
        $mensagemResultadoTipo = 'warning';
    }
}

$codigoCertificado = isset($codigo) ? (string) $codigo : '';
$cpfInformado = isset($cpf) ? (string) $cpf : '';
$certificado = $resultadoOk && !empty($resultado['certificado']) && is_array($resultado['certificado']) ? $resultado['certificado'] : array();
$urlValidacao = !empty($certificado['validacao_url']) ? $certificado['validacao_url'] : '/certificados/show?codigo=' . urlencode(isset($certificado['codigo']) ? $certificado['codigo'] : $codigoCertificado);
$temPdf = !empty($certificado['pdf_url']);
?>

<style>
    .certificados-validacao-page--success [data-certificados-validacao-form-card],
    .certificados-validacao-page--success [data-certificados-validacao-note] {
        display: none;
    }

    [data-certificados-validacao-result][hidden] {
        display: none !important;
    }

    .certificados-validacao-page .certificados-validacao-actions-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    .certificados-validacao-page .pill--secondary {
        border: 1px solid rgba(99, 102, 241, 0.35);
        background: rgba(255, 255, 255, 0.92);
        color: #5b21b6;
        box-shadow: none;
    }

    .certificados-validacao-page .pill--secondary:hover,
    .certificados-validacao-page .pill--secondary:focus-visible {
        border-color: rgba(99, 102, 241, 0.55);
        background: rgba(244, 242, 255, 0.96);
        color: #4c1d95;
    }
</style>

<div class="front-section-stack certificados-validacao-page<?php echo $resultadoOk ? ' certificados-validacao-page--success' : ''; ?>" data-certificados-validacao-page>
    <section class="page-header front-section certificados-validacao-hero">
        <span class="certificados-validacao-badge">Validação pública</span>
        <h1>Validar certificado</h1>
        <p>Qualquer pessoa pode confirmar a autenticidade do documento usando o código do certificado e, opcionalmente, o CPF.</p>
    </section>

    <section class="panel front-section certificados-validacao-form-card" data-certificados-validacao-form-card>
        <div class="panel-header certificados-validacao-form-header">
            <div>
                <h2>Consultar certificado</h2>
                <p>Preencha os dados abaixo para validar o documento.</p>
            </div>
        </div>

        <form method="post" action="/certificados/validar" class="form-grid certificados-validacao-form" data-certificados-validacao-form>
            <div class="certificados-validacao-fields">
                <label class="certificados-validacao-field" for="certificado_codigo">
                    <span>Código do certificado</span>
                    <input
                        type="text"
                        id="certificado_codigo"
                        name="codigo"
                        value="<?php echo Helpers::e($codigoCertificado); ?>"
                        required
                        autocomplete="off"
                        placeholder="Ex.: ABC123"
                    >
                </label>
                <label class="certificados-validacao-field" for="certificado_cpf">
                    <span>CPF opcional</span>
                    <input
                        type="text"
                        id="certificado_cpf"
                        name="cpf"
                        value="<?php echo Helpers::e($cpfInformado); ?>"
                        autocomplete="off"
                        placeholder="Ex.: 000.000.000-00"
                    >
                </label>
            </div>

            <div class="certificados-validacao-actions">
                <button type="submit" class="button-link certificados-validacao-submit">Validar</button>
            </div>
        </form>

        <div class="certificados-validacao-note" data-certificados-validacao-note>
            <strong>Consulta pública</strong>
            <p>Esta verificação confirma se o certificado foi emitido pela instituição e mantém a autenticidade do documento acessível a qualquer pessoa.</p>
        </div>
    </section>

    <?php if ($resultadoOk): ?>
        <section class="status-card front-section certificados-validacao-result certificados-validacao-result--success" role="status" aria-live="polite" data-certificados-validacao-result>
            <div class="certificados-validacao-result__badge certificados-validacao-result__badge--success">
                <span aria-hidden="true">✓</span>
                <span>Certificado válido</span>
            </div>
            <div class="certificados-validacao-result__header">
                <div>
                    <h2>Certificado válido</h2>
                    <p>A autenticidade foi confirmada com os dados informados.</p>
                </div>
            </div>
            <div class="certificados-validacao-result__grid">
                <article class="status-card certificados-validacao-result__item">
                    <strong>Titular</strong>
                    <span><?php echo Helpers::e($certificado['nome_participante']); ?></span>
                </article>
                <article class="status-card certificados-validacao-result__item">
                    <strong>CPF</strong>
                    <span><?php echo Helpers::e($certificado['cpf_mascarado']); ?></span>
                </article>
                <article class="status-card certificados-validacao-result__item">
                    <strong>Curso</strong>
                    <span><?php echo Helpers::e($certificado['curso_nome']); ?></span>
                </article>
                <article class="status-card certificados-validacao-result__item">
                    <strong>Código</strong>
                    <span class="certificados-validacao-code"><?php echo Helpers::e($certificado['codigo']); ?></span>
                </article>
            </div>
            <div class="pill-row certificados-validacao-actions-row">
                <?php if ($temPdf): ?>
                    <a class="pill" href="<?php echo Helpers::e($certificado['pdf_url']); ?>">Abrir certificado</a>
                <?php endif; ?>
                <button type="button" class="pill pill--secondary" data-certificados-validacao-novo>Validar outro certificado</button>
            </div>
        </section>
    <?php elseif (!empty($mensagemResultado)): ?>
        <section class="auth-message certificados-validacao-alert certificados-validacao-alert--<?php echo Helpers::e($mensagemResultadoTipo); ?> front-section" role="alert">
            <div class="certificados-validacao-alert__icon" aria-hidden="true">
                <?php if ($mensagemResultadoTipo === 'warning'): ?>
                    !
                <?php else: ?>
                    i
                <?php endif; ?>
            </div>
            <div class="certificados-validacao-alert__body">
                <strong><?php echo $mensagemResultadoTipo === 'warning' ? 'Atenção' : 'Não foi possível validar'; ?></strong>
                <p><?php echo Helpers::e($mensagemResultado); ?></p>
            </div>
        </section>
    <?php endif; ?>

</div>

<script>
(function () {
    var form = document.querySelector('[data-certificados-validacao-form]');
    var page = document.querySelector('[data-certificados-validacao-page]');
    var result = document.querySelector('[data-certificados-validacao-result]');
    var formCard = document.querySelector('[data-certificados-validacao-form-card]');
    var noteCard = document.querySelector('[data-certificados-validacao-note]');
    var novoBotao = document.querySelector('[data-certificados-validacao-novo]');
    var codigoField = document.getElementById('certificado_codigo');
    var cpfField = document.getElementById('certificado_cpf');

    if (novoBotao && page && formCard && noteCard && result && codigoField && cpfField) {
        novoBotao.addEventListener('click', function () {
            result.hidden = true;
            page.classList.remove('certificados-validacao-page--success');
            formCard.hidden = false;
            noteCard.hidden = false;
            codigoField.value = '';
            cpfField.value = '';
            codigoField.focus();
        });
    }

    if (!form) {
        return;
    }

    form.addEventListener('submit', function () {
        var button = form.querySelector('button[type="submit"]');
        if (!button) {
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.dataset.originalText = button.textContent;
        button.textContent = 'Consultando...';
    });
})();
</script>
