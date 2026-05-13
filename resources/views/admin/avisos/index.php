<?php use App\Core\Helpers; ?>
<?php
$avisos = isset($avisos) && is_array($avisos) ? $avisos : array();
$filters = isset($filters) && is_array($filters) ? $filters : array();
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$tiposDestino = isset($tipos_destino) && is_array($tipos_destino) ? $tipos_destino : array();
$statusOptions = isset($status_options) && is_array($status_options) ? $status_options : array();
$label = function (array $map, $value, $default = '-') {
    return isset($map[$value]) ? $map[$value] : $default;
};
$totalAvisos = count($avisos);
$totalEnviados = 0;
$totalRascunhos = 0;
$totalPausados = 0;
$totalEncerrados = 0;
foreach ($avisos as $avisoResumo) {
    $statusResumo = (string) ($avisoResumo['status'] ?? '');
    if ($statusResumo === 'enviado') {
        $totalEnviados++;
    } elseif ($statusResumo === 'rascunho') {
        $totalRascunhos++;
    } elseif ($statusResumo === 'pausado') {
        $totalPausados++;
    } elseif ($statusResumo === 'encerrado') {
        $totalEncerrados++;
    }
}
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Avisos</h1>
            <p class="admin-page__subtitle">Listagem, envio e acompanhamento de avisos manuais para alunos.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/avisos/criar">Novo aviso</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="card-grid card-grid--inside" style="margin-bottom:12px;">
        <article class="status-card">
            <strong>Total</strong>
            <span><?php echo (int) $totalAvisos; ?> avisos</span>
        </article>
        <article class="status-card">
            <strong>Enviados</strong>
            <span><?php echo (int) $totalEnviados; ?> avisos</span>
        </article>
        <article class="status-card">
            <strong>Rascunhos</strong>
            <span><?php echo (int) $totalRascunhos; ?> avisos</span>
        </article>
        <article class="status-card">
            <strong>Em pausa / encerrados</strong>
            <span><?php echo (int) $totalPausados + (int) $totalEncerrados; ?> avisos</span>
        </article>
    </section>

    <section class="status-card">
        <form method="get" action="/admin/avisos" class="admin-form">
            <div class="split-actions">
                <label style="flex:1;min-width:220px;">
                    Buscar
                    <input type="text" name="q" value="<?php echo Helpers::e($filters['q'] ?? ''); ?>" placeholder="Título ou mensagem">
                </label>
                <label style="min-width:180px;">
                    Status
                    <select name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statusOptions as $key => $text): ?>
                            <option value="<?php echo Helpers::e($key); ?>" <?php echo (($filters['status'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo Helpers::e($text); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="min-width:220px;">
                    Tipo de destino
                    <select name="tipo_destino">
                        <option value="">Todos</option>
                        <?php foreach ($tiposDestino as $key => $text): ?>
                            <option value="<?php echo Helpers::e($key); ?>" <?php echo (($filters['tipo_destino'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo Helpers::e($text); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="min-width:220px;">
                    Curso
                    <select name="curso_evento_id">
                        <option value="">Todos</option>
                        <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) ($filters['curso_evento_id'] ?? 0) === (int) $curso['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($curso['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="min-width:180px;">
                    Criado de
                    <input type="date" name="criado_de" value="<?php echo Helpers::e($filters['criado_de'] ?? ''); ?>">
                </label>
                <label style="min-width:180px;">
                    Criado até
                    <input type="date" name="criado_ate" value="<?php echo Helpers::e($filters['criado_ate'] ?? ''); ?>">
                </label>
            </div>
            <div class="cta-group">
                <button type="submit">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/avisos">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Tipo de destino</th>
                    <th>Curso</th>
                    <th>Usuário</th>
                    <th>Mostrar início</th>
                    <th>Mostrar fim</th>
                    <th>Permitir ocultar</th>
                    <th>Status</th>
                    <th>Origem</th>
                    <th>Criado em</th>
                    <th>Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($avisos)): ?>
                    <tr><td colspan="12">Nenhum aviso encontrado.</td></tr>
                <?php else: foreach ($avisos as $aviso): ?>
                    <tr>
                        <td><?php echo (int) $aviso['id']; ?></td>
                        <td>
                            <strong><?php echo Helpers::e(!empty($aviso['titulo']) ? $aviso['titulo'] : '—'); ?></strong>
                            <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
                                <span class="badge badge--soft"><?php echo Helpers::e($aviso['origem'] ?? 'manual'); ?></span>
                                <span class="badge <?php echo ($aviso['status'] ?? '') === 'enviado' ? 'badge--success' : (($aviso['status'] ?? '') === 'pausado' ? 'badge--warn' : 'badge--soft'); ?>"><?php echo Helpers::e($statusOptions[$aviso['status']] ?? $aviso['status']); ?></span>
                            </div>
                        </td>
                        <td><?php echo Helpers::e($label($tiposDestino, $aviso['tipo_destino'] ?? '', $aviso['tipo_destino'] ?? '-')); ?></td>
                        <td>
                            <?php echo Helpers::e($aviso['curso_nome'] ?? '-'); ?>
                            <?php if (!empty($aviso['curso_evento_id'])): ?>
                                <div class="muted" style="font-size:12px;">ID <?php echo (int) $aviso['curso_evento_id']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo Helpers::e($aviso['usuario_nome'] ?? '-'); ?>
                            <?php if (!empty($aviso['usuario_id'])): ?>
                                <div class="muted" style="font-size:12px;">ID <?php echo (int) $aviso['usuario_id']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo !empty($aviso['mostrar_inicio']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_inicio']))) : '-'; ?></td>
                        <td><?php echo !empty($aviso['mostrar_fim']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_fim']))) : '-'; ?></td>
                        <td><?php echo !empty($aviso['permitir_ocultar']) ? 'Sim' : 'Não'; ?></td>
                        <td><?php echo Helpers::e($statusOptions[$aviso['status']] ?? $aviso['status']); ?></td>
                        <td><?php echo Helpers::e($aviso['origem'] ?? '-'); ?></td>
                        <td><?php echo !empty($aviso['criado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['criado_em']))) : '-'; ?></td>
                        <td>
                            <div class="cta-group" style="flex-wrap:wrap;gap:8px;align-items:flex-start;">
                                <a class="button-link button-link--ghost" href="/admin/avisos/show?aviso_id=<?php echo (int) $aviso['id']; ?>">Ver</a>
                                <a class="button-link button-link--ghost" href="/admin/avisos/editar?aviso_id=<?php echo (int) $aviso['id']; ?>">Editar</a>
                                <a class="button-link button-link--ghost" href="/admin/avisos/destinatarios?aviso_id=<?php echo (int) $aviso['id']; ?>">Destinatários</a>
                                <form method="post" action="/admin/avisos/enviar" class="admin-form" style="display:inline;">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $aviso['id']; ?>">
                                    <button type="submit" class="button-link" onclick="return confirm('Enviar este aviso agora?');"><?php echo $aviso['status'] === 'enviado' ? 'Reenviar' : 'Enviar'; ?></button>
                                </form>
                                <form method="post" action="/admin/avisos/excluir" class="admin-form" style="display:inline;">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $aviso['id']; ?>">
                                    <input type="hidden" name="justificativa" class="js-justificativa-aviso">
                                    <button type="submit" class="button-link button-link--ghost js-excluir-aviso">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<script>
(function () {
    var botoes = document.querySelectorAll('.js-excluir-aviso');
    for (var i = 0; i < botoes.length; i++) {
        botoes[i].addEventListener('click', function (event) {
            var justificativa = window.prompt('Justificativa para lixeira:');
            if (justificativa === null) {
                event.preventDefault();
                return false;
            }
            justificativa = String(justificativa || '').trim();
            if (!justificativa) {
                window.alert('A justificativa é obrigatória.');
                event.preventDefault();
                return false;
            }
            var form = this.closest ? this.closest('form') : null;
            if (!form) {
                event.preventDefault();
                return false;
            }
            var input = form.querySelector('.js-justificativa-aviso');
            if (input) {
                input.value = justificativa;
            }
            return true;
        });
    }
})();
</script>
