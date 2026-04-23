<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Certificado <?php echo Helpers::e($certificado['codigo']); ?></h1>
    <p><?php echo Helpers::e($certificado['participante_nome']); ?></p>
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

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Dados</h2>
            <p>PDF, validacao publica e historico.</p>
        </div>
    </div>

    <dl class="detail-list">
        <div><dt>Codigo</dt><dd><?php echo Helpers::e($certificado['codigo']); ?></dd></div>
        <div><dt>Status</dt><dd><?php echo Helpers::e($certificado['status']); ?></dd></div>
        <div><dt>Participante</dt><dd><?php echo Helpers::e($certificado['participante_nome']); ?></dd></div>
        <div><dt>CPF</dt><dd><?php echo Helpers::e($certificado['cpf_participante']); ?></dd></div>
        <div><dt>Curso</dt><dd><?php echo Helpers::e($certificado['curso_nome']); ?></dd></div>
        <div><dt>Turma</dt><dd><?php echo Helpers::e(isset($certificado['turma_nome']) ? $certificado['turma_nome'] : 'N/A'); ?></dd></div>
    </dl>

    <div class="pill-row" style="margin-top:16px;">
        <a class="pill" href="/admin/certificados/pdf?codigo=<?php echo urlencode($certificado['codigo']); ?>">Abrir PDF</a>
        <a class="pill" href="/certificados/validar?codigo=<?php echo urlencode($certificado['codigo']); ?>">Validacao publica</a>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Reemissao</h2>
            <p>Escolha manter o codigo ou gerar um novo.</p>
        </div>
    </div>

    <form method="post" action="/admin/certificados/reemitir" class="form-grid">
        <input type="hidden" name="certificado_id" value="<?php echo (int) $certificado['id']; ?>">
        <label class="checkbox">
            <input type="checkbox" name="manter_codigo" value="1" checked>
            Manter codigo atual
        </label>
        <button type="submit">Reemitir</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Cancelar ou revogar</h2>
            <p>Acoes irreversiveis de status.</p>
        </div>
    </div>
    <form method="post" action="/admin/certificados/cancelar" class="form-grid">
        <input type="hidden" name="certificado_id" value="<?php echo (int) $certificado['id']; ?>">
        <label>
            Observacao
            <input type="text" name="observacao">
        </label>
        <button type="submit">Cancelar</button>
    </form>
    <form method="post" action="/admin/certificados/revogar" class="form-grid" style="margin-top:12px;">
        <input type="hidden" name="certificado_id" value="<?php echo (int) $certificado['id']; ?>">
        <label>
            Observacao
            <input type="text" name="observacao">
        </label>
        <button type="submit">Revogar</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Historico</h2>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Status</th><th>Observacao</th><th>Data</th></tr></thead>
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
    <div class="panel-header"><div><h2>Validacoes</h2></div></div>
    <div class="table-wrapper">
        <table class="table">
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
