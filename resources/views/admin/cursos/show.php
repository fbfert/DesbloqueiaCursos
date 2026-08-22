<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php $canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar'); ?>
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

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($curso['nome']); ?></h1>
        <p class="admin-page__subtitle">Detalhe administrativo do curso ou evento.</p>
    </div>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>Slug</dt><dd><?php echo Helpers::e($curso['slug']); ?></dd>
        <dt>Categoria</dt><dd><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></dd>
        <dt>Tipo</dt><dd><?php echo Helpers::e($curso['tipo']); ?></dd>
        <dt>Modalidade</dt><dd><?php echo Helpers::e(Helpers::modalidadeCurso($curso['modalidade'])); ?></dd>
        <dt>Professores responsáveis</dt><dd><?php echo Helpers::e(!empty($professoresResponsaveisNomes) ? implode(', ', $professoresResponsaveisNomes) : '-'); ?></dd>
        <dt>Valor</dt><dd>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></dd>
        <?php
        $temPromo = !empty($curso['em_promocao'])
            && isset($curso['valor_promocional'])
            && $curso['valor_promocional'] !== null
            && $curso['valor_promocional'] !== ''
            && (float) $curso['valor_promocional'] >= 0
            && (float) $curso['valor_promocional'] < (float) $curso['valor'];
        ?>
        <?php if ($temPromo): ?>
            <?php
            $valorOriginal = (float) $curso['valor'];
            $valorPromo = (float) $curso['valor_promocional'];
            $descontoValor = $valorOriginal - $valorPromo;
            $descontoPerc = $valorOriginal > 0 ? ($descontoValor / $valorOriginal) * 100 : 0;
            ?>
            <dt>Valor promocional</dt><dd>R$ <?php echo number_format($valorPromo, 2, ',', '.'); ?></dd>
            <dt>Desconto</dt><dd>R$ <?php echo number_format($descontoValor, 2, ',', '.'); ?> (<?php echo (int) round($descontoPerc); ?>%)</dd>
        <?php endif; ?>
        <dt>Status</dt><dd><?php echo Helpers::e($curso['status']); ?></dd>
        <dt>Turmas</dt><dd><?php echo (int) $curso['total_turmas']; ?></dd>
        <dt>Pessoas vinculadas</dt><dd><?php echo (int) $curso['total_pessoas_vinculadas']; ?></dd>
    </dl>
