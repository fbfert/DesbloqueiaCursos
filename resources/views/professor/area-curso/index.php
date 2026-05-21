<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Área do professor</h1>
    <p>Somente cursos e turmas atribuídos ao professor.</p>
</section>

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

<form method="get" action="/professor/area-curso" class="form-grid">
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
    <label>
        Turma
        <select name="turma_id">
            <option value="">Curso inteiro</option>
            <?php foreach ($turmas as $item): ?>
                <option value="<?php echo (int) $item['id']; ?>" <?php echo !empty($turma) && (int) $turma['id'] === (int) $item['id'] ? 'selected' : ''; ?>>
                    <?php echo Helpers::e($item['curso_nome'] . ' - ' . $item['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="cta-group">
        <button type="submit" class="button-link button-link--primary">Abrir contexto</button>
        <a class="button-link button-link--ghost" href="/professor/area-curso">Cancelar</a>
    </div>
</form>

<?php if (!empty($curso)): ?>
    <?php $areaCursoCancelUrl = '/professor/area-curso?curso_id=' . (int) $curso['id'] . (!empty($turma['id']) ? '&turma_id=' . (int) $turma['id'] : ''); ?>
    <?php $selectedTab = isset($selected_tab) && $selected_tab !== '' ? (string) $selected_tab : 'conteudo'; ?>

    <nav class="area-curso-tabs" style="margin-top:12px;">
        <a class="area-curso-tabs__link<?php echo $selectedTab === 'conteudo' ? ' is-active' : ''; ?>" href="/professor/area-curso?curso_id=<?php echo (int) $curso['id']; ?><?php echo !empty($turma) ? '&turma_id=' . (int) $turma['id'] : ''; ?>&aba=conteudo">Conteúdo</a>
    </nav>

    <?php if ($selectedTab === 'conteudo'): ?>
        <?php $areaCursoBaseUrl = '/professor/area-curso'; require BASE_PATH . '/resources/views/professor/area-curso/_conteudo.php'; ?>
    <?php else: ?>
    <section class="panel">
        <div class="panel-header"><div><h2>Instruções</h2></div></div>
        <form method="post" action="/professor/area-curso/instrucoes" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Titulo<input type="text" name="titulo"></label>
            <label>Conteudo<textarea name="conteudo" rows="4"></textarea></label>
            <label>Ordem<input type="number" name="ordem" value="1" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" checked> Visivel</label>
            <?php
            $cancel_url = $areaCursoCancelUrl;
            $show_save_as_copy = false;
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Modulos</h2></div></div>
        <form method="post" action="/professor/area-curso/modulos" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Titulo<input type="text" name="titulo"></label>
            <label>Descrição<textarea name="descricao" rows="3"></textarea></label>
            <label>Ordem<input type="number" name="ordem" value="1" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" checked> Visivel</label>
            <?php
            $cancel_url = $areaCursoCancelUrl;
            $show_save_as_copy = false;
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
        <div class="table-wrapper" style="margin-top:12px;">
            <table class="table">
                <thead><tr><th>Titulo</th><th>Status</th><th>Delete</th></tr></thead>
                <tbody>
                    <?php foreach ($modulos as $modulo): ?>
                        <tr>
                            <td><?php echo Helpers::e($modulo['titulo']); ?></td>
                            <td><?php echo !empty($modulo['visivel']) ? 'visivel' : 'oculto'; ?></td>
                            <td>
                                <form method="post" action="/professor/area-curso/excluir" class="form-grid">
                                    <input type="hidden" name="tipo" value="modulo">
                                    <input type="hidden" name="id" value="<?php echo (int) $modulo['id']; ?>">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                    <input type="text" name="justificativa" placeholder="Justificativa">
                                    <button type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Aulas</h2></div></div>
        <form method="post" action="/professor/area-curso/aulas" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Modulo
                <select name="modulo_id">
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?php echo (int) $modulo['id']; ?>"><?php echo Helpers::e($modulo['titulo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Titulo<input type="text" name="titulo"></label>
            <label>Conteudo<textarea name="conteudo" rows="3"></textarea></label>
            <label>Tipo<input type="text" name="tipo" value="texto"></label>
            <label>URL video<input type="text" name="url_video"></label>
            <label>Duracao minutos<input type="number" name="duracao_minutos"></label>
            <label>Ordem<input type="number" name="ordem" value="1" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" checked> Visivel</label>
            <label class="checkbox"><input type="checkbox" name="obrigatoria" value="1"> Obrigatoria</label>
            <?php
            $cancel_url = $areaCursoCancelUrl;
            $show_save_as_copy = false;
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
        <div class="table-wrapper" style="margin-top:12px;">
            <table class="table">
                <thead><tr><th>Titulo</th><th>Tipo</th><th>Delete</th></tr></thead>
                <tbody>
                    <?php foreach ($modulos as $modulo): ?>
                        <?php foreach ($modulo['aulas'] as $aula): ?>
                            <tr>
                                <td><?php echo Helpers::e($aula['titulo']); ?></td>
                                <td><?php echo Helpers::e($aula['tipo']); ?></td>
                                <td>
                                    <form method="post" action="/professor/area-curso/excluir" class="form-grid">
                                        <input type="hidden" name="tipo" value="aula">
                                        <input type="hidden" name="id" value="<?php echo (int) $aula['id']; ?>">
                                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                        <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                        <input type="text" name="justificativa" placeholder="Justificativa">
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

    <section class="panel">
        <div class="panel-header"><div><h2>Materiais</h2></div></div>
        <form method="post" action="/professor/area-curso/materiais" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Modulo
                <select name="modulo_id">
                    <option value="">Sem modulo</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?php echo (int) $modulo['id']; ?>"><?php echo Helpers::e($modulo['titulo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Aula
                <select name="aula_id">
                    <option value="">Sem aula</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <?php foreach ($modulo['aulas'] as $aula): ?>
                            <option value="<?php echo (int) $aula['id']; ?>"><?php echo Helpers::e($modulo['titulo'] . ' - ' . $aula['titulo']); ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Titulo<input type="text" name="titulo"></label>
            <label>Descrição<textarea name="descricao" rows="3"></textarea></label>
            <label>Tipo arquivo<input type="text" name="tipo_arquivo" value="outro"></label>
            <label>Arquivo<input type="file" name="arquivo"></label>
            <label>Ordem<input type="number" name="ordem" value="1" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" checked> Visivel</label>
            <?php
            $cancel_url = $areaCursoCancelUrl;
            $show_save_as_copy = false;
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
        <div class="table-wrapper" style="margin-top:12px;">
            <table class="table">
                <thead><tr><th>Titulo</th><th>Tipo</th><th>Delete</th></tr></thead>
                <tbody>
                    <?php foreach ($materiais as $material): ?>
                        <tr>
                            <td><?php echo Helpers::e($material['titulo']); ?></td>
                            <td><?php echo Helpers::e($material['tipo_arquivo']); ?></td>
                            <td>
                                <form method="post" action="/professor/area-curso/excluir" class="form-grid">
                                    <input type="hidden" name="tipo" value="material">
                                    <input type="hidden" name="id" value="<?php echo (int) $material['id']; ?>">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                    <input type="text" name="justificativa" placeholder="Justificativa">
                                    <button type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Links externos</h2></div></div>
        <form method="post" action="/professor/area-curso/links" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Modulo
                <select name="modulo_id">
                    <option value="">Sem modulo</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <option value="<?php echo (int) $modulo['id']; ?>"><?php echo Helpers::e($modulo['titulo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Aula
                <select name="aula_id">
                    <option value="">Sem aula</option>
                    <?php foreach ($modulos as $modulo): ?>
                        <?php foreach ($modulo['aulas'] as $aula): ?>
                            <option value="<?php echo (int) $aula['id']; ?>"><?php echo Helpers::e($modulo['titulo'] . ' - ' . $aula['titulo']); ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Titulo<input type="text" name="titulo"></label>
            <label>URL<input type="text" name="url"></label>
            <label>Tipo link<input type="text" name="tipo_link" value="generico"></label>
            <label>Ordem<input type="number" name="ordem" value="1" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" checked> Visivel</label>
            <?php
            $cancel_url = $areaCursoCancelUrl;
            $show_save_as_copy = false;
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
        <div class="table-wrapper" style="margin-top:12px;">
            <table class="table">
                <thead><tr><th>Titulo</th><th>Tipo</th><th>Delete</th></tr></thead>
                <tbody>
                    <?php foreach ($links as $link): ?>
                        <tr>
                            <td><?php echo Helpers::e($link['titulo']); ?></td>
                            <td><?php echo Helpers::e($link['tipo_link']); ?></td>
                            <td>
                                <form method="post" action="/professor/area-curso/excluir" class="form-grid">
                                    <input type="hidden" name="tipo" value="link">
                                    <input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                    <input type="text" name="justificativa" placeholder="Justificativa">
                                    <button type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php $areaCursoBaseUrl = '/professor/area-curso'; require BASE_PATH . '/resources/views/admin/area-curso/_atividades.php'; ?>

    <section class="panel">
        <div class="panel-header">
            <div><h2>Avaliações / Notas — Notas do Conteúdo</h2></div>
            <div class="admin-area-curso__actions">
                <a href="<?php echo Helpers::e('/professor/area-curso/conteudo/avaliacoes/exportar?' . http_build_query(array('curso_id' => (int) ($curso['id'] ?? 0), 'turma_id' => (int) ($turma['id'] ?? 0), 'curso_nome' => (string) ($curso['nome'] ?? ''), 'turma_nome' => (string) ($turma['nome'] ?? '')) + (isset($conteudo_avaliacoes_filtros) && is_array($conteudo_avaliacoes_filtros) ? $conteudo_avaliacoes_filtros : array()))); ?>">Exportar CSV do conteúdo</a>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Aluno</th><th>Módulo</th><th>Avaliação</th><th>Status</th><th>Tentativa</th><th>Nota</th><th>Peso</th><th>Enviado</th><th>Corrigido</th></tr></thead>
                <tbody>
                <?php $notasConteudo = isset($conteudo_avaliacoes_notas) && is_array($conteudo_avaliacoes_notas) ? $conteudo_avaliacoes_notas : array(); ?>
                <?php if (empty($notasConteudo)): ?>
                    <tr><td colspan="9" class="muted">Nenhum registro de avaliação textual no conteúdo.</td></tr>
                <?php else: foreach ($notasConteudo as $item): ?>
                    <tr>
                        <td><?php echo Helpers::e((string) ($item['aluno_nome'] ?? '')); ?></td>
                        <td><?php echo Helpers::e((string) ($item['modulo_titulo'] ?? '')); ?></td>
                        <td><?php echo Helpers::e((string) ($item['avaliacao_titulo'] ?? '')); ?></td>
                        <td><?php echo Helpers::e((string) ($item['status'] ?? '')); ?></td>
                        <td><?php echo (int) ($item['tentativa'] ?? 0); ?></td>
                        <td><?php echo $item['nota'] !== null ? Helpers::e(number_format((float) $item['nota'], 2, ',', '.')) : '—'; ?></td>
                        <td><?php echo Helpers::e(number_format((float) ($item['peso'] ?? 1), 2, ',', '.')); ?></td>
                        <td><?php echo !empty($item['enviado_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $item['enviado_em']))) : '—'; ?></td>
                        <td><?php echo !empty($item['corrigido_em']) ? Helpers::e(date('d/m/Y H:i', strtotime((string) $item['corrigido_em']))) : '—'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php $areaCursoBaseUrl = '/professor/area-curso'; require BASE_PATH . '/resources/views/admin/area-curso/_relatorios.php'; ?>

    <section class="panel">
        <div class="panel-header"><div><h2>Participantes</h2></div></div>
        <div class="table-wrapper">
            <table class="table">
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
<?php endif; ?>

