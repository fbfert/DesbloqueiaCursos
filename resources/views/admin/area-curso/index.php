<?php use App\Core\Helpers; ?>

<?php
$instrucaoEditar = isset($instrucao_selecionada) ? $instrucao_selecionada : null;
$moduloEditar = isset($modulo_selecionado) ? $modulo_selecionado : null;
$aulaEditar = isset($aula_selecionada) ? $aula_selecionada : null;
$materialEditar = isset($material_selecionado) ? $material_selecionado : null;
$linkEditar = isset($link_selecionado) ? $link_selecionado : null;
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Área interna do curso</h1>
            <p class="admin-page__subtitle">Administração de instruções, módulos, aulas, materiais, links e participantes.</p>
        </div>
    </section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="get" action="/admin/area-curso" class="form-grid">
        <label>
            Curso
            <select name="curso_id">
                <option value="">Selecione</option>
                <?php foreach ($cursos as $item): ?>
                    <option value="<?php echo (int) $item['id']; ?>" <?php echo !empty($curso) && (int) $curso['id'] === (int) $item['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($item['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Abrir contexto</button>
    </form>
</section>

<?php if (!empty($curso)): ?>
    <section class="status-card">
        <strong>Instruções</strong>
        <form method="post" action="/admin/area-curso/instrucoes" class="form-grid">
            <input type="hidden" name="id" value="<?php echo !empty($instrucaoEditar['id']) ? (int) $instrucaoEditar['id'] : 0; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <label>Título<input type="text" name="titulo" value="<?php echo Helpers::e($instrucaoEditar['titulo'] ?? ''); ?>"></label>
            <label>Conteúdo<textarea name="conteudo" rows="4"><?php echo Helpers::e($instrucaoEditar['conteudo'] ?? ''); ?></textarea></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($instrucaoEditar['ordem'] ?? 1)); ?>" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($instrucaoEditar) ? (!empty($instrucaoEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
            <button type="submit"><?php echo !empty($instrucaoEditar) ? 'Atualizar instrução' : 'Salvar instrução'; ?></button>
        </form>
    </section>

    <section class="status-card">
        <strong>Modulos</strong>
        <form method="post" action="/admin/area-curso/modulos" class="form-grid">
            <input type="hidden" name="id" value="<?php echo !empty($moduloEditar['id']) ? (int) $moduloEditar['id'] : 0; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <label>Título<input type="text" name="titulo" value="<?php echo Helpers::e($moduloEditar['titulo'] ?? ''); ?>"></label>
            <label>Descrição<textarea name="descricao" rows="3"><?php echo Helpers::e($moduloEditar['descricao'] ?? ''); ?></textarea></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($moduloEditar['ordem'] ?? 1)); ?>" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($moduloEditar) ? (!empty($moduloEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
            <button type="submit"><?php echo !empty($moduloEditar) ? 'Atualizar módulo' : 'Salvar módulo'; ?></button>
        </form>

        <div class="table-wrap admin-mt-12">
            <table class="admin-table admin-table--area-modulos">
                <thead><tr><th>Título</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($modulos as $modulo): ?>
                        <tr>
                            <td><?php echo Helpers::e($modulo['titulo']); ?></td>
                            <td><?php echo (int) $modulo['ordem']; ?></td>
                            <td><?php echo !empty($modulo['visivel']) ? 'visível' : 'oculto'; ?></td>
                            <td>
                                <div class="split-actions">
                                    <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?>&modulo_id=<?php echo (int) $modulo['id']; ?>">Editar</a>
                                </div>
                                <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-mt-8">
                                    <input type="hidden" name="tipo" value="modulo">
                                    <input type="hidden" name="id" value="<?php echo (int) $modulo['id']; ?>">
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

    <section class="status-card">
        <strong>Aulas</strong>
        <form method="post" action="/admin/area-curso/aulas" class="form-grid">
            <input type="hidden" name="id" value="<?php echo !empty($aulaEditar['id']) ? (int) $aulaEditar['id'] : 0; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <label>Módulo
                <select name="modulo_id">
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?php echo (int) $modulo['id']; ?>" <?php echo !empty($aulaEditar) && (int) ($aulaEditar['modulo_id'] ?? 0) === (int) $modulo['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($modulo['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Título<input type="text" name="titulo" value="<?php echo Helpers::e($aulaEditar['titulo'] ?? ''); ?>"></label>
            <label>Conteúdo<textarea name="conteudo" rows="3"><?php echo Helpers::e($aulaEditar['conteudo'] ?? ''); ?></textarea></label>
            <label>Tipo<input type="text" name="tipo" value="<?php echo Helpers::e($aulaEditar['tipo'] ?? 'texto'); ?>"></label>
            <label>URL vídeo<input type="text" name="url_video" value="<?php echo Helpers::e($aulaEditar['url_video'] ?? ''); ?>"></label>
            <label>Duração (minutos)<input type="number" name="duracao_minutos" value="<?php echo Helpers::e((string) ($aulaEditar['duracao_minutos'] ?? '')); ?>"></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($aulaEditar['ordem'] ?? 1)); ?>" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($aulaEditar) ? (!empty($aulaEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
            <label class="checkbox"><input type="checkbox" name="obrigatoria" value="1" <?php echo !empty($aulaEditar) && !empty($aulaEditar['obrigatoria']) ? 'checked' : ''; ?>> Obrigatória</label>
            <button type="submit"><?php echo !empty($aulaEditar) ? 'Atualizar aula' : 'Salvar aula'; ?></button>
        </form>

        <div class="table-wrap admin-mt-12">
            <table class="admin-table admin-table--area-aulas">
                <thead><tr><th>Título</th><th>Tipo</th><th>Ordem</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($modulos as $modulo): ?>
                        <?php foreach ($modulo['aulas'] as $aula): ?>
                            <tr>
                                <td><?php echo Helpers::e($aula['titulo']); ?></td>
                                <td><?php echo Helpers::e($aula['tipo']); ?></td>
                                <td><?php echo (int) $aula['ordem']; ?></td>
                                <td>
                                    <div class="split-actions">
                                        <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?>&aula_id=<?php echo (int) $aula['id']; ?>">Editar</a>
                                    </div>
                                    <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-mt-8">
                                        <input type="hidden" name="tipo" value="aula">
                                        <input type="hidden" name="id" value="<?php echo (int) $aula['id']; ?>">
                                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                        <input type="text" name="justificativa" placeholder="Justificativa" required>
                                        <button type="submit">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="status-card">
        <strong>Materiais</strong>
        <form method="post" action="/admin/area-curso/materiais" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo !empty($materialEditar['id']) ? (int) $materialEditar['id'] : 0; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <label>Módulo
                <select name="modulo_id">
                    <option value="">Sem módulo</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?php echo (int) $modulo['id']; ?>" <?php echo !empty($materialEditar) && (int) ($materialEditar['modulo_id'] ?? 0) === (int) $modulo['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($modulo['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Aula
                <select name="aula_id">
                    <option value="">Sem aula</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <?php foreach ($modulo['aulas'] as $aula): ?>
                            <option value="<?php echo (int) $aula['id']; ?>" <?php echo !empty($materialEditar) && (int) ($materialEditar['aula_id'] ?? 0) === (int) $aula['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($modulo['titulo'] . ' - ' . $aula['titulo']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Título<input type="text" name="titulo" value="<?php echo Helpers::e($materialEditar['titulo'] ?? ''); ?>"></label>
            <label>Descrição<textarea name="descricao" rows="3"><?php echo Helpers::e($materialEditar['descricao'] ?? ''); ?></textarea></label>
            <label>Tipo de arquivo<input type="text" name="tipo_arquivo" value="<?php echo Helpers::e($materialEditar['tipo_arquivo'] ?? 'outro'); ?>"></label>
            <label>Arquivo<input type="file" name="arquivo"></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($materialEditar['ordem'] ?? 1)); ?>" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($materialEditar) ? (!empty($materialEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
            <button type="submit"><?php echo !empty($materialEditar) ? 'Atualizar material' : 'Salvar material'; ?></button>
        </form>

        <div class="table-wrap admin-mt-12">
            <table class="admin-table admin-table--area-materiais">
                <thead><tr><th>Título</th><th>Tipo</th><th>Acesso</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($materiais as $material): ?>
                        <tr>
                            <td><?php echo Helpers::e($material['titulo']); ?></td>
                            <td><?php echo Helpers::e($material['tipo_arquivo']); ?></td>
                            <td><a href="/admin/area-curso/material?material_id=<?php echo (int) $material['id']; ?>">Abrir</a></td>
                            <td>
                                <div class="split-actions">
                                    <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?>&material_id=<?php echo (int) $material['id']; ?>">Editar</a>
                                </div>
                                <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-mt-8">
                                    <input type="hidden" name="tipo" value="material">
                                    <input type="hidden" name="id" value="<?php echo (int) $material['id']; ?>">
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

    <section class="status-card">
        <strong>Links externos</strong>
        <form method="post" action="/admin/area-curso/links" class="form-grid">
            <input type="hidden" name="id" value="<?php echo !empty($linkEditar['id']) ? (int) $linkEditar['id'] : 0; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <label>Módulo
                <select name="modulo_id">
                    <option value="">Sem módulo</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?php echo (int) $modulo['id']; ?>" <?php echo !empty($linkEditar) && (int) ($linkEditar['modulo_id'] ?? 0) === (int) $modulo['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($modulo['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Aula
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
            <label>Título<input type="text" name="titulo" value="<?php echo Helpers::e($linkEditar['titulo'] ?? ''); ?>"></label>
            <label>URL<input type="text" name="url" value="<?php echo Helpers::e($linkEditar['url'] ?? ''); ?>"></label>
            <label>Tipo de link<input type="text" name="tipo_link" value="<?php echo Helpers::e($linkEditar['tipo_link'] ?? 'generico'); ?>"></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) ($linkEditar['ordem'] ?? 1)); ?>" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" <?php echo !empty($linkEditar) ? (!empty($linkEditar['visivel']) ? 'checked' : '') : 'checked'; ?>> Visível</label>
            <button type="submit"><?php echo !empty($linkEditar) ? 'Atualizar link' : 'Salvar link'; ?></button>
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
                                <form method="post" action="/admin/area-curso/excluir" class="form-grid admin-mt-8">
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

    <section class="status-card">
        <strong>Participantes</strong>
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
<?php endif; ?>
</div>

