<?php use App\Core\Helpers; ?>

<?php $curso = isset($form_data['curso']) ? $form_data['curso'] : null; ?>
<?php $categorias = isset($form_data['categorias']) ? $form_data['categorias'] : array(); ?>
<?php $professores = isset($form_data['professores']) ? $form_data['professores'] : array(); ?>
<?php $professorResponsavel = isset($form_data['professor_responsavel']) ? $form_data['professor_responsavel'] : null; ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
        <p class="admin-page__subtitle">Cadastro e manutenção de cursos e eventos.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
        <input type="hidden" name="id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
        <label>
            Nome
            <input type="text" name="nome" value="<?php echo Helpers::e($curso['nome'] ?? ''); ?>" required>
        </label>
        <label>
            Slug
            <input type="text" name="slug" value="<?php echo Helpers::e($curso['slug'] ?? ''); ?>">
        </label>
        <label>
            Categoria
            <select name="categoria_id">
                <option value="">Sem categoria</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?php echo (int) $categoria['id']; ?>" <?php echo !empty($curso['categoria_id']) && (int) $curso['categoria_id'] === (int) $categoria['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($categoria['nome']); ?>
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
            Tipo
            <select name="tipo">
                <option value="curso" <?php echo (($curso['tipo'] ?? '') === 'curso') ? 'selected' : ''; ?>>Curso</option>
                <option value="evento" <?php echo (($curso['tipo'] ?? '') === 'evento') ? 'selected' : ''; ?>>Evento</option>
            </select>
        </label>
        <label>
            Modalidade
            <select name="modalidade">
                <?php foreach (($form_data['modalidades'] ?? array()) as $modalidade): ?>
                    <option value="<?php echo Helpers::e($modalidade); ?>" <?php echo (($curso['modalidade'] ?? 'presencial') === $modalidade) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($modalidade); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Thumbnail
            <input type="text" name="thumbnail" value="<?php echo Helpers::e($curso['thumbnail'] ?? ''); ?>">
        </label>
        <label class="full">
            Descrição curta
            <textarea name="descricao_curta" rows="3"><?php echo Helpers::e($curso['descricao_curta'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Descrição completa
            <textarea name="descricao_completa" rows="5"><?php echo Helpers::e($curso['descricao_completa'] ?? ''); ?></textarea>
        </label>
        <label>
            Carga horária
            <input type="number" name="carga_horaria" min="0" value="<?php echo Helpers::e((string) ($curso['carga_horaria'] ?? '')); ?>">
        </label>
        <label>
            Valor
            <input type="number" step="0.01" min="0" name="valor" value="<?php echo Helpers::e((string) ($curso['valor'] ?? '0.00')); ?>">
        </label>
        <label>
            Ordem
            <input type="number" name="ordem" min="0" value="<?php echo Helpers::e((string) ($curso['ordem'] ?? 0)); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <?php foreach (array('rascunho', 'ativo', 'inativo', 'arquivado') as $status): ?>
                    <option value="<?php echo Helpers::e($status); ?>" <?php echo (($curso['status'] ?? '') === $status) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="em_promocao" value="1" <?php echo !empty($curso['em_promocao']) ? 'checked' : ''; ?>>
            Em promoção
        </label>
        <label class="checkbox">
            <input type="checkbox" name="destaque" value="1" <?php echo !empty($curso['destaque']) ? 'checked' : ''; ?>>
            Destaque
        </label>
        <button type="submit"><?php echo Helpers::e($submit_label); ?></button>
    </form>
</section>
</div>