</section>
<section class="status-card" style="margin:16px 0;">
    <h2 style="margin:0 0 12px;">Descritivo do curso</h2>
    <?php if (!empty($curso['descricao_curta'])): ?>
        <h3 style="margin:0 0 6px;">Descrição curta</h3>
        <p><?php echo nl2br(Helpers::e($curso['descricao_curta'])); ?></p>
    <?php endif; ?>
        <?php if (!empty($curso['descricao_completa'])): ?>
            <h3 style="margin:12px 0 6px;">Descritivo do curso</h3>
            <p><?php echo nl2br(Helpers::e($curso['descricao_completa'])); ?></p>
        <?php endif; ?>

    <?php if (!empty($curso['objetivo_geral'])): ?>
        <h2 style="margin:16px 0 6px;">Objetivo geral</h2>
        <p><?php echo nl2br(Helpers::e($curso['objetivo_geral'])); ?></p>
    <?php endif; ?>

    <?php if (!empty($curso['objetivos_especificos'])): ?>
        <h2 style="margin:16px 0 6px;">Objetivos específicos</h2>
        <ul>
            <?php foreach (preg_split('/\\r\\n|\\r|\\n/', (string) $curso['objetivos_especificos']) as $linha): ?>
                <?php $linha = trim((string) $linha); ?>
                <?php if ($linha !== ''): ?><li><?php echo Helpers::e($linha); ?></li><?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($curso['publico_alvo'])): ?>
        <h2 style="margin:16px 0 6px;">Público-alvo</h2>
        <p><?php echo nl2br(Helpers::e($curso['publico_alvo'])); ?></p>
    <?php endif; ?>

    <?php if (!empty($curso['pre_requisitos_texto']) || !empty($curso['pre_requisitos_itens'])): ?>
        <h2 style="margin:16px 0 6px;">Pré-requisitos</h2>
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
        <h2 style="margin:16px 0 6px;">Ementa</h2>
        <p><?php echo nl2br(Helpers::e($curso['ementa'])); ?></p>
    <?php endif; ?>

    <?php
    $tipo = (string) ($curso['conteudo_programatico_tipo'] ?? 'texto');
    $texto = (string) ($curso['conteudo_programatico_texto'] ?? '');
    $modulosJson = (string) ($curso['conteudo_programatico_modulos'] ?? '');
    ?>
    <?php if (trim($texto) !== '' || trim($modulosJson) !== ''): ?>
        <h2 style="margin:16px 0 6px;">Conteúdo programático</h2>
        <p class="muted" style="margin:0 0 8px;">Formato: <?php echo Helpers::e($tipo ?: 'texto'); ?></p>
        <?php if ($tipo === 'modulos'): ?>
            <?php $modulos = json_decode($modulosJson, true); ?>
            <?php if (is_array($modulos)): ?>
                <?php foreach ($modulos as $modulo): ?>
                    <?php if (!is_array($modulo)) continue; ?>
                    <?php $titulo = trim((string) ($modulo['titulo'] ?? '')); ?>
                    <?php $itens = isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array(); ?>
                    <div class="status-card" style="margin:12px 0;padding:12px;">
                        <?php if ($titulo !== ''): ?><strong><?php echo Helpers::e($titulo); ?></strong><?php endif; ?>
                        <?php if (!empty($itens)): ?>
                            <ul style="margin-top:8px;">
                                <?php foreach ($itens as $item): ?>
                                    <?php $item = trim((string) $item); ?>
                                    <?php if ($item !== ''): ?><li><?php echo Helpers::e($item); ?></li><?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">JSON de módulos inválido. Ajuste no formulário de edição.</p>
            <?php endif; ?>
        <?php else: ?>
            <p><?php echo nl2br(Helpers::e($texto)); ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($curso['metodologia'])): ?>
        <h2 style="margin:16px 0 6px;">Metodologia</h2>
        <p><?php echo nl2br(Helpers::e($curso['metodologia'])); ?></p>
    <?php endif; ?>

    <?php if (!empty($curso['produto_final'])): ?>
        <h2 style="margin:16px 0 6px;">Produto final</h2>
        <ul>
            <?php foreach (preg_split('/\\r\\n|\\r|\\n/', (string) $curso['produto_final']) as $linha): ?>
                <?php $linha = trim((string) $linha); ?>
                <?php if ($linha !== ''): ?><li><?php echo Helpers::e($linha); ?></li><?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($curso['avaliacao'])): ?>
        <h2 style="margin:16px 0 6px;">Avaliação</h2>
        <p><?php echo nl2br(Helpers::e($curso['avaliacao'])); ?></p>
    <?php endif; ?>
</section>

<section class="status-card">
    <div class="split-actions">
        <?php if ($canManage): ?>
            <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
        <?php endif; ?>
        <a href="/admin/cursos">Voltar</a>
        <?php if ($canManage): ?>
            <form method="post" action="/admin/cursos/status" class="admin-form">
                <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
                <input type="hidden" name="status" value="<?php echo $curso['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>">
                <button type="submit"><?php echo $curso['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($canManage): ?>
        <form method="post" action="/admin/cursos/excluir" class="admin-form admin-mt-16">
            <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
            <label>
                Justificativa para lixeira
                <input type="text" name="justificativa" required>
            </label>
            <button type="submit" onclick="return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a exclusão deste curso ou evento?' });">Excluir curso/evento</button>
        </form>
    <?php endif; ?>
</section>
</div>
