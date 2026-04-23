<?php use App\Core\Helpers; ?>

<?php $turma = isset($form_data['turma']) ? $form_data['turma'] : null; ?>
<?php $cursos = isset($form_data['cursos']) ? $form_data['cursos'] : array(); ?>

<section class="hero">
    <h1><?php echo Helpers::e($title); ?></h1>
    <p>Cadastro e manutencao de turmas e edicoes.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
        <input type="hidden" name="id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : 0; ?>">
        <label>
            Curso
            <select name="curso_evento_id" required>
                <option value="">Selecione</option>
                <?php foreach ($cursos as $curso): ?>
                    <option value="<?php echo (int) $curso['id']; ?>" <?php echo !empty($turma['curso_evento_id']) && (int) $turma['curso_evento_id'] === (int) $curso['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($curso['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Nome
            <input type="text" name="nome" value="<?php echo Helpers::e($turma['nome'] ?? ''); ?>" required>
        </label>
        <label>
            Slug
            <input type="text" name="slug" value="<?php echo Helpers::e($turma['slug'] ?? ''); ?>">
        </label>
        <label>
            Codigo
            <input type="text" name="codigo" value="<?php echo Helpers::e($turma['codigo'] ?? ''); ?>" required>
        </label>
        <label>
            Data inicio
            <input type="date" name="data_inicio" value="<?php echo Helpers::e(substr((string) ($turma['data_inicio'] ?? ''), 0, 10)); ?>">
        </label>
        <label>
            Data fim
            <input type="date" name="data_fim" value="<?php echo Helpers::e(substr((string) ($turma['data_fim'] ?? ''), 0, 10)); ?>">
        </label>
        <label>
            Vagas
            <input type="number" name="vagas" min="0" value="<?php echo Helpers::e((string) ($turma['vagas'] ?? '')); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <?php foreach (array('planejada', 'aberta', 'encerrada', 'cancelada') as $status): ?>
                    <option value="<?php echo Helpers::e($status); ?>" <?php echo (($turma['status'] ?? '') === $status) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit"><?php echo Helpers::e($submit_label); ?></button>
    </form>
</section>
