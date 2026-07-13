<?php use App\Core\Helpers; ?>

<?php
$turma = isset($turma) && is_array($turma) ? $turma : array();
$destinatarios = isset($destinatarios) && is_array($destinatarios) ? $destinatarios : array();
$turmaId = isset($turma['id']) ? (int) $turma['id'] : 0;
$old = isset($old) && is_array($old) ? $old : array();
$assuntoAtual = isset($old['assunto']) ? (string) $old['assunto'] : '';
$corpoAtual = isset($old['corpo_html']) ? (string) $old['corpo_html'] : '';

$placeholders = array(
    '{usuario.nome}' => 'Nome do aluno',
    '{usuario.email}' => 'E-mail do aluno',
    '{turma.nome}' => 'Nome da turma',
    '{turma.codigo}' => 'Código da turma',
    '{turma.data_inicio}' => 'Início da turma',
    '{turma.data_fim}' => 'Fim da turma',
    '{curso.nome}' => 'Nome do curso',
    '{sistema.nome}' => 'Nome do portal',
);
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Novo e-mail para a turma</h1>
            <p class="admin-page__subtitle">
                <?php echo Helpers::e($turma['nome']); ?>
                <?php if (!empty($turma['codigo'])): ?>
                    (<?php echo Helpers::e($turma['codigo']); ?>)
                <?php endif; ?>
                &middot; <?php echo Helpers::e($turma['curso_nome']); ?>
            </p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/turmas/emails?turma_id=<?php echo $turmaId; ?>">Voltar</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <?php if (empty($destinatarios)): ?>
        <section class="status-card">
            <p>Esta turma não tem alunos matriculados com e-mail válido, então não há para quem enviar.</p>
        </section>
    <?php else: ?>
        <section class="status-card">
            <form method="post" action="/admin/turmas/emails/enviar" class="admin-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">

                <label>Assunto
                    <input type="text" name="assunto" maxlength="255" required
                           value="<?php echo Helpers::e($assuntoAtual); ?>"
                           placeholder="Ex.: Aviso sobre a aula de {turma.nome}">
                </label>

                <label>Conteúdo do e-mail
                    <textarea name="corpo_html" class="js-email-html-editor" rows="14"><?php echo Helpers::e($corpoAtual); ?></textarea>
                </label>

                <p class="muted">
                    Você pode usar estes campos no assunto e no conteúdo — eles são trocados pelos dados de cada aluno:
                </p>
                <ul class="muted">
                    <?php foreach ($placeholders as $chave => $descricao): ?>
                        <li><code><?php echo Helpers::e($chave); ?></code> — <?php echo Helpers::e($descricao); ?></li>
                    <?php endforeach; ?>
                </ul>

                <div class="admin-form__actions">
                    <button type="submit">Enviar para <?php echo count($destinatarios); ?> aluno(s)</button>
                </div>
            </form>
        </section>

        <section class="status-card">
            <details>
                <summary>
                    <h2 class="admin-section__title">Destinatários (<?php echo count($destinatarios); ?>)</h2>
                </summary>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>E-mail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($destinatarios as $destinatario): ?>
                                <tr>
                                    <td><?php echo Helpers::e($destinatario['nome']); ?></td>
                                    <td><?php echo Helpers::e($destinatario['email']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
    <?php endif; ?>
</div>
