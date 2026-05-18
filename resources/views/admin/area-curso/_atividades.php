<?php use App\Core\Helpers; ?>

<?php
$truncateText = static function ($text, $limit = 120, $ellipsis = '...') {
    $text = trim(strip_tags((string) $text));

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, (int) $limit, $ellipsis);
    }

    return strlen($text) > (int) $limit ? substr($text, 0, (int) $limit) . $ellipsis : $text;
};

$areaCursoBaseUrl = isset($areaCursoBaseUrl) && $areaCursoBaseUrl !== '' ? $areaCursoBaseUrl : '/admin/area-curso';
$atividadeEditar = isset($atividade_selecionada) ? $atividade_selecionada : null;
$atividadesOriginais = isset($atividades) && is_array($atividades) ? $atividades : array();
$atividadeModuloFiltro = isset($atividade_modulo_id) ? (int) $atividade_modulo_id : 0;
$atividadeAulaFiltro = isset($atividade_aula_id) ? (int) $atividade_aula_id : 0;
$atividadeStatusFiltro = isset($atividade_status) ? (string) $atividade_status : '';
$atividadesFiltradas = array();

foreach ($atividadesOriginais as $atividadeItem) {
    if ($atividadeModuloFiltro > 0 && (int) $atividadeItem['modulo_id'] !== $atividadeModuloFiltro) {
        continue;
    }
    if ($atividadeAulaFiltro > 0 && (int) $atividadeItem['aula_id'] !== $atividadeAulaFiltro) {
        continue;
    }
    if ($atividadeStatusFiltro !== '' && (string) $atividadeItem['status'] !== $atividadeStatusFiltro) {
        continue;
    }
    $atividadesFiltradas[] = $atividadeItem;
}

$atividadesCount = count($atividadesFiltradas);
$atividadeQueryBase = array(
    'curso_id' => !empty($curso['id']) ? (int) $curso['id'] : 0,
    'turma_id' => !empty($turma['id']) ? (int) $turma['id'] : 0,
    'aba' => 'atividades',
);

$modulosLista = isset($modulos) && is_array($modulos) ? $modulos : array();
$aulasPorModulo = array();
$temAulasDisponiveis = false;
foreach ($modulosLista as $moduloItem) {
    $aulasPorModulo[(int) $moduloItem['id']] = !empty($moduloItem['aulas']) ? $moduloItem['aulas'] : array();
    if (!empty($aulasPorModulo[(int) $moduloItem['id']])) {
        $temAulasDisponiveis = true;
    }
}

$statusOptions = array(
    'rascunho' => 'Rascunho',
    'publicado' => 'Publicado',
    'oculto' => 'Oculto',
);

$tipoEntregaOptions = array(
    'texto' => 'Texto',
    'arquivo' => 'Arquivo',
    'texto_ou_arquivo' => 'Texto ou arquivo',
);

$entregaStatusOptions = array(
    '' => 'Todas',
    'nao_enviada' => 'Não enviada',
    'enviada' => 'Enviada',
    'reenviada' => 'Reenviada',
    'devolvida' => 'Devolvida',
    'corrigida' => 'Corrigida',
    'atrasada' => 'Atrasada',
    'pendente' => 'Pendente de correção',
);

$entregaSelecionada = isset($entrega_selecionada) && is_array($entrega_selecionada) ? $entrega_selecionada : null;
$entregaSelecionadaDados = !empty($entregaSelecionada['entrega']) && is_array($entregaSelecionada['entrega']) ? $entregaSelecionada['entrega'] : null;
$entregaHistorico = !empty($entregaSelecionada['historico']) && is_array($entregaSelecionada['historico']) ? $entregaSelecionada['historico'] : array();
$entregaStatusFiltro = isset($entrega_status) ? (string) $entrega_status : '';

