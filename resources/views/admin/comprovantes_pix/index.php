<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Comprovantes PIX</h1>
        <p class="admin-page__subtitle">Análise administrativa dos comprovantes versionados.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>
<?php
if (!function_exists('comprovantesPixFormatarTelefone')) {
    function comprovantesPixFormatarTelefone($telefone)
    {
        $telefone = preg_replace('/\D+/', '', (string) $telefone);

        if ($telefone === '') {
            return '';
        }

        if (strlen($telefone) === 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
        }

        if (strlen($telefone) === 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
        }

        return $telefone;
    }
}

if (!function_exists('comprovantesPixCursoNome')) {
    function comprovantesPixCursoNome($cursoNome)
    {
        $cursoNome = trim((string) $cursoNome);

        if ($cursoNome === '') {
            return 'Curso não informado';
        }

        return $cursoNome;
    }
}
?>

<section class="status-card admin-pending-pix">
    <div class="admin-pending-pix__header">
        <div>
            <strong class="admin-pending-pix__title">Comprovantes pendentes de avaliação</strong>
            <p class="muted admin-pending-pix__subtitle">Comprovantes enviados pelos alunos que ainda aguardam análise administrativa.</p>
        </div>
        <span class="badge badge--status badge--status-pendente"><?php echo count($comprovantes_pendentes); ?> pendentes</span>
    </div>
    <?php
    function statusComprovanteMeta($status)
    {
        $valor = strtolower(trim((string) $status));

        if ($valor === 'aprovado') {
            return array('icone' => '✔', 'classe' => 'badge badge--status badge--status-aprovado', 'texto' => 'Aprovado');
        }

        if ($valor === 'reprovado') {
            return array('icone' => '✖', 'classe' => 'badge badge--status badge--status-reprovado', 'texto' => 'Reprovado');
        }

        return array('icone' => '◷', 'classe' => 'badge badge--status badge--status-pendente', 'texto' => 'Pendente');
    }
    ?>
    <?php if (empty($comprovantes_pendentes)): ?>
        <div class="alert alert-warning admin-pending-pix__empty">Nenhum comprovante pendente de avaliação no momento.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table admin-pending-pix__table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pedido</th>
                    <th>Pagador</th>
                    <th>Curso / Turma</th>
                    <th>Valor do pedido</th>
                    <th>Versão</th>
                    <th>Status</th>
                    <th>Enviado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comprovantes_pendentes as $comprovante): ?>
                    <?php $statusMeta = statusComprovanteMeta(isset($comprovante['status']) ? $comprovante['status'] : ''); ?>
                    <tr>
                        <td><?php echo (int) $comprovante['id']; ?></td>
                        <td>
                            <a href="/admin/pedidos/show?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>">
                                <?php echo htmlspecialchars((string) $comprovante['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <br><small class="muted text-muted">Curso: <?php echo htmlspecialchars(comprovantesPixCursoNome(isset($comprovante['cursos_nome']) ? $comprovante['cursos_nome'] : ''), ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars((string) $comprovante['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) $comprovante['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php if (!empty($comprovante['pagador_telefone'])): ?>
                                <br><small><?php echo htmlspecialchars(comprovantesPixFormatarTelefone((string) $comprovante['pagador_telefone']), ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars((string) ($comprovante['cursos_nome'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) ($comprovante['turmas_nome'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>R$ <?php echo number_format((float) $comprovante['pedido_total'], 2, ',', '.'); ?></td>
                        <td><?php echo (int) $comprovante['versao']; ?></td>
                        <td>
                            <span class="<?php echo htmlspecialchars($statusMeta['classe'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusMeta['icone'], ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($statusMeta['texto'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars((string) $comprovante['enviado_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <div class="admin-pending-pix__action-list">
                                <a class="button-link button-link--ghost admin-pending-pix__icon-link" href="/admin/pedidos/show?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>" aria-label="Abrir pedido <?php echo htmlspecialchars((string) $comprovante['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <img class="admin-pending-pix__icon" src="/assets/icons/admin-pedido.svg" alt="" aria-hidden="true">
                                    <span class="u-sr-only">Abrir pedido</span>
                                </a>
                                <a class="button-link button-link--ghost admin-pending-pix__icon-link" href="/admin/pedidos/comprovante?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>&comprovante_id=<?php echo (int) $comprovante['id']; ?>" target="_blank" rel="noopener" aria-label="Ver comprovante do pedido <?php echo htmlspecialchars((string) $comprovante['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <img class="admin-pending-pix__icon" src="/assets/icons/admin-comprovante.svg" alt="" aria-hidden="true">
                                    <span class="u-sr-only">Ver comprovante</span>
                                </a>
                            </div>
                            <form method="post" action="/admin/comprovantes-pix/aprovar" class="admin-pending-pix__action-form">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="comprovante_id" value="<?php echo (int) $comprovante['id']; ?>">
                                <input type="hidden" name="observacao" value="">
                                <input type="hidden" name="confirmacao_aprovacao" value="">
                                <button type="submit" class="button-link button-link--primary" onclick="return confirmarAprovacaoComprovante(this);">Aprovar</button>
                            </form>
                            <form method="post" action="/admin/comprovantes-pix/reprovar" class="admin-pending-pix__action-form">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="comprovante_id" value="<?php echo (int) $comprovante['id']; ?>">
                                <input type="hidden" name="observacao" value="">
                                <input type="hidden" name="confirmacao_reprovacao" value="">
                                <button type="submit" class="button-link button-link--danger" onclick="return confirmarReprovacaoComprovante(this);">Recusar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<section class="status-card admin-pending-pix__all">
    <strong>Comprovantes</strong>
    <?php if (!empty($comprovantes_pix)): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Pagador</th>
                        <th>Versão</th>
                        <th>Status</th>
                        <th>Enviado em</th>
                        <th>Pedido</th>
                        <th>Motivo do reenvio</th>
                        <th>Abrir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comprovantes_pix as $comprovante): ?>
                        <?php $statusMeta = statusComprovanteMeta(isset($comprovante['status']) ? $comprovante['status'] : ''); ?>
                        <tr>
                        <td>
                            <a href="/admin/pedidos/show?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>">
                                <?php echo htmlspecialchars((string) $comprovante['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <br><small class="muted text-muted">Curso: <?php echo htmlspecialchars(comprovantesPixCursoNome(isset($comprovante['cursos_nome']) ? $comprovante['cursos_nome'] : ''), ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars((string) $comprovante['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) $comprovante['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php if (!empty($comprovante['pagador_telefone'])): ?>
                                <br><small><?php echo htmlspecialchars(comprovantesPixFormatarTelefone((string) $comprovante['pagador_telefone']), ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                            <td><?php echo (int) $comprovante['versao']; ?></td>
                            <td>
                                <span class="<?php echo htmlspecialchars($statusMeta['classe'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($statusMeta['icone'], ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($statusMeta['texto'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars((string) $comprovante['enviado_em'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $comprovante['pedido_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $comprovante['motivo_reenvio'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div class="admin-pending-pix__action-list">
                                    <a class="button-link button-link--ghost admin-pending-pix__icon-link" href="/admin/pedidos/show?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>" aria-label="Abrir pedido <?php echo htmlspecialchars((string) $comprovante['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <img class="admin-pending-pix__icon" src="/assets/icons/admin-pedido.svg" alt="" aria-hidden="true">
                                        <span class="u-sr-only">Abrir pedido</span>
                                    </a>
                                    <a class="button-link button-link--ghost admin-pending-pix__icon-link" href="/admin/pedidos/comprovante?pedido_id=<?php echo (int) $comprovante['pedido_id']; ?>&comprovante_id=<?php echo (int) $comprovante['id']; ?>" target="_blank" rel="noopener" aria-label="Ver comprovante do pedido <?php echo htmlspecialchars((string) $comprovante['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <img class="admin-pending-pix__icon" src="/assets/icons/admin-comprovante.svg" alt="" aria-hidden="true">
                                        <span class="u-sr-only">Ver comprovante</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">Nenhum comprovante encontrado.</p>
    <?php endif; ?>
</section>
</div>

<script>
function confirmarAprovacaoComprovante(botao) {
    var formulario = botao.closest('form');
    var confirmacao = window.prompt('Você conferiu o comprovante? Digite CONFERIDO para confirmar.');

    if (confirmacao === null) {
        return false;
    }

    if (confirmacao.trim().toUpperCase() !== 'CONFERIDO') {
        window.alert('Confirmação inválida. Digite CONFERIDO para aprovar este comprovante.');
        return false;
    }

    var campoConfirmacao = formulario.querySelector('input[name="confirmacao_aprovacao"]');
    if (campoConfirmacao) {
        campoConfirmacao.value = 'CONFERIDO';
    }

    return true;
}

function confirmarReprovacaoComprovante(botao) {
    var formulario = botao.closest('form');
    var confirmacao = window.prompt('Você conferiu o comprovante? Digite REPROVADO para confirmar.');

    if (confirmacao === null) {
        return false;
    }

    if (confirmacao.trim().toUpperCase() !== 'REPROVADO') {
        window.alert('Confirmação inválida. Digite REPROVADO para recusar este comprovante.');
        return false;
    }

    var campoConfirmacao = formulario.querySelector('input[name="confirmacao_reprovacao"]');
    if (campoConfirmacao) {
        campoConfirmacao.value = 'REPROVADO';
    }

    return true;
}
</script>

