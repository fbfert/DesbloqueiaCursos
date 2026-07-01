<?php use App\Core\Helpers; ?>
<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Templates de certificados</h1>
            <p class="admin-page__subtitle">Crie e gerencie modelos de certificado com placeholders.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/certificados/templates/criar">Novo template</a>
        </div>
    </header>

    <?php if (!empty($success)): ?><section class="auth-message auth-message-success"><p><?php echo Helpers::e($success); ?></p></section><?php endif; ?>
    <?php if (!empty($errors)): ?><section class="auth-message auth-message-error"><?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?></section><?php endif; ?>

    <section class="status-card">
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Status</th>
                    <th>Contexto</th>
                    <th>Curso/Turma</th>
                    <th>Padrão</th>
                    <th>Atualização</th>
                    <th>Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($templates)): ?>
                    <tr><td colspan="8">Nenhum template cadastrado.</td></tr>
                <?php else: foreach ($templates as $t): ?>
                    <?php
                    $status = !empty($t['status']) ? (string) $t['status'] : ((int) ($t['ativo'] ?? 0) === 1 ? 'ativo' : 'inativo');
                    $contexto = !empty($t['contexto']) ? (string) $t['contexto'] : 'global';
                    $cursoTurma = '';
                    if (!empty($t['turma_id'])) {
                        $cursoTurma = 'Turma #' . (int) $t['turma_id'];
                    } elseif (!empty($t['curso_id'])) {
                        $cursoTurma = 'Curso #' . (int) $t['curso_id'];
                    } else {
                        $cursoTurma = '—';
                    }
                    ?>
                    <tr>
                        <td><?php echo (int) $t['id']; ?></td>
                        <td>
                            <?php echo Helpers::e($t['nome']); ?>
                            <br><small class="muted"><?php echo Helpers::e($t['slug']); ?></small>
                        </td>
                        <td><span class="pill"><?php echo Helpers::e($status); ?></span></td>
                        <td><?php echo Helpers::e($contexto); ?></td>
                        <td><?php echo Helpers::e($cursoTurma); ?></td>
                        <td><?php echo !empty($t['padrao']) ? 'Sim' : 'Não'; ?></td>
                        <td><?php echo Helpers::e(!empty($t['updated_at']) ? $t['updated_at'] : $t['created_at']); ?></td>
                        <td>
                            <a href="/admin/certificados/templates/editar?template_id=<?php echo (int) $t['id']; ?>">Editar</a>
                            |
                            <a href="/admin/certificados/templates/preview?template_id=<?php echo (int) $t['id']; ?>">Preview</a>
                            |
                            <a href="#" onclick="return duplicarTemplate(<?php echo (int) $t['id']; ?>);">Duplicar</a>
                            |
                            <a href="#" onclick="return definirPadraoTemplate(<?php echo (int) $t['id']; ?>);">Definir como padrão</a>
                            |
                            <a href="#" onclick="return excluirTemplate(<?php echo (int) $t['id']; ?>);">Lixeira</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<form method="post" action="/admin/certificados/templates/excluir" id="form-excluir-template" style="display:none;">
    <input type="hidden" name="id" id="excluir-template-id" value="">
    <input type="hidden" name="justificativa" id="excluir-template-justificativa" value="">
</form>

<form method="post" action="/admin/certificados/templates/duplicar" id="form-duplicar-template" style="display:none;">
    <input type="hidden" name="id" id="duplicar-template-id" value="">
</form>

<form method="post" action="/admin/certificados/templates/definir-padrao" id="form-definir-padrao-template" style="display:none;">
    <input type="hidden" name="id" id="definir-padrao-template-id" value="">
</form>

<script>
function excluirTemplate(id) {
    var justificativa = window.prompt('Informe a justificativa para enviar o template à lixeira:');
    if (justificativa === null) return false;
    justificativa = justificativa.trim();
    if (justificativa === '') { window.alert('A justificativa é obrigatória.'); return false; }
    if (!confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a exclusão deste template?' })) return false;
    document.getElementById('excluir-template-id').value = String(id);
    document.getElementById('excluir-template-justificativa').value = justificativa;
    document.getElementById('form-excluir-template').submit();
    return false;
}

function duplicarTemplate(id) {
    if (!confirmarAcaoCritica({ palavra: 'DUPLICAR', pergunta: 'Você conferiu a duplicação deste template?' })) return false;
    document.getElementById('duplicar-template-id').value = String(id);
    document.getElementById('form-duplicar-template').submit();
    return false;
}

function definirPadraoTemplate(id) {
    if (!confirmarAcaoCritica({ palavra: 'DEFINIR', pergunta: 'Você conferiu a definição deste template como padrão global?' })) return false;
    document.getElementById('definir-padrao-template-id').value = String(id);
    document.getElementById('form-definir-padrao-template').submit();
    return false;
}
</script>

