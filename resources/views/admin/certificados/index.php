<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Certificados</h1>
    <p>Emissão manual, reemissao e validacao publica.</p>
</section>

<?php if (!empty($success)): ?>
    <section class="auth-message auth-message-success">
        <p><?php echo Helpers::e($success); ?></p>
    </section>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <section class="auth-message auth-message-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo Helpers::e($error); ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="card-grid">
    <article class="status-card">
        <strong>Aptos para emissao</strong>
        <span><?php echo count($aptos); ?> inscricoes aptas</span>
        <a href="/admin/certificados/emitir">Emitir agora</a>
    </article>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Certificados emitidos</h2>
            <p>Ultimos registros do portal.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Participante</th>
                    <th>Curso</th>
                    <th>Status</th>
                    <th>Emissão</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certificados as $certificado): ?>
                    <tr>
                        <td><?php echo Helpers::e($certificado['codigo']); ?></td>
                        <td><?php echo Helpers::e($certificado['participante_nome']); ?><br><small><?php echo Helpers::e($certificado['cpf_mascarado']); ?></small></td>
                        <td><?php echo Helpers::e($certificado['curso_nome']); ?></td>
                        <td><span class="pill"><?php echo Helpers::e($certificado['status']); ?></span></td>
                        <td><?php echo Helpers::e($certificado['emitido_em']); ?></td>
                        <td><a href="/admin/certificados/show?certificado_id=<?php echo (int) $certificado['id']; ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

