<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php
$canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar');
$cursosAtivos = isset($cursos) && is_array($cursos) ? $cursos : array();
$cursosRascunho = isset($cursos_rascunho) && is_array($cursos_rascunho) ? $cursos_rascunho : array();
$cursosInativos = isset($cursos_inativos) && is_array($cursos_inativos) ? $cursos_inativos : array();
$filters = isset($filters) && is_array($filters) ? $filters : array();
$categorias = isset($categorias) && is_array($categorias) ? $categorias : array();
$modalidades = isset($modalidades) && is_array($modalidades) ? $modalidades : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array('total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1);
$statusTotals = isset($status_totals) && is_array($status_totals) ? $status_totals : array();

if (!function_exists('curso_professores_texto')) {
    function curso_professores_texto(array $curso)
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

        return !empty($nomes) ? implode(', ', $nomes) : '-';
    }
}

if (!function_exists('cursosAdminQuery')) {
    function cursosAdminQuery(array $filters, $page = null)
    {
        $params = array(
            'busca' => isset($filters['busca']) ? $filters['busca'] : '',
            'categoria_id' => isset($filters['categoria_id']) ? $filters['categoria_id'] : '',
            'status' => isset($filters['status']) ? $filters['status'] : '',
            'tipo' => isset($filters['tipo']) ? $filters['tipo'] : '',
            'modalidade' => isset($filters['modalidade']) ? $filters['modalidade'] : '',
        );

        if ($page !== null) {
            $params['page'] = (int) $page;
        }

        return '/admin/cursos?' . http_build_query($params);
    }
}

