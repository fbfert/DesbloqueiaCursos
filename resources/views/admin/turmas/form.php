<?php use App\Core\Helpers; ?>

<?php $turma = isset($form_data['turma']) ? $form_data['turma'] : null; ?>
<?php $cursos = isset($form_data['cursos']) ? $form_data['cursos'] : array(); ?>
<?php $professores = isset($form_data['professores']) ? $form_data['professores'] : array(); ?>
<?php $professorResponsavel = isset($form_data['professor_responsavel']) ? $form_data['professor_responsavel'] : null; ?>
<?php $statusLabels = array('planejada' => 'Planejada', 'aberta' => 'Aberta', 'encerrada' => 'Encerrada', 'excluida' => 'Excluída'); ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
        <p class="admin-page__subtitle">Cadastro e manutenção de turmas e edições.</p>
    </div>
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
            Professor responsável
            <select name="professor_responsavel_usuario_id">
                <option value="">Sem professor definido</option>
                <?php foreach ($professores as $professor): ?>
                    <option value="<?php echo (int) $professor['id']; ?>" <?php echo !empty($professorResponsavel['usuario_id']) && (int) $professorResponsavel['usuario_id'] === (int) $professor['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($professor['nome']); ?>
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
            Código
            <input type="text" name="codigo" value="<?php echo Helpers::e($turma['codigo'] ?? ''); ?>" required>
        </label>
        <label>
            Data de início
            <input type="date" name="data_inicio" value="<?php echo Helpers::e(substr((string) ($turma['data_inicio'] ?? ''), 0, 10)); ?>">
        </label>
        <label>
            Data de fim
            <input type="date" name="data_fim" value="<?php echo Helpers::e(substr((string) ($turma['data_fim'] ?? ''), 0, 10)); ?>">
        </label>
        <label>
            Vagas
            <input type="number" name="vagas" min="0" value="<?php echo Helpers::e((string) ($turma['vagas'] ?? '')); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <?php foreach ($statusLabels as $status => $label): ?>
                    <option value="<?php echo Helpers::e($status); ?>" <?php echo (($turma['status'] ?? '') === $status) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php $cancelUrl = '/admin/turmas'; ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>
