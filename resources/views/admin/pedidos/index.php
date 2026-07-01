<?php use App\Core\Helpers; ?>
<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array('total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1);
$currentSortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : 'id';
$currentSortDir = isset($filters['sort_dir']) ? strtolower((string) $filters['sort_dir']) : 'desc';
$queryBase = array(
    'q' => isset($filters['q']) ? $filters['q'] : '',
    'status' => isset($filters['status']) ? $filters['status'] : '',
    'curso' => isset($filters['curso']) ? $filters['curso'] : '',
    'de' => isset($filters['de']) ? $filters['de'] : '',
    'ate' => isset($filters['ate']) ? $filters['ate'] : '',
    'per_page' => isset($filters['per_page']) ? $filters['per_page'] : 20,
);

if (!function_exists('pedidosSortUrl')) {
    function pedidosSortUrl($field, $currentSortBy, $currentSortDir, array $queryBase)
    {
        $nextDir = ($currentSortBy === $field && $currentSortDir === 'asc') ? 'desc' : 'asc';
        $params = array_merge($queryBase, array('sort_by' => $field, 'sort_dir' => $nextDir));
        return '/admin/pedidos?' . http_build_query($params);
    }
}

if (!function_exists('pedidosQuery')) {
    function pedidosQuery(array $queryBase, $sortBy, $sortDir, $page = null)
    {
        $params = array_merge($queryBase, array(
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ));

        if ($page !== null) {
            $params['page'] = (int) $page;
        }

        return $params;
    }
}

if (!function_exists('pedidosFormatarTelefone')) {
    function pedidosFormatarTelefone($telefone)
    {
        $telefone = preg_replace('/\D+/', '', (string) $telefone);

        if ($telefone === '') {
            return '';
        }

        if (strlen($telefone) === 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
        }

        if (strlen($telefone) === 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
        }

        return $telefone;
    }
}

if (!function_exists('pedidosCursoNome')) {
    function pedidosCursoNome($cursoNome)
    {
        $cursoNome = trim((string) $cursoNome);

        if ($cursoNome === '') {
            return 'Curso não informado';
        }

        return $cursoNome;
    }
}

$totalPedidos = is_array($pedidos) ? count($pedidos) : 0;
$pendentes = 0;
$comPix = 0;

foreach ((array) $pedidos as $pedidoResumo) {
    $status = isset($pedidoResumo['status']) ? (string) $pedidoResumo['status'] : '';
    if (in_array($status, array('aguardando_pagamento', 'comprovante_enviado', 'em_analise', 'pendencia', 'aguardando_reenvio'), true)) {
        $pendentes++;
    }

    if (!empty($pedidoResumo['comprovante_pix'])) {
        $comPix++;
    }
}
$page = isset($pagination['page']) ? (int) $pagination['page'] : 1;
$pages = isset($pagination['pages']) ? (int) $pagination['pages'] : 1;
$total = isset($pagination['total']) ? (int) $pagination['total'] : 0;
$perPage = isset($pagination['per_page']) ? (int) $pagination['per_page'] : 20;
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to = $total > 0 ? min($page * $perPage, $total) : 0;
$statusAtual = (string) ($filters['status'] ?? '');
?>

<div class="admin-page admin-pedidos-page">
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__header-content">
            <h1 class="admin-page__title">Pedidos</h1>
            <p class="admin-page__subtitle">Listagem administrativa de pedidos, participantes e comprovantes PIX.</p>
            <?php if (!empty($recuperacao_ativa)): ?>
                <p class="admin-page__subtitle">Exibindo pedidos incompletos elegíveis para recuperação manual.</p>
            <?php endif; ?>
        </div>
        <div class="admin-page__metrics">
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Pedidos listados</span>
                <strong class="admin-page__metric-value"><?php echo (int) $totalPedidos; ?></strong>
                <small class="admin-page__metric-help">na tela atual</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Pedidos pendentes</span>
                <strong class="admin-page__metric-value"><?php echo (int) $pendentes; ?></strong>
                <small class="admin-page__metric-help">com necessidade de ação</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Com comprovante PIX</span>
                <strong class="admin-page__metric-value"><?php echo (int) $comPix; ?></strong>
                <small class="admin-page__metric-help">com arquivo enviado</small>
            </article>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atalhos de operação</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <?php if (!empty($can_manage_pedidos)): ?>
                <a class="card-link admin-shortcut" href="/admin/pedidos/criar"><span>Criar pedido manualmente</span><small>Selecionar aluno, curso, turma e cupom</small></a>
            <?php endif; ?>
            <a class="card-link admin-shortcut" href="/admin/comprovantes-pix"><span>Comprovantes PIX</span><small>Fila de análise e aprovação</small></a>
            <a class="card-link admin-shortcut" href="/admin/pedidos/recuperacao"><span>Recuperação Manual por e-mail</span><small>Pedidos incompletos elegíveis</small></a>
            <a class="card-link admin-shortcut" href="/admin/inscricoes"><span>Inscrições</span><small>Acompanhar status de alunos</small></a>
            <a class="card-link admin-shortcut" href="/admin/pedidos/excluidos"><span>Pedidos excluídos</span><small>Consultar a lixeira de pedidos</small></a>
            <a class="card-link admin-shortcut" href="/admin/dashboard"><span>Dashboard</span><small>Voltar ao painel executivo</small></a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Pedidos</h2>
        </div>
        <div class="status-card admin-pedidos-card">
            <form method="get" action="/admin/pedidos" class="admin-filters">
                <div class="admin-filters__row admin-pedidos__filters-row">
                    <label>Busca
                        <input type="text" name="q" value="<?php echo Helpers::e((string) ($filters['q'] ?? '')); ?>" placeholder="Código, pagador, e-mail, CPF ou curso">
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="rascunho" <?php echo $statusAtual === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="aguardando_pagamento" <?php echo $statusAtual === 'aguardando_pagamento' ? 'selected' : ''; ?>>Aguardando pagamento</option>
                            <option value="comprovante_enviado" <?php echo $statusAtual === 'comprovante_enviado' ? 'selected' : ''; ?>>Comprovante enviado</option>
                            <option value="em_analise" <?php echo $statusAtual === 'em_analise' ? 'selected' : ''; ?>>Em análise</option>
                            <option value="pendencia" <?php echo $statusAtual === 'pendencia' ? 'selected' : ''; ?>>Pendência</option>
                            <option value="aguardando_reenvio" <?php echo $statusAtual === 'aguardando_reenvio' ? 'selected' : ''; ?>>Aguardando reenvio</option>
                            <option value="pedido_incompleto" <?php echo $statusAtual === 'pedido_incompleto' ? 'selected' : ''; ?>>Pedidos incompletos</option>
                            <option value="aprovado" <?php echo $statusAtual === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                            <option value="cancelado" <?php echo $statusAtual === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </label>
                    <label>Curso
                        <input type="text" name="curso" value="<?php echo Helpers::e((string) ($filters['curso'] ?? '')); ?>" placeholder="Nome do curso">
                    </label>
                </div>
                <div class="admin-filters__row admin-pedidos__filters-row">
                    <label>De
                        <input type="date" name="de" value="<?php echo Helpers::e((string) ($filters['de'] ?? '')); ?>">
                    </label>
                    <label>Até
                        <input type="date" name="ate" value="<?php echo Helpers::e((string) ($filters['ate'] ?? '')); ?>">
                    </label>
                    <label>Por página
                        <select name="per_page">
                            <?php $perPageAtual = (int) ($filters['per_page'] ?? 20); ?>
                            <?php foreach (array(20, 50, 100) as $valor): ?>
                                <option value="<?php echo (int) $valor; ?>" <?php echo $perPageAtual === (int) $valor ? 'selected' : ''; ?>><?php echo (int) $valor; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <input type="hidden" name="sort_by" value="<?php echo Helpers::e($currentSortBy); ?>">
                    <input type="hidden" name="sort_dir" value="<?php echo Helpers::e($currentSortDir); ?>">
                </div>
                <div class="cta-group admin-pedidos__filter-actions">
                    <button type="submit" class="button-link button-link--primary">Filtrar</button>
                    <a class="button-link button-link--ghost" href="/admin/pedidos">Limpar filtros</a>
                </div>
            </form>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--pedidos-lista">
            <thead>
                <tr>
                    <th><a href="<?php echo Helpers::e(pedidosSortUrl('codigo', $currentSortBy, $currentSortDir, pedidosQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Código</a></th>
                    <th><a href="<?php echo Helpers::e(pedidosSortUrl('pagador_nome', $currentSortBy, $currentSortDir, pedidosQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Pagador</a></th>
                    <th>Participantes</th>
                    <th><a href="<?php echo Helpers::e(pedidosSortUrl('total', $currentSortBy, $currentSortDir, pedidosQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Total</a></th>
                    <th><a href="<?php echo Helpers::e(pedidosSortUrl('status', $currentSortBy, $currentSortDir, pedidosQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Status</a></th>
                    <th>Gateway</th>
                    <th>Comprovante</th>
                    <th><a href="<?php echo Helpers::e(pedidosSortUrl('created_at', $currentSortBy, $currentSortDir, pedidosQuery($queryBase, $currentSortBy, $currentSortDir))); ?>">Data</a></th>
                    <th>Recuperação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="10">Nenhum pedido encontrado.</td>
                    </tr>
                <?php endif; ?>
                    <?php foreach ($pedidos as $pedido): ?>
                    <?php
                    $comprovante = isset($pedido['comprovante_pix']) ? $pedido['comprovante_pix'] : null;
                    $exclusao = isset($pedido['exclusao']) && is_array($pedido['exclusao']) ? $pedido['exclusao'] : array('ok' => false, 'motivos_texto' => 'Este pedido não pode ser excluído.');
                    $ehRecuperavel = in_array((string) $pedido['status'], array('rascunho', 'aguardando_pagamento', 'aguardando_reenvio', 'pendencia'), true);
                    ?>
                    <tr>
                        <td>
                            <div class="admin-pedidos__cell-stack">
                                <strong><?php echo htmlspecialchars($pedido['codigo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small class="muted text-muted">Curso: <?php echo htmlspecialchars(pedidosCursoNome(isset($pedido['cursos_nome']) ? $pedido['cursos_nome'] : ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php if (!empty($pedido['is_presente'])): ?>
                                    <span class="badge badge--status badge--status-pendente">Presente</span>
                                    <?php if (!empty($pedido['presente_campanha_titulo'])): ?>
                                        <small><?php echo htmlspecialchars((string) $pedido['presente_campanha_titulo'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="admin-pedidos__cell-stack">
                                <strong><?php echo htmlspecialchars((string) $pedido['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small><?php echo htmlspecialchars((string) $pedido['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php if (!empty($pedido['pagador_telefone'])): ?>
                                    <small><?php echo htmlspecialchars(pedidosFormatarTelefone((string) $pedido['pagador_telefone']), ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo isset($pedido['quantidade_participantes']) ? (int) $pedido['quantidade_participantes'] : 0; ?></td>
                        <td>R$ <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($pedido['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <div class="admin-pedidos__cell-stack">
                                <strong><?php echo htmlspecialchars((string) ($pedido['payment_gateway'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($pedido['payment_provider_status'])): ?>
                                    <small><?php echo htmlspecialchars((string) $pedido['payment_provider_status'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="admin-pedidos__cell-stack">
                                <?php if ($comprovante): ?>
                                    <strong><?php echo htmlspecialchars($comprovante['status'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small>v<?php echo (int) $comprovante['versao']; ?></small>
                                <?php else: ?>
                                    <strong>-</strong>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars((string) $pedido['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <div class="admin-pedidos__cell-stack">
                                <?php if (!empty($pedido['ultimo_envio_em'])): ?>
                                    <strong><?php echo htmlspecialchars((string) $pedido['ultimo_envio_em'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small>Total: <?php echo (int) ($pedido['total_envios'] ?? 0); ?> | Manuais: <?php echo (int) ($pedido['envios_manuais'] ?? 0); ?> | Automáticos: <?php echo (int) ($pedido['envios_automaticos'] ?? 0); ?></small>
                                <?php else: ?>
                                    <strong>-</strong>
                                <?php endif; ?>
                                <?php if (!empty($pedido['optout_ativo'])): ?>
                                    <small>Opt-out ativo</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a class="button-link button-link--ghost" href="/admin/pedidos/show?pedido_id=<?php echo (int) $pedido['id']; ?>">Abrir</a>
                        </td>
                    </tr>
                    <?php if (!empty($can_manage_pedidos)): ?>
                        <tr class="admin-pedidos__actions-row">
                            <td colspan="10">
                                <details class="admin-pedidos__actions-details">
                                    <summary>Mais opções</summary>
                                    <div class="admin-pedidos__actions-body">
                                        <?php if ($ehRecuperavel || !empty($recuperacao_ativa)): ?>
                                            <form method="post" action="/admin/pedidos/recuperacao/enviar" class="admin-form admin-pedidos__delete-form">
                                                <?php echo $csrfField; ?>
                                                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                                <input type="hidden" name="modelo_chave" value="pedido_recuperacao_primeiro_lembrete">
                                                <label>Cupom manual</label>
                                                <input type="text" name="cupom_codigo" placeholder="Opcional">
                                                <label class="checkbox" style="margin-top:8px;">
                                                    <input type="checkbox" name="confirmar_envio" value="1">
                                                    Confirmar envio mesmo com lembrete recente
                                                </label>
                                                <button type="submit" class="button-link button-link--primary">Enviar recuperação</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!empty($pedido['cupom_manual']) && !empty($pedido['cupom_manual']['ok'])): ?>
                                            <a class="button-link button-link--ghost" href="/admin/pedidos/show?pedido_id=<?php echo (int) $pedido['id']; ?>#cupom-manual"><?php echo !empty($pedido['cupom_manual']['pedido_confirmado']) ? 'Ajustar cupom' : 'Aplicar cupom'; ?></a>
                                        <?php endif; ?>
                                        <?php if (!empty($exclusao['ok'])): ?>
                                            <form method="post" action="/admin/pedidos/excluir" class="admin-form admin-pedidos__delete-form">
                                                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                                <label>Justificativa da lixeira</label>
                                                <textarea name="justificativa" rows="2" required placeholder="Informe a justificativa da exclusão."></textarea>
                                                <button type="submit" class="button-link button-link--danger" onclick="return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu este pedido?' });">🗑 Excluir pedido</button>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert-danger">
                                                <?php echo htmlspecialchars(isset($exclusao['motivos_texto']) ? $exclusao['motivos_texto'] : 'Este pedido não pode ser excluído.', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>

    <?php if ($pages > 1): ?>
        <section class="status-card admin-pedidos-card admin-pedidos-card--pagination">
            <div class="cta-group admin-pedidos__pagination-row">
                <small>Página <?php echo (int) $page; ?> de <?php echo (int) $pages; ?> — mostrando <?php echo (int) $from; ?> a <?php echo (int) $to; ?> de <?php echo (int) $total; ?> pedidos.</small>
                <div class="cta-group">
                    <?php if ($page > 1): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/pedidos?' . http_build_query(pedidosQuery($queryBase, $currentSortBy, $currentSortDir, $page - 1))); ?>">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/pedidos?' . http_build_query(pedidosQuery($queryBase, $currentSortBy, $currentSortDir, $page + 1))); ?>">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($can_manage_pedidos)): ?>
        <section class="admin-section admin-pedidos-card admin-pedidos-card--bulk">
            <details class="admin-pedidos__actions-details">
                <summary>Exclusão em lote</summary>
                <div class="admin-pedidos__actions-body">
                    <div class="admin-pedidos__bulk-warning">
                        <p>Esta ação exclui apenas pedidos com mais de 30 dias sem pagamento confirmado. Pedidos pagos, aprovados, com comprovante aprovado, inscrições ativas ou certificados permanecem protegidos.</p>
                        <form method="post" action="/admin/pedidos/excluir-lote" class="admin-form">
                            <input type="hidden" name="dias" value="30">
                            <label>Justificativa da lixeira</label>
                            <textarea name="justificativa" rows="3" required placeholder="Informe a justificativa para a exclusão em lote."></textarea>
                            <div class="cta-group">
                                <button type="submit" class="button-link button-link--danger" onclick="return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a exclusão em lote dos pedidos antigos?' });">🗑 Excluir pedidos antigos</button>
                            </div>
                        </form>
                    </div>
                </div>
            </details>
        </section>
    <?php endif; ?>
</div>

