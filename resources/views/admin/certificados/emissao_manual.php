<?php use App\Core\Helpers; ?>

<?php
$filtros = isset($filtros) && is_array($filtros) ? $filtros : array();
$resultado = isset($resultado_emissao_manual) && is_array($resultado_emissao_manual) ? $resultado_emissao_manual : null;
$resumo = !empty($resultado['resumo']) && is_array($resultado['resumo']) ? $resultado['resumo'] : array();
$configCertificados = isset($configCertificados) && is_array($configCertificados) ? $configCertificados : array();
$cursosLista = isset($cursos) && is_array($cursos) ? $cursos : array();
$turmasLista = isset($turmas) && is_array($turmas) ? $turmas : array();
$templatesLista = isset($templates) && is_array($templates) ? $templates : array();
$candidatosLista = isset($candidatos) && is_array($candidatos) ? $candidatos : array();

$filtroCurso = (int) ($filtros['curso_evento_id'] ?? 0);
$filtroTurma = (int) ($filtros['turma_id'] ?? 0);
$filtroBusca = (string) ($filtros['busca'] ?? '');
$filtroAptidao = (string) ($filtros['aptidao'] ?? 'todos');
$filtroCertificado = (string) ($filtros['certificado'] ?? 'todos');
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Emissão Manual de Certificados</h1>
            <p class="admin-page__subtitle">A emissão com pendências deve ser usada apenas por decisão administrativa justificada.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/certificados">Voltar para certificados</a>
        </div>
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

<?php if (empty($configCertificados['certificados_habilitado']) || empty($configCertificados['certificados_emissao_habilitada'])): ?>
    <section class="status-card" style="margin-bottom:12px;border-left:4px solid #b45309;background:#fff7ed;">
        <strong>Emissão desativada</strong>
        <p class="muted" style="margin:8px 0 0 0;">A emissão de certificados está desativada nas configurações globais.</p>
    </section>
