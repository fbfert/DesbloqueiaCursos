<?php use App\Core\Helpers; ?>

<?php
$configCertificados = isset($configCertificados) && is_array($configCertificados) ? $configCertificados : array();
$alunosEncontrados = isset($alunosEncontrados) && is_array($alunosEncontrados) ? $alunosEncontrados : array();
$alunoSelecionado = isset($alunoSelecionado) && is_array($alunoSelecionado) ? $alunoSelecionado : null;
$inscricoesAluno = isset($inscricoesAluno) && is_array($inscricoesAluno) ? $inscricoesAluno : array();
$resultadoEmissao = isset($resultado_emissao_rapida) && is_array($resultado_emissao_rapida) ? $resultado_emissao_rapida : null;

$mascararCpf = function ($cpf) {
    $digits = preg_replace('/\D+/', '', (string) $cpf);
    if ($digits === '') {
        return '-';
    }

    if (strlen($digits) !== 11) {
        return $digits;
    }

    return substr($digits, 0, 3) . '.***.***-' . substr($digits, -2);
};

$emailValidoAluno = $alunoSelecionado && !empty($alunoSelecionado['email']) && filter_var((string) $alunoSelecionado['email'], FILTER_VALIDATE_EMAIL) !== false;
$temAlunoSelecionado = !empty($alunoSelecionado);
$temInscricoes = !empty($inscricoesAluno);
$temResultados = !empty($resultadoEmissao) && !empty($resultadoEmissao['resultados']) && is_array($resultadoEmissao['resultados']);
$podeEmitir = $temAlunoSelecionado && $emailValidoAluno && $temInscricoes;
?>

