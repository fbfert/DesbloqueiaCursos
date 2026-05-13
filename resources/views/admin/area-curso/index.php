<?php use App\Core\Helpers; ?>

<?php
$instrucaoEditar = isset($instrucao_selecionada) ? $instrucao_selecionada : null;
$moduloEditar = isset($modulo_selecionado) ? $modulo_selecionado : null;
$aulaEditar = isset($aula_selecionada) ? $aula_selecionada : null;
$materialEditar = isset($material_selecionado) ? $material_selecionado : null;
$linkEditar = isset($link_selecionado) ? $link_selecionado : null;
$selectedTab = isset($selected_tab) && $selected_tab !== '' ? $selected_tab : 'visao-geral';
$abasLms = isset($tabs) && !empty($tabs) ? $tabs : array(
    array('slug' => 'visao-geral', 'label' => 'Visão geral'),
    array('slug' => 'turmas', 'label' => 'Turmas'),
    array('slug' => 'modulos-aulas', 'label' => 'Módulos e aulas'),
    array('slug' => 'materiais', 'label' => 'Materiais'),
    array('slug' => 'atividades', 'label' => 'Atividades'),
    array('slug' => 'participantes', 'label' => 'Participantes'),
    array('slug' => 'presenca', 'label' => 'Presença'),
    array('slug' => 'avaliacoes-notas', 'label' => 'Avaliações / Notas'),
    array('slug' => 'certificados', 'label' => 'Certificados'),
    array('slug' => 'relatorios', 'label' => 'Relatórios'),
    array('slug' => 'configuracoes', 'label' => 'Configurações'),
    array('slug' => 'aptos-certificado', 'label' => 'Aptos para certificado'),
);
$quantidadeModulos = !empty($modulos) ? count($modulos) : 0;
$quantidadeAulas = 0;

if (!empty($modulos)) {
    foreach ($modulos as $modulo) {
        $quantidadeAulas += !empty($modulo['aulas']) ? count($modulo['aulas']) : 0;
    }
}

$quantidadeMateriais = !empty($materiais) ? count($materiais) : 0;
$quantidadeLinks = !empty($links) ? count($links) : 0;
$quantidadeAtividades = !empty($atividades) ? count($atividades) : 0;
$quantidadeParticipantes = !empty($participantes) ? count($participantes) : 0;
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array('modulos' => 0, 'aulas' => 0, 'atividades' => 0, 'participantes' => 0, 'certificados' => 0, 'turmas' => 0);
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Área interna do curso</h1>
            <p class="admin-page__subtitle">Administração de instruções, módulos, aulas, materiais, links e participantes.</p>
        </div>
    </section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card admin-area-curso__selector">
    <div class="panel-header">
        <h2>Cursos para edição</h2>
    </div>
    <?php
    $cursosAtivos = array();
    $cursosInativos = array();
    if (!empty($cursos)) {
        foreach ($cursos as $c) {
            if (!empty($c['status']) && $c['status'] === 'ativo') {
                $cursosAtivos[] = $c;
            } else {
                $cursosInativos[] = $c;
            }
        }
    }
    ?>
    <?php if (!empty($cursosAtivos)): ?>
        <div class="table-wrap">
            <table class="admin-table admin-table--area-cursos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Modalidade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursosAtivos as $item): ?>
                        <?php $isAtual = !empty($curso) && (int) $curso['id'] === (int) $item['id']; ?>
                        <tr class="<?php echo $isAtual ? 'is-active' : ''; ?>">
                            <td><span class="muted">#<?php echo (int) $item['id']; ?></span></td>
                            <td><a href="/admin/area-curso?curso_id=<?php echo (int) $item['id']; ?>"><?php echo Helpers::e($item['nome']); ?><?php if ($isAtual): ?><strong> · Em edição</strong><?php endif; ?></a></td>
                            <td><span class="badge"><?php echo Helpers::e($item['tipo']); ?></span></td>
                            <td><span class="badge"><?php echo Helpers::e($item['modalidade']); ?></span></td>
                            <td><span class="badge badge--success">Ativo</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">Nenhum curso ativo disponível para edição no momento.</p>
    <?php endif; ?>
    <?php if (!empty($cursosInativos)): ?>
        <details class="admin-area-curso__inativos-details admin-mt-12">
            <summary class="panel-header">
                <div>
                    <h2>Cursos inativos</h2>
                    <span class="badge"><?php echo count($cursosInativos); ?> itens</span>
                </div>
            </summary>
            <div class="table-wrap admin-mt-8">
                <table class="admin-table admin-table--area-cursos admin-table--muted">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Modalidade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursosInativos as $item): ?>
                            <?php $isAtual = !empty($curso) && (int) $curso['id'] === (int) $item['id']; ?>
                            <tr class="<?php echo $isAtual ? 'is-active' : ''; ?>">
                                <td><span class="muted">#<?php echo (int) $item['id']; ?></span></td>
                                <td><a href="/admin/area-curso?curso_id=<?php echo (int) $item['id']; ?>"><?php echo Helpers::e($item['nome']); ?><?php if ($isAtual): ?><strong> · Em edição</strong><?php endif; ?></a></td>
                                <td><span class="badge"><?php echo Helpers::e($item['tipo']); ?></span></td>
                                <td><span class="badge"><?php echo Helpers::e($item['modalidade']); ?></span></td>
                                <td><span class="badge badge--warn"><?php echo Helpers::e(ucfirst(str_replace('_', ' ', $item['status'] ?? 'rascunho'))); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>
    <p class="muted admin-mt-8">A turma é refinada dentro do próprio curso, quando necessário.</p>
