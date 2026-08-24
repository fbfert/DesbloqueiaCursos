<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php
$canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar');
$turma = isset($turma) && is_array($turma) ? $turma : array();
$inscritos = isset($inscritos) && is_array($inscritos) ? $inscritos : array();
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array();
$filters = isset($filters) && is_array($filters) ? $filters : array();

$turmaId = isset($turma['id']) ? (int) $turma['id'] : 0;
$qAtual = isset($filters['q']) ? (string) $filters['q'] : '';
$statusAtual = isset($filters['status']) ? (string) $filters['status'] : '';
$perPageAtual = isset($filters['per_page']) ? (int) $filters['per_page'] : 20;
$sortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : 'nome';
$sortDir = isset($filters['sort_dir']) ? (string) $filters['sort_dir'] : 'asc';

$paginaAtual = isset($pagination['page']) ? (int) $pagination['page'] : 1;
$totalPaginas = isset($pagination['pages']) ? (int) $pagination['pages'] : 1;
$totalRegistros = isset($pagination['total']) ? (int) $pagination['total'] : 0;

$statusLabels = array(
    'pendente' => 'Pendente',
    'com_pendencia' => 'Com pendência',
    'ativa' => 'Ativa',
    'em_andamento' => 'Em andamento',
    'cancelada' => 'Cancelada',
    'reprovada' => 'Reprovada',
    'concluida' => 'Concluída',
    'concluida_sem_certificado' => 'Concluída sem certificado',
    'certificado_emitido' => 'Certificado emitido',
);

// Monta uma URL desta tela preservando os filtros atuais e trocando só o que for passado.
$urlCom = function (array $troca = array()) use ($turmaId, $qAtual, $statusAtual, $perPageAtual, $sortBy, $sortDir, $paginaAtual) {
    $query = array_merge(array(
        'turma_id' => $turmaId,
        'q' => $qAtual,
        'status' => $statusAtual,
        'per_page' => $perPageAtual,
        'sort_by' => $sortBy,
        'sort_dir' => $sortDir,
        'page' => $paginaAtual,
    ), $troca);

    $query = array_filter($query, function ($valor) {
        return $valor !== '' && $valor !== null;
    });

    return '/admin/turmas/inscritos?' . http_build_query($query);
};

// URL de exportação: leva os filtros e a ordenação atuais, sem paginação.
$urlExportar = function ($rota) use ($turmaId, $qAtual, $statusAtual, $sortBy, $sortDir) {
    $query = array_filter(array(
        'turma_id' => $turmaId,
        'q' => $qAtual,
        'status' => $statusAtual,
        'sort_by' => $sortBy,
        'sort_dir' => $sortDir,
    ), function ($valor) {
        return $valor !== '' && $valor !== null;
    });

    return $rota . '?' . http_build_query($query);
};

// Cabeçalho ordenável: clicar alterna asc/desc e volta para a primeira página.
$colunaOrdenavel = function ($chave, $rotulo) use ($sortBy, $sortDir, $urlCom) {
    $direcao = ($sortBy === $chave && $sortDir === 'asc') ? 'desc' : 'asc';
    $indicador = '';
    if ($sortBy === $chave) {
        $indicador = $sortDir === 'asc' ? ' ▲' : ' ▼';
    }

    $url = $urlCom(array('sort_by' => $chave, 'sort_dir' => $direcao, 'page' => 1));

    return '<a href="' . Helpers::e($url) . '">' . Helpers::e($rotulo) . $indicador . '</a>';
};

$formatarData = function ($valor) {
    $valor = trim((string) $valor);
    if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
        return '-';
    }

    $timestamp = strtotime($valor);

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
};

$formatarPercentual = function ($valor) {
    if ($valor === null || $valor === '') {
        return '-';
    }

    return number_format((float) $valor, 0, ',', '.') . '%';
};

