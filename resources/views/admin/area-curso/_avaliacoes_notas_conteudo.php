<?php use App\Core\Helpers; ?>
<?php
$conteudoNotas = isset($conteudo_avaliacoes_notas) && is_array($conteudo_avaliacoes_notas) ? $conteudo_avaliacoes_notas : array();
$conteudoFiltros = isset($conteudo_avaliacoes_filtros) && is_array($conteudo_avaliacoes_filtros) ? $conteudo_avaliacoes_filtros : array();
$conteudoResumoAlunos = isset($conteudo_avaliacoes_resumo_alunos) && is_array($conteudo_avaliacoes_resumo_alunos) ? $conteudo_avaliacoes_resumo_alunos : array();
$tabAtiva = isset($selectedTab) && $selectedTab === 'avaliacoes-notas';
$baseAvaliacaoUrl = isset($areaCursoBaseUrl) && $areaCursoBaseUrl !== '' ? $areaCursoBaseUrl : '/admin/area-curso';
$statusOpcoes = array('' => 'Todos', 'enviada' => 'Enviada', 'reenviada' => 'Reenviada', 'devolvida' => 'Devolvida', 'corrigida' => 'Corrigida', 'aprovada' => 'Aprovada', 'reprovada' => 'Reprovada');
?>
<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $tabAtiva ? ' is-active' : ''; ?>" data-area-curso-tab="avaliacoes-notas" id="area-curso-avaliacoes-notas">
    <div class="panel-header">
        <div><h2>Avaliações / Notas</h2></div>
        <div class="admin-area-curso__actions">
            <a href="<?php echo Helpers::e('/admin/area-curso/conteudo/avaliacoes/exportar?' . http_build_query(array('curso_id' => (int) ($curso['id'] ?? 0), 'turma_id' => (int) ($turma['id'] ?? 0), 'curso_nome' => (string) ($curso['nome'] ?? ''), 'turma_nome' => (string) ($turma['nome'] ?? '')) + $conteudoFiltros)); ?>">Exportar CSV do conteúdo</a>
        </div>
    </div>
    <p class="muted">Seção adicional do Conteúdo Unificado. O fluxo legado de avaliações/notas permanece inalterado.</p>

    <form method="get" action="<?php echo Helpers::e($baseAvaliacaoUrl); ?>" class="form-grid admin-area-curso__form">
        <input type="hidden" name="curso_id" value="<?php echo (int) ($curso['id'] ?? 0); ?>">
        <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
        <input type="hidden" name="aba" value="avaliacoes-notas">
        <label>Aluno ID<input type="number" name="conteudo_aluno_id" min="0" value="<?php echo Helpers::e((string) ($conteudoFiltros['aluno_id'] ?? 0)); ?>"></label>
        <label>Status
            <select name="conteudo_status">
                <?php foreach ($statusOpcoes as $valor => $rotulo): ?>
                    <option value="<?php echo Helpers::e($valor); ?>" <?php echo (string) ($conteudoFiltros['status'] ?? '') === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Módulo ID<input type="number" name="conteudo_modulo_id_filtro" min="0" value="<?php echo Helpers::e((string) ($conteudoFiltros['modulo_id'] ?? 0)); ?>"></label>
        <label>Avaliação ID<input type="number" name="conteudo_avaliacao_id" min="0" value="<?php echo Helpers::e((string) ($conteudoFiltros['avaliacao_id'] ?? 0)); ?>"></label>
        <button type="submit">Aplicar filtros</button>
    </form>

    <div class="admin-area-curso__stats">
        <div><small>Registros</small><strong><?php echo count($conteudoNotas); ?></strong></div>
        <div><small>Alunos no resumo</small><strong><?php echo count($conteudoResumoAlunos); ?></strong></div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Aluno</th><th>Módulo</th><th>Avaliação</th><th>Status</th><th>Tentativa</th><th>Nota</th><th>Máx.</th><th>Mín.</th><th>Peso</th><th>Média aluno</th><th>Feedback</th><th>Enviado</th><th>Corrigido</th></tr></thead>
            <tbody>
            <?php if (empty($conteudoNotas)): ?>
                <tr><td colspan="13" class="muted">Nenhum registro encontrado.</td></tr>
            <?php else: foreach ($conteudoNotas as $item): ?>
                <?php $mediaAluno = null; foreach ($conteudoResumoAlunos as $resumoAluno) { if ((int) ($resumoAluno['inscricao_id'] ?? 0) === (int) ($item['inscricao_id'] ?? 0)) { $mediaAluno = $resumoAluno['media_ponderada']; break; } } ?>
                <tr>
                    <td><?php echo Helpers::e((string) ($item['aluno_nome'] ?? '')); ?></td>
                    <td><?php echo Helpers::e((string) ($item['modulo_titulo'] ?? '')); ?></td>
                    <td><?php echo Helpers::e((string) ($item['avaliacao_titulo'] ?? '')); ?></td>
                    <td><?php echo Helpers::e((string) ($item['status'] ?? '')); ?></td>
                    <td><?php echo (int) ($item['tentativa'] ?? 0); ?></td>
                    <td><?php echo $item['nota'] !== null ? Helpers::e(number_format((float) $item['nota'], 2, ',', '.')) : '—'; ?></td>
                    <td><?php echo $item['nota_maxima'] !== null ? Helpers::e(number_format((float) $item['nota_maxima'], 2, ',', '.')) : '—'; ?></td>
                    <td><?php echo $item['nota_minima'] !== null ? Helpers::e(number_format((float) $item['nota_minima'], 2, ',', '.')) : '—'; ?></td>
                    <td><?php echo Helpers::e(number_format((float) ($item['peso'] ?? 1), 2, ',', '.')); ?></td>
                    <td><?php echo $mediaAluno !== null ? Helpers::e(number_format((float) $mediaAluno, 2, ',', '.')) : '—'; ?></td>
                    <td><?php echo Helpers::e(substr(trim((string) ($item['feedback'] ?? '')), 0, 90)); ?></td>
                    <td><?php echo !empty($item['enviado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $item['enviado_em']))) : '—'; ?></td>
                    <td><?php echo !empty($item['corrigido_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $item['corrigido_em']))) : '—'; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
