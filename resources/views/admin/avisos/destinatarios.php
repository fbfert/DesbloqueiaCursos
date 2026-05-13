<?php use App\Core\Helpers; ?>
<?php
$destinatarios = isset($destinatarios) && is_array($destinatarios) ? $destinatarios : array();
$filters = isset($filters) && is_array($filters) ? $filters : array();
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array('total' => 0, 'visualizados' => 0, 'ocultados' => 0, 'ativos' => 0);
$statusOptions = array(
    '' => 'Todos',
    'visualizados' => 'Visualizados',
    'nao_visualizados' => 'Não visualizados',
    'ocultados' => 'Ocultados',
);
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Destinatários do aviso</h1>
            <p class="admin-page__subtitle"><?php echo Helpers::e(!empty($aviso['titulo']) ? $aviso['titulo'] : 'Aviso #' . (int) $aviso['id']); ?></p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/avisos/show?aviso_id=<?php echo (int) $aviso['id']; ?>">Voltar ao aviso</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <div class="pill-row">
            <span class="pill">Total: <?php echo (int) ($resumo['total'] ?? 0); ?></span>
            <span class="pill">Visualizados: <?php echo (int) ($resumo['visualizados'] ?? 0); ?></span>
            <span class="pill">Ocultados: <?php echo (int) ($resumo['ocultados'] ?? 0); ?></span>
            <span class="pill">Ativos: <?php echo (int) ($resumo['ativos'] ?? 0); ?></span>
        </div>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <form method="get" action="/admin/avisos/destinatarios" class="admin-form">
            <input type="hidden" name="aviso_id" value="<?php echo (int) $aviso['id']; ?>">
            <div class="split-actions">
                <label style="flex:1;min-width:220px;">
                    Buscar
                    <input type="text" name="q" value="<?php echo Helpers::e($filters['q'] ?? ''); ?>" placeholder="Nome, e-mail ou CPF">
                </label>
                <label style="min-width:220px;">
                    Status
                    <select name="status">
                        <?php foreach ($statusOptions as $key => $text): ?>
                            <option value="<?php echo Helpers::e($key); ?>" <?php echo (($filters['status'] ?? '') === $key) ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="cta-group">
                <button type="submit">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/avisos/destinatarios?aviso_id=<?php echo (int) $aviso['id']; ?>">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Curso</th>
                    <th>Status</th>
                    <th>Visualizado em</th>
                    <th>Ocultado em</th>
                    <th>Criado em</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($destinatarios)): ?>
                    <tr><td colspan="7">Nenhum destinatário encontrado.</td></tr>
                <?php else: foreach ($destinatarios as $destinatario): ?>
                    <tr>
                        <td><?php echo Helpers::e($destinatario['usuario_nome'] ?? '-'); ?></td>
                        <td><?php echo Helpers::e($destinatario['usuario_email'] ?? '-'); ?></td>
                        <td><?php echo Helpers::e($destinatario['curso_nome'] ?? '-'); ?></td>
                        <td><?php echo Helpers::e($destinatario['status'] ?? '-'); ?></td>
                        <td><?php echo !empty($destinatario['visualizado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $destinatario['visualizado_em']))) : '-'; ?></td>
                        <td><?php echo !empty($destinatario['ocultado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $destinatario['ocultado_em']))) : '-'; ?></td>
                        <td><?php echo !empty($destinatario['criado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $destinatario['criado_em']))) : '-'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
