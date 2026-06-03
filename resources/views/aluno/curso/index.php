<?php use App\Core\Helpers; ?>
<?php
$inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$turmaNome = Helpers::normalizarTextoLms(!empty($turma['nome']) ? $turma['nome'] : '');
$cursoTitulo = $cursoNome !== '' ? $cursoNome : 'Curso';
$cursoSubtitulo = $turmaNome !== '' ? 'Turma: ' . $turmaNome : '';
$resumo = isset($conteudo_resumo) && is_array($conteudo_resumo) ? $conteudo_resumo : array();
$modulos = isset($conteudo_modulos) && is_array($conteudo_modulos) ? $conteudo_modulos : array();
$totalConteudos = (int) ($resumo['total_itens'] ?? 0);
$concluidosConteudos = (int) ($resumo['concluidos_itens'] ?? 0);
$percentual = (float) ($resumo['percentual'] ?? ($resumo['percentual_progresso'] ?? 0));
$progressoTexto = $totalConteudos > 0 ? $concluidosConteudos . '/' . $totalConteudos . ' conteúdos concluídos' : 'Sem conteúdos publicados';
?>

<section class="aluno-curso-shell">
    <section class="status-card aluno-course-header">
        <div class="aluno-course-header__top">
            <div class="aluno-course-header__title">
                <p class="aluno-course-eyebrow">Módulos do curso</p>
                <h1><?php echo Helpers::e($cursoTitulo); ?></h1>
                <?php if ($cursoSubtitulo !== ''): ?>
                    <p class="aluno-course-header__subtitle"><?php echo Helpers::e($cursoSubtitulo); ?></p>
                <?php endif; ?>
            </div>

            <div class="aluno-progress-chip" aria-label="Progresso geral do curso">
                <strong><?php echo Helpers::e(number_format(max(0, min(100, $percentual)), 2, ',', '.')); ?>%</strong>
                <span><?php echo Helpers::e($progressoTexto); ?></span>
            </div>
        </div>

    </section>

    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success" aria-live="polite">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error" aria-live="polite">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (empty($modulos)): ?>
        <section class="status-card aluno-empty-state">
            <strong>Nenhum módulo publicado foi encontrado.</strong>
            <span>Quando houver módulos publicados nesta turma, eles aparecerão aqui em cartões compactos.</span>
            <p><a class="button-link button-link--ghost" href="/aluno/meus-cursos">Voltar aos meus cursos</a></p>
        </section>
    <?php else: ?>
        <section class="aluno-modulos-grid" aria-label="Lista de módulos publicados">
            <?php foreach ($modulos as $modulo): ?>
                <?php
                $moduloId = (int) ($modulo['id'] ?? 0);
                $moduloTitulo = Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo'));
                $moduloUrl = '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
                $moduloTotal = (int) ($modulo['total_itens'] ?? 0);
                $moduloConcluidos = (int) ($modulo['concluidos_itens'] ?? 0);
                $moduloPercentual = (float) ($modulo['percentual_conclusao'] ?? 0);
                $moduloStatus = isset($modulo['status_label']) ? (string) $modulo['status_label'] : 'Publicado';
                $moduloStatusClass = isset($modulo['status_class']) ? (string) $modulo['status_class'] : 'pill--neutral';
                ?>
                <article class="status-card aluno-modulo-card">
                    <a class="aluno-modulo-card__title" href="<?php echo Helpers::e($moduloUrl); ?>">
                        <?php echo Helpers::e($moduloTitulo); ?>
                    </a>

                    <div class="aluno-modulo-card__meta">
                        <span class="pill pill--neutral"><?php echo (int) $moduloTotal; ?> conteúdos</span>
                        <span class="pill pill--neutral"><?php echo (int) $moduloConcluidos; ?>/<?php echo (int) $moduloTotal; ?> concluídos</span>
                        <span class="pill <?php echo Helpers::e($moduloStatusClass); ?>"><?php echo Helpers::e($moduloStatus); ?></span>
                    </div>

                    <div class="aluno-modulo-card__footer">
                        <div class="aluno-progress-chip aluno-progress-chip--compact" aria-label="Progresso do módulo">
                            <strong><?php echo Helpers::e(number_format(max(0, min(100, $moduloPercentual)), 2, ',', '.')); ?>%</strong>
                            <span><?php echo (int) $moduloConcluidos; ?>/<?php echo (int) $moduloTotal; ?></span>
                        </div>

                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e($moduloUrl); ?>">Abrir módulo</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</section>