$entregasAtividade = isset($entregas_atividade) && is_array($entregas_atividade) ? $entregas_atividade : array();
?>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'atividades' ? ' is-active' : ''; ?>" data-area-curso-tab="atividades" id="area-curso-atividades">
    <div class="panel-header">
        <div>
            <?php echo areaCursoHeadingWithTooltip('Atividades', 'Cadastro, publicação, correção manual e entregas dos alunos.'); ?>
        </div>
        <span class="badge"><?php echo (int) $atividadesCount; ?> atividades</span>
    </div>

    <div class="admin-area-curso__actions">
        <a href="#area-curso-atividades-formulario">Nova atividade</a>
        <?php if (!empty($atividadeEditar)): ?>
            <a href="#area-curso-atividades-entregas">Ver entregas</a>
        <?php endif; ?>
    </div>

    <form method="get" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>" class="form-grid admin-area-curso__form admin-area-curso__activities-filter">
        <input type="hidden" name="curso_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
        <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
        <input type="hidden" name="aba" value="atividades">
        <label>
            Módulo
            <select name="atividade_modulo_id">
                <option value="">Todos</option>
                <?php foreach ($modulosLista as $moduloItem): ?>
                    <option value="<?php echo (int) $moduloItem['id']; ?>" <?php echo $atividadeModuloFiltro === (int) $moduloItem['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($moduloItem['titulo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Aula
            <select name="atividade_aula_id">
                <option value="">Todas</option>
                <?php foreach ($modulosLista as $moduloItem): ?>
                    <?php foreach ($aulasPorModulo[(int) $moduloItem['id']] as $aulaItem): ?>
                        <option value="<?php echo (int) $aulaItem['id']; ?>" <?php echo $atividadeAulaFiltro === (int) $aulaItem['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($moduloItem['titulo'] . ' · ' . $aulaItem['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Status
            <select name="atividade_status">
                <option value="">Todos</option>
                <?php foreach ($statusOptions as $valor => $rotulo): ?>
                    <option value="<?php echo Helpers::e($valor); ?>" <?php echo $atividadeStatusFiltro === $valor ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($rotulo); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Entrega
            <select name="entrega_status">
                <?php foreach ($entregaStatusOptions as $valor => $rotulo): ?>
                    <option value="<?php echo Helpers::e($valor); ?>" <?php echo $entregaStatusFiltro === $valor ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($rotulo); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="cta-group">
            <button type="submit" class="button-link button-link--primary">Filtrar</button>
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($areaCursoBaseUrl); ?>?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=atividades">Limpar filtros</a>
        </div>
    </form>

    <div class="admin-area-curso__aula-materiais">
        <div class="admin-area-curso__aula-materiais-head">
            <strong id="area-curso-atividades-formulario"><?php echo !empty($atividadeEditar) ? 'Editar atividade' : 'Nova atividade'; ?></strong>
        </div>

        <?php if (!$temAulasDisponiveis): ?>
            <p class="muted">Cadastre uma aula antes de adicionar atividades.</p>
        <?php else: ?>
            <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>/atividades" class="form-grid admin-area-curso__form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="id" value="<?php echo !empty($atividadeEditar['id']) ? (int) $atividadeEditar['id'] : 0; ?>">
                <input type="hidden" name="curso_evento_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
                <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                <label class="full">
                    Módulo
                    <select name="modulo_id">
                        <option value="">Selecione</option>
                        <?php foreach ($modulosLista as $moduloItem): ?>
                            <option value="<?php echo (int) $moduloItem['id']; ?>" <?php echo !empty($atividadeEditar) && (int) $atividadeEditar['modulo_id'] === (int) $moduloItem['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($moduloItem['titulo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="full">
                    Aula
                    <select name="aula_id">
                        <option value="">Selecione</option>
                        <?php foreach ($modulosLista as $moduloItem): ?>
                            <optgroup label="<?php echo Helpers::e($moduloItem['titulo']); ?>">
                                <?php foreach ($aulasPorModulo[(int) $moduloItem['id']] as $aulaItem): ?>
                                    <option value="<?php echo (int) $aulaItem['id']; ?>" <?php echo !empty($atividadeEditar) && (int) $atividadeEditar['aula_id'] === (int) $aulaItem['id'] ? 'selected' : ''; ?>>
                                        <?php echo Helpers::e($aulaItem['titulo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="full">Título<input type="text" name="titulo" value="<?php echo Helpers::e($atividadeEditar['titulo'] ?? ''); ?>"></label>
                <label class="full">Descrição<textarea name="descricao" class="js-wysiwyg" data-wysiwyg="full" rows="4"><?php echo Helpers::e($atividadeEditar['descricao'] ?? ''); ?></textarea></label>
                <label>
                    Tipo de entrega
                    <select name="tipo_entrega">
                        <?php foreach ($tipoEntregaOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo !empty($atividadeEditar) && (string) ($atividadeEditar['tipo_entrega'] ?? '') === $valor ? 'selected' : (!empty($atividadeEditar) ? '' : ($valor === 'texto' ? 'selected' : '')); ?>>
                                <?php echo Helpers::e($rotulo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Prazo
                    <input type="datetime-local" name="prazo" value="<?php echo !empty($atividadeEditar['prazo']) ? Helpers::e(date('Y-m-d\TH:i', strtotime($atividadeEditar['prazo']))) : ''; ?>">
                </label>
                <label>
                    Nota máxima
                    <input type="number" name="nota_maxima" step="0.01" min="0" value="<?php echo Helpers::e((string) ($atividadeEditar['nota_maxima'] ?? '10.00')); ?>">
                </label>
                <label>
                    Ordem
                    <input type="number" name="ordem" min="1" value="<?php echo Helpers::e((string) ($atividadeEditar['ordem'] ?? '1')); ?>">
                </label>
                <label>
                    Status
                    <select name="status">
                        <?php foreach ($statusOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo !empty($atividadeEditar) && (string) ($atividadeEditar['status'] ?? '') === $valor ? 'selected' : (!empty($atividadeEditar) ? '' : ($valor === 'publicado' ? 'selected' : '')); ?>>
                                <?php echo Helpers::e($rotulo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php
                $cancel_url = $areaCursoBaseUrl . '?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : '') . '&aba=atividades';
                $show_save_as_copy = false;
                require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                ?>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'atividades' ? ' is-active' : ''; ?>" data-area-curso-tab="atividades" id="area-curso-atividades-lista">
    <div class="panel-header">
        <div>
            <?php echo areaCursoHeadingWithTooltip('Lista de atividades', 'Exibe a estrutura cadastrada com contagem de entregas.'); ?>
        </div>
    </div>

    <?php if (empty($atividadesFiltradas)): ?>
        <p class="muted">Nenhuma atividade encontrada para os filtros aplicados.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Módulo</th>
                        <th>Aula</th>
                        <th>Entrega</th>
                        <th>Prazo</th>
                        <th>Status</th>
                        <th>Entregas</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($atividadesFiltradas as $atividadeItem): ?>
                        <?php
                        $atividadeIdAtual = (int) $atividadeItem['id'];
                        $atividadeStatusAtual = (string) $atividadeItem['status'];
                        $atividadeAulaAtual = !empty($atividadeItem['aula_titulo']) ? $atividadeItem['aula_titulo'] : '';
                        $atividadeModuloAtual = !empty($atividadeItem['modulo_titulo']) ? $atividadeItem['modulo_titulo'] : '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo Helpers::e($atividadeItem['titulo']); ?></strong>
                                <?php if (!empty($atividadeItem['descricao'])): ?>
                                    <div class="muted"><?php echo Helpers::e($truncateText($atividadeItem['descricao'], 120, '...')); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helpers::e($atividadeModuloAtual); ?></td>
                            <td><?php echo Helpers::e($atividadeAulaAtual); ?></td>
                            <td><?php echo Helpers::e($atividadeItem['tipo_entrega']); ?></td>
                            <td><?php echo !empty($atividadeItem['prazo']) ? Helpers::e(date('d/m/Y H:i', strtotime($atividadeItem['prazo']))) : 'Sem prazo'; ?></td>
                            <td><span class="badge"><?php echo Helpers::e(ucfirst($atividadeStatusAtual)); ?></span></td>
                            <td><span class="badge badge--soft"><?php echo (int) ($atividadeItem['total_entregas'] ?? 0); ?></span></td>
                            <td>
                                <div class="admin-area-curso__material-actions">
                                    <a href="<?php echo Helpers::e($areaCursoBaseUrl . '?' . http_build_query(array_merge($atividadeQueryBase, array('atividade_id' => $atividadeIdAtual)))); ?>">Editar</a>
                                    <?php if ($atividadeStatusAtual === 'publicado'): ?>
                                        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>/atividades/status" class="admin-area-curso__inline-action">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="id" value="<?php echo $atividadeIdAtual; ?>">
                                            <input type="hidden" name="status" value="oculto">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                            <button type="submit">Ocultar</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>/atividades/status" class="admin-area-curso__inline-action">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="id" value="<?php echo $atividadeIdAtual; ?>">
                                            <input type="hidden" name="status" value="publicado">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                            <button type="submit">Publicar</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>/excluir" class="admin-area-curso__inline-action">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="tipo" value="atividade">
                                        <input type="hidden" name="id" value="<?php echo $atividadeIdAtual; ?>">
                                        <input type="hidden" name="curso_evento_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
                                        <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                        <input type="hidden" name="aba" value="atividades">
                                        <input type="text" name="justificativa" placeholder="Justificativa" required>
                                        <button type="submit">Excluir</button>
                                    </form>
                                    <a href="<?php echo Helpers::e($areaCursoBaseUrl . '?' . http_build_query(array_merge($atividadeQueryBase, array('atividade_id' => $atividadeIdAtual)))); ?>#area-curso-atividades-entregas">Entregas</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

    <?php if (!empty($atividadeEditar)): ?>
    <section class="status-card admin-area-curso__section area-curso-tab-panel<?php echo $selectedTab === 'atividades' ? ' is-active' : ''; ?>" data-area-curso-tab="atividades" id="area-curso-atividades-entregas">
        <div class="panel-header">
            <div>
                <?php echo areaCursoHeadingWithTooltip('Entregas da atividade', 'Respostas enviadas pelos alunos para a atividade selecionada.'); ?>
            </div>
            <span class="badge"><?php echo (int) count($entregasAtividade); ?> entregas</span>
        </div>

        <p class="muted"><?php echo Helpers::e($atividadeEditar['titulo']); ?></p>

        <?php if (empty($entregasAtividade)): ?>
            <p class="muted">Nenhuma entrega encontrada para esta atividade.</p>
        <?php else: ?>
            <div class="admin-area-curso__actions" style="margin-bottom: 12px;">
                <span class="badge badge--soft">Status filtrado: <?php echo Helpers::e($entregaStatusOptions[$entregaStatusFiltro] ?? 'Todas'); ?></span>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Status</th>
                            <th>Atraso</th>
                            <th>Entrega</th>
                            <th>Nota</th>
                            <th>Feedback</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entregasAtividade as $entregaItem): ?>
                            <tr>
                                <td>
                                    <strong><?php echo Helpers::e($entregaItem['usuario_nome']); ?></strong>
                                    <div class="muted"><?php echo Helpers::e($entregaItem['usuario_email'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <?php
                                    $statusEntrega = (string) $entregaItem['status'];
                                    $rotuloStatusEntrega = array(
                                        'nao_enviada' => 'Não enviada',
                                        'enviada' => 'Enviada',
                                        'reenviada' => 'Reenviada',
                                        'devolvida' => 'Devolvida',
                                        'corrigida' => 'Corrigida',
                                        'atrasada' => 'Atrasada',
                                    );
                                    ?>
                                    <span class="badge"><?php echo Helpers::e($rotuloStatusEntrega[$statusEntrega] ?? ucfirst($statusEntrega)); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $prazoAtividade = !empty($entregaItem['atividade_prazo']) ? strtotime($entregaItem['atividade_prazo']) : false;
                                    $entregueEm = !empty($entregaItem['entregue_em']) ? strtotime($entregaItem['entregue_em']) : false;
                                    $entregaAtrasada = $prazoAtividade !== false && $entregueEm !== false && $entregueEm > $prazoAtividade;
                                    ?>
                                    <?php if ($statusEntrega === 'nao_enviada'): ?>
                                        <span class="badge badge--soft">Sem entrega</span>
                                    <?php elseif ($entregaAtrasada || $statusEntrega === 'atrasada'): ?>
                                        <span class="badge badge--warning">Atrasada</span>
                                    <?php else: ?>
                                        <span class="badge badge--soft">No prazo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($statusEntrega === 'nao_enviada'): ?>
                                        <span class="muted">Sem envio</span>
                                    <?php else: ?>
                                        <?php if (!empty($entregaItem['resposta_texto'])): ?>
                                            <div class="muted"><?php echo nl2br(Helpers::e($truncateText($entregaItem['resposta_texto'], 180, '...'))); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($entregaItem['arquivo_caminho'])): ?>
                                            <div style="margin-top:6px;">
                                                <a href="<?php echo Helpers::e($areaCursoBaseUrl . '/atividades/entregas/arquivo?entrega_id=' . (int) $entregaItem['id']); ?>">Baixar arquivo</a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $statusEntrega === 'nao_enviada' ? '—' : ($entregaItem['nota'] !== null ? Helpers::e(number_format((float) $entregaItem['nota'], 2, ',', '.')) : '-'); ?></td>
                                <td><?php echo $statusEntrega === 'nao_enviada' ? '—' : (!empty($entregaItem['feedback']) ? Helpers::e($truncateText($entregaItem['feedback'], 120, '...')) : '-'); ?></td>
                                <td>
                                    <?php if ($statusEntrega !== 'nao_enviada'): ?>
                                        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>/atividades/entregas/corrigir" class="form-grid admin-area-curso__delete-form">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="entrega_id" value="<?php echo (int) $entregaItem['id']; ?>">
                                            <input type="hidden" name="curso_evento_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                            <input type="hidden" name="atividade_id" value="<?php echo (int) $atividadeEditar['id']; ?>">
                                            <label>
                                                Nota
                                                <input type="number" step="0.01" min="0" max="<?php echo Helpers::e((string) ($atividadeEditar['nota_maxima'] ?? '10')); ?>" name="nota" value="<?php echo $entregaItem['nota'] !== null ? Helpers::e((string) $entregaItem['nota']) : ''; ?>">
                                            </label>
                                            <label>
                                                Status
                                                <select name="status">
                                                    <option value="corrigida">Corrigida</option>
                                                    <option value="devolvida">Devolvida</option>
                                                </select>
                                            </label>
                                            <label class="full">
                                                Feedback
                                                <textarea name="feedback" rows="3"><?php echo Helpers::e($entregaItem['feedback'] ?? ''); ?></textarea>
                                            </label>
                                            <button type="submit" class="full">Salvar correção</button>
                                        </form>
                                        <div class="admin-area-curso__material-actions" style="margin-top: 10px;">
                                            <a href="<?php echo Helpers::e($areaCursoBaseUrl . '?' . http_build_query(array_merge($atividadeQueryBase, array('atividade_id' => $atividadeIdAtual, 'entrega_id' => (int) $entregaItem['id'], 'entrega_status' => $entregaStatusFiltro)))); ?>#area-curso-atividades-detalhe">Ver detalhes</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge--soft">Aguardando envio</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($entregaSelecionadaDados)): ?>
        <?php
        $historicoLabels = array(
            'area_curso.atividade.entrega_criada' => 'Entrega criada',
            'area_curso.atividade.entrega_reenviada' => 'Entrega reenviada',
            'area_curso.atividade.entrega_corrigida' => 'Entrega corrigida',
            'area_curso.atividade.entrega_devolvida' => 'Entrega devolvida',
            'area_curso.atividade.entrega_bloqueada' => 'Entrega bloqueada',
        );
        $historicoLegivel = array();
        foreach ($entregaHistorico as $eventoHistorico) {
            $historicoLegivel[] = array(
                'acao' => isset($eventoHistorico['acao']) ? (string) $eventoHistorico['acao'] : '',
                'usuario' => !empty($eventoHistorico['usuario_nome']) ? (string) $eventoHistorico['usuario_nome'] : 'Sistema',
                'created_at' => !empty($eventoHistorico['created_at']) ? (string) $eventoHistorico['created_at'] : '',
                'metadados' => !empty($eventoHistorico['metadados']) && is_array($eventoHistorico['metadados']) ? $eventoHistorico['metadados'] : array(),
            );
        }
        ?>
        <section class="status-card admin-area-curso__section admin-area-curso__delivery-detail" id="area-curso-atividades-detalhe">
            <div class="panel-header">
                <div>
                    <?php echo areaCursoHeadingWithTooltip('Detalhes da entrega', 'Resumo da resposta enviada pelo aluno, com dados da atividade e histórico de correção.'); ?>
                </div>
                <span class="badge"><?php echo Helpers::e(ucfirst((string) ($entregaSelecionadaDados['status'] ?? ''))); ?></span>
            </div>

            <p class="muted">
                <?php echo Helpers::e($entregaSelecionada['usuario_nome'] ?? 'Aluno'); ?>
                ·
                <?php echo Helpers::e($entregaSelecionada['atividade_titulo'] ?? ($atividadeEditar['titulo'] ?? 'Atividade')); ?>
            </p>

            <div class="admin-area-curso__delivery-grid">
                <div class="admin-area-curso__delivery-meta">
                    <p><strong>Aluno:</strong> <?php echo Helpers::e($entregaSelecionada['usuario_nome'] ?? ''); ?></p>
                    <p><strong>E-mail:</strong> <?php echo Helpers::e($entregaSelecionada['usuario_email'] ?? ''); ?></p>
                    <p><strong>Curso:</strong> <?php echo Helpers::e($curso['nome'] ?? ''); ?></p>
                    <p><strong>Turma:</strong> <?php echo !empty($turma['nome']) ? Helpers::e($turma['nome']) : '—'; ?></p>
                    <p><strong>Módulo:</strong> <?php echo Helpers::e($atividadeEditar['modulo_titulo'] ?? ($entregaSelecionada['modulo']['titulo'] ?? '')); ?></p>
                    <p><strong>Aula:</strong> <?php echo Helpers::e($atividadeEditar['aula_titulo'] ?? ($entregaSelecionada['aula']['titulo'] ?? '')); ?></p>
                    <p><strong>Prazo:</strong> <?php echo !empty($entregaSelecionada['atividade_prazo']) ? Helpers::e(date('d/m/Y H:i', strtotime($entregaSelecionada['atividade_prazo']))) : (!empty($atividadeEditar['prazo']) ? Helpers::e(date('d/m/Y H:i', strtotime($atividadeEditar['prazo']))) : 'Sem prazo'); ?></p>
                    <p><strong>Enviada em:</strong> <?php echo !empty($entregaSelecionadaDados['entregue_em']) ? Helpers::e(date('d/m/Y H:i', strtotime($entregaSelecionadaDados['entregue_em']))) : '—'; ?></p>
                    <p><strong>Corrigida em:</strong> <?php echo !empty($entregaSelecionadaDados['corrigido_em']) ? Helpers::e(date('d/m/Y H:i', strtotime($entregaSelecionadaDados['corrigido_em']))) : '—'; ?></p>
                    <p><strong>Atraso:</strong> <?php echo !empty($entregaSelecionada['atividade_prazo']) && !empty($entregaSelecionadaDados['entregue_em']) && strtotime($entregaSelecionadaDados['entregue_em']) > strtotime($entregaSelecionada['atividade_prazo']) ? 'Sim' : 'Não'; ?></p>
                </div>

                <div class="admin-area-curso__delivery-content">
                    <?php if (!empty($entregaSelecionadaDados['resposta_texto'])): ?>
                        <strong>Resposta textual</strong>
                        <div class="admin-area-curso__delivery-box"><?php echo nl2br(Helpers::e($entregaSelecionadaDados['resposta_texto'])); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($entregaSelecionadaDados['arquivo_caminho'])): ?>
                        <p style="margin-top: 12px;"><a class="button-link button-link--ghost" href="<?php echo Helpers::e($areaCursoBaseUrl . '/atividades/entregas/arquivo?entrega_id=' . (int) $entregaSelecionadaDados['id']); ?>">Baixar arquivo enviado</a></p>
                    <?php endif; ?>

                    <div class="admin-area-curso__delivery-actions">
                        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>/atividades/entregas/corrigir" class="form-grid admin-area-curso__delivery-form">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="entrega_id" value="<?php echo (int) $entregaSelecionadaDados['id']; ?>">
                            <input type="hidden" name="curso_evento_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                            <input type="hidden" name="atividade_id" value="<?php echo (int) $atividadeEditar['id']; ?>">
                            <label>
                                Nota
                                <input type="number" step="0.01" min="0" max="<?php echo Helpers::e((string) ($atividadeEditar['nota_maxima'] ?? '10')); ?>" name="nota" value="<?php echo $entregaSelecionadaDados['nota'] !== null ? Helpers::e((string) $entregaSelecionadaDados['nota']) : ''; ?>">
                            </label>
                            <label class="full">
                                Feedback
                                <textarea name="feedback" rows="4"><?php echo Helpers::e($entregaSelecionadaDados['feedback'] ?? ''); ?></textarea>
                            </label>
                            <input type="hidden" name="status" value="corrigida">
                            <button type="submit" class="full">Salvar correção</button>
                        </form>

                        <form method="post" action="<?php echo Helpers::e($areaCursoBaseUrl); ?>/atividades/entregas/devolver" class="form-grid admin-area-curso__delivery-form">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="entrega_id" value="<?php echo (int) $entregaSelecionadaDados['id']; ?>">
                            <input type="hidden" name="curso_evento_id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                            <input type="hidden" name="atividade_id" value="<?php echo (int) $atividadeEditar['id']; ?>">
                            <input type="hidden" name="status" value="devolvida">
                            <label class="full">
                                Feedback para ajuste
                                <textarea name="feedback" rows="4" required><?php echo Helpers::e($entregaSelecionadaDados['feedback'] ?? ''); ?></textarea>
                            </label>
                            <button type="submit" class="full">Devolver para ajuste</button>
                        </form>
                    </div>

                    <?php if (!empty($historicoLegivel)): ?>
                        <div class="admin-area-curso__delivery-history">
                            <strong>Histórico da entrega</strong>
                            <ul class="history-list">
                                <?php foreach ($historicoLegivel as $itemHistorico): ?>
                                    <li>
                                        <span class="history-list__action"><?php echo Helpers::e($historicoLabels[$itemHistorico['acao']] ?? $itemHistorico['acao']); ?></span>
                                        <span class="history-list__meta">
                                            <?php echo Helpers::e($itemHistorico['usuario']); ?>
                                            <?php if (!empty($itemHistorico['created_at'])): ?> · <?php echo Helpers::e(date('d/m/Y H:i', strtotime($itemHistorico['created_at']))); ?><?php endif; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