<style>
    .emissao-rapida-card-list {
        display: grid;
        gap: 12px;
        margin-top: 12px;
    }
    .emissao-rapida-card {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #fff;
        padding: 16px;
    }
    .emissao-rapida-card__meta {
        display: grid;
        gap: 4px;
        margin-top: 8px;
        color: #6b7280;
        font-size: 14px;
    }
    .emissao-rapida-table {
        width: 100%;
    }
    .emissao-rapida-table td,
    .emissao-rapida-table th {
        vertical-align: top;
    }
    .emissao-rapida-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
        background: #e5e7eb;
        color: #111827;
    }
    .emissao-rapida-status--apto {
        background: #dcfce7;
        color: #166534;
    }
    .emissao-rapida-status--bloqueado {
        background: #fee2e2;
        color: #991b1b;
    }
    .emissao-rapida-status--emitido {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .emissao-rapida-row--disabled {
        opacity: 0.72;
    }
    .emissao-rapida-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        justify-content: flex-end;
        margin-top: 16px;
    }
    .emissao-rapida-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 12px;
    }
    .emissao-rapida-summary-grid .status-card {
        margin: 0;
    }
    .emissao-rapida-message {
        border-left: 4px solid #b45309;
        background: #fff7ed;
        margin-top: 12px;
    }
    @media (max-width: 900px) {
        .emissao-rapida-summary-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 640px) {
        .emissao-rapida-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page emissao-rapida-individual-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Emissão Rápida Individual</h1>
            <p class="admin-page__subtitle">Localize o aluno, selecione as inscrições desejadas e emita os certificados em uma única operação.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/certificados">Voltar</a>
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

    <section class="status-card">
        <form method="get" action="/admin/certificados/emissao-rapida-individual" class="admin-form">
            <label>
                Nome, e-mail ou CPF do aluno
                <input
                    type="text"
                    name="q"
                    value="<?php echo Helpers::e($busca ?? ''); ?>"
                    placeholder="Digite o nome, e-mail ou CPF"
                >
            </label>
            <div class="admin-page__actions">
                <button type="submit" class="button-link button-link--primary">Buscar aluno</button>
            </div>
        </form>
    </section>

    <?php if (empty($alunoSelecionado) && $busca !== '' && empty($alunosEncontrados)): ?>
        <section class="auth-message auth-message-error" style="margin-top:12px;">
            <p>Nenhum aluno encontrado com os dados informados.</p>
        </section>
    <?php endif; ?>

    <?php if (!empty($alunosEncontrados)): ?>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Resultados da busca</h2>
                    <p>Selecione apenas um aluno para visualizar as inscrições elegíveis.</p>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>E-mail</th>
                            <th>CPF</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alunosEncontrados as $aluno): ?>
                            <tr>
                                <td><?php echo Helpers::e($aluno['nome'] ?? ''); ?></td>
                                <td><?php echo Helpers::e($aluno['email'] ?? ''); ?></td>
                                <td><?php echo Helpers::e($mascararCpf($aluno['cpf'] ?? '')); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="/admin/certificados/emissao-rapida-individual?usuario_id=<?php echo (int) ($aluno['id'] ?? 0); ?>">Selecionar</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($temAlunoSelecionado): ?>
        <section class="status-card" style="margin-top:12px;">
            <strong><?php echo Helpers::e($alunoSelecionado['nome'] ?? 'Aluno selecionado'); ?></strong>
            <div class="emissao-rapida-card__meta">
                <span><strong>E-mail:</strong> <?php echo Helpers::e($alunoSelecionado['email'] ?? '-'); ?></span>
                <span><strong>CPF:</strong> <?php echo Helpers::e($mascararCpf($alunoSelecionado['cpf'] ?? '')); ?></span>
            </div>
            <div class="admin-page__actions" style="margin-top:12px;">
                <a class="button-link button-link--ghost" href="/admin/certificados/emissao-rapida-individual">Trocar busca</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($temAlunoSelecionado): ?>
        <?php if (!$emailValidoAluno): ?>
            <section class="auth-message auth-message-error emissao-rapida-message">
                <p>O aluno não possui e-mail válido cadastrado. Atualize o cadastro antes de emitir o certificado.</p>
            </section>
        <?php endif; ?>

        <form method="post" action="/admin/certificados/emissao-rapida-individual/emitir" id="form-emissao-rapida" class="admin-form" style="margin-top:12px;">
            <?php echo $csrfField; ?>
            <input type="hidden" name="usuario_id" value="<?php echo (int) ($alunoSelecionado['id'] ?? 0); ?>">

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Inscrições do aluno</h2>
                        <p>Marque apenas inscrições aptas e ainda sem certificado.</p>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="table emissao-rapida-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Curso</th>
                                <th>Turma</th>
                                <th>Modalidade</th>
                                <th>Período</th>
                                <th>Progresso</th>
                                <th>Inscrição</th>
                                <th>Certificado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inscricoesAluno)): ?>
                                <tr>
                                    <td colspan="8">Nenhuma inscrição válida encontrada para este aluno.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($inscricoesAluno as $inscricao): ?>
                                <?php
                                $selecionavel = !empty($inscricao['selecionavel']) && $emailValidoAluno;
                                $certEmitido = !empty($inscricao['certificado_emitido']);
                                $statusLabel = !empty($inscricao['situacao_elegibilidade_label']) ? (string) $inscricao['situacao_elegibilidade_label'] : 'Não apto para emissão';
                                $statusClass = $certEmitido ? 'emissao-rapida-status--emitido' : ($selecionavel ? 'emissao-rapida-status--apto' : 'emissao-rapida-status--bloqueado');
                                $cursoNome = !empty($inscricao['curso_nome']) ? (string) $inscricao['curso_nome'] : '-';
                                $turmaNome = !empty($inscricao['turma_nome']) ? (string) $inscricao['turma_nome'] : '-';
                                $modalidade = !empty($inscricao['curso_modalidade']) ? (string) $inscricao['curso_modalidade'] : '-';
                                $periodo = !empty($inscricao['turma_periodo']) ? (string) $inscricao['turma_periodo'] : '-';
                                $progresso = isset($inscricao['percentual_progresso']) && $inscricao['percentual_progresso'] !== null
                                    ? number_format((float) $inscricao['percentual_progresso'], 2, ',', '.') . '%'
                                    : '0,00%';
                                $statusInscricao = !empty($inscricao['status']) ? Helpers::statusLms($inscricao['status']) : '-';
                                $motivo = !empty($inscricao['motivo_resumido']) ? (string) $inscricao['motivo_resumido'] : '';
                                ?>
                                <tr class="<?php echo $selecionavel ? '' : 'emissao-rapida-row--disabled'; ?>">
                                    <td>
                                        <input
                                            type="checkbox"
                                            name="inscricao_ids[]"
                                            value="<?php echo (int) $inscricao['id']; ?>"
                                            <?php echo $selecionavel ? '' : 'disabled'; ?>
                                            data-emissao-rapida-checkbox
                                        >
                                    </td>
                                    <td>
                                        <strong><?php echo Helpers::e($cursoNome); ?></strong><br>
                                        <small class="muted"><?php echo Helpers::e($motivo); ?></small>
                                    </td>
                                    <td><?php echo Helpers::e($turmaNome); ?></td>
                                    <td><?php echo Helpers::e($modalidade); ?></td>
                                    <td><?php echo Helpers::e($periodo); ?></td>
                                    <td><?php echo Helpers::e($progresso); ?></td>
                                    <td><?php echo Helpers::e($statusInscricao); ?></td>
                                    <td>
                                        <span class="emissao-rapida-status <?php echo Helpers::e($statusClass); ?>">
                                            <?php echo Helpers::e($statusLabel); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="emissao-rapida-actions">
                <a class="button-link button-link--ghost" href="/admin/certificados">Cancelar</a>
                <button
                    type="submit"
                    class="button-link button-link--primary"
                    data-emissao-rapida-submit
                    disabled
                >
                    Emitir agora
                </button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($temResultados): ?>
        <section class="panel" style="margin-top:20px;">
            <div class="panel-header">
                <div>
                    <h2>Certificados emitidos</h2>
                    <p>Resultado da última operação de emissão rápida.</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Curso</th>
                            <th>Turma</th>
                            <th>Código</th>
                            <th>Emissão</th>
                            <th>E-mail</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadoEmissao['resultados'] as $item): ?>
                            <?php if (!empty($item['ok'])): ?>
                                <tr>
                                    <td>
                                        <?php echo Helpers::e($item['aluno_nome'] ?? ''); ?><br>
                                        <small class="muted"><?php echo Helpers::e($item['aluno_email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo Helpers::e($item['curso_nome'] ?? ''); ?></td>
                                    <td><?php echo Helpers::e($item['turma_nome'] ?? ''); ?></td>
                                    <td><?php echo Helpers::e($item['codigo'] ?? ''); ?></td>
                                    <td><?php echo Helpers::e($item['emissao_em'] ?? date('d/m/Y H:i')); ?></td>
                                    <td>
                                        <?php if ($item['email_ok'] === true): ?>
                                            Enviado
                                        <?php elseif ($item['email_ok'] === false): ?>
                                            Não enviado
                                        <?php else: ?>
                                            Não disponível
                                        <?php endif; ?>
                                        <?php if (!empty($item['email_message'])): ?>
                                            <br><small class="muted"><?php echo Helpers::e($item['email_message']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if (!empty($item['certificado_url_download'])): ?>
                                                <a href="<?php echo Helpers::e($item['certificado_url_download']); ?>" target="_blank" rel="noopener">Baixar PDF</a>
                                            <?php endif; ?>
                                            <?php if (!empty($item['versao_online_url'])): ?>
                                                <a href="<?php echo Helpers::e($item['versao_online_url']); ?>" target="_blank" rel="noopener">Abrir versão online</a>
                                            <?php endif; ?>
                                            <?php if (!empty($item['validacao_url'])): ?>
                                                <a href="<?php echo Helpers::e($item['validacao_url']); ?>" target="_blank" rel="noopener">Validar certificado</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <strong>Falha na emissão</strong><br>
                                        <small class="muted"><?php echo Helpers::e($item['message'] ?? 'Não foi possível emitir o certificado.'); ?></small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-emissao-rapida');
    if (!form) {
        return;
    }

    var submitButton = form.querySelector('[data-emissao-rapida-submit]');
    var checkboxes = Array.prototype.slice.call(form.querySelectorAll('[data-emissao-rapida-checkbox]'));

    function atualizarEstadoBotao() {
        var selecionados = checkboxes.filter(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
        }).length;

        submitButton.disabled = selecionados === 0;
        submitButton.dataset.countSelecionado = String(selecionados);
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', atualizarEstadoBotao);
    });

    form.addEventListener('submit', function (event) {
        var selecionados = checkboxes.filter(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
        }).length;

        if (selecionados <= 0) {
            event.preventDefault();
            return;
        }

        var confirmar = window.confirm('Serão emitidos ' + selecionados + ' certificado(s). Continuar?');
        if (!confirmar) {
            event.preventDefault();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Processando...';
        }
    });

    atualizarEstadoBotao();
});
</script>
