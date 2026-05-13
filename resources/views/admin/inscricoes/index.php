<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Inscrições</h1>
        <p class="admin-page__subtitle">Listagem administrativa de inscrições e controle de progresso.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Inscrições</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Pagador</th>
                    <th>Participante</th>
                    <th>Curso</th>
                    <th>Turma</th>
                    <th>Status</th>
                    <th>Pedido</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inscricoes)): ?>
                    <tr>
                        <td colspan="9">Nenhuma inscrição encontrada.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($inscricoes as $inscricao): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $inscricao['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars((string) $inscricao['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) $inscricao['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars((string) $inscricao['participante_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) $inscricao['participante_cpf'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars((string) $inscricao['curso_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $inscricao['turma_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($inscricao['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $inscricao['pedido_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $inscricao['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form method="post" action="/admin/inscricoes/status" class="admin-form">
                                <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricao['id']; ?>">
                                <select name="status">
                                    <option value="pendente" <?php echo $inscricao['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="ativa" <?php echo $inscricao['status'] === 'ativa' ? 'selected' : ''; ?>>Ativa</option>
                                    <option value="em_andamento" <?php echo $inscricao['status'] === 'em_andamento' ? 'selected' : ''; ?>>Em andamento</option>
                                    <option value="com_pendencia" <?php echo $inscricao['status'] === 'com_pendencia' ? 'selected' : ''; ?>>Com pendência</option>
                                    <option value="cancelada" <?php echo $inscricao['status'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    <option value="reprovada" <?php echo $inscricao['status'] === 'reprovada' ? 'selected' : ''; ?>>Reprovada</option>
                                    <option value="concluida" <?php echo $inscricao['status'] === 'concluida' ? 'selected' : ''; ?>>Concluída</option>
                                    <option value="concluida_sem_certificado" <?php echo $inscricao['status'] === 'concluida_sem_certificado' ? 'selected' : ''; ?>>Concluída sem certificado</option>
                                    <option value="certificado_emitido" <?php echo $inscricao['status'] === 'certificado_emitido' ? 'selected' : ''; ?>>Certificado emitido</option>
                                </select>
                                <input type="text" name="observacao" placeholder="Observação">
                                <div class="cta-group">
                                    <button type="submit" class="button-link button-link--primary">Salvar</button>
                                    <a class="button-link button-link--ghost" href="/admin/inscricoes">Cancelar</a>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

