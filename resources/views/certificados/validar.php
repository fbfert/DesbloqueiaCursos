<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Validar certificado</h1>
    <p>Sem login, por codigo.</p>
</section>

<form method="post" action="/certificados/validar" class="form-grid">
    <label>
        Codigo do certificado
        <input type="text" name="codigo" value="<?php echo Helpers::e($codigo); ?>">
    </label>
    <label>
        CPF opcional
        <input type="text" name="cpf" value="<?php echo Helpers::e($cpf); ?>">
    </label>
    <button type="submit">Validar</button>
</form>

<?php if (!empty($resultado)): ?>
    <?php if (!empty($resultado['ok'])): ?>
        <section class="panel">
            <h2>Certificado valido</h2>
            <p><strong><?php echo Helpers::e($resultado['certificado']['nome_participante']); ?></strong></p>
            <p>CPF: <?php echo Helpers::e($resultado['certificado']['cpf_mascarado']); ?></p>
            <p>Curso: <?php echo Helpers::e($resultado['certificado']['curso_nome']); ?></p>
            <p>Codigo: <?php echo Helpers::e($resultado['certificado']['codigo']); ?></p>
            <p><a href="/certificados/show?codigo=<?php echo urlencode($resultado['certificado']['codigo']); ?>">Abrir versao online</a></p>
        </section>
    <?php else: ?>
        <section class="auth-message auth-message-error">
            <p><?php echo Helpers::e(isset($resultado['message']) ? $resultado['message'] : 'Certificado invalido.'); ?></p>
        </section>
    <?php endif; ?>
<?php endif; ?>