</section>

<?php if (!empty($curso)): ?>
    <section class="status-card admin-area-curso__tabs">
        <div class="admin-area-curso__tablinks">
            <?php foreach ($abasLms as $aba): ?>
                <a class="admin-area-curso__tablink<?php echo $selectedTab === $aba['slug'] ? ' is-active' : ''; ?>" href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=<?php echo urlencode($aba['slug']); ?>#<?php echo Helpers::e('area-curso-' . $aba['slug']); ?>">
                    <?php echo Helpers::e($aba['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="admin-area-curso__layout">
        <div class="admin-area-curso__main">
            <section class="status-card admin-area-curso__section admin-area-curso__overview" id="area-curso-visao-geral">
                <div class="panel-header">
                    <div>
                        <h2>Visão geral</h2>
                        <p class="muted">Resumo rápido do contexto pedagógico deste curso.</p>
                    </div>
                    <div class="split-actions">
                        <a href="#area-curso-modulos-aulas">Módulos e aulas</a>
                        <a href="#area-curso-legado">Blocos legados</a>
                    </div>
                </div>

                <div class="admin-area-curso__stats">
                    <div>
                        <small>Curso</small>
                        <strong><?php echo Helpers::e($curso['nome'] ?? ''); ?></strong>
                    </div>
                    <div>
                        <small>Turma</small>
                        <strong><?php echo !empty($turma['nome']) ? Helpers::e($turma['nome']) : 'Curso inteiro'; ?></strong>
                    </div>
                    <div>
                        <small>Módulos</small>
                        <strong><?php echo (int) $resumo['modulos']; ?></strong>
                    </div>
                    <div>
                        <small>Aulas</small>
                        <strong><?php echo (int) $resumo['aulas']; ?></strong>
                    </div>
                    <div>
                        <small>Atividades</small>
                        <strong><?php echo (int) $resumo['atividades']; ?></strong>
                    </div>
                    <div>
                        <small>Participantes</small>
                        <strong><?php echo (int) $resumo['participantes']; ?></strong>
                    </div>
                    <div>
                        <small>Certificados</small>
                        <strong><?php echo (int) $resumo['certificados']; ?></strong>
                    </div>
                </div>
            </section>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder" id="area-curso-turmas">
                <div class="panel-header">
                    <div>
                        <h2>Turmas</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <?php require BASE_PATH . '/resources/views/admin/area-curso/_materiais.php'; ?>

            <?php $areaCursoBaseUrl = '/admin/area-curso'; require BASE_PATH . '/resources/views/admin/area-curso/_atividades.php'; ?>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder" id="area-curso-participantes">
                <div class="panel-header">
                    <div>
                        <h2>Participantes</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder" id="area-curso-presenca">
                <div class="panel-header">
                    <div>
                        <h2>Presença</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder" id="area-curso-avaliacoes-notas">
                <div class="panel-header">
                    <div>
                        <h2>Avaliações / Notas</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <section class="status-card admin-area-curso__section admin-area-curso__placeholder" id="area-curso-certificados">
                <div class="panel-header">
                    <div>
                        <h2>Certificados</h2>
                    </div>
                </div>
                <p class="muted">Esta área será implementada em etapa posterior.</p>
            </section>

            <?php require BASE_PATH . '/resources/views/admin/area-curso/_relatorios.php'; ?>

            <section class="status-card admin-area-curso__section" id="area-curso-configuracoes">
                <div class="panel-header">
                    <div>
                        <h2>Critérios de conclusão</h2>
                        <p class="muted">Esta configuração calcula a elegibilidade e não emite certificado automaticamente.</p>
                    </div>
                </div>
                <?php $criterios = isset($criterios_conclusao) && is_array($criterios_conclusao) ? $criterios_conclusao : array(); ?>
                <form method="post" action="/admin/area-curso/criterios-conclusao" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="curso_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="aba" value="configuracoes">

                    <label class="checkbox"><input type="checkbox" name="exigir_progresso" value="1" <?php echo !empty($criterios['exigir_progresso']) ? 'checked' : ''; ?>> Exigir conclusão de aulas</label>
                    <label>Percentual mínimo de progresso
                        <input type="number" name="progresso_minimo" min="0" max="100" step="0.01" value="<?php echo Helpers::e(number_format((float) ($criterios['progresso_minimo'] ?? 75), 2, '.', '')); ?>">
                    </label>

                    <label class="checkbox"><input type="checkbox" name="exigir_atividades" value="1" <?php echo !empty($criterios['exigir_atividades']) ? 'checked' : ''; ?>> Exigir atividades</label>
                    <label>Critério de atividades
                        <select name="criterio_atividades">
                            <?php $criterioAtividades = isset($criterios['criterio_atividades']) ? (string) $criterios['criterio_atividades'] : 'nenhuma'; ?>
                            <option value="nenhuma" <?php echo $criterioAtividades === 'nenhuma' ? 'selected' : ''; ?>>Nenhuma exigência</option>
                            <option value="todas_enviadas" <?php echo $criterioAtividades === 'todas_enviadas' ? 'selected' : ''; ?>>Todas enviadas</option>
                            <option value="todas_corrigidas" <?php echo $criterioAtividades === 'todas_corrigidas' ? 'selected' : ''; ?>>Todas corrigidas</option>
                            <option value="media_minima" <?php echo $criterioAtividades === 'media_minima' ? 'selected' : ''; ?>>Média mínima</option>
                        </select>
                    </label>
                    <label>Nota mínima nas atividades
                        <input type="number" name="nota_minima_atividades" min="0" step="0.01" value="<?php echo Helpers::e(isset($criterios['nota_minima_atividades']) && $criterios['nota_minima_atividades'] !== null ? number_format((float) $criterios['nota_minima_atividades'], 2, '.', '') : ''); ?>">
                    </label>

                    <label class="checkbox"><input type="checkbox" name="exigir_presenca" value="1" <?php echo !empty($criterios['exigir_presenca']) ? 'checked' : ''; ?>> Exigir presença</label>
                    <label>Percentual mínimo de presença
                        <input type="number" name="presenca_minima" min="0" max="100" step="0.01" value="<?php echo Helpers::e(number_format((float) ($criterios['presenca_minima'] ?? 75), 2, '.', '')); ?>">
                    </label>

                    <label class="checkbox"><input type="checkbox" name="exigir_avaliacao" value="1" <?php echo !empty($criterios['exigir_avaliacao']) ? 'checked' : ''; ?>> Exigir avaliação</label>
                    <label>Nota mínima de avaliação
                        <input type="number" name="nota_minima_avaliacao" min="0" step="0.01" value="<?php echo Helpers::e(isset($criterios['nota_minima_avaliacao']) && $criterios['nota_minima_avaliacao'] !== null ? number_format((float) $criterios['nota_minima_avaliacao'], 2, '.', '') : ''); ?>">
                    </label>

                    <p class="muted full"><?php echo Helpers::e($criterios['aviso_certificado_manual'] ?? 'A conclusão exibida aqui não emite certificado automaticamente.'); ?></p>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=configuracoes';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </section>

            <section class="status-card admin-area-curso__section" id="area-curso-modulos-aulas">
                <div class="panel-header">
                    <div>
                        <h2>Módulos e aulas</h2>
                        <p class="muted">Administração da estrutura pedagógica principal do curso.</p>
                    </div>
                </div>

                <div class="admin-area-curso__actions">
                    <a href="#area-curso-legado-modulos">Novo módulo</a>
                    <a href="#area-curso-legado-aulas">Nova aula</a>
                    <a href="#area-curso-materiais">Materiais</a>
                    <a href="#area-curso-legado">Ver blocos legados</a>
                </div>

            </section>

            <section class="status-card admin-area-curso__section admin-area-curso__legacy" id="area-curso-legado">
                <details class="admin-area-curso__legacy-details">
                    <summary class="panel-header">
                        <div>
                            <h2>Blocos legados reaproveitados</h2>
                            <p class="muted">Os formulários abaixo foram mantidos para compatibilidade com o fluxo anterior.</p>
                        </div>
                    </summary>

                    <div class="admin-actions admin-area-curso__anchors admin-area-curso__anchors--secondary">
                        <a href="#area-curso-legado-instrucoes">Próxima ação</a>
                        <a href="#area-curso-legado-modulos">Módulos</a>
                        <a href="#area-curso-legado-aulas">Aulas</a>
                        <a href="#area-curso-materiais">Materiais</a>
                        <a href="#area-curso-legado-links">Links externos</a>
                        <a href="#area-curso-legado-participantes">Participantes</a>
                    </div>

            <section class="status-card admin-area-curso__section" id="area-curso-legado-instrucoes">
                <div class="panel-header">
                    <div>
                        <h2>Próxima ação do checkout</h2>
                        <p class="muted">Este conteúdo pode ser exibido na página de sucesso do pedido.</p>
                    </div>
                </div>
                <form method="post" action="/admin/area-curso/instrucoes" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($instrucaoEditar['id']) ? (int) $instrucaoEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="visao-geral">
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($instrucaoEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">Conteúdo<textarea name="conteudo" rows="4"><?php echo Helpers::e($instrucaoEditar['conteudo'] ?? ''); ?></textarea></label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($instrucaoEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($instrucaoEditar) ? (!empty($instrucaoEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=visao-geral';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>
            </section>

            <section class="status-card admin-area-curso__section" id="area-curso-legado-modulos">
                <div class="panel-header">
                    <h2>Módulos</h2>
                    <span class="badge"><?php echo (int) $quantidadeModulos; ?> itens</span>
                </div>
                <form method="post" action="/admin/area-curso/modulos" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($moduloEditar['id']) ? (int) $moduloEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($moduloEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">Descrição<textarea name="descricao" rows="3"><?php echo Helpers::e($moduloEditar['descricao'] ?? ''); ?></textarea></label>
                    <label>Status
                        <select name="status">
                            <?php $statusModulo = 'publicado'; ?>
                            <?php if (!empty($moduloEditar)): ?>
                                <?php $statusModulo = !empty($moduloEditar['status']) ? $moduloEditar['status'] : (!empty($moduloEditar['visivel']) ? 'publicado' : 'oculto'); ?>
                            <?php endif; ?>
                            <option value="rascunho" <?php echo $statusModulo === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="publicado" <?php echo $statusModulo === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="oculto" <?php echo $statusModulo === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                        </select>
                    </label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($moduloEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=modulos-aulas';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>

                <div class="table-wrap admin-mt-12">
                    <table class="admin-table admin-table--area-modulos">
                        <thead><tr><th>Título</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($modulos as $modulo): ?>
                                <tr>
                                    <td><?php echo Helpers::e($modulo['titulo']); ?></td>
                                    <td><?php echo (int) $modulo['ordem']; ?></td>
                                    <td><span class="badge"><?php echo Helpers::e($modulo['status'] ?? (!empty($modulo['visivel']) ? 'publicado' : 'oculto')); ?></span></td>
                                    <td>
                                        <div class="split-actions">
                                            <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&modulo_id=<?php echo (int) $modulo['id']; ?>&aba=modulos-aulas">Editar</a>
                                        </div>
                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form admin-mt-8">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="tipo" value="modulo">
                                            <input type="hidden" name="id" value="<?php echo (int) $modulo['id']; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                            <input type="hidden" name="aba" value="modulos-aulas">
                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                            <button type="submit">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section" id="area-curso-legado-aulas">
                <div class="panel-header">
                    <h2>Aulas</h2>
                    <span class="badge"><?php echo (int) $quantidadeAulas; ?> itens</span>
                </div>
                <form method="post" action="/admin/area-curso/aulas" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($aulaEditar['id']) ? (int) $aulaEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Módulo
                        <select name="modulo_id">
                            <?php foreach ($modulos as $modulo): ?>
                                <option value="<?php echo (int) $modulo['id']; ?>" <?php echo !empty($aulaEditar) && (int) ($aulaEditar['modulo_id'] ?? 0) === (int) $modulo['id'] ? 'selected' : ''; ?>>
                                    <?php echo Helpers::e($modulo['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($aulaEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">Conteúdo<textarea name="conteudo" rows="3"><?php echo Helpers::e($aulaEditar['conteudo'] ?? ''); ?></textarea></label>
                    <label>Status
                        <?php $statusAula = 'publicado'; ?>
                        <?php if (!empty($aulaEditar)): ?>
                            <?php $statusAula = !empty($aulaEditar['status']) ? $aulaEditar['status'] : (!empty($aulaEditar['visivel']) ? 'publicado' : 'oculto'); ?>
                        <?php endif; ?>
                        <select name="status">
                            <option value="rascunho" <?php echo $statusAula === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="publicado" <?php echo $statusAula === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="oculto" <?php echo $statusAula === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                        </select>
                    </label>
                    <label>Tipo<input type="text" name="tipo" value="<?php echo Helpers::e($aulaEditar['tipo'] ?? 'texto'); ?>"></label>
                    <label>URL vídeo<input type="text" name="url_video" value="<?php echo Helpers::e($aulaEditar['url_video'] ?? ''); ?>"></label>
                    <label>Duração (minutos)<input type="number" name="duracao_minutos" value="<?php echo Helpers::e((string) ($aulaEditar['duracao_minutos'] ?? '')); ?>"></label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($aulaEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <label class="checkbox"><input type="checkbox" name="obrigatoria" value="1" <?php echo !empty($aulaEditar) && !empty($aulaEditar['obrigatoria']) ? 'checked' : ''; ?>> Obrigatória</label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=modulos-aulas';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>

                <div class="table-wrap admin-mt-12">
                    <table class="admin-table admin-table--area-aulas">
                        <thead><tr><th>Título</th><th>Tipo</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($modulos as $modulo): ?>
                                <?php foreach ($modulo['aulas'] as $aula): ?>
                                <tr>
                                    <td><?php echo Helpers::e($aula['titulo']); ?></td>
                                    <td><?php echo Helpers::e($aula['tipo']); ?></td>
                                    <td><?php echo (int) $aula['ordem']; ?></td>
                                    <td><span class="badge"><?php echo Helpers::e($aula['status'] ?? (!empty($aula['visivel']) ? 'publicado' : 'oculto')); ?></span></td>
                                    <td>
                                        <div class="split-actions">
                                            <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aula_id=<?php echo (int) $aula['id']; ?>&aba=modulos-aulas">Editar</a>
                                        </div>
                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form admin-mt-8">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="tipo" value="aula">
                                            <input type="hidden" name="id" value="<?php echo (int) $aula['id']; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                            <input type="hidden" name="aba" value="modulos-aulas">
                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                            <button type="submit">Remover</button>
                                        </form>
                                        <div class="admin-area-curso__aula-materiais">
                                            <div class="admin-area-curso__aula-materiais-head">
                                                <strong>Materiais</strong>
                                                <span class="badge"><?php echo (int) ($aula['total_materiais'] ?? 0); ?></span>
                                            </div>
                                            <div class="admin-area-curso__aula-materiais-actions">
                                                <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&modulo_id=<?php echo (int) $modulo['id']; ?>&aula_id=<?php echo (int) $aula['id']; ?>&aba=materiais">Adicionar material</a>
                                            </div>
                                            <?php if (!empty($aula['materiais'])): ?>
                                                <div class="admin-area-curso__aula-materiais-list">
                                                    <?php foreach (array_slice($aula['materiais'], 0, 3) as $materialAula): ?>
                                                        <?php $materialStatusLinha = !empty($materialAula['status']) ? $materialAula['status'] : (!empty($materialAula['visivel']) ? 'publicado' : 'oculto'); ?>
                                                        <span class="badge badge--soft"><?php echo Helpers::e($materialAula['titulo']); ?> · <?php echo Helpers::e($materialStatusLinha); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section" id="area-curso-legado-links">
                <div class="panel-header">
                    <h2>Links externos</h2>
                    <span class="badge"><?php echo (int) $quantidadeLinks; ?> itens</span>
                </div>
                <form method="post" action="/admin/area-curso/links" class="form-grid admin-area-curso__form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="id" value="<?php echo !empty($linkEditar['id']) ? (int) $linkEditar['id'] : 0; ?>">
                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                    <input type="hidden" name="aba" value="modulos-aulas">
                    <label class="full">Módulo
                        <select name="modulo_id">
                            <option value="">Sem módulo</option>
                            <?php foreach ($modulos as $modulo): ?>
                                <option value="<?php echo (int) $modulo['id']; ?>" <?php echo !empty($linkEditar) && (int) ($linkEditar['modulo_id'] ?? 0) === (int) $modulo['id'] ? 'selected' : ''; ?>>
                                    <?php echo Helpers::e($modulo['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Aula
                        <select name="aula_id">
                            <option value="">Sem aula</option>
                            <?php foreach ($modulos as $modulo): ?>
                                <?php foreach ($modulo['aulas'] as $aula): ?>
                                    <option value="<?php echo (int) $aula['id']; ?>" <?php echo !empty($linkEditar) && (int) ($linkEditar['aula_id'] ?? 0) === (int) $aula['id'] ? 'selected' : ''; ?>>
                                        <?php echo Helpers::e($modulo['titulo'] . ' - ' . $aula['titulo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($linkEditar['titulo'] ?? ''); ?>"></label>
                    <label class="full">URL<input type="text" name="url" value="<?php echo Helpers::e($linkEditar['url'] ?? ''); ?>"></label>
                    <label>Tipo de link<input type="text" name="tipo_link" value="<?php echo Helpers::e($linkEditar['tipo_link'] ?? 'generico'); ?>"></label>
                    <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($linkEditar['ordem'] ?? 1)); ?>" min="1"></label>
                    <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($linkEditar) ? (!empty($linkEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
                    <?php
                    $cancel_url = '/admin/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=modulos-aulas';
                    $show_save_as_copy = false;
                    require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                    ?>
                </form>

                <div class="table-wrap admin-mt-12">
                    <table class="admin-table admin-table--area-links">
                        <thead><tr><th>Título</th><th>Tipo</th><th>URL</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($links as $link): ?>
                                <tr>
                                    <td><?php echo Helpers::e($link['titulo']); ?></td>
                                    <td><?php echo Helpers::e($link['tipo_link']); ?></td>
                                    <td><a href="<?php echo Helpers::e($link['url']); ?>" target="_blank" rel="noopener">Abrir</a></td>
                                    <td>
                                        <div class="split-actions">
                                            <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?>&link_id=<?php echo (int) $link['id']; ?>">Editar</a>
                                        </div>
                                        <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-area-curso__delete-form admin-mt-8">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="tipo" value="link">
                                            <input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="text" name="justificativa" placeholder="Justificativa" required>
                                            <button type="submit">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="status-card admin-area-curso__section" id="area-curso-legado-participantes">
                <div class="panel-header">
                    <h2>Participantes</h2>
                    <span class="badge"><?php echo (int) $quantidadeParticipantes; ?> itens</span>
                </div>
                <div class="table-wrap">
                    <table class="admin-table admin-table--area-participantes">
                        <thead><tr><th>Nome</th><th>CPF</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($participantes as $participante): ?>
                                <tr>
                                    <td><?php echo Helpers::e($participante['nome']); ?></td>
                                    <td><?php echo Helpers::e($participante['cpf']); ?></td>
                                    <td><?php echo Helpers::e($participante['inscricao_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
                </details>
            </section>
        </div>

        <aside class="admin-area-curso__aside">
            <section class="status-card admin-area-curso__summary">
                <div class="panel-header">
                    <h2>Resumo</h2>
                </div>
                <dl class="detail-list">
                    <div>
                        <dt>Curso</dt>
                        <dd><?php echo Helpers::e($curso['nome'] ?? ''); ?></dd>
                    </div>
                    <div>
                        <dt>ID</dt>
                        <dd><?php echo (int) $curso['id']; ?></dd>
                    </div>
                    <div>
                        <dt>Módulos</dt>
                        <dd><?php echo (int) $quantidadeModulos; ?></dd>
                    </div>
                    <div>
                        <dt>Aulas</dt>
                        <dd><?php echo (int) $quantidadeAulas; ?></dd>
                    </div>
                    <div>
                        <dt>Materiais</dt>
                        <dd><?php echo (int) $quantidadeMateriais; ?></dd>
                    </div>
                    <div>
                        <dt>Links</dt>
                        <dd><?php echo (int) $quantidadeLinks; ?></dd>
                    </div>
                    <div>
                        <dt>Participantes</dt>
                        <dd><?php echo (int) $quantidadeParticipantes; ?></dd>
                    </div>
                </dl>
            </section>

    <section class="status-card admin-area-curso__nav">
        <div class="panel-header">
            <h2>Navegação</h2>
        </div>
        <div class="admin-actions admin-area-curso__anchors">
                    <a href="#area-curso-visao-geral">Visão geral</a>
                    <a href="#area-curso-modulos-aulas">Módulos e aulas</a>
                    <a href="#area-curso-turmas">Turmas</a>
                    <a href="#area-curso-materiais">Materiais</a>
                    <a href="#area-curso-atividades">Atividades</a>
                    <a href="#area-curso-participantes">Participantes</a>
                    <a href="#area-curso-presenca">Presença</a>
                    <a href="#area-curso-avaliacoes-notas">Avaliações / Notas</a>
                    <a href="#area-curso-certificados">Certificados</a>
                    <a href="#area-curso-relatorios">Relatórios</a>
                    <a href="#area-curso-aptos-certificado">Aptos para certificado</a>
                    <a href="#area-curso-configuracoes">Configurações</a>
            <a href="#area-curso-legado">Blocos legados</a>
        </div>
    </section>

    <section class="status-card admin-area-curso__summary">
        <div class="panel-header">
            <h2>Refinar turma</h2>
        </div>
        <form method="get" action="/admin/area-curso" class="form-grid admin-area-curso__selector-form">
            <input type="hidden" name="curso_id" value="<?php echo (int) $curso['id']; ?>">
            <label>
                Turma
                <select name="turma_id">
                    <option value="">Curso inteiro</option>
                    <?php foreach ($turmas as $item): ?>
                        <option value="<?php echo (int) $item['id']; ?>" <?php echo !empty($turma) && (int) $turma['id'] === (int) $item['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($item['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="button-link button-link--primary">Aplicar turma</button>
        </form>
        <p class="muted">Use este refinamento apenas quando a operação depender de uma turma específica.</p>
    </section>
</aside>
</div>
<?php endif; ?>
</div>

