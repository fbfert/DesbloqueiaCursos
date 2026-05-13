<?php use App\Core\Helpers; ?>
<?php
$resumo = isset($resumo_destinatarios) && is_array($resumo_destinatarios) ? $resumo_destinatarios : array('total' => 0, 'visualizados' => 0, 'ocultados' => 0, 'ativos' => 0);
$tiposDestino = array(
    'todos_alunos' => 'Todos os alunos da plataforma',
    'alunos_curso' => 'Todos os alunos de um curso',
    'alunos_sem_compra_confirmada' => 'Alunos sem compra confirmada',
    'aluno_curso' => 'Um aluno de um curso',
    'aluno_individual' => 'Um aluno da plataforma',
);
$statusOptions = array(
    'rascunho' => 'Rascunho',
    'enviado' => 'Enviado',
    'pausado' => 'Pausado',
    'encerrado' => 'Encerrado',
);
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e(!empty($aviso['titulo']) ? $aviso['titulo'] : 'Aviso #' . (int) $aviso['id']); ?></h1>
            <p class="admin-page__subtitle">Detalhe administrativo e resumo de destinatários.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/avisos">Voltar</a>
            <a class="button-link" href="/admin/avisos/editar?aviso_id=<?php echo (int) $aviso['id']; ?>">Editar</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <dl class="summary-list">
            <dt>ID</dt><dd><?php echo (int) $aviso['id']; ?></dd>
            <dt>Título</dt><dd><?php echo Helpers::e(!empty($aviso['titulo']) ? $aviso['titulo'] : '-'); ?></dd>
            <dt>Mensagem</dt><dd><?php echo nl2br(Helpers::e($aviso['mensagem'])); ?></dd>
            <dt>Tipo de destino</dt><dd><?php echo Helpers::e($tiposDestino[$aviso['tipo_destino']] ?? $aviso['tipo_destino']); ?></dd>
            <dt>Curso</dt><dd><?php echo Helpers::e($aviso['curso_nome'] ?? '-'); ?></dd>
            <dt>Usuário</dt><dd><?php echo Helpers::e($aviso['usuario_nome'] ?? '-'); ?></dd>
            <dt>Mostrar início</dt><dd><?php echo !empty($aviso['mostrar_inicio']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_inicio']))) : '-'; ?></dd>
            <dt>Mostrar fim</dt><dd><?php echo !empty($aviso['mostrar_fim']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_fim']))) : '-'; ?></dd>
            <dt>Permitir ocultar</dt><dd><?php echo !empty($aviso['permitir_ocultar']) ? 'Sim' : 'Não'; ?></dd>
            <dt>Status</dt><dd><?php echo Helpers::e($statusOptions[$aviso['status']] ?? $aviso['status']); ?></dd>
            <dt>Origem</dt><dd><?php echo Helpers::e($aviso['origem'] ?? '-'); ?></dd>
            <dt>Gatilho</dt><dd><?php echo Helpers::e($aviso['gatilho'] ?? '-'); ?></dd>
            <dt>Prioridade</dt><dd><?php echo (int) ($aviso['prioridade'] ?? 0); ?></dd>
            <dt>Link</dt><dd><?php echo !empty($aviso['link_url']) ? '<a href="' . Helpers::e($aviso['link_url']) . '" target="_blank" rel="noopener noreferrer">' . Helpers::e($aviso['link_rotulo'] ?: $aviso['link_url']) . '</a>' : '-'; ?></dd>
            <dt>Destaque</dt><dd><?php echo !empty($aviso['destaque']) ? 'Sim' : 'Não'; ?></dd>
            <dt>Criado por</dt><dd><?php echo Helpers::e($aviso['criado_por'] ?? '-'); ?></dd>
            <dt>Criado em</dt><dd><?php echo !empty($aviso['criado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['criado_em']))) : '-'; ?></dd>
            <dt>Editado em</dt><dd><?php echo !empty($aviso['editado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['editado_em']))) : '-'; ?></dd>
            <dt>Enviado em</dt><dd><?php echo !empty($aviso['enviado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['enviado_em']))) : '-'; ?></dd>
        </dl>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <h2 style="margin:0 0 12px;">Resumo dos destinatários</h2>
        <div class="pill-row">
            <span class="pill">Total: <?php echo (int) ($resumo['total'] ?? 0); ?></span>
            <span class="pill">Visualizados: <?php echo (int) ($resumo['visualizados'] ?? 0); ?></span>
            <span class="pill">Ocultados: <?php echo (int) ($resumo['ocultados'] ?? 0); ?></span>
            <span class="pill">Ativos: <?php echo (int) ($resumo['ativos'] ?? 0); ?></span>
        </div>
        <div class="cta-group" style="margin-top:12px;">
            <a class="button-link button-link--ghost" href="/admin/avisos/destinatarios?aviso_id=<?php echo (int) $aviso['id']; ?>">Ver destinatários</a>
            <form method="post" action="/admin/avisos/enviar" class="admin-form" style="display:inline;">
                <?php echo $csrfField; ?>
                <input type="hidden" name="id" value="<?php echo (int) $aviso['id']; ?>">
                <button type="submit" onclick="return confirm('Enviar este aviso agora?');"><?php echo $aviso['status'] === 'enviado' ? 'Reenviar aviso' : 'Enviar aviso'; ?></button>
            </form>
        </div>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <h2 style="margin:0 0 12px;">Mensagem</h2>
        <p><?php echo nl2br(Helpers::e($aviso['mensagem'])); ?></p>
        <?php if (!empty($aviso['link_url'])): ?>
            <div class="cta-group" style="margin-top:12px;">
                <a class="button-link" href="<?php echo Helpers::e($aviso['link_url']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo Helpers::e($aviso['link_rotulo'] ?: 'Abrir link'); ?>
                </a>
            </div>
        <?php endif; ?>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <h2 style="margin:0 0 12px;">Ações</h2>
        <div class="split-actions">
            <a href="/admin/avisos/editar?aviso_id=<?php echo (int) $aviso['id']; ?>">Editar</a>
            <a href="/admin/avisos/destinatarios?aviso_id=<?php echo (int) $aviso['id']; ?>">Destinatários</a>
            <a href="/admin/avisos">Lista</a>
        </div>
        <form method="post" action="/admin/avisos/excluir" class="admin-form admin-mt-16">
            <?php echo $csrfField; ?>
            <input type="hidden" name="id" value="<?php echo (int) $aviso['id']; ?>">
            <label>
                Justificativa para lixeira
                <input type="text" name="justificativa" required>
            </label>
            <button type="submit">Excluir aviso</button>
        </form>
    </section>
</section>
