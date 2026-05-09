<?php use App\Core\Helpers; ?>

<?php
$materialAtual = isset($materialEditar) && is_array($materialEditar) ? $materialEditar : array();
$materialStatus = !empty($materialAtual['status']) ? $materialAtual['status'] : (!empty($materialAtual) ? (!empty($materialAtual['visivel']) ? 'publicado' : 'oculto') : 'publicado');
$materialTipo = !empty($materialAtual['tipo_material']) ? $materialAtual['tipo_material'] : 'arquivo_protegido';
$materialModuloPadrao = !empty($materialAtual['modulo_id'])
    ? (int) $materialAtual['modulo_id']
    : (!empty($aula_selecionada) && !empty($aula_selecionada['modulo_id']) ? (int) $aula_selecionada['modulo_id'] : (!empty($modulo_selecionado) && !empty($modulo_selecionado['id']) ? (int) $modulo_selecionado['id'] : 0));
$materialAulaPadrao = !empty($materialAtual['aula_id'])
    ? (int) $materialAtual['aula_id']
    : (!empty($aula_selecionada) && !empty($aula_selecionada['id']) ? (int) $aula_selecionada['id'] : 0);
$temAulas = false;

foreach ($modulos as $moduloMaterialCheck) {
    if (!empty($moduloMaterialCheck['aulas'])) {
        $temAulas = true;
        break;
    }
}

$tiposMateriais = array(
    'arquivo_protegido' => 'Arquivo protegido',
    'link_externo' => 'Link externo',
    'video_externo' => 'Vídeo externo',
    'embed_controlado' => 'Embed controlado',
);

$quantidadeMateriaisContexto = !empty($materiais) ? count($materiais) : 0;
?>

