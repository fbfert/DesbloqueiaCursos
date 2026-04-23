<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Area academica do professor</h1>
    <p>Escopo limitado aos cursos e turmas atribuídos.</p>
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

<form method="get" action="/professor/academico" class="form-grid">
    <label>Curso
        <select name="curso_id">
            <option value="">Selecione</option>
            <?php foreach ($cursos as $item): ?>
                <option value="<?php echo (int) $item['id']; ?>" <?php echo !empty($curso) && (int) $curso['id'] === (int) $item['id'] ? 'selected' : ''; ?>>
                    <?php echo Helpers::e($item['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Turma
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
    <?php $configContext = !empty($turma) ? $turma : $curso; ?>
    <section class="panel">
        <div class="panel-header"><div><h2>Configuracao</h2></div></div>
        <form method="post" action="/professor/academico/configuracao" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label class="checkbox"><input type="checkbox" name="exige_presenca" value="1" <?php echo !empty($configContext['exige_presenca']) ? 'checked' : ''; ?>> Exigir presenca</label>
            <label>Min. presenca<input type="number" step="0.01" name="percentual_minimo_presenca" value="<?php echo Helpers::e(isset($configContext['percentual_minimo_presenca']) ? $configContext['percentual_minimo_presenca'] : '75.00'); ?>"></label>
            <label>Min. conclusao<input type="number" step="0.01" name="percentual_minimo_conclusao" value="<?php echo Helpers::e(isset($configContext['percentual_minimo_conclusao']) ? $configContext['percentual_minimo_conclusao'] : '75.00'); ?>"></label>
            <label class="checkbox"><input type="checkbox" name="exige_avaliacao" value="1" <?php echo !empty($configContext['exige_avaliacao']) ? 'checked' : ''; ?>> Exigir avaliacao</label>
            <label>Nota minima<input type="number" step="0.01" name="nota_minima" value="<?php echo Helpers::e(isset($configContext['nota_minima']) ? $configContext['nota_minima'] : '70.00'); ?>"></label>
            <label>Base do progresso
                <select name="progresso_base">
                    <option value="aulas" <?php echo empty($configContext['progresso_base']) || $configContext['progresso_base'] === 'aulas' ? 'selected' : ''; ?>>Aulas</option>
                    <option value="modulos" <?php echo !empty($configContext['progresso_base']) && $configContext['progresso_base'] === 'modulos' ? 'selected' : ''; ?>>Modulos</option>
                </select>
            </label>
            <button type="submit">Salvar configuracao</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Participantes</h2></div></div>
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Nome</th><th>Progresso</th><th>Presenca</th><th>Nota</th><th>Apto</th><th>Acoes</th></tr></thead>
                <tbody>
                    <?php foreach ($inscricoes as $inscricao): ?>
                        <tr>
                            <td><?php echo Helpers::e($inscricao['participante_nome']); ?></td>
                            <td><?php echo Helpers::e(isset($inscricao['percentual_progresso']) ? $inscricao['percentual_progresso'] . '%' : '-'); ?></td>
                            <td><?php echo Helpers::e(isset($inscricao['presenca_percentual']) ? $inscricao['presenca_percentual'] . '%' : '-'); ?></td>
                            <td><?php echo Helpers::e(isset($inscricao['nota_final']) ? $inscricao['nota_final'] : '-'); ?></td>
                            <td><?php echo !empty($inscricao['apto_certificado']) ? 'sim' : 'nao'; ?></td>
                            <td>
                                <form method="post" action="/professor/academico/recalcular" class="form-grid">
                                    <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
                                    <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricao['id']; ?>">
                                    <button type="submit">Recalcular</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Presencas</h2></div></div>
        <form method="post" action="/professor/academico/presenca" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Inscricao
                <select name="inscricao_id">
                    <?php foreach ($inscricoes as $inscricao): ?>
                        <option value="<?php echo (int) $inscricao['id']; ?>"><?php echo Helpers::e($inscricao['participante_nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Data<input type="date" name="data_presenca" value="<?php echo date('Y-m-d'); ?>"></label>
            <label>Status
                <select name="status">
                    <option value="presente">Presente</option>
                    <option value="ausente">Ausente</option>
                    <option value="justificada">Justificada</option>
                </select>
            </label>
            <label>Observacao<textarea name="observacao" rows="2"></textarea></label>
            <button type="submit">Salvar presenca</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Avaliacoes</h2></div></div>
        <form method="post" action="/professor/academico/avaliacao" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Titulo<input type="text" name="titulo"></label>
            <label>Descricao<textarea name="descricao" rows="2"></textarea></label>
            <label>Percentual minimo<input type="number" step="0.01" name="percentual_minimo" value="0"></label>
            <label>Nota minima<input type="number" step="0.01" name="nota_minima" value="70.00"></label>
            <label>Ordem<input type="number" name="ordem" value="1"></label>
            <label class="checkbox"><input type="checkbox" name="visivel" value="1" checked> Visivel</label>
            <label class="checkbox"><input type="checkbox" name="obrigatoria" value="1"> Obrigatoria</label>
            <button type="submit">Salvar avaliacao</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Nota manual</h2></div></div>
        <form method="post" action="/professor/academico/nota" class="form-grid">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($turma['id']) ? (int) $turma['id'] : ''; ?>">
            <label>Inscricao
                <select name="inscricao_id">
                    <?php foreach ($inscricoes as $inscricao): ?>
                        <option value="<?php echo (int) $inscricao['id']; ?>"><?php echo Helpers::e($inscricao['participante_nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Avaliacao
                <select name="avaliacao_id">
                    <?php foreach ($avaliacoes as $avaliacao): ?>
                        <option value="<?php echo (int) $avaliacao['id']; ?>"><?php echo Helpers::e($avaliacao['titulo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nota<input type="number" step="0.01" name="nota" value="0"></label>
            <label>Percentual<input type="number" step="0.01" name="percentual" value="0"></label>
            <label>Status
                <select name="status">
                    <option value="corrigida">Corrigida</option>
                    <option value="aprovada">Aprovada</option>
                    <option value="reprovada">Reprovada</option>
                </select>
            </label>
            <label>Observacao<textarea name="observacao" rows="2"></textarea></label>
            <button type="submit">Salvar nota</button>
        </form>
    </section>
<?php endif; ?>
