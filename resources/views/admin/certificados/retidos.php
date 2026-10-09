<?php
use App\Core\Helpers;

$retidos = isset($retidos) && is_array($retidos) ? $retidos : array();
$dataBr = function ($valor) {
    $ts = $valor ? strtotime((string) $valor) : false;
    return $ts ? date('d/m/Y H:i', $ts) : '';
};
?>
<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Certificados aguardando CPF</h1>
        <p class="admin-page__subtitle">Emissões pedidas para alunos que ainda não informaram o CPF (contas criadas pelo Google). Cada certificado é emitido automaticamente, com os parâmetros escolhidos, assim que o aluno informar o CPF.</p>
    </div>
    <a class="button-link button-link--ghost" href="/admin/certificados">Voltar para certificados</a>
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
            <h2><?php echo count($retidos); ?> retido(s)</h2>
            <p>O aluno recebe um e-mail pedindo o CPF e vê um aviso fixo na área do aluno.</p>
        </div>
    </div>

    <?php if (empty($retidos)): ?>
        <p class="muted">Nenhum certificado aguardando CPF.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Aluno</th>
                    <th>Curso / turma</th>
                    <th>Pedido em</th>
                    <th>Pedido por</th>
                    <th>Última falha</th>
                    <th>Cancelar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($retidos as $r): ?>
                <tr>
                    <td>
                        <?php echo Helpers::e($r['aluno_nome'] ?? ''); ?><br>
                        <small class="muted"><?php echo Helpers::e($r['aluno_email'] ?? ''); ?></small>
                    </td>
                    <td>
                        <?php echo Helpers::e($r['curso_nome'] ?? ''); ?>
                        <?php if (!empty($r['turma_nome'])): ?><br><small class="muted"><?php echo Helpers::e($r['turma_nome']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo Helpers::e($dataBr($r['created_at'] ?? null)); ?></td>
                    <td><?php echo Helpers::e($r['solicitante_nome'] ?? '—'); ?></td>
                    <td>
                        <?php if (!empty($r['ultima_falha'])): ?>
                            <?php echo Helpers::e($r['ultima_falha']); ?><br>
                            <small class="muted"><?php echo (int) ($r['tentativas'] ?? 0); ?> tentativa(s)</small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="/admin/certificados/retidos/cancelar" class="admin-form">
                            <input type="hidden" name="retencao_id" value="<?php echo (int) $r['id']; ?>">
                            <label class="sr-only" for="just-<?php echo (int) $r['id']; ?>">Justificativa</label>
                            <input type="text" id="just-<?php echo (int) $r['id']; ?>" name="justificativa" placeholder="Justificativa" required>
                            <button type="submit" class="button-link button-link--ghost">Cancelar retenção</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
</div>
