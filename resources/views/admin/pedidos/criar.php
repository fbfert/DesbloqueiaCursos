<?php use App\Core\Helpers; ?>
<?php
$formData = isset($form_data) && is_array($form_data) ? $form_data : array();
$alunoSelecionado = isset($aluno_selecionado) && is_array($aluno_selecionado) ? $aluno_selecionado : null;
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$turmas = isset($turmas) && is_array($turmas) ? $turmas : array();

$valor = function ($chave, $padrao = '') use ($formData) {
    return isset($formData[$chave]) && $formData[$chave] !== '' ? (string) $formData[$chave] : $padrao;
};

$alunoId = (int) $valor('aluno_usuario_id', !empty($alunoSelecionado['id']) ? (int) $alunoSelecionado['id'] : 0);
$alunoLabel = trim(
    (string) ($alunoSelecionado['nome'] ?? '') .
    (!empty($alunoSelecionado['email']) ? ' · ' . $alunoSelecionado['email'] : '') .
    (!empty($alunoSelecionado['cpf']) ? ' · CPF ' . $alunoSelecionado['cpf'] : '')
);

$turmasPorCurso = array();
foreach ($turmas as $turma) {
    $cursoIdTurma = isset($turma['curso_evento_id']) ? (int) $turma['curso_evento_id'] : 0;
    if ($cursoIdTurma <= 0) {
        continue;
    }
    if (!isset($turmasPorCurso[$cursoIdTurma])) {
        $turmasPorCurso[$cursoIdTurma] = array();
    }
    $turmasPorCurso[$cursoIdTurma][] = array(
        'id' => (int) $turma['id'],
        'nome' => (string) $turma['nome'],
        'codigo' => (string) ($turma['codigo'] ?? ''),
        'status' => (string) ($turma['status'] ?? ''),
    );
}

$alunoDadosPadrao = array(
    'nome' => $alunoSelecionado['nome'] ?? '',
    'email' => $alunoSelecionado['email'] ?? '',
    'cpf' => $alunoSelecionado['cpf'] ?? '',
    'telefone' => $alunoSelecionado['telefone'] ?? '',
);

if (!empty($formData['pagador_nome'])) {
    $alunoDadosPadrao['nome'] = (string) $formData['pagador_nome'];
}
if (!empty($formData['pagador_email'])) {
    $alunoDadosPadrao['email'] = (string) $formData['pagador_email'];
}
if (!empty($formData['pagador_cpf'])) {
    $alunoDadosPadrao['cpf'] = (string) $formData['pagador_cpf'];
}
if (!empty($formData['pagador_telefone'])) {
    $alunoDadosPadrao['telefone'] = (string) $formData['pagador_telefone'];
}
?>