$formatarNota = function ($valor) {
    if ($valor === null || $valor === '') {
        return '-';
    }

    return number_format((float) $valor, 1, ',', '.');
};
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Inscritos da turma</h1>
            <p class="admin-page__subtitle">
                <?php echo Helpers::e($turma['nome']); ?>
                <?php if (!empty($turma['codigo'])): ?>
                    (<?php echo Helpers::e($turma['codigo']); ?>)
                <?php endif; ?>
                &middot; <?php echo Helpers::e($turma['curso_nome']); ?>
                &middot; <?php echo $totalRegistros; ?> inscrito(s)<?php echo ($qAtual !== '' || $statusAtual !== '') ? ' no filtro atual' : ''; ?>
            </p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/turmas">Voltar</a>
            <?php if ($totalRegistros > 0): ?>
                <a class="button-link button-link--ghost"
                   href="<?php echo Helpers::e($urlExportar('/admin/turmas/inscritos/exportar')); ?>">Exportar CSV</a>
                <a class="button-link button-link--ghost"
                   href="<?php echo Helpers::e($urlExportar('/admin/turmas/inscritos/exportar-pdf')); ?>">Exportar PDF</a>
            <?php endif; ?>
            <a class="button-link button-link--ghost" href="/admin/turmas/emails?turma_id=<?php echo $turmaId; ?>">E-mails da turma</a>
            <?php if ($canManage): ?>
                <a class="button-link" href="/admin/turmas/emails/novo?turma_id=<?php echo $turmaId; ?>">Enviar e-mail</a>
            <?php endif; ?>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <?php if (!empty($resumo) && (int) $resumo['total'] > 0): ?>
        <section class="status-card">
            <p class="muted">
                <?php
                $partes = array();
                foreach ($statusLabels as $chave => $rotulo) {
                    if (!empty($resumo[$chave])) {
                        $partes[] = Helpers::e($rotulo) . ': <strong>' . (int) $resumo[$chave] . '</strong>';
                    }
                }
                echo implode(' &middot; ', $partes);
                ?>
            </p>
        </section>
    <?php endif; ?>

    <section class="status-card">
        <form method="get" action="/admin/turmas/inscritos" class="admin-filters">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
            <input type="hidden" name="sort_by" value="<?php echo Helpers::e($sortBy); ?>">
            <input type="hidden" name="sort_dir" value="<?php echo Helpers::e($sortDir); ?>">

            <div class="admin-filters__row">
                <label>Busca
                    <input type="text" name="q" value="<?php echo Helpers::e($qAtual); ?>" placeholder="Nome, e-mail ou telefone">
                </label>
                <label>Status
                    <select name="status">
                        <option value="">Todos os status</option>
                        <?php foreach ($statusLabels as $chave => $rotulo): ?>
                            <option value="<?php echo Helpers::e($chave); ?>" <?php echo $statusAtual === $chave ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($rotulo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Por página
                    <select name="per_page">
                        <?php foreach (array(20, 50, 100) as $opcao): ?>
                            <option value="<?php echo $opcao; ?>" <?php echo $perPageAtual === $opcao ? 'selected' : ''; ?>>
                                <?php echo $opcao; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="cta-group" style="margin-top:12px;">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/turmas/inscritos?turma_id=<?php echo $turmaId; ?>">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card">
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?php echo $colunaOrdenavel('nome', 'Aluno'); ?></th>
                        <th><?php echo $colunaOrdenavel('email', 'E-mail'); ?></th>
                        <th>WhatsApp</th>
                        <th><?php echo $colunaOrdenavel('status', 'Status'); ?></th>
                        <th><?php echo $colunaOrdenavel('progresso', 'Progresso'); ?></th>
                        <th>Presença</th>
                        <th><?php echo $colunaOrdenavel('nota', 'Nota'); ?></th>
                        <th>Certificado</th>
                        <th><?php echo $colunaOrdenavel('created_at', 'Inscrito em'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inscritos)): ?>
                        <tr>
                            <td colspan="9">
                                <?php echo ($qAtual !== '' || $statusAtual !== '')
                                    ? 'Nenhum inscrito encontrado com esses filtros.'
                                    : 'Esta turma ainda não tem inscritos.'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inscritos as $inscrito): ?>
                            <?php $status = (string) $inscrito['status']; ?>
                            <tr>
                                <td><?php echo Helpers::e($inscrito['nome']); ?></td>
                                <td><?php echo Helpers::e($inscrito['email']); ?></td>
                                <td><?php echo Helpers::e(($inscrito['telefone'] ?? '') !== '' ? $inscrito['telefone'] : '-'); ?></td>
                                <td><?php echo Helpers::e(isset($statusLabels[$status]) ? $statusLabels[$status] : $status); ?></td>
                                <td><?php echo Helpers::e($formatarPercentual($inscrito['percentual_progresso'])); ?></td>
                                <td><?php echo Helpers::e($formatarPercentual($inscrito['presenca_percentual'])); ?></td>
                                <td><?php echo Helpers::e($formatarNota($inscrito['nota_final'])); ?></td>
                                <td><?php echo !empty($inscrito['apto_certificado']) ? 'Apto' : '-'; ?></td>
                                <td><?php echo Helpers::e($formatarData($inscrito['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="cta-group" style="margin-top:12px;align-items:center;">
                <?php if ($paginaAtual > 1): ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($urlCom(array('page' => $paginaAtual - 1))); ?>">Anterior</a>
                <?php endif; ?>

                <small>Página <?php echo $paginaAtual; ?> de <?php echo $totalPaginas; ?> (<?php echo $totalRegistros; ?> registro(s))</small>

                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a class="button-link button-link--ghost" href="<?php echo Helpers::e($urlCom(array('page' => $paginaAtual + 1))); ?>">Próxima</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
