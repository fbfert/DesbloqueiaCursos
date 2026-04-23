<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1><?php echo Helpers::e($selected_modulo['titulo']); ?></h1>
    <p><?php echo Helpers::e($curso['nome']); ?> - <?php echo Helpers::e(isset($turma['nome']) ? $turma['nome'] : 'Sem turma'); ?></p>
</section>

<?php if (!empty($success)): ?>
    <section class="auth-message auth-message-success">
        <p><?php echo Helpers::e($success); ?></p>
    </section>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <section class="auth-message auth-message-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo Helpers::e($error); ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="pill-row" style="margin-bottom:16px;">
    <a class="pill" href="/area-curso?inscricao_id=<?php echo (int) $inscricao['id']; ?>">Voltar</a>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Modulo</h2>
            <p><?php echo Helpers::e($selected_modulo['descricao']); ?></p>
        </div>
    </div>

    <div class="pill-row">
        <span class="pill"><?php echo !empty($selected_modulo['visivel']) ? 'visivel' : 'oculto'; ?></span>
        <span class="pill">ordem <?php echo (int) $selected_modulo['ordem']; ?></span>
    </div>

    <form method="post" action="/area-curso/modulo/concluir-modulo" class="form-grid" style="margin-top:16px;">
        <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricao['id']; ?>">
        <input type="hidden" name="modulo_id" value="<?php echo (int) $selected_modulo['id']; ?>">
        <button type="submit">Marcar modulo como concluido</button>
    </form>
</section>

<?php if (!empty($selected_aula)): ?>
    <section class="panel">
        <div class="panel-header"><div><h2>Aula selecionada</h2></div></div>
        <h3><?php echo Helpers::e($selected_aula['titulo']); ?></h3>
        <p><?php echo Helpers::e($selected_aula['conteudo']); ?></p>
        <?php if (!empty($selected_aula['url_video'])): ?>
            <p><a href="<?php echo Helpers::e($selected_aula['url_video']); ?>" target="_blank" rel="noopener">Abrir video/link da aula</a></p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><div><h2>Aulas</h2></div></div>
    <?php foreach ($selected_modulo['aulas'] as $aula): ?>
        <article class="course-card" style="margin-bottom:12px;">
            <div class="course-card__body">
                <strong><?php echo Helpers::e($aula['titulo']); ?></strong>
                <p><?php echo Helpers::e($aula['conteudo']); ?></p>
                <div class="pill-row">
                    <span class="pill"><?php echo Helpers::e($aula['tipo']); ?></span>
                    <span class="pill"><?php echo !empty($aula['obrigatoria']) ? 'obrigatoria' : 'opcional'; ?></span>
                </div>
                <?php if (!empty($aula['url_video'])): ?>
                    <p style="margin-top:12px;"><a href="<?php echo Helpers::e($aula['url_video']); ?>" target="_blank" rel="noopener">Abrir video/link</a></p>
                <?php endif; ?>
                <form method="post" action="/area-curso/modulo/concluir-aula" class="form-grid" style="margin-top:12px;">
                    <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricao['id']; ?>">
                    <input type="hidden" name="modulo_id" value="<?php echo (int) $selected_modulo['id']; ?>">
                    <input type="hidden" name="aula_id" value="<?php echo (int) $aula['id']; ?>">
                    <button type="submit">Concluir aula</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-header"><div><h2>Materiais</h2></div></div>
    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Titulo</th><th>Tipo</th><th>Acesso</th></tr></thead>
            <tbody>
                <?php foreach ($selected_modulo['materiais'] as $material): ?>
                    <tr>
                        <td><?php echo Helpers::e($material['titulo']); ?></td>
                        <td><?php echo Helpers::e($material['tipo_arquivo']); ?></td>
                        <td><a href="/area-curso/material?material_id=<?php echo (int) $material['id']; ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><div><h2>Links externos</h2></div></div>
    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Titulo</th><th>Tipo</th><th>URL</th></tr></thead>
            <tbody>
                <?php foreach ($selected_modulo['links'] as $link): ?>
                    <tr>
                        <td><?php echo Helpers::e($link['titulo']); ?></td>
                        <td><?php echo Helpers::e($link['tipo_link']); ?></td>
                        <td><a href="<?php echo Helpers::e($link['url']); ?>" target="_blank" rel="noopener">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
