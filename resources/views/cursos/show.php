<?php use App\Core\Helpers; ?>
<?php
$professoresResponsaveis = isset($curso['professores_responsaveis']) && is_array($curso['professores_responsaveis']) ? $curso['professores_responsaveis'] : array();
$professoresResponsaveisNomes = array();
foreach ($professoresResponsaveis as $professorResponsavel) {
    if (!empty($professorResponsavel['nome'])) {
        $professoresResponsaveisNomes[] = $professorResponsavel['nome'];
    }
}
if (empty($professoresResponsaveisNomes) && !empty($curso['professor_responsavel']['nome'])) {
    $professoresResponsaveisNomes[] = $curso['professor_responsavel']['nome'];
}
?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1><?php echo Helpers::e($curso['nome']); ?></h1>
        <p><?php echo Helpers::e($curso['descricao_curta'] ?: 'Curso disponível para inscrição pública.'); ?></p>
    </section>

    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success front-section">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error front-section">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="checkout-grid front-card-grid front-section">
        <article class="checkout-panel front-card">
        <h2>Detalhes</h2>
        <?php if (!empty($curso['thumbnail'])): ?>
            <div class="course-detail__image">
                <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome']); ?>">
            </div>
        <?php endif; ?>
        <dl class="summary-list">
            <dt>Categoria</dt>
            <dd><?php echo Helpers::e($curso['categoria_nome'] ?: 'Sem categoria'); ?></dd>
            <dt>Tipo</dt>
            <dd><?php echo Helpers::e($curso['tipo']); ?></dd>
            <dt>Modalidade</dt>
            <dd><?php echo Helpers::e($curso['modalidade']); ?></dd>
            <dt>Valor</dt>
            <dd>
                <?php if (!empty($curso['desconto_promocional'])): ?>
                    <?php $valorEfetivo = (float) $curso['valor_efetivo']; ?>
                    <?php if ($valorEfetivo > 0): ?>
                        <div class="muted" style="text-decoration:line-through;">R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></div>
                        <div><strong>R$ <?php echo number_format($valorEfetivo, 2, ',', '.'); ?></strong></div>
                        <div class="muted" style="font-size:12px;">
                            Desconto de R$ <?php echo number_format((float) $curso['desconto_promocional']['desconto_valor'], 2, ',', '.'); ?>
                            (<?php echo (int) round((float) $curso['desconto_promocional']['desconto_percentual']); ?>%)
                        </div>
                    <?php else: ?>
                        <strong>Gratuito</strong>
                    <?php endif; ?>
                <?php else: ?>
                    <?php $valorPublico = (float) ($curso['valor_efetivo'] ?? $curso['valor']); ?>
                    <strong><?php echo $valorPublico <= 0 ? 'Gratuito' : 'R$ ' . number_format($valorPublico, 2, ',', '.'); ?></strong>
                <?php endif; ?>
            </dd>
            <?php if (!empty($professoresResponsaveisNomes)): ?>
                <dt><?php echo Helpers::e(count($professoresResponsaveisNomes) > 1 ? 'Professores responsáveis' : 'Professor responsável'); ?></dt>
                <dd><?php echo Helpers::e(implode(', ', $professoresResponsaveisNomes)); ?></dd>
            <?php endif; ?>
        </dl>

        <?php if (!empty($curso['descricao_completa']) || !empty($curso['descricao_curta'])): ?>
            <h3 style="margin:16px 0 6px;">Descritivo do curso</h3>
            <p><?php echo nl2br(Helpers::e($curso['descricao_completa'] ?: $curso['descricao_curta'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($curso['objetivo_geral'])): ?>
            <h3 style="margin:16px 0 6px;">Objetivo geral</h3>
            <p><?php echo nl2br(Helpers::e($curso['objetivo_geral'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($curso['objetivos_especificos'])): ?>
            <h3 style="margin:16px 0 6px;">Objetivos específicos</h3>
            <p><strong>Ao final do curso, o participante deverá ser capaz de:</strong></p>
            <ul>
                <?php foreach (preg_split('/\\r\\n|\\r|\\n/', (string) $curso['objetivos_especificos']) as $linha): ?>
                    <?php $linha = trim((string) $linha); ?>
                    <?php if ($linha !== ''): ?><li><?php echo Helpers::e($linha); ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($curso['publico_alvo'])): ?>
            <h3 style="margin:16px 0 6px;">Público-alvo</h3>
            <p><strong>O curso é indicado para:</strong></p>
            <p><?php echo nl2br(Helpers::e($curso['publico_alvo'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($curso['pre_requisitos_texto']) || !empty($curso['pre_requisitos_itens'])): ?>
            <h3 style="margin:16px 0 6px;">Pré-requisitos</h3>
            <?php if (!empty($curso['pre_requisitos_texto'])): ?>
                <p><?php echo nl2br(Helpers::e($curso['pre_requisitos_texto'])); ?></p>
            <?php endif; ?>
            <?php if (!empty($curso['pre_requisitos_itens'])): ?>
                <ul>
                    <?php foreach (preg_split('/\\r\\n|\\r|\\n/', (string) $curso['pre_requisitos_itens']) as $linha): ?>
                        <?php $linha = trim((string) $linha); ?>
                        <?php if ($linha !== ''): ?><li><?php echo Helpers::e($linha); ?></li><?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($curso['ementa'])): ?>
            <h3 style="margin:16px 0 6px;">Ementa</h3>
            <p><?php echo nl2br(Helpers::e($curso['ementa'])); ?></p>
        <?php endif; ?>

        <?php
        $cp = isset($curso['conteudo_programatico_view']) && is_array($curso['conteudo_programatico_view'])
            ? $curso['conteudo_programatico_view']
            : array('tipo' => 'texto');
        ?>
        <?php if (!empty($cp['texto']) || !empty($cp['html']) || !empty($cp['modulos'])): ?>
            <h3 style="margin:16px 0 6px;">Conteúdo programático</h3>
            <?php if (($cp['tipo'] ?? 'texto') === 'modulos'): ?>
                <div class="stack front-card-list">
                    <?php foreach (($cp['modulos'] ?? array()) as $modulo): ?>
                        <?php if (!is_array($modulo)) continue; ?>
                        <div class="status-card front-card" style="padding:12px;">
                            <?php if (!empty($modulo['titulo'])): ?>
                                <strong><?php echo Helpers::e($modulo['titulo']); ?></strong>
                            <?php endif; ?>
                            <?php if (!empty($modulo['itens']) && is_array($modulo['itens'])): ?>
                                <ul style="margin-top:8px;">
                                    <?php foreach ($modulo['itens'] as $item): ?>
                                        <?php $item = trim((string) $item); ?>
                                        <?php if ($item !== ''): ?><li><?php echo Helpers::e($item); ?></li><?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (($cp['tipo'] ?? 'texto') === 'html'): ?>
                <div><?php echo (string) ($cp['html'] ?? ''); ?></div>
            <?php else: ?>
                <p><?php echo nl2br(Helpers::e((string) ($cp['texto'] ?? ''))); ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($curso['metodologia'])): ?>
            <h3 style="margin:16px 0 6px;">Metodologia</h3>
            <p><?php echo nl2br(Helpers::e($curso['metodologia'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($curso['produto_final'])): ?>
            <h3 style="margin:16px 0 6px;">Produto final</h3>
            <p><strong>O participante desenvolverá competências para:</strong></p>
            <?php $linhas = array_values(array_filter(array_map('trim', preg_split('/\\r\\n|\\r|\\n/', (string) $curso['produto_final'])))); ?>
            <?php if (count($linhas) > 1): ?>
                <ul>
                    <?php foreach ($linhas as $linha): ?><li><?php echo Helpers::e($linha); ?></li><?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php echo nl2br(Helpers::e((string) $curso['produto_final'])); ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($curso['avaliacao'])): ?>
            <h3 style="margin:16px 0 6px;">Avaliação</h3>
            <p><?php echo nl2br(Helpers::e($curso['avaliacao'])); ?></p>
        <?php endif; ?>
        </article>

        <article class="checkout-panel">
        <h2>Turmas abertas</h2>
        <?php if (empty($curso['turmas_abertas'])): ?>
            <p class="muted">Não há turma aberta no momento para inscrição pública.</p>
        <?php else: ?>
            <div class="stack front-card-list">
                <?php foreach ($curso['turmas_abertas'] as $turma): ?>
                    <div class="status-card front-card<?php echo !empty($curso['turma_selecionada']) && (int) $curso['turma_selecionada']['id'] === (int) $turma['id'] ? ' status-card--selected' : ''; ?>">
                        <strong><?php echo Helpers::e($turma['nome']); ?></strong>
                        <span>Código <?php echo Helpers::e($turma['codigo']); ?></span>
                        <?php if (!empty($turma['data_inicio'])): ?>
                            <span>Início <?php echo Helpers::e($turma['data_inicio']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($turma['data_fim'])): ?>
                            <span>Fim <?php echo Helpers::e($turma['data_fim']); ?></span>
                        <?php endif; ?>
                        <span>Status <?php echo Helpers::e($turma['status']); ?></span>
                        <div class="cta-group">
                            <a class="button-link button-link--ghost" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $turma['id']; ?>">Ver turma</a>
                            <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $turma['id']; ?>">Inscrever nesta turma</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </article>
    </section>

    <?php if (!empty($curso['inscricao_disponivel']) && !empty($curso['turma_selecionada'])): ?>
        <section class="notice notice--success front-card front-section">
            <strong>Inscrição disponível</strong>
            <p>A turma <?php echo Helpers::e($curso['turma_selecionada']['nome']); ?> está aberta e pode receber inscrições agora.</p>
            <div class="cta-group">
                <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $curso['turma_selecionada']['id']; ?>">Iniciar inscrição</a>
            </div>
        </section>
    <?php else: ?>
        <section class="notice front-card front-section">
            <strong>Sem inscrição aberta</strong>
            <p>Este curso está público, mas ainda não possui turma aberta para inscrição.</p>
        </section>
    <?php endif; ?>

    <?php if (empty($loggedIn)): ?>
        <section class="notice front-section">
            <strong>Para continuar</strong>
            <p>Entre na sua conta ou crie uma nova para seguir com a inscrição.</p>
            <div class="cta-group">
                <a class="button-link" href="/login">Entrar</a>
                <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
            </div>
        </section>
    <?php endif; ?>
</div>

