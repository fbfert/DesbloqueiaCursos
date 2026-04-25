<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1><?php echo Helpers::e($curso['nome']); ?></h1>
    <p><?php echo Helpers::e(isset($turma['nome']) ? $turma['nome'] : 'Area vinculada a inscricao aprovada'); ?></p>
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

<section class="card-grid">
    <article class="status-card">
        <strong>Progresso</strong>
        <span><?php echo Helpers::e(number_format((float) $percentual_progresso, 2, ',', '.')); ?>%</span>
    </article>
    <article class="status-card">
        <strong>Certificado</strong>
        <span><?php echo !empty($apto_certificado) ? 'Apto' : 'Em andamento'; ?></span>
    </article>
</section>

<?php if (!empty($inscricoes)): ?>
    <section class="pill-row" style="margin: 12px 0 18px;">
        <?php foreach ($inscricoes as $inscricaoItem): ?>
            <a class="pill<?php echo (int) $inscricaoItem['id'] === (int) $inscricao['id'] ? ' pill--active' : ''; ?>" href="/area-curso?inscricao_id=<?php echo (int) $inscricaoItem['id']; ?>">
                <?php echo Helpers::e($inscricaoItem['curso_nome']); ?>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Instruções</h2>
            <p>Orientacoes publicas da turma ou do curso.</p>
        </div>
    </div>
    <?php if (!empty($instrucoes)): ?>
        <h3><?php echo Helpers::e($instrucoes['titulo']); ?></h3>
        <div><?php echo nl2br(Helpers::e($instrucoes['conteudo'])); ?></div>
    <?php else: ?>
        <p>Sem instrucoes publicadas.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Modulos</h2>
            <p>Estrutura inicial da area interna.</p>
        </div>
    </div>

    <div class="card-grid">
        <?php foreach ($modulos as $modulo): ?>
            <?php
                $aulasVisiveis = 0;
                foreach ($modulo['aulas'] as $aula) {
                    if (!empty($aula['visivel'])) {
                        $aulasVisiveis++;
                    }
                }
            ?>
            <article class="course-card">
                <div class="course-card__body">
                    <strong><?php echo Helpers::e($modulo['titulo']); ?></strong>
                    <p><?php echo Helpers::e($modulo['descricao']); ?></p>
                    <div class="pill-row">
                        <span class="pill"><?php echo (int) $aulasVisiveis; ?> aulas</span>
                        <span class="pill"><?php echo !empty($modulo['visivel']) ? 'visivel' : 'oculto'; ?></span>
                    </div>
                    <div style="margin-top:12px;">
                        <a class="pill" href="/area-curso/modulo?inscricao_id=<?php echo (int) $inscricao['id']; ?>&modulo_id=<?php echo (int) $modulo['id']; ?>">Abrir modulo</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><div><h2>Materiais e links</h2></div></div>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr><th>Tipo</th><th>Titulo</th><th>Acesso</th></tr>
            </thead>
            <tbody>
                <?php foreach ($materiais as $material): ?>
                    <tr>
                        <td>Material</td>
                        <td><?php echo Helpers::e($material['titulo']); ?></td>
                        <td><a href="/area-curso/material?material_id=<?php echo (int) $material['id']; ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($links as $link): ?>
                    <tr>
                        <td>Link</td>
                        <td><?php echo Helpers::e($link['titulo']); ?></td>
                        <td><a href="<?php echo Helpers::e($link['url']); ?>" target="_blank" rel="noopener">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

