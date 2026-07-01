<?php use App\Core\Helpers; ?>

<div class="admin-page">
<?php $configCertificados = isset($configCertificados) && is_array($configCertificados) ? $configCertificados : array(); ?>
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Certificado <?php echo Helpers::e($certificado['codigo']); ?></h1>
        <p class="admin-page__subtitle"><?php echo Helpers::e($certificado['participante_nome']); ?></p>
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
        <strong>Emissão desativada</strong>
        <p class="muted" style="margin:8px 0 0 0;">A emissão de certificados está desativada nas configurações globais.</p>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Dados</h2>
            <p>PDF, validação pública e histórico.</p>
        </div>
    </div>

    <dl class="detail-list">
        <div><dt>Código</dt><dd><?php echo Helpers::e($certificado['codigo']); ?></dd></div>
        <div><dt>Status</dt><dd><?php echo Helpers::e($certificado['status']); ?></dd></div>
        <div><dt>Participante</dt><dd><?php echo Helpers::e($certificado['participante_nome']); ?></dd></div>
        <div><dt>CPF</dt><dd><?php echo Helpers::e($certificado['cpf_participante']); ?></dd></div>
        <div><dt>Curso</dt><dd><?php echo Helpers::e($certificado['curso_nome']); ?></dd></div>
        <div><dt>Turma</dt><dd><?php echo Helpers::e(isset($certificado['turma_nome']) ? $certificado['turma_nome'] : 'Não informada'); ?></dd></div>
    </dl>

    <?php if (!empty($certificado['emissao_excepcional'])): ?>
        <section class="status-card admin-mt-16" style="border-left:4px solid #b45309;background:#fff7ed;">
            <strong>Este certificado foi emitido por exceção administrativa.</strong>
            <div class="detail-list admin-mt-12">
                <div><dt>Justificativa</dt><dd><?php echo Helpers::e($certificado['emissao_excepcional']['justificativa']); ?></dd></div>
                <div><dt>Emitido por</dt><dd><?php echo Helpers::e(!empty($certificado['emissao_excepcional']['emitido_por_nome']) ? $certificado['emissao_excepcional']['emitido_por_nome'] : 'Não informado'); ?></dd></div>
                <div><dt>Data</dt><dd><?php echo Helpers::e($certificado['emissao_excepcional']['created_at']); ?></dd></div>
                <div><dt>Situação na emissão</dt><dd><?php echo Helpers::e(!empty($certificado['emissao_excepcional']['situacao_elegibilidade']) ? $certificado['emissao_excepcional']['situacao_elegibilidade'] : 'Não informada'); ?></dd></div>
                <div><dt>Motivos de pendência</dt><dd><?php echo Helpers::e(!empty($certificado['emissao_excepcional']['motivos_pendencias']) ? $certificado['emissao_excepcional']['motivos_pendencias'] : 'Não informados'); ?></dd></div>
            </div>
        </section>
    <?php endif; ?>

    <div class="pill-row admin-mt-16">
        <?php if (!empty($configCertificados['certificados_permitir_download'])): ?>
            <a class="pill" href="/admin/certificados/pdf?codigo=<?php echo urlencode($certificado['codigo']); ?>">Abrir PDF</a>
        <?php else: ?>
            <span class="pill" aria-disabled="true">Download desativado</span>
        <?php endif; ?>
        <a class="pill" href="/certificados/validar?codigo=<?php echo urlencode($certificado['codigo']); ?>">Validação pública</a>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Reemissão</h2>
            <p>Escolha manter o código ou gerar um novo.</p>
        </div>
    </div>

    <form method="post" action="/admin/certificados/reemitir" class="form-grid">
        <input type="hidden" name="certificado_id" value="<?php echo (int) $certificado['id']; ?>">
        <label class="checkbox">
            <input type="checkbox" name="manter_codigo" value="1" checked>
            Manter código atual
        </label>
        <button type="submit" class="button-link button-link--primary" <?php if (empty($configCertificados['certificados_permitir_segunda_via'])): ?>disabled<?php endif; ?>>Reemitir</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Cancelar ou revogar</h2>
            <p>Ações irreversíveis de status.</p>
        </div>
    </div>
    <form method="post" action="/admin/certificados/cancelar" class="form-grid">
        <input type="hidden" name="certificado_id" value="<?php echo (int) $certificado['id']; ?>">
        <label>
            Observação
            <input type="text" name="observacao">
        </label>
        <button type="submit" class="button-link button-link--primary" <?php if (empty($configCertificados['certificados_permitir_cancelamento'])): ?>disabled<?php else: ?>onclick="return confirmarAcaoCritica({ palavra: 'CANCELAR', pergunta: 'Você conferiu o cancelamento deste certificado?' });"<?php endif; ?>>Cancelar</button>
    </form>
    <form method="post" action="/admin/certificados/revogar" class="form-grid admin-mt-12">
        <input type="hidden" name="certificado_id" value="<?php echo (int) $certificado['id']; ?>">
        <label>
            Observação
            <input type="text" name="observacao">
        </label>
        <button type="submit" class="button-link button-link--primary" <?php if (empty($configCertificados['certificados_permitir_cancelamento'])): ?>disabled<?php else: ?>onclick="return confirmarAcaoCritica({ palavra: 'REVOGAR', pergunta: 'Você conferiu a revogação deste certificado?' });"<?php endif; ?>>Revogar</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Histórico</h2>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table--certificados-historico">
            <thead><tr><th>Status</th><th>Observação</th><th>Data</th></tr></thead>
            <tbody>
                <?php foreach ($certificado['historico'] as $item): ?>
                    <tr>
                        <td><?php echo Helpers::e($item['status_novo']); ?></td>
                        <td><?php echo Helpers::e($item['observacao']); ?></td>
                        <td><?php echo Helpers::e($item['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <div class="panel-header"><div><h2>Validações</h2></div></div>
    <div class="table-wrapper">
        <table class="table table--certificados-validacoes">
            <thead><tr><th>Resultado</th><th>CPF</th><th>Data</th></tr></thead>
            <tbody>
                <?php foreach ($certificado['validacoes'] as $item): ?>
                    <tr>
                        <td><?php echo Helpers::e($item['resultado']); ?></td>
                        <td><?php echo Helpers::e($item['cpf_informado']); ?></td>
                        <td><?php echo Helpers::e($item['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