<section class="status-card admin-area-curso__section admin-area-curso__materials" id="area-curso-materiais">
    <div class="panel-header">
        <div>
            <h2>Materiais</h2>
            <p class="muted">Gestão dos materiais protegidos vinculados às aulas do curso.</p>
        </div>
        <div class="split-actions">
            <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=materiais">Novo material</a>
            <span class="badge"><?php echo (int) $quantidadeMateriaisContexto; ?> itens</span>
        </div>
    </div>

    <?php if (!$temAulas): ?>
        <p class="muted">Cadastre uma aula antes de adicionar materiais.</p>
    <?php else: ?>
        <form method="post" action="/admin/area-curso/materiais" class="form-grid admin-area-curso__form admin-area-curso__materials-form" enctype="multipart/form-data">
            <?php echo $csrfField; ?>
            <input type="hidden" name="id" value="<?php echo !empty($materialAtual['id']) ? (int) $materialAtual['id'] : 0; ?>">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <input type="hidden" name="aba" value="materiais">
            <label class="full">Módulo
                <select name="modulo_id">
                    <option value="">Usar o módulo da aula</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?php echo (int) $modulo['id']; ?>" <?php echo $materialModuloPadrao === (int) $modulo['id'] ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($modulo['titulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full">Aula
                <select name="aula_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <?php foreach ($modulo['aulas'] as $aula): ?>
                            <option value="<?php echo (int) $aula['id']; ?>" <?php echo $materialAulaPadrao === (int) $aula['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($modulo['titulo'] . ' - ' . $aula['titulo']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tipo de material
                <select name="tipo_material" required>
                    <?php foreach ($tiposMateriais as $valorTipo => $rotuloTipo): ?>
                        <option value="<?php echo Helpers::e($valorTipo); ?>" <?php echo $materialTipo === $valorTipo ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($rotuloTipo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Status
                <select name="status" required>
                    <option value="rascunho" <?php echo $materialStatus === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                    <option value="publicado" <?php echo $materialStatus === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                    <option value="oculto" <?php echo $materialStatus === 'oculto' ? 'selected' : ''; ?>>Oculto</option>
                </select>
            </label>
            <label class="full">Título
                <input type="text" name="titulo" value="<?php echo Helpers::e($materialAtual['titulo'] ?? ''); ?>" required>
            </label>
            <label class="full">Descrição
                <textarea name="descricao" rows="3"><?php echo Helpers::e($materialAtual['descricao'] ?? ''); ?></textarea>
            </label>
            <label class="full">URL do material
                <input type="url" name="url" value="<?php echo Helpers::e($materialAtual['url'] ?? ''); ?>" placeholder="Obrigatória para link, vídeo externo e embed controlado">
            </label>
            <label class="full">Arquivo protegido
                <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                <small class="muted">Use apenas para materiais do tipo arquivo protegido. Se não enviar um novo arquivo na edição, o arquivo atual será mantido.</small>
            </label>
            <label>Ordem
                <input type="number" name="ordem" value="<?php echo Helpers::e((string) ($materialAtual['ordem'] ?? 1)); ?>" min="1">
            </label>
            <button type="submit" class="full"><?php echo !empty($materialAtual['id']) ? 'Atualizar material' : 'Salvar material'; ?></button>
        </form>

        <div class="table-wrap admin-mt-12">
            <table class="admin-table admin-table--area-materiais">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Módulo</th>
                        <th>Aula</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ordem</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiais as $material): ?>
                        <?php
                        $tipoMaterialLabel = isset($tiposMateriais[$material['tipo_material'] ?? '']) ? $tiposMateriais[$material['tipo_material']] : (!empty($material['tipo_arquivo']) ? $material['tipo_arquivo'] : 'Arquivo protegido');
                        $materialStatusLinha = !empty($material['status']) ? $material['status'] : (!empty($material['visivel']) ? 'publicado' : 'oculto');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo Helpers::e($material['titulo']); ?></strong>
                                <?php if (!empty($material['descricao'])): ?>
                                    <div class="muted"><?php echo Helpers::e($material['descricao']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helpers::e($material['modulo_titulo'] ?? '—'); ?></td>
                            <td><?php echo Helpers::e($material['aula_titulo'] ?? '—'); ?></td>
                            <td><?php echo Helpers::e($tipoMaterialLabel); ?></td>
                            <td><span class="badge"><?php echo Helpers::e($materialStatusLinha); ?></span></td>
                            <td><?php echo (int) $material['ordem']; ?></td>
                            <td>
                                <div class="split-actions admin-area-curso__material-actions">
                                    <a href="/admin/area-curso/material?material_id=<?php echo (int) $material['id']; ?>">Abrir</a>
                                    <a href="/admin/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&material_id=<?php echo (int) $material['id']; ?>&aba=materiais">Editar</a>
                                    <form method="post" action="/admin/area-curso/materiais" class="admin-area-curso__inline-action">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $material['id']; ?>">
                                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                        <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                        <input type="hidden" name="modulo_id" value="<?php echo (int) ($material['modulo_id'] ?? 0); ?>">
                                        <input type="hidden" name="aula_id" value="<?php echo (int) ($material['aula_id'] ?? 0); ?>">
                                        <input type="hidden" name="tipo_material" value="<?php echo Helpers::e($material['tipo_material'] ?? 'arquivo_protegido'); ?>">
                                        <input type="hidden" name="titulo" value="<?php echo Helpers::e($material['titulo'] ?? ''); ?>">
                                        <input type="hidden" name="descricao" value="<?php echo Helpers::e($material['descricao'] ?? ''); ?>">
                                        <input type="hidden" name="url" value="<?php echo Helpers::e($material['url'] ?? ''); ?>">
                                        <input type="hidden" name="ordem" value="<?php echo (int) ($material['ordem'] ?? 1); ?>">
                                        <input type="hidden" name="status" value="<?php echo $materialStatusLinha === 'publicado' ? 'oculto' : 'publicado'; ?>">
                                        <button type="submit"><?php echo $materialStatusLinha === 'publicado' ? 'Ocultar' : 'Publicar'; ?></button>
                                    </form>
                                    <form method="post" action="/admin/area-curso/excluir" class="admin-area-curso__inline-action">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="tipo" value="material">
                                        <input type="hidden" name="id" value="<?php echo (int) $material['id']; ?>">
                                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                        <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                        <input type="hidden" name="aba" value="materiais">
                                        <input type="text" name="justificativa" placeholder="Justificativa" required>
                                        <button type="submit">Remover</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
