<?php use App\Core\Helpers; ?>
<?php
if (!isset($frontend_template)) { try { $frontend_template = (new \App\Services\ConfiguracaoGlobalService())->templateVisualPortal(); } catch (\Throwable $e) { $frontend_template = 'v1'; } }
if ((string) $frontend_template === 'v4-claude') { require BASE_PATH . '/resources/views/v4-claude/aluno/curso/index.php'; return; }
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

    <?php
    $cards = array();
    foreach ($modulos as $modulo) {
        $moduloId = (int) ($modulo['id'] ?? 0);
        $moduloUrl = '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
        $moduloTotal = (int) ($modulo['total_itens'] ?? 0);
        $moduloConcluidos = (int) ($modulo['concluidos_itens'] ?? 0);
        $moduloPercentual = (float) ($modulo['percentual_conclusao'] ?? 0);
        $moduloStatus = isset($modulo['status_label']) ? (string) $modulo['status_label'] : 'Publicado';
        $moduloStatusClass = isset($modulo['status_class']) ? (string) $modulo['status_class'] : 'pill--neutral';

        $cards[] = array(
            'titulo' => Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo')),
            'url' => $moduloUrl,
            'meta' => array(
                array('label' => $moduloTotal . ' conteúdos', 'class' => 'pill--neutral'),
                array('label' => $moduloConcluidos . '/' . $moduloTotal . ' concluídos', 'class' => 'pill--neutral'),
                array('label' => $moduloStatus, 'class' => $moduloStatusClass),
            ),
            'percentual' => $moduloPercentual,
            'progresso_texto' => $moduloConcluidos . '/' . $moduloTotal,
            'progresso_label' => 'Progresso do módulo',
            'acao_label' => 'Abrir módulo',
            'acao_class' => 'button-link--ghost',
        );
    }

    echo \App\Core\View::render('aluno/curso/_conteudo_cards', array(
        'cards' => $cards,
        'lista_label' => 'Lista de módulos publicados',
        'empty_message' => 'Nenhum módulo publicado foi encontrado.',
        'empty_description' => 'Quando houver módulos publicados nesta turma, eles aparecerão aqui em cartões compactos.',
        'empty_action_url' => '/aluno/meus-cursos',
        'empty_action_label' => 'Voltar aos meus cursos',
    ), false);
    ?>
</section>
