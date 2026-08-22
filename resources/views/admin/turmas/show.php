<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php $rbacService = new RbacService(); ?>
<?php $canManage = $rbacService->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar'); ?>
<?php $canViewUsuarios = $rbacService->userHasPermission(Session::get('usuario_id'), 'usuarios.ver') || $rbacService->userHasPermission(Session::get('usuario_id'), 'usuarios.gerenciar'); ?>
<?php $canViewCertificados = $rbacService->userHasPermission(Session::get('usuario_id'), 'certificados.ver'); ?>
<?php $returnTo = isset($return_to) ? (string) $return_to : ''; ?>
<?php $voltarUrl = $returnTo !== '' ? $returnTo : '/admin/turmas'; ?>
<?php $alunosMatriculados = isset($alunos_matriculados) && is_array($alunos_matriculados) ? $alunos_matriculados : array(); ?>
<?php $alunosMatriculadosTotal = isset($alunos_matriculados_total) ? (int) $alunos_matriculados_total : count($alunosMatriculados); ?>

<style>
    .app-admin .turma-summary-card {
        padding: 16px;
    }

    .app-admin .turma-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }

    .app-admin .turma-summary-item {
        min-width: 0;
        padding: 10px 12px;
        border: 1px solid var(--admin-border);
        border-radius: 12px;
        background: #ffffff;
    }

    .app-admin .turma-summary-item--wide {
        grid-column: span 2;
    }

    .app-admin .turma-summary-label {
        display: block;
        margin-bottom: 4px;
        font-size: 11px;
        line-height: 1.2;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--admin-muted);
    }

    .app-admin .turma-summary-value {
        display: block;
        min-width: 0;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
        color: var(--admin-text);
        word-break: break-word;
    }

    @media (max-width: 1400px) {
        .app-admin .turma-summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (max-width: 1100px) {
        .app-admin .turma-summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .app-admin .turma-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .app-admin .turma-summary-item--wide {
            grid-column: span 2;
        }
    }

    @media (max-width: 560px) {
        .app-admin .turma-summary-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .app-admin .turma-summary-item--wide {
            grid-column: auto;
        }
    }
</style>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($turma['nome']); ?></h1>
        <p class="admin-page__subtitle">Detalhe administrativo da turma.</p>
    </div>
</section>

<section class="status-card turma-summary-card">
    <div class="turma-summary-grid">
        <div class="turma-summary-item">
            <span class="turma-summary-label">Código</span>
            <strong class="turma-summary-value"><?php echo Helpers::e($turma['codigo']); ?></strong>
        </div>
        <div class="turma-summary-item turma-summary-item--wide">
            <span class="turma-summary-label">Curso</span>
            <strong class="turma-summary-value"><?php echo Helpers::e($turma['curso_nome']); ?></strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Professor responsável</span>
            <strong class="turma-summary-value"><?php echo Helpers::e($turma['professor_responsavel_nome'] ?? '-'); ?></strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Categoria</span>
            <strong class="turma-summary-value"><?php echo Helpers::e($turma['categoria_nome'] ?? ''); ?></strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Modalidade</span>
            <strong class="turma-summary-value"><?php echo Helpers::e(Helpers::modalidadeCurso($turma['curso_modalidade'])); ?></strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Data de início</span>
            <strong class="turma-summary-value"><?php echo Helpers::e((string) $turma['data_inicio']); ?></strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Data de fim</span>
            <strong class="turma-summary-value"><?php echo Helpers::e((string) $turma['data_fim']); ?></strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Vagas</span>
            <strong class="turma-summary-value"><?php echo (int) $turma['vagas']; ?></strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Alunos matriculados</span>
            <strong class="turma-summary-value"><?php echo (int) $alunosMatriculadosTotal; ?> aluno(s)</strong>
        </div>
        <div class="turma-summary-item">
            <span class="turma-summary-label">Status</span>
            <strong class="turma-summary-value"><?php echo Helpers::e($turma['status']); ?></strong>
        </div>
    </div>
</section>

<section class="status-card">
    <div class="panel-header" style="margin-bottom: 12px;">
        <div>
            <h2>Alunos matriculados</h2>
            <p>Lista de matrículas efetivamente vinculadas a esta turma.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>WhatsApp/Telefone</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alunosMatriculados)): ?>
                    <tr>
                        <td colspan="4">Nenhum aluno matriculado nesta turma até o momento.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($alunosMatriculados as $aluno): ?>
                        <?php $filtroAluno = !empty($aluno['aluno_email']) ? $aluno['aluno_email'] : ($aluno['aluno_nome'] ?? ''); ?>
                        <tr>
                            <td><?php echo Helpers::e($aluno['aluno_nome'] ?? '-'); ?></td>
                            <td><?php echo Helpers::e($aluno['aluno_email'] ?? '-'); ?></td>
                            <td><?php echo !empty($aluno['aluno_telefone']) ? Helpers::e($aluno['aluno_telefone']) : 'Não informado'; ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="/admin/inscricoes?turma_id=<?php echo (int) $turma['id']; ?>&q=<?php echo urlencode($filtroAluno); ?>">Ver matrícula</a>
                                    <?php if ($canViewUsuarios && !empty($aluno['aluno_usuario_id'])): ?>
                                        <a href="/admin/usuarios/editar?usuario_id=<?php echo (int) $aluno['aluno_usuario_id']; ?>">Ver aluno</a>
                                    <?php endif; ?>
                                    <?php if ($canViewCertificados && !empty($aluno['certificado_id'])): ?>
                                        <a href="/admin/certificados/show?certificado_id=<?php echo (int) $aluno['certificado_id']; ?>">Certificado</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <div class="split-actions">
        <?php if ($canManage): ?>
            <a href="/admin/turmas/editar?turma_id=<?php echo (int) $turma['id']; ?><?php echo !empty($turma['curso_evento_id']) ? '&curso_id=' . (int) $turma['curso_evento_id'] : ''; ?><?php echo $returnTo !== '' ? '&return_to=' . urlencode($returnTo) : ''; ?>">Editar</a>
        <?php endif; ?>
        <a href="<?php echo Helpers::e($voltarUrl); ?>">Voltar</a>
        <?php if ($canManage): ?>
            <form method="post" action="/admin/turmas/status" class="admin-form js-turma-status-form">
                <input type="hidden" name="id" value="<?php echo (int) $turma['id']; ?>">
                <input type="hidden" name="curso_id" value="<?php echo !empty($turma['curso_evento_id']) ? (int) $turma['curso_evento_id'] : 0; ?>">
                <input type="hidden" name="status" value="<?php echo $turma['status'] === 'aberta' ? 'encerrada' : 'aberta'; ?>">
                <input type="hidden" name="justificativa" value="">
                <input type="hidden" name="return_to" value="<?php echo Helpers::e($returnTo); ?>">
                <button type="submit"><?php echo $turma['status'] === 'aberta' ? 'Encerrar' : 'Abrir'; ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
</div>

<script>
(function () {
    var forms = document.querySelectorAll('.js-turma-status-form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var statusInput = form.querySelector('input[name="status"]');
            var justificativaInput = form.querySelector('input[name="justificativa"]');

            if (!statusInput || statusInput.value !== 'encerrada') {
                return;
            }

            event.preventDefault();
            var motivo = window.prompt('Informe o motivo do encerramento da turma:');
            if (motivo === null) {
                return;
            }

            motivo = motivo.trim();
            if (!motivo) {
                window.alert('O motivo do encerramento é obrigatório.');
                return;
            }

            if (justificativaInput) {
                justificativaInput.value = motivo;
            }

            form.submit();
        });
    });
})();
</script>
