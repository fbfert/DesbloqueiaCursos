<?php use App\Core\Helpers; ?>
<?php
if (!function_exists('curso_preco_publico_texto')) {
    function curso_preco_publico_texto(array $curso)
    {
        $valorEfetivo = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : (float) ($curso['valor'] ?? 0);
        return $valorEfetivo <= 0 ? 'Gratuito' : 'R$ ' . number_format($valorEfetivo, 2, ',', '.');
    }
}

if (!function_exists('curso_professores_publico_texto')) {
    function curso_professores_publico_texto(array $curso)
    {
        $nomes = array();
        $professoresResponsaveis = isset($curso['professores_responsaveis']) && is_array($curso['professores_responsaveis']) ? $curso['professores_responsaveis'] : array();

        foreach ($professoresResponsaveis as $professorResponsavel) {
            if (!empty($professorResponsavel['nome'])) {
                $nomes[] = $professorResponsavel['nome'];
            }
        }

        if (empty($nomes) && !empty($curso['professor_responsavel']['nome'])) {
            $nomes[] = $curso['professor_responsavel']['nome'];
        }

        return $nomes;
    }
}
?>

<section class="page-header">
    <h1>Cursos e eventos</h1>
    <p>Confira apenas cursos ativos do portal. O frontend não publica itens inativos nem dados operacionais do backoffice.</p>
</section>

<section class="card-grid">
    <?php if (empty($cursos)): ?>
        <article class="status-card">
            <strong>Nenhum curso ativo</strong>
            <span>O catalogo ainda não possui itens publicos disponiveis.</span>
        </article>
    <?php else: ?>
        <?php foreach ($cursos as $curso): ?>
            <article class="course-card">
                <?php if (!empty($curso['thumbnail'])): ?>
                    <div class="course-card__image">
                        <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome']); ?>">
                    </div>
                <?php endif; ?>
                <div class="course-card__media">
                    <strong><?php echo Helpers::e($curso['nome']); ?></strong>
                    <span><?php echo Helpers::e($curso['categoria_nome'] ?: 'Sem categoria'); ?></span>
                </div>
                <div class="course-card__body">
                    <div class="pill-row">
                        <span class="pill"><?php echo Helpers::e($curso['tipo']); ?></span>
                        <span class="pill"><?php echo Helpers::e($curso['modalidade']); ?></span>
                        <?php $professoresResponsaveisNomes = curso_professores_publico_texto($curso); ?>
                        <?php if (!empty($professoresResponsaveisNomes)): ?>
                            <span class="pill">
                                <?php echo Helpers::e(count($professoresResponsaveisNomes) > 1 ? 'Professores responsáveis: ' : 'Professor responsável: '); ?>
                                <?php echo Helpers::e(implode(', ', $professoresResponsaveisNomes)); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($curso['em_promocao'])): ?>
                            <span class="pill pill--alert">Promoção</span>
                        <?php endif; ?>
                    </div>
                    <p><?php echo Helpers::e($curso['descricao_curta'] ?: 'Descrição resumida em breve.'); ?></p>
                    <div class="course-card__meta">
                        <span><?php echo (int) $curso['total_turmas_abertas']; ?> turma(s) aberta(s)</span>
                        <?php if (!empty($curso['desconto_promocional'])): ?>
                            <?php $precoEfetivo = (float) $curso['valor_efetivo']; ?>
                            <div>
                                <?php if ($precoEfetivo > 0): ?>
                                    <span class="muted" style="font-size:12px;text-decoration:line-through;">R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                                <strong style="display:block;"><?php echo $precoEfetivo <= 0 ? 'Gratuito' : 'R$ ' . number_format($precoEfetivo, 2, ',', '.'); ?></strong>
                            </div>
                        <?php else: ?>
                            <strong><?php echo curso_preco_publico_texto($curso); ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="cta-group">
                        <a class="button-link button-link--ghost" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">Ver detalhes</a>
                        <?php if (!empty($curso['turmas_abertas'][0]['id'])): ?>
                            <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $curso['turmas_abertas'][0]['id']; ?>">Inscreva-se já</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

