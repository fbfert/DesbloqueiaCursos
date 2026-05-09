<?php use App\Core\Helpers; ?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Páginas</h1>
            <p class="admin-page__subtitle">Gerencie páginas institucionais com rota pública e conteúdo em HTML.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/paginas/criar">Nova página</a>
        </div>
    </header>

    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="status-card">
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Status</th>
                        <th>Ordem</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paginas)): ?>
                        <tr>
                            <td colspan="4">Nenhuma página cadastrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paginas as $pagina): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo Helpers::e($pagina['rota']); ?>" target="_blank" rel="noopener"><?php echo Helpers::e($pagina['titulo']); ?></a><br>
                                    <small><?php echo Helpers::e($pagina['slug']); ?> · <?php echo Helpers::e($pagina['rota']); ?></small>
                                </td>
                                <td><?php echo Helpers::e($pagina['status']); ?></td>
                                <td><?php echo (int) $pagina['ordem']; ?></td>
                                <td>
                                    <a href="/admin/paginas/show?pagina_id=<?php echo (int) $pagina['id']; ?>">Ver</a> |
                                    <a href="/admin/paginas/editar?pagina_id=<?php echo (int) $pagina['id']; ?>">Editar</a> |
                                    <a href="#" onclick="return excluirPagina(<?php echo (int) $pagina['id']; ?>);">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <details>
            <summary><strong>Lixeira de páginas</strong></summary>
            <div class="table-wrap" style="margin-top:12px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID lixeira</th>
                            <th>Página</th>
                            <th>Justificativa</th>
                            <th>Excluído por</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lixeira_paginas)): ?>
                            <tr>
                                <td colspan="5">Nenhum item na lixeira de páginas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lixeira_paginas as $item): ?>
                                <tr>
                                    <td><?php echo (int) $item['id']; ?></td>
                                    <td><?php echo Helpers::e($item['snapshot_titulo'] !== '' ? $item['snapshot_titulo'] : ('ID ' . (int) $item['entidade_id'])); ?></td>
                                    <td><?php echo Helpers::e($item['justificativa']); ?></td>
                                    <td><?php echo Helpers::e((string) $item['excluido_por_nome']); ?></td>
                                    <td><?php echo Helpers::e((string) $item['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </section>
</section>

<form method="post" action="/admin/paginas/excluir" id="form-excluir-pagina" style="display:none;">
    <input type="hidden" name="id" id="excluir-pagina-id" value="">
    <input type="hidden" name="justificativa" id="excluir-pagina-justificativa" value="">
</form>

<script>
function excluirPagina(paginaId) {
    var justificativa = window.prompt('Informe a justificativa para enviar a página à lixeira:');
    if (justificativa === null) {
        return false;
    }
    justificativa = justificativa.trim();
    if (justificativa === '') {
        window.alert('A justificativa é obrigatória.');
        return false;
    }

    document.getElementById('excluir-pagina-id').value = String(paginaId);
    document.getElementById('excluir-pagina-justificativa').value = justificativa;
    document.getElementById('form-excluir-pagina').submit();
    return false;
}
</script>
