<?php use App\Core\Helpers; ?>

<div class="admin-page">
<?php $configCertificados = isset($configCertificados) && is_array($configCertificados) ? $configCertificados : array(); ?>
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Certificados</h1>
        <p class="admin-page__subtitle">Emissão manual, reemissão e validação pública.</p>
    </div>
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

<?php if (empty($configCertificados['certificados_habilitado']) || empty($configCertificados['certificados_emissao_habilitada'])): ?>
    <section class="status-card" style="margin-bottom:12px;border-left:4px solid #b45309;background:#fff7ed;">
        <strong>Emissão de certificados desativada</strong>
        <p class="muted" style="margin:8px 0 0 0;">A emissão de certificados está desativada nas configurações globais.</p>
    </section>
<?php endif; ?>

<section class="card-grid">
    <article class="status-card">
        <strong>Aptos para emissão</strong>
        <span><?php echo count($aptos); ?> inscrições aptas</span>
        <a href="/admin/certificados/emitir">Emitir agora</a>
    </article>
    <article class="status-card">
        <strong>Emissão Manual</strong>
        <span>Filtre cursos, turmas e alunos para emissão individual ou em lote.</span>
        <a href="/admin/certificados/emissao-manual">Acessar emissão manual</a>
    </article>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Certificados emitidos</h2>
            <p>Últimos registros do portal.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="table table--certificados-lista">
            <thead>
                <tr>
                    <th>Código</th>
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
                        <td>
                            <div class="table-actions">
                                <a href="/admin/certificados/show?certificado_id=<?php echo (int) $certificado['id']; ?>">Abrir</a>

                                <?php if (!empty($certificado['codigo'])): ?>
                                    <?php if (!empty($configCertificados['certificados_permitir_download'])): ?>
                                        <a
                                            href="/admin/certificados/pdf?codigo=<?php echo urlencode($certificado['codigo']); ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Ver certificado
                                        </a>
                                    <?php else: ?>
                                        <span class="muted">Download desativado</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

