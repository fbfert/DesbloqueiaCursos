<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1><?php echo Helpers::e($turma['nome']); ?></h1>
    <p>Detalhe administrativo da turma.</p>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>Codigo</dt><dd><?php echo Helpers::e($turma['codigo']); ?></dd>
        <dt>Curso</dt><dd><?php echo Helpers::e($turma['curso_nome']); ?></dd>
        <dt>Categoria</dt><dd><?php echo Helpers::e($turma['categoria_nome'] ?? ''); ?></dd>
        <dt>Modalidade</dt><dd><?php echo Helpers::e($turma['curso_modalidade']); ?></dd>
        <dt>Data inicio</dt><dd><?php echo Helpers::e((string) $turma['data_inicio']); ?></dd>
        <dt>Data fim</dt><dd><?php echo Helpers::e((string) $turma['data_fim']); ?></dd>
        <dt>Vagas</dt><dd><?php echo (int) $turma['vagas']; ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($turma['status']); ?></dd>
    </dl>
</section>

<section class="status-card">
    <div class="split-actions">
        <a href="/admin/turmas/editar?turma_id=<?php echo (int) $turma['id']; ?>">Editar</a>
        <a href="/admin/turmas">Voltar</a>
    </div>
    <form method="post" action="/admin/turmas/excluir" class="admin-form" style="margin-top: 16px;">
        <input type="hidden" name="id" value="<?php echo (int) $turma['id']; ?>">
        <label>
            Justificativa para lixeira
            <input type="text" name="justificativa" required>
        </label>
        <button type="submit">Excluir turma</button>
    </form>
</section>