<div class="admin-page admin-pedido-manual">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Criar pedido manualmente</h1>
            <p class="admin-page__subtitle">Selecione o aluno, curso e turma, aplique um cupom se necessário e depois envie o comprovante no pedido gerado.</p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost" href="/admin/pedidos">Voltar para pedidos</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <p class="muted">Este fluxo cria o pedido, gera a inscrição vinculada e deixa o pedido pronto para receber o comprovante PIX na própria tela de detalhes.</p>
    </section>

    <section class="status-card">
        <form method="post" action="/admin/pedidos/criar" class="admin-form admin-pedido-manual__form" id="pedido-manual-form">
            <?php echo $csrfField; ?>

            <div class="admin-pedido-manual__grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start;">
                <div class="full" style="grid-column:1/-1;">
                    <label for="pedido-aluno-busca">Aluno</label>
                    <input type="hidden" name="aluno_usuario_id" id="pedido-aluno-id" value="<?php echo (int) $alunoId; ?>">
                    <input
                        id="pedido-aluno-busca"
                        type="text"
                        value="<?php echo Helpers::e($alunoLabel !== '' ? $alunoLabel : ''); ?>"
                        placeholder="Digite o nome, e-mail ou CPF do aluno"
                        autocomplete="off"
                    >
                    <div class="avisos-autocomplete" id="pedido-aluno-sugestoes" hidden></div>
                    <div class="status-card" id="pedido-aluno-resumo" style="margin-top:10px;<?php echo $alunoSelecionado ? '' : 'display:none;'; ?>">
                        <strong>Aluno selecionado</strong>
                        <p id="pedido-aluno-resumo-nome" style="margin:6px 0 0;"><?php echo Helpers::e($alunoSelecionado ? ($alunoSelecionado['nome'] ?? 'Aluno selecionado') : 'Selecione um aluno.'); ?></p>
                        <p id="pedido-aluno-resumo-email" class="muted" style="margin:4px 0 0;"><?php echo Helpers::e($alunoSelecionado && !empty($alunoSelecionado['email']) ? $alunoSelecionado['email'] : ''); ?></p>
                        <p id="pedido-aluno-resumo-cpf" class="muted" style="margin:4px 0 0;"><?php echo Helpers::e($alunoSelecionado && !empty($alunoSelecionado['cpf']) ? 'CPF ' . $alunoSelecionado['cpf'] : ''); ?></p>
                        <p id="pedido-aluno-resumo-telefone" class="muted" style="margin:4px 0 0;"><?php echo Helpers::e($alunoSelecionado && !empty($alunoSelecionado['telefone']) ? 'Telefone ' . $alunoSelecionado['telefone'] : ''); ?></p>
                    </div>
                </div>

                <label>Nome do pagador
                    <input type="text" name="pagador_nome" id="pedido-pagador-nome" value="<?php echo Helpers::e($alunoDadosPadrao['nome']); ?>" required>
                </label>

                <label>CPF do pagador
                    <input type="text" name="pagador_cpf" id="pedido-pagador-cpf" value="<?php echo Helpers::e($alunoDadosPadrao['cpf']); ?>" required>
                </label>

                <label>E-mail do pagador
                    <input type="email" name="pagador_email" id="pedido-pagador-email" value="<?php echo Helpers::e($alunoDadosPadrao['email']); ?>" required>
                </label>

                <label>Telefone do pagador
                    <input type="text" name="pagador_telefone" id="pedido-pagador-telefone" value="<?php echo Helpers::e($alunoDadosPadrao['telefone']); ?>" required>
                </label>

                <label>Curso
                    <select name="curso_evento_id" id="pedido-curso-id" required>
                        <option value="">Selecione</option>
                        <?php $cursoSelecionado = (int) $valor('curso_evento_id', 0); ?>
                        <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo (int) $curso['id']; ?>" <?php echo $cursoSelecionado === (int) $curso['id'] ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($curso['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Turma
                    <select name="turma_id" id="pedido-turma-id" required>
                        <option value="">Selecione um curso primeiro</option>
                    </select>
                </label>

                <label>Código do cupom
                    <input type="text" name="cupom_codigo" id="pedido-cupom-codigo" value="<?php echo Helpers::e($valor('cupom_codigo')); ?>" placeholder="Opcional">
                </label>

                <label class="full" id="pedido-cupom-justificativa-bloco" style="display:none;grid-column:1/-1;">
                    Justificativa do cupom
                    <textarea name="cupom_justificativa" id="pedido-cupom-justificativa" rows="3" placeholder="Explique por que o cupom está sendo aplicado manualmente."><?php echo Helpers::e($valor('cupom_justificativa')); ?></textarea>
                </label>

                <label class="full" style="grid-column:1/-1;">
                    Observações internas
                    <textarea name="observacoes_internas" rows="4" placeholder="Observação visível apenas no backoffice."><?php echo Helpers::e($valor('observacoes_internas')); ?></textarea>
                </label>
            </div>

            <div class="cta-group" style="margin-top:16px;">
                <button type="submit" class="button-link button-link--primary">Criar pedido manualmente</button>
                <a class="button-link button-link--ghost" href="/admin/pedidos">Cancelar</a>
            </div>
        </form>
    </section>
</div>

