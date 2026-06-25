<?php use App\Core\Helpers; ?>
<?php
if (!isset($frontend_template)) { try { $frontend_template = (new \App\Services\ConfiguracaoGlobalService())->templateVisualPortal(); } catch (\Throwable $e) { $frontend_template = 'v1'; } }
if ((string) $frontend_template === 'v4-claude') { require BASE_PATH . '/resources/views/v4-claude/aluno/curso/modulo.php'; return; }
$inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$turmaNome = Helpers::normalizarTextoLms(!empty($turma['nome']) ? $turma['nome'] : '');
$cursoTitulo = $cursoNome !== '' ? $cursoNome : 'Curso';
$modulo = isset($conteudo_modulo) && is_array($conteudo_modulo) ? $conteudo_modulo : array();
$moduloId = (int) ($modulo['id'] ?? 0);
$moduloTitulo = Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo'));
$moduloUrl = '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
$cursoUrl = isset($conteudo_geral_url) && $conteudo_geral_url !== '' ? (string) $conteudo_geral_url : '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId;
$itens = !empty($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
$totalItens = (int) ($modulo['total_itens'] ?? count($itens));
$concluidosItens = (int) ($modulo['concluidos_itens'] ?? 0);
$percentual = (float) ($modulo['percentual_conclusao'] ?? 0);
$statusLabel = isset($modulo['status_label']) ? (string) $modulo['status_label'] : 'Publicado';
$statusClass = isset($modulo['status_class']) ? (string) $modulo['status_class'] : 'pill--neutral';
$progressoTexto = $totalItens > 0 ? $concluidosItens . '/' . $totalItens . ' concluídos' : 'Sem conteúdos publicados';
?>

<div class="student-module-page aluno-curso-shell">
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

    <section class="status-card aluno-module-header student-module-shell student-module-hero">
        <div class="aluno-module-header__top">
            <div>
                <p class="aluno-course-eyebrow">Conteúdos do módulo</p>
                <h1><?php echo Helpers::e($moduloTitulo); ?></h1>
                <p class="aluno-module-header__subtitle"><?php echo Helpers::e($cursoTitulo); ?><?php echo $turmaNome !== '' ? ' - ' . Helpers::e($turmaNome) : ''; ?></p>
            </div>

            <div class="aluno-progress-chip" aria-label="Progresso do módulo">
                <strong><?php echo Helpers::e(number_format(max(0, min(100, $percentual)), 2, ',', '.')); ?>%</strong>
                <span><?php echo Helpers::e($progressoTexto); ?></span>
            </div>
        </div>

        <div class="aluno-module-header__actions">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($cursoUrl); ?>">← Voltar aos módulos</a>
            <span class="pill <?php echo Helpers::e($statusClass); ?>"><?php echo Helpers::e($statusLabel); ?></span>
            <span class="pill pill--neutral"><?php echo (int) $totalItens; ?> conteúdos</span>
        </div>
    </section>

    <section class="student-module-shell student-module-content">
    <?php
    $cards = array();
    foreach ($itens as $item) {
        $itemId = (int) ($item['id'] ?? 0);
        $itemUrl = !empty($item['detalhes_url']) ? (string) $item['detalhes_url'] : $moduloUrl . '/conteudo/' . $itemId;
        $itemTipo = isset($item['tipo_label']) ? (string) $item['tipo_label'] : 'Conteúdo';
        $itemStatus = isset($item['status_label']) ? (string) $item['status_label'] : 'Pendente';
        $itemStatusClass = isset($item['status_class']) ? (string) $item['status_class'] : 'pill--neutral';
        $itemConcluido = !empty($item['concluido_aluno']);
        $itemDescricao = !empty($item['descricao_curta']) ? trim((string) $item['descricao_curta']) : '';

        $cards[] = array(
            'titulo' => Helpers::normalizarTextoLms((string) ($item['titulo'] ?? 'Conteúdo')),
            'url' => $itemUrl,
            'meta' => array(
                array('label' => $itemTipo, 'class' => 'pill--neutral'),
                array('label' => $itemStatus, 'class' => $itemStatusClass),
                $itemConcluido ? array('label' => 'Concluído', 'class' => 'pill--success') : null,
            ),
            'percentual' => $itemConcluido ? 100 : 0,
            'progresso_texto' => $itemConcluido ? 'Concluído' : 'Pendente',
            'progresso_label' => 'Status do conteúdo',
            'acao_label' => isset($item['acao_label']) && $item['acao_label'] !== '' ? (string) $item['acao_label'] : 'Abrir conteúdo',
            'acao_class' => 'button-link--ghost',
            'descricao' => $itemDescricao,
        );
    }

    echo \App\Core\View::render('aluno/curso/_conteudo_cards', array(
        'cards' => $cards,
        'lista_label' => 'Lista de conteúdos publicados',
        'empty_message' => 'Este módulo ainda não possui conteúdos publicados.',
        'empty_description' => 'Quando houver conteúdos publicados, eles aparecerão nesta lista.',
        'empty_action_url' => $cursoUrl,
        'empty_action_label' => 'Voltar aos módulos',
    ), false);
    ?>
    </section>
</div>
