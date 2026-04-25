<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Area do professor</h1>
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
    <button type="submit">Abrir contexto</button>
</form>

<?php if (!empty($curso)): ?>
    <section class="panel">
        <div class="panel-header"><div><h2>Instruções</h2></div></div>
        <form method="post" action="/professor/area-curso/instrucoes" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Titulo<input type="text" name="titulo"></label>
            <label>Conteudo<textarea name="conteudo" rows="4"></textarea></label>
            <label>Ordem<input type="number" name="ordem" value="1" min="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" checked> Visivel</label>
            <button type="submit">Salvar instrucao</button>
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
            <button type="submit">Salvar modulo</button>
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
            <button type="submit">Salvar aula</button>
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
            <button type="submit">Salvar material</button>
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
            <button type="submit">Salvar link</button>
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