<script>
(function () {
    var alunoBusca = document.getElementById('pedido-aluno-busca');
    var alunoId = document.getElementById('pedido-aluno-id');
    var alunoSugestoes = document.getElementById('pedido-aluno-sugestoes');
    var alunoResumo = document.getElementById('pedido-aluno-resumo');
    var alunoResumoNome = document.getElementById('pedido-aluno-resumo-nome');
    var alunoResumoEmail = document.getElementById('pedido-aluno-resumo-email');
    var alunoResumoCpf = document.getElementById('pedido-aluno-resumo-cpf');
    var alunoResumoTelefone = document.getElementById('pedido-aluno-resumo-telefone');
    var pagadorNome = document.getElementById('pedido-pagador-nome');
    var pagadorCpf = document.getElementById('pedido-pagador-cpf');
    var pagadorEmail = document.getElementById('pedido-pagador-email');
    var pagadorTelefone = document.getElementById('pedido-pagador-telefone');
    var cursoSelect = document.getElementById('pedido-curso-id');
    var turmaSelect = document.getElementById('pedido-turma-id');
    var cupomCodigo = document.getElementById('pedido-cupom-codigo');
    var cupomJustificativaBloco = document.getElementById('pedido-cupom-justificativa-bloco');
    var cupomJustificativa = document.getElementById('pedido-cupom-justificativa');
    var form = document.getElementById('pedido-manual-form');

    var turmasPorCurso = <?php echo json_encode($turmasPorCurso, JSON_UNESCAPED_UNICODE); ?>;

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }

    function setResumoAluno(aluno) {
        if (!alunoResumo) {
            return;
        }

        if (!aluno) {
            alunoResumo.style.display = 'none';
            return;
        }

        alunoResumo.style.display = '';
        if (alunoResumoNome) alunoResumoNome.textContent = aluno.nome || 'Aluno selecionado';
        if (alunoResumoEmail) alunoResumoEmail.textContent = aluno.email ? aluno.email : '';
        if (alunoResumoCpf) alunoResumoCpf.textContent = aluno.cpf ? 'CPF ' + aluno.cpf : '';
        if (alunoResumoTelefone) alunoResumoTelefone.textContent = aluno.telefone ? 'Telefone ' + aluno.telefone : '';
    }

    function preencherAluno(aluno) {
        if (!aluno || !alunoId || !alunoBusca) {
            return;
        }

        alunoId.value = String(aluno.id || '');
        alunoBusca.value = aluno.label || aluno.nome || '';
        if (pagadorNome) pagadorNome.value = aluno.nome || '';
        if (pagadorCpf) pagadorCpf.value = aluno.cpf || '';
        if (pagadorEmail) pagadorEmail.value = aluno.email || '';
        if (pagadorTelefone) pagadorTelefone.value = aluno.telefone || '';
        setResumoAluno(aluno);
        alunoSugestoes.hidden = true;
        alunoSugestoes.innerHTML = '';
    }

    function montarSugestoes(lista) {
        if (!alunoSugestoes) {
            return;
        }

        alunoSugestoes.innerHTML = '';
        if (!lista.length) {
            alunoSugestoes.hidden = false;
            alunoSugestoes.innerHTML = '<div class="avisos-autocomplete__empty">Nenhum aluno encontrado.</div>';
            return;
        }

        lista.slice(0, 10).forEach(function (aluno) {
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'avisos-autocomplete__item';
            botao.innerHTML =
                '<strong>' + escapeHtml(aluno.nome || 'Aluno') + '</strong>' +
                '<span>' + escapeHtml(aluno.email || '') + '</span>' +
                '<small>' + escapeHtml(aluno.cpf ? 'CPF ' + aluno.cpf : '') + '</small>';
            botao.addEventListener('click', function () {
                preencherAluno(aluno);
            });
            alunoSugestoes.appendChild(botao);
        });

        alunoSugestoes.hidden = false;
    }

    function buscarAlunos() {
        if (!alunoBusca || !alunoSugestoes) {
            return;
        }

        var termo = String(alunoBusca.value || '').trim();
        if (termo.length < 2) {
            alunoSugestoes.hidden = true;
            alunoSugestoes.innerHTML = '';
            return;
        }

        fetch('/admin/pedidos/alunos?q=' + encodeURIComponent(termo) + '&limit=10', {
            headers: {'Accept': 'application/json'}
        })
            .then(function (response) {
                return response.ok ? response.json() : Promise.reject(new Error('Falha ao buscar alunos.'));
            })
            .then(function (payload) {
                if (!payload || !payload.ok || !Array.isArray(payload.alunos)) {
                    throw new Error('Resposta inválida.');
                }
                if (String(alunoBusca.value || '').trim() !== termo) {
                    return;
                }
                montarSugestoes(payload.alunos);
            })
            .catch(function () {
                if (String(alunoBusca.value || '').trim() === termo) {
                    alunoSugestoes.hidden = false;
                    alunoSugestoes.innerHTML = '<div class="avisos-autocomplete__empty">Não foi possível buscar alunos agora.</div>';
                }
            });
    }

    function atualizarTurmas() {
        if (!cursoSelect || !turmaSelect) {
            return;
        }

        var cursoId = String(cursoSelect.value || '');
        var turmas = cursoId && turmasPorCurso[cursoId] ? turmasPorCurso[cursoId] : [];
        var turmaSelecionada = String(<?php echo json_encode((string) $valor('turma_id', '')); ?> || '');

        turmaSelect.innerHTML = '';
        if (!turmas.length) {
            var vazio = document.createElement('option');
            vazio.value = '';
            vazio.textContent = cursoId ? 'Nenhuma turma encontrada para este curso' : 'Selecione um curso primeiro';
            turmaSelect.appendChild(vazio);
            return;
        }

        var primeiro = document.createElement('option');
        primeiro.value = '';
        primeiro.textContent = 'Selecione';
        turmaSelect.appendChild(primeiro);

        turmas.forEach(function (turma) {
            var option = document.createElement('option');
            option.value = String(turma.id || '');
            option.textContent = turma.nome + (turma.codigo ? ' · ' + turma.codigo : '');
            if (turma.status) {
                option.textContent += ' · ' + turma.status;
            }
            if (turmaSelecionada && String(turma.id || '') === turmaSelecionada) {
                option.selected = true;
            }
            turmaSelect.appendChild(option);
        });
    }

    function atualizarCupomJustificativa() {
        if (!cupomCodigo || !cupomJustificativaBloco || !cupomJustificativa) {
            return;
        }

        var possuiCupom = String(cupomCodigo.value || '').trim() !== '';
        cupomJustificativaBloco.style.display = possuiCupom ? '' : 'none';
        cupomJustificativa.required = possuiCupom;
    }

    if (alunoBusca) {
        alunoBusca.addEventListener('input', function () {
            if (alunoId) {
                alunoId.value = '';
            }
            setResumoAluno(null);
            buscarAlunos();
        });
        alunoBusca.addEventListener('focus', buscarAlunos);
        alunoBusca.addEventListener('blur', function () {
            window.setTimeout(function () {
                if (alunoSugestoes) {
                    alunoSugestoes.hidden = true;
                }
            }, 150);
        });
    }

    if (cursoSelect) {
        cursoSelect.addEventListener('change', atualizarTurmas);
    }

    if (cupomCodigo) {
        cupomCodigo.addEventListener('input', atualizarCupomJustificativa);
    }

    if (form) {
        form.addEventListener('submit', function () {
            atualizarCupomJustificativa();
        });
    }

    if (alunoId && alunoId.value) {
        setResumoAluno({
            id: alunoId.value,
            nome: <?php echo json_encode((string) ($alunoSelecionado['nome'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
            email: <?php echo json_encode((string) ($alunoSelecionado['email'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
            cpf: <?php echo json_encode((string) ($alunoSelecionado['cpf'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
            telefone: <?php echo json_encode((string) ($alunoSelecionado['telefone'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
        });
    }

    atualizarTurmas();
    atualizarCupomJustificativa();
})();
</script>