$page = (int) ($pagination['page'] ?? 1);
$pages = (int) ($pagination['pages'] ?? 1);
$total = (int) ($pagination['total'] ?? 0);
$perPage = (int) ($pagination['per_page'] ?? 20);
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to = $total > 0 ? min($page * $perPage, $total) : 0;
$categoriasSelecionadas = (int) ($filters['categoria_id'] ?? 0);
$statusAtual = (string) ($filters['status'] ?? '');
$tipoAtual = (string) ($filters['tipo'] ?? '');
$modalidadeAtual = (string) ($filters['modalidade'] ?? '');
$buscaAtual = (string) ($filters['busca'] ?? '');
$statusAtivos = (int) ($statusTotals['ativo'] ?? count($cursosAtivos));
$statusRascunho = (int) ($statusTotals['rascunho'] ?? count($cursosRascunho));
$statusInativos = (int) (($statusTotals['inativo'] ?? 0) + ($statusTotals['arquivado'] ?? 0));
$tipoOptions = array(
    '' => 'Todos',
    'curso' => 'Curso',
    'evento' => 'Evento',
);
$statusOptions = array(
    '' => 'Todos',
    'ativo' => 'Ativo',
    'rascunho' => 'Rascunho',
    'inativo' => 'Inativo',
    'arquivado' => 'Arquivado',
);
$modalidadeOptions = array('' => 'Todas');
foreach ($modalidades as $modalidade) {
    $modalidadeOptions[$modalidade] = Helpers::modalidadeCurso($modalidade);
}
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Cursos e eventos</h1>
            <p class="admin-page__subtitle">Operação administrativa do catálogo principal.</p>
        </div>
        <?php if ($canManage): ?>
            <div class="admin-page__actions">
                <a class="button-link" href="/admin/cursos/criar">Novo curso/evento</a>
            </div>
        <?php endif; ?>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card" style="margin-bottom:12px;">
        <form method="get" action="/admin/cursos" class="admin-filters">
            <div class="admin-filters__row">
                <label>Busca
                    <input type="text" name="busca" value="<?php echo Helpers::e($buscaAtual); ?>" placeholder="Nome, slug ou ID do curso">
                </label>
                <label>Categoria
                    <select name="categoria_id">
                        <option value="0">Todas</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo (int) $categoria['id']; ?>" <?php echo $categoriasSelecionadas === (int) $categoria['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($categoria['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <?php foreach ($statusOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo $statusAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="admin-filters__row" style="margin-top:12px;">
                <label>Tipo
                    <select name="tipo">
                        <?php foreach ($tipoOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo $tipoAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Modalidade
                    <select name="modalidade">
                        <?php foreach ($modalidadeOptions as $valor => $rotulo): ?>
                            <option value="<?php echo Helpers::e($valor); ?>" <?php echo $modalidadeAtual === $valor ? 'selected' : ''; ?>><?php echo Helpers::e($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="cta-group" style="margin-top:12px;">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/cursos">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card" style="margin-bottom:12px;">
        <div class="cta-group" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <small><?php echo $total > 0 ? ('Exibindo ' . $from . ' a ' . $to . ' de ' . $total . ' cursos.') : 'Nenhum curso encontrado.'; ?></small>
            <small>Ativos: <?php echo $statusAtivos; ?> | Rascunhos: <?php echo $statusRascunho; ?> | Inativos: <?php echo $statusInativos; ?></small>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Cursos e eventos ativos (<?php echo $statusAtivos; ?>)</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Professores responsáveis</th>
                    <th>Tipo</th>
                    <th>Modalidade</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cursosAtivos)): ?>
                    <tr><td colspan="8">Nenhum curso/evento cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($cursosAtivos as $curso): ?>
                    <tr>
                        <td><?php echo Helpers::e($curso['nome']); ?></td>
                        <td><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></td>
                        <td><?php echo Helpers::e(curso_professores_texto($curso)); ?></td>
                        <td><?php echo Helpers::e($curso['tipo']); ?></td>
                        <td><?php echo Helpers::e(Helpers::modalidadeCurso($curso['modalidade'])); ?></td>
                        <td>
                            <?php
                            $temPromo = !empty($curso['em_promocao'])
                                && isset($curso['valor_promocional'])
                                && $curso['valor_promocional'] !== null
                                && $curso['valor_promocional'] !== ''
                                && (float) $curso['valor_promocional'] >= 0
                                && (float) $curso['valor_promocional'] < (float) $curso['valor'];
                            ?>
                            <?php if ($temPromo): ?>
                                <div><strong>R$ <?php echo number_format((float) $curso['valor_promocional'], 2, ',', '.'); ?></strong></div>
                                <div class="muted" style="font-size:12px;">De R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></div>
                            <?php else: ?>
                                R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge--success"><?php echo Helpers::e(ucfirst((string) $curso['status'])); ?></span></td>
                        <td>
                            <div class="split-actions">
                                <a href="/admin/cursos/show?curso_id=<?php echo (int) $curso['id']; ?>">Ver</a>
                                <?php if ($canManage): ?>
                                    <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
                                    <form method="post" action="/admin/cursos/status" class="admin-form">
                                        <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $curso['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>">
                                        <button type="submit"><?php echo $curso['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section" style="margin-top:16px;">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Cursos e eventos em rascunho (<?php echo $statusRascunho; ?>)</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Professores responsáveis</th>
                    <th>Tipo</th>
                    <th>Modalidade</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cursosRascunho)): ?>
                    <tr><td colspan="8">Nenhum curso ou evento em rascunho no momento.</td></tr>
                <?php endif; ?>
                <?php foreach ($cursosRascunho as $curso): ?>
                    <tr>
                        <td><?php echo Helpers::e($curso['nome']); ?></td>
                        <td><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></td>
                        <td><?php echo Helpers::e(curso_professores_texto($curso)); ?></td>
                        <td><?php echo Helpers::e($curso['tipo']); ?></td>
                        <td><?php echo Helpers::e(Helpers::modalidadeCurso($curso['modalidade'])); ?></td>
                        <td>
                            <?php
                            $temPromo = !empty($curso['em_promocao'])
                                && isset($curso['valor_promocional'])
                                && $curso['valor_promocional'] !== null
                                && $curso['valor_promocional'] !== ''
                                && (float) $curso['valor_promocional'] >= 0
                                && (float) $curso['valor_promocional'] < (float) $curso['valor'];
                            ?>
                            <?php if ($temPromo): ?>
                                <div><strong>R$ <?php echo number_format((float) $curso['valor_promocional'], 2, ',', '.'); ?></strong></div>
                                <div class="muted" style="font-size:12px;">De R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></div>
                            <?php else: ?>
                                R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge--warn">Rascunho</span></td>
                        <td>
                            <div class="split-actions">
                                <a href="/admin/cursos/show?curso_id=<?php echo (int) $curso['id']; ?>">Ver</a>
                                <?php if ($canManage): ?>
                                    <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
                                    <form method="post" action="/admin/cursos/status" class="admin-form">
                                        <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
                                        <input type="hidden" name="status" value="ativo">
                                        <button type="submit">Publicar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section" style="margin-top:16px;">
        <details>
            <summary style="cursor:pointer;font-weight:700;">Cursos e eventos inativos (<?php echo $statusInativos; ?>)</summary>
            <div class="table-wrap" style="margin-top:12px;">
                <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Professores responsáveis</th>
                        <th>Tipo</th>
                        <th>Modalidade</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursosInativos)): ?>
                        <tr><td colspan="8">Nenhum curso/evento inativo.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cursosInativos as $curso): ?>
                        <tr>
                            <td><?php echo Helpers::e($curso['nome']); ?></td>
                            <td><?php echo Helpers::e($curso['categoria_nome'] ?? ''); ?></td>
                            <td><?php echo Helpers::e(curso_professores_texto($curso)); ?></td>
                            <td><?php echo Helpers::e($curso['tipo']); ?></td>
                            <td><?php echo Helpers::e(Helpers::modalidadeCurso($curso['modalidade'])); ?></td>
                            <td>
                                <?php
                                $temPromo = !empty($curso['em_promocao'])
                                    && isset($curso['valor_promocional'])
                                    && $curso['valor_promocional'] !== null
                                    && $curso['valor_promocional'] !== ''
                                    && (float) $curso['valor_promocional'] >= 0
                                    && (float) $curso['valor_promocional'] < (float) $curso['valor'];
                                ?>
                                <?php if ($temPromo): ?>
                                    <div><strong>R$ <?php echo number_format((float) $curso['valor_promocional'], 2, ',', '.'); ?></strong></div>
                                    <div class="muted" style="font-size:12px;">De R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></div>
                                <?php else: ?>
                                    R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helpers::e($curso['status']); ?></td>
                            <td>
                                <div class="split-actions">
                                    <a href="/admin/cursos/show?curso_id=<?php echo (int) $curso['id']; ?>">Ver</a>
                                    <?php if ($canManage): ?>
                                        <a href="/admin/cursos/editar?curso_id=<?php echo (int) $curso['id']; ?>">Editar</a>
                                        <form method="post" action="/admin/cursos/status" class="admin-form">
                                            <input type="hidden" name="id" value="<?php echo (int) $curso['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $curso['status'] === 'ativo' ? 'inativo' : 'ativo'; ?>">
                                            <button type="submit"><?php echo $curso['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        </details>
    </section>

    <?php if ($pages > 1): ?>
        <section class="status-card" style="margin-top:12px;">
            <div class="cta-group" style="justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <small>Página <?php echo (int) $page; ?> de <?php echo (int) $pages; ?>.</small>
                <div class="cta-group">
                    <?php if ($page > 1): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e(cursosAdminQuery($filters, $page - 1)); ?>">Anterior</a>
                    <?php endif; ?>
                    <?php for ($paginaAtual = 1; $paginaAtual <= $pages; $paginaAtual++): ?>
                        <a class="button-link<?php echo $paginaAtual === $page ? ' button-link--primary' : ' button-link--ghost'; ?>" href="<?php echo Helpers::e(cursosAdminQuery($filters, $paginaAtual)); ?>"><?php echo (int) $paginaAtual; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e(cursosAdminQuery($filters, $page + 1)); ?>">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
