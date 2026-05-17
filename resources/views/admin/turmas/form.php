<?php use App\Core\Helpers; ?>

<?php $turma = isset($form_data['turma']) ? $form_data['turma'] : null; ?>
<?php $cursos = isset($form_data['cursos']) ? $form_data['cursos'] : array(); ?>
<?php $professores = isset($form_data['professores']) ? $form_data['professores'] : array(); ?>
<?php $professorResponsavel = isset($form_data['professor_responsavel']) ? $form_data['professor_responsavel'] : null; ?>
<?php $cursoBloqueadoId = isset($form_data['curso_id']) ? (int) $form_data['curso_id'] : 0; ?>
<?php $cursoBloqueado = !empty($curso_locked); ?>
<?php $returnTo = isset($return_to) ? (string) $return_to : ''; ?>
<?php $statusLabels = array('planejada' => 'Planejada', 'aberta' => 'Aberta', 'encerrada' => 'Encerrada', 'excluida' => 'Excluída'); ?>
<?php $oldData = isset($old) && is_array($old) ? $old : array(); ?>
<?php $value = function($key, $default = '') use ($oldData, $turma, $cursoBloqueadoId) {
    if (array_key_exists($key, $oldData)) {
        return $oldData[$key];
    }
    if (is_array($turma) && array_key_exists($key, $turma)) {
        return $turma[$key];
    }
    if ($key === 'curso_evento_id' && $cursoBloqueadoId > 0) {
        return $cursoBloqueadoId;
    }
    return $default;
}; ?>
<?php $show_save_as_copy = !empty((int) $value('id', 0)) && $returnTo === ''; ?>
<?php $show_save_and_new = $returnTo === ''; ?>
<?php $show_save_and_exit = $returnTo === ''; ?>
<?php $saveLabel = $returnTo !== '' ? 'Salvar e voltar' : 'Salvar'; ?>

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
        <input type="hidden" name="return_to" value="<?php echo Helpers::e($returnTo); ?>">
        <?php if ($cursoBloqueado): ?>
            <?php
            $cursoBloqueadoNome = '';
            foreach ($cursos as $curso) {
                if ((int) $curso['id'] === $cursoBloqueadoId) {
                    $cursoBloqueadoNome = $curso['nome'];
                    break;
                }
            }
            ?>
            <div class="area-curso-course-lock">
                <span class="area-curso-course-lock__label">Curso vinculado</span>
                <strong class="area-curso-course-lock__name"><?php echo Helpers::e($cursoBloqueadoNome !== '' ? $cursoBloqueadoNome : 'Curso selecionado'); ?></strong>
                <p class="area-curso-course-lock__help">Este vínculo veio da área interna do curso e não pode ser alterado aqui.</p>
            </div>
            <input type="hidden" name="curso_id" value="<?php echo (int) $cursoBloqueadoId; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoBloqueadoId; ?>">
        <?php else: ?>
            <label>
                Curso
                <select name="curso_evento_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) $value('curso_evento_id', 0) === (int) $curso['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($curso['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>
            Professor responsável
            <select name="professor_responsavel_usuario_id">
                <option value="">Sem professor definido</option>
                <?php foreach ($professores as $professor): ?>
                    <option value="<?php echo (int) $professor['id']; ?>" <?php echo (int) $value('professor_responsavel_usuario_id', !empty($professorResponsavel['usuario_id']) ? (int) $professorResponsavel['usuario_id'] : 0) === (int) $professor['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($professor['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Nome
            <input type="text" name="nome" value="<?php echo Helpers::e($value('nome')); ?>" required>
        </label>
        <label>
            Slug
            <input type="text" name="slug" value="<?php echo Helpers::e($value('slug')); ?>">
        </label>
        <label>
            Código
            <input type="text" name="codigo" value="<?php echo Helpers::e($value('codigo')); ?>" required>
        </label>
        <label>
            Data de início
            <input type="date" name="data_inicio" value="<?php echo Helpers::e(substr((string) $value('data_inicio', ''), 0, 10)); ?>">
        </label>
        <label>
            Data de fim
            <input type="date" name="data_fim" value="<?php echo Helpers::e(substr((string) $value('data_fim', ''), 0, 10)); ?>">
        </label>
        <label>
            Vagas
            <input type="number" name="vagas" min="0" value="<?php echo Helpers::e((string) $value('vagas', '')); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <?php foreach ($statusLabels as $status => $label): ?>
                    <option value="<?php echo Helpers::e($status); ?>" <?php echo ((string) $value('status', 'planejada') === $status) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php $cancelUrl = $returnTo !== '' ? $returnTo : '/admin/turmas'; ?>
        <?php $save_label = $saveLabel; ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>