<?php endif; ?>

    <?php if (!empty($resumo)): ?>
        <section class="status-card" style="margin-bottom:12px;">
            <strong>Resumo da última emissão</strong>
            <div class="pill-row admin-mt-12">
                <span class="pill">Selecionados: <?php echo (int) ($resumo['selecionados'] ?? 0); ?></span>
                <span class="pill">Emitidos: <?php echo (int) ($resumo['emitidos'] ?? 0); ?></span>
                <span class="pill">Por exceção: <?php echo (int) ($resumo['emitidos_excecao'] ?? 0); ?></span>
                <span class="pill">Já emitidos: <?php echo (int) ($resumo['ja_emitidos'] ?? 0); ?></span>
                <span class="pill">Bloqueados: <?php echo (int) ($resumo['bloqueados'] ?? 0); ?></span>
                <span class="pill">Erros: <?php echo (int) ($resumo['erros'] ?? 0); ?></span>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($resultado['resultados']) && is_array($resultado['resultados'])): ?>
        <section class="panel" style="margin-bottom:12px;">
            <div class="panel-header">
                <div>
                    <h2>Detalhe do processamento</h2>
                    <p>Resultado por inscrição selecionada.</p>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Inscrição</th>
                            <th>Status</th>
                            <th>Mensagem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultado['resultados'] as $item): ?>
                            <tr>
                                <td><?php echo (int) ($item['inscricao_id'] ?? 0); ?></td>
                                <td><?php echo Helpers::e((string) ($item['status'] ?? '')); ?></td>
                                <td><?php echo Helpers::e((string) ($item['message'] ?? ($item['ok'] ? 'Processado com sucesso.' : 'Sem mensagem.'))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="status-card" style="margin-bottom:12px;">
        <strong>Atenção</strong>
        <p class="muted" style="margin:8px 0 0 0;">
            Use a exceção administrativa somente quando houver decisão formal, justificativa registrada e autorização para emissão com pendências.
        </p>
    </section>

    <section class="panel" style="margin-bottom:12px;">
        <div class="panel-header">
            <div>
                <h2>Filtros</h2>
                <p>Localize inscrições por curso, turma ou participante.</p>
            </div>
        </div>

        <form method="get" action="/admin/certificados/emissao-manual" class="form-grid">
            <label>
                Curso
                <select name="curso_evento_id">
                    <option value="0">Todos</option>
                    <?php foreach ($cursosLista as $curso): ?>
                        <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) $curso['id'] === $filtroCurso ? 'selected' : ''; ?>>
                            <?php echo Helpers::e($curso['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Turma
                <select name="turma_id">
                    <option value="0">Todas</option>
                    <?php foreach ($turmasLista as $turma): ?>
                        <option value="<?php echo (int) $turma['id']; ?>" <?php echo (int) $turma['id'] === $filtroTurma ? 'selected' : ''; ?>>
                            <?php echo Helpers::e((!empty($turma['curso_nome']) ? $turma['curso_nome'] . ' - ' : '') . $turma['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Nome, e-mail ou CPF
                <input type="text" name="busca" value="<?php echo Helpers::e($filtroBusca); ?>" placeholder="Buscar aluno ou participante">
            </label>

            <label>
                Aptidão
                <select name="aptidao">
                    <option value="todos" <?php echo $filtroAptidao === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="aptos" <?php echo $filtroAptidao === 'aptos' ? 'selected' : ''; ?>>Aptos</option>
                    <option value="pendentes" <?php echo $filtroAptidao === 'pendentes' ? 'selected' : ''; ?>>Pendentes</option>
                </select>
            </label>

            <label>
                Certificado
                <select name="certificado">
                    <option value="todos" <?php echo $filtroCertificado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="sem_certificado" <?php echo $filtroCertificado === 'sem_certificado' ? 'selected' : ''; ?>>Sem certificado</option>
                    <option value="com_certificado" <?php echo $filtroCertificado === 'com_certificado' ? 'selected' : ''; ?>>Com certificado</option>
                </select>
            </label>

            <div class="full cta-group">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/certificados/emissao-manual">Limpar filtros</a>
            </div>
        </form>
    </section>

    <form method="post" action="/admin/certificados/emissao-manual/emitir" id="form-emissao-manual">
        <?php echo $csrfField; ?>
        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $filtroCurso; ?>">
        <input type="hidden" name="turma_id" value="<?php echo (int) $filtroTurma; ?>">
        <input type="hidden" name="busca" value="<?php echo Helpers::e($filtroBusca); ?>">
        <input type="hidden" name="aptidao" value="<?php echo Helpers::e($filtroAptidao); ?>">
        <input type="hidden" name="certificado" value="<?php echo Helpers::e($filtroCertificado); ?>">

        <section class="panel" style="margin-bottom:12px;">
            <div class="panel-header">
                <div>
                    <h2>Emissão em lote</h2>
                    <p>Selecione uma ou mais inscrições e defina a regra da emissão.</p>
                </div>
            </div>

            <div class="form-grid">
                <label>
                    Template
                    <select name="template_id">
                        <?php foreach ($templatesLista as $template): ?>
                            <option value="<?php echo (int) $template['id']; ?>" <?php echo !empty($template['padrao']) ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($template['nome']); ?><?php echo !empty($template['padrao']) ? ' - padrão' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="checkbox" style="align-self:end;">
                    <input type="checkbox" name="permitir_excecao" value="1" id="permitir-excecao">
                    Permitir emissão com pendências por exceção administrativa
                </label>

                <label style="grid-column:1/-1;">
                    Justificativa administrativa obrigatória
                    <textarea name="justificativa_excecao" id="justificativa-excecao" rows="4" placeholder="Informe a decisão administrativa e o motivo da exceção"></textarea>
                </label>

                <div class="full cta-group">
                    <button type="submit" class="button-link button-link--primary" <?php echo (empty($configCertificados['certificados_habilitado']) || empty($configCertificados['certificados_emissao_habilitada'])) ? 'disabled' : ''; ?>>Emitir certificados selecionados</button>
                    <a class="button-link button-link--ghost" href="/admin/certificados">Cancelar</a>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Candidatos</h2>
                    <p>Exibindo até 100 inscrições conforme os filtros aplicados.</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="table table--certificados-lista">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selecionar-todos"></th>
                            <th>Aluno / Participante</th>
                            <th>Curso</th>
                            <th>Turma</th>
                            <th>Status</th>
                            <th>Progresso</th>
                            <th>Presença</th>
                            <th>Nota</th>
                            <th>Apto?</th>
                            <th>Certificado</th>
                            <th>Situação / motivos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($candidatosLista)): ?>
                            <tr>
                                <td colspan="11">Nenhuma inscrição encontrada com os filtros aplicados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($candidatosLista as $candidato): ?>
                                <?php
                                $alunoNome = !empty($candidato['aluno_nome']) ? $candidato['aluno_nome'] : (!empty($candidato['participante_nome']) ? $candidato['participante_nome'] : 'Aluno não identificado');
                                $alunoEmail = !empty($candidato['aluno_email']) ? $candidato['aluno_email'] : '';
                                $alunoCpf = !empty($candidato['participante_cpf']) ? $candidato['participante_cpf'] : '';
                                $certificadoLabel = 'Sem certificado';
                                if (!empty($candidato['certificado_id'])) {
                                    $certificadoLabel = !empty($candidato['certificado_codigo']) ? $candidato['certificado_codigo'] : ('Certificado #' . (int) $candidato['certificado_id']);
                                }
                                $situacaoElegibilidade = !empty($candidato['situacao_elegibilidade']) ? $candidato['situacao_elegibilidade'] : 'não calculada';
                                $motivosPendencias = trim((string) ($candidato['motivos_pendencias_texto'] ?? ''));
                                $modalId = 'pendencias-modal-content-' . (int) $candidato['id'];
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="inscricao_ids[]" value="<?php echo (int) $candidato['id']; ?>"></td>
                                    <td>
                                        <strong><?php echo Helpers::e($alunoNome); ?></strong><br>
                                        <?php if ($alunoEmail !== ''): ?><small><?php echo Helpers::e($alunoEmail); ?></small><br><?php endif; ?>
                                        <?php if ($alunoCpf !== ''): ?><small><?php echo Helpers::e($alunoCpf); ?></small><?php endif; ?>
                                    </td>
                                    <td><?php echo Helpers::e($candidato['curso_nome']); ?></td>
                                    <td><?php echo Helpers::e(!empty($candidato['turma_nome']) ? $candidato['turma_nome'] : 'Sem turma'); ?></td>
                                    <td><?php echo Helpers::e($candidato['status']); ?></td>
                                    <td><?php echo Helpers::e(isset($candidato['percentual_progresso']) ? $candidato['percentual_progresso'] : '0'); ?>%</td>
                                    <td><?php echo Helpers::e(isset($candidato['presenca_percentual']) ? $candidato['presenca_percentual'] : '0'); ?>%</td>
                                    <td><?php echo Helpers::e(isset($candidato['nota_final']) ? $candidato['nota_final'] : '0'); ?></td>
                                    <td><span class="pill"><?php echo !empty($candidato['apto_certificado']) ? 'Sim' : 'Não'; ?></span></td>
                                    <td><?php echo Helpers::e($certificadoLabel); ?></td>
                                    <td>
                                        <strong><?php echo Helpers::e($situacaoElegibilidade); ?></strong><br>
                                        <?php if ($motivosPendencias !== ''): ?>
                                            <button
                                                type="button"
                                                class="button-link button-link--small button-link--ghost js-open-pendencias-modal"
                                                data-pendencias-target="<?php echo Helpers::e($modalId); ?>"
                                            >
                                                Ver pendências
                                            </button>
                                            <div id="<?php echo Helpers::e($modalId); ?>" class="admin-pendencias-modal-source" hidden>
                                                <h3>Pendências para emissão do certificado</h3>
                                                <p>
                                                    <strong>Aluno/participante:</strong>
                                                    <?php echo Helpers::e($alunoNome); ?>
                                                </p>
                                                <p>
                                                    <strong>Curso:</strong>
                                                    <?php echo Helpers::e($candidato['curso_nome']); ?>
                                                </p>
                                                <?php if (!empty($candidato['turma_nome'])): ?>
                                                    <p>
                                                        <strong>Turma:</strong>
                                                        <?php echo Helpers::e($candidato['turma_nome']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p>
                                                    <strong>Situação:</strong>
                                                    <?php echo Helpers::e($situacaoElegibilidade); ?>
                                                </p>
                                                <div class="admin-pendencias-modal__text">
                                                    <?php echo nl2br(Helpers::e($motivosPendencias)); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="muted">Sem pendências listadas.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </form>

    <script>
    (function () {
        var checkbox = document.getElementById('permitir-excecao');
        var textarea = document.getElementById('justificativa-excecao');
        var form = document.getElementById('form-emissao-manual');
        var selecionarTodos = document.getElementById('selecionar-todos');

        function atualizarObrigatoriedade() {
            var ativo = checkbox && checkbox.checked;
            if (!textarea) {
                return;
            }
            textarea.required = !!ativo;
            textarea.disabled = false;
            if (!ativo) {
                textarea.value = textarea.value.trim();
            }
        }

        if (checkbox) {
            checkbox.addEventListener('change', atualizarObrigatoriedade);
        }

        if (selecionarTodos) {
            selecionarTodos.addEventListener('change', function () {
                var marcados = form ? form.querySelectorAll('input[name="inscricao_ids[]"]') : [];
                Array.prototype.forEach.call(marcados, function (item) {
                    item.checked = selecionarTodos.checked;
                });
            });
        }

        if (form) {
            form.addEventListener('submit', function () {
                atualizarObrigatoriedade();
            });
        }

        atualizarObrigatoriedade();
    })();
    </script>

    <div class="admin-modal" id="pendencias-modal" hidden aria-hidden="true">
        <div class="admin-modal__overlay" data-close-pendencias-modal></div>

        <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pendencias-modal-title">
            <div class="admin-modal__header">
                <h2 id="pendencias-modal-title">Pendências para emissão do certificado</h2>
                <button type="button" class="admin-modal__close" data-close-pendencias-modal aria-label="Fechar">×</button>
            </div>

            <div class="admin-modal__body" id="pendencias-modal-body"></div>

            <div class="admin-modal__footer">
                <button type="button" class="button-link button-link--ghost" data-close-pendencias-modal>Fechar</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('pendencias-modal');
        var modalBody = document.getElementById('pendencias-modal-body');
        var openButtons = document.querySelectorAll('.js-open-pendencias-modal');
        var closeButtons = document.querySelectorAll('[data-close-pendencias-modal]');

        if (!modal || !modalBody) {
            return;
        }

        function openModal(sourceId) {
            var source = document.getElementById(sourceId);

            if (!source) {
                return;
            }

            modalBody.innerHTML = source.innerHTML;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('admin-modal-open');

            var closeButton = modal.querySelector('.admin-modal__close');
            if (closeButton) {
                closeButton.focus();
            }
        }

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            modalBody.innerHTML = '';
            document.body.classList.remove('admin-modal-open');
        }

        Array.prototype.forEach.call(openButtons, function (button) {
            button.addEventListener('click', function () {
                openModal(button.getAttribute('data-pendencias-target'));
            });
        });

        Array.prototype.forEach.call(closeButtons, function (button) {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.classList.contains('admin-modal__overlay')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    });
    </script>
</div>
