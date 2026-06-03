<?php use App\Core\Helpers; ?>
<?php
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

<section class="aluno-curso-shell">
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

    <section class="status-card aluno-module-header">
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

    <?php if (empty($itens)): ?>
        <section class="status-card aluno-empty-state">
            <strong>Este módulo ainda não possui conteúdos publicados.</strong>
            <span>Quando houver conteúdos publicados, eles aparecerão nesta lista.</span>
            <p><a class="button-link button-link--ghost" href="<?php echo Helpers::e($cursoUrl); ?>">Voltar aos módulos</a></p>
        </section>
    <?php else: ?>
        <section class="aluno-conteudos-list" aria-label="Lista de conteúdos publicados">
            <?php foreach ($itens as $item): ?>
                <?php
                $itemId = (int) ($item['id'] ?? 0);
                $itemTitulo = Helpers::normalizarTextoLms((string) ($item['titulo'] ?? 'Conteúdo'));
                $itemUrl = !empty($item['detalhes_url']) ? (string) $item['detalhes_url'] : $moduloUrl . '/conteudo/' . $itemId;
                $itemTipo = isset($item['tipo_label']) ? (string) $item['tipo_label'] : 'Conteúdo';
                $itemStatus = isset($item['status_label']) ? (string) $item['status_label'] : 'Pendente';
                $itemStatusClass = isset($item['status_class']) ? (string) $item['status_class'] : 'pill--neutral';
                $itemConcluido = !empty($item['concluido_aluno']);
                $itemDescricao = !empty($item['descricao_curta']) ? trim((string) $item['descricao_curta']) : '';
                ?>
                <article class="status-card aluno-conteudo-card">
                    <div class="aluno-conteudo-card__top">
                        <a class="aluno-conteudo-card__title" href="<?php echo Helpers::e($itemUrl); ?>">
                            <?php echo Helpers::e($itemTitulo); ?>
                        </a>
                        <div class="aluno-conteudo-card__badges">
                            <span class="pill pill--neutral"><?php echo Helpers::e($itemTipo); ?></span>
                            <span class="pill <?php echo Helpers::e($itemStatusClass); ?>"><?php echo Helpers::e($itemStatus); ?></span>
                            <?php if ($itemConcluido): ?>
                                <span class="pill pill--success">Concluído</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($itemDescricao !== ''): ?>
                        <p class="aluno-conteudo-card__description"><?php echo Helpers::e($itemDescricao); ?></p>
                    <?php endif; ?>

                    <div class="aluno-conteudo-card__footer">
                        <span class="aluno-progress-chip aluno-progress-chip--compact" aria-label="Status do conteúdo">
                            <strong><?php echo $itemConcluido ? '100%' : '0%'; ?></strong>
                            <span><?php echo $itemConcluido ? 'Concluído' : 'Pendente'; ?></span>
                        </span>
                        <a class="button-link" href="<?php echo Helpers::e($itemUrl); ?>"><?php echo Helpers::e(isset($item['acao_label']) && $item['acao_label'] !== '' ? (string) $item['acao_label'] : 'Abrir conteúdo'); ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</section>
