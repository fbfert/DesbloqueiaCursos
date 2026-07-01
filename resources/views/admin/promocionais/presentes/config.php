<?php
$selecionados = isset($selecionados) && is_array($selecionados) ? $selecionados : array();
$selecionadosIds = isset($selecionados_ids) && is_array($selecionados_ids) ? array_map('intval', $selecionados_ids) : array();
$selecionadosLookup = array_fill_keys($selecionadosIds, true);
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$turmas = isset($turmas) && is_array($turmas) ? $turmas : array();
$emailModelo = isset($email_modelo) && is_array($email_modelo) ? $email_modelo : array();
$modelosEmail = isset($modelos_email) && is_array($modelos_email) ? $modelos_email : array();
$old = isset($old) && is_array($old) ? $old : array();
$cursoSelecionado = isset($curso_selecionado) && is_array($curso_selecionado) ? $curso_selecionado : null;
$cursoSelecionadoId = (int) ($curso_evento_id ?? 0);
if ($cursoSelecionadoId <= 0 && !empty($old['curso_evento_id'])) {
    $cursoSelecionadoId = (int) $old['curso_evento_id'];
}
?>
<div class="admin-page">
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__content">
            <div>
                <h1 class="admin-page__title">Configuração da campanha</h1>
                <p class="admin-page__subtitle">Escolha o curso ativo, selecione a turma vinculada e finalize a campanha do presente.</p>
            </div>
            <div class="admin-page__actions">
                <a class="button-link" href="/admin/promocionais/presentes/criar">Voltar para a seleção</a>
            </div>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <div class="admin-page__content">
            <div>
                <strong>Escolha o curso ativo</strong>
                <p class="muted">Ao selecionar um curso, a lista de turmas será carregada automaticamente.</p>
            </div>
        </div>

        <form method="get" action="/admin/promocionais/presentes/configurar" class="admin-form-grid" id="presentes-curso-form" style="margin-top: 16px;">
            <label class="admin-form-grid__full">
                <span>Curso ativo</span>
                <select name="curso_evento_id" id="presentes-curso-select" required>
                    <option value="">Selecione um curso ativo</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) $cursoSelecionadoId === (int) $curso['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($curso['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="hidden" name="turma_id" value="<?php echo (int) ($turma_id ?? 0); ?>">
            <input type="hidden" name="confirmar_contexto" value="0">
            <noscript>
                <div class="admin-page__actions">
                    <button type="submit" class="button-link button-link--primary">Carregar turmas</button>
                </div>
            </noscript>
        </form>

        <?php if ($cursoSelecionado): ?>
            <div class="admin-warning-box" style="margin-top: 16px;">
                Curso selecionado: <strong><?php echo htmlspecialchars($cursoSelecionado['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($cursoSelecionado): ?>
        <section class="status-card">
            <div class="admin-page__content">
                <div>
                    <strong>Confirme o curso e a turma</strong>
                    <p class="muted">Selecione a turma vinculada ao curso e confirme para liberar a configuração da campanha.</p>
                </div>
            </div>

            <form method="get" action="/admin/promocionais/presentes/configurar" class="admin-form-grid" style="margin-top: 16px;">
                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoSelecionadoId; ?>">
                <label class="admin-form-grid__full">
                    <span>Turma do curso selecionado</span>
                    <select name="turma_id" required>
                        <option value="">Selecione uma turma</option>
                        <?php foreach ($turmas as $turma): ?>
                            <option value="<?php echo (int) $turma['id']; ?>" <?php echo (int) ($turma_selecionada['id'] ?? 0) === (int) $turma['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($turma['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <input type="hidden" name="confirmar_contexto" value="1">
                <div class="admin-page__actions">
                    <button type="submit" class="button-link button-link--primary">Confirmar curso e turma</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="status-card">
        <div class="admin-page__content">
            <div>
                <strong>Configuração da campanha</strong>
                <p class="muted">A configuração só aparece depois da confirmação do curso e da turma.</p>
            </div>
        </div>

        <?php if (empty($contexto_confirmado)): ?>
            <div class="admin-warning-box" style="margin-top: 16px;">
                Selecione e confirme o curso e a turma para liberar a configuração da campanha.
            </div>
        <?php else: ?>
            <div class="admin-warning-box" style="margin-top: 16px;">
                Curso confirmado: <strong><?php echo htmlspecialchars($cursoSelecionado['nome'], ENT_QUOTES, 'UTF-8'); ?></strong> —
                Turma confirmada: <strong><?php echo htmlspecialchars($turma_selecionada['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>

            <details class="admin-presentes-selected" style="margin-top: 16px;">
                <summary>
                    Usuários selecionados (<?php echo (int) count($selecionados); ?>)
                </summary>
                <div class="admin-presentes-selected__body">
                    <?php if (!empty($selecionados)): ?>
                        <div class="table-wrap" style="margin-top: 16px;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Cidade</th>
                                        <th>Perfil</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($selecionados as $usuario): ?>
                                        <tr>
                                            <td><?php echo (int) $usuario['id']; ?></td>
                                            <td><?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['cidade'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['perfis'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <form method="post" action="/admin/promocionais/presentes/selecionado-remover" style="margin: 0;">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="usuario_id" value="<?php echo (int) $usuario['id']; ?>">
                                                    <button type="submit" class="button-link button-link--small button-link--ghost">Remover</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="admin-warning-box" style="margin-top: 16px;">
                            Nenhum usuário selecionado.
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <form method="post" action="/admin/promocionais/presentes/preview" id="presentes-preview-form">
                <?php echo $csrfField; ?>
                <?php foreach ($selecionadosIds as $selecionadoId): ?>
                    <input type="hidden" name="usuarios_selecionados[]" value="<?php echo (int) $selecionadoId; ?>">
                <?php endforeach; ?>
                <input type="hidden" name="selecionar_todos_filtrados" value="0">
                <input type="hidden" name="filtros_usuarios[nome]" value="<?php echo htmlspecialchars($old['filtros_usuarios']['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="filtros_usuarios[cidade]" value="<?php echo htmlspecialchars($old['filtros_usuarios']['cidade'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="filtros_usuarios[data_inicio]" value="<?php echo htmlspecialchars($old['filtros_usuarios']['data_inicio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="filtros_usuarios[data_fim]" value="<?php echo htmlspecialchars($old['filtros_usuarios']['data_fim'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="filtros_usuarios[perfil]" value="<?php echo htmlspecialchars($old['filtros_usuarios']['perfil'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="filtros_usuarios[curso_evento_id]" value="<?php echo (int) ($old['filtros_usuarios']['curso_evento_id'] ?? $cursoSelecionadoId); ?>">
                <input type="hidden" name="filtros_usuarios[compras_tipo]" value="<?php echo htmlspecialchars($old['filtros_usuarios']['compras_tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoSelecionadoId; ?>">
                <input type="hidden" name="turma_id" value="<?php echo (int) ($turma_selecionada['id'] ?? 0); ?>">
                <input type="hidden" name="contexto_confirmado" value="1">

                <div class="admin-form-grid" style="margin-top: 16px;">
                    <label>
                        <span>Título da campanha</span>
                        <input type="text" name="titulo" value="<?php echo htmlspecialchars($old['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label>
                        <span>Prazo de acesso</span>
                        <select name="acesso_tipo" id="acesso_tipo">
                            <option value="sem_prazo" <?php echo ($old['acesso_tipo'] ?? 'sem_prazo') === 'sem_prazo' ? 'selected' : ''; ?>>Sem prazo definido</option>
                            <option value="dias" <?php echo ($old['acesso_tipo'] ?? '') === 'dias' ? 'selected' : ''; ?>>Por dias</option>
                            <option value="data" <?php echo ($old['acesso_tipo'] ?? '') === 'data' ? 'selected' : ''; ?>>Até uma data</option>
                        </select>
                    </label>
                    <label>
                        <span>Dias de acesso</span>
                        <input type="number" min="1" name="acesso_dias" value="<?php echo htmlspecialchars((string) ($old['acesso_dias'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>
                        <span>Data de expiração</span>
                        <input type="datetime-local" name="acesso_expira_em" value="<?php echo htmlspecialchars(str_replace(' ', 'T', substr((string) ($old['acesso_expira_em'] ?? ''), 0, 16)), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>
                        <span>Modelo de e-mail</span>
                        <select name="email_modelo_evento">
                            <option value="email.presente_concedido">Curso recebido como presente</option>
                            <?php foreach ($modelosEmail as $modelo): ?>
                                <option value="<?php echo htmlspecialchars($modelo['evento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($old['email_modelo_evento'] ?? '') === ($modelo['evento'] ?? '') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($modelo['nome'] ?? $modelo['evento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="admin-form-grid__full">
                        <span>Assunto do e-mail</span>
                        <input type="text" name="email_assunto" value="<?php echo htmlspecialchars($old['email_assunto'] ?? ($emailModelo['assunto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label class="admin-form-grid__full">
                        <span>Mensagem do e-mail</span>
                        <textarea name="email_corpo" rows="8" required><?php echo htmlspecialchars($old['email_corpo'] ?? ($emailModelo['corpo_html'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>
                    <label class="admin-form-grid__full">
                        <span>Justificativa</span>
                        <textarea name="justificativa" rows="4" required><?php echo htmlspecialchars($old['justificativa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>
                </div>

                <div class="admin-warning-box" style="margin-top: 16px;">
                    Esta ação criará pedidos aprovados de valor R$ 0,00, sem cobrança, sem comprovante PIX e sem geração de financeiro, rateios ou comissões.
                </div>

                <div class="admin-page__actions" style="margin-top: 16px;">
                    <button type="submit" class="button-link button-link--primary">Pré-visualizar concessão</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>

<script>
(function () {
    var cursoSelect = document.getElementById('presentes-curso-select');
    var cursoForm = document.getElementById('presentes-curso-form');

    if (cursoSelect && cursoForm) {
        cursoSelect.addEventListener('change', function () {
            cursoForm.submit();
        });
    }
})();
</script>
