<?php
use App\Core\Helpers;

$aviso = isset($form_data['aviso']) && is_array($form_data['aviso']) ? $form_data['aviso'] : null;
$cursos = isset($form_data['cursos']) && is_array($form_data['cursos']) ? $form_data['cursos'] : array();
$tiposDestino = isset($form_data['tipos_destino']) && is_array($form_data['tipos_destino']) ? $form_data['tipos_destino'] : array();
$statusOptions = isset($form_data['status_options']) && is_array($form_data['status_options']) ? $form_data['status_options'] : array();
$alunoSelecionado = isset($form_data['aluno_selecionado']) && is_array($form_data['aluno_selecionado']) ? $form_data['aluno_selecionado'] : null;
$oldData = isset($old) && is_array($old) ? $old : array();

$value = function ($key, $default = '') use ($oldData, $aviso) {
    if (array_key_exists($key, $oldData)) {
        return $oldData[$key];
    }
    if (is_array($aviso) && array_key_exists($key, $aviso)) {
        return $aviso[$key];
    }
    return $default;
};

$formatDateTimeLocal = function ($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }
    return date('Y-m-d\TH:i', $timestamp);
};

$tipoDestino = (string) $value('tipo_destino', 'todos_alunos');
$cursoSelecionadoId = (int) $value('curso_evento_id', 0);
$usuarioSelecionadoId = (int) $value('usuario_id', 0);
$avisoStatus = (string) $value('status', 'rascunho');
$destinoBloqueado = !empty($aviso) && ($aviso['status'] ?? '') !== 'rascunho';
$tipoDestinoLabel = isset($tiposDestino[$tipoDestino]) ? $tiposDestino[$tipoDestino] : $tipoDestino;

$alunoSelecionadoLabel = '';
$alunoSelecionadoCursos = '';
$alunoSelecionadoBusca = '';
if ($alunoSelecionado) {
    $alunoSelecionadoLabel = trim((string) ($alunoSelecionado['nome'] ?? '') . (!empty($alunoSelecionado['email']) ? ' · ' . $alunoSelecionado['email'] : '') . (!empty($alunoSelecionado['cpf']) ? ' · CPF ' . $alunoSelecionado['cpf'] : ''));
    $alunoSelecionadoCursos = (string) ($alunoSelecionado['cursos_confirmados_titulos'] ?? '');
    $alunoSelecionadoBusca = trim(implode(' ', array_filter(array(
        (string) ($alunoSelecionado['nome'] ?? ''),
        (string) ($alunoSelecionado['email'] ?? ''),
        (string) ($alunoSelecionado['cpf'] ?? ''),
        (string) ($alunoSelecionado['cursos_confirmados_ids'] ?? ''),
        (string) ($alunoSelecionado['cursos_confirmados_titulos'] ?? ''),
    ))));
}
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Cadastre, envie e acompanhe avisos para alunos da plataforma.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/avisos">Voltar</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form" id="aviso-form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">
            <input type="hidden" name="origem" value="<?php echo Helpers::e($value('origem', 'manual')); ?>">
            <input type="hidden" name="gatilho" value="<?php echo Helpers::e($value('gatilho', '')); ?>">
            <input type="hidden" name="usuario_id" id="aviso-usuario-id" value="<?php echo (int) $usuarioSelecionadoId; ?>">

            <div class="admin-form-grid">
                <label class="full">
                    Título
                    <input type="text" name="titulo" value="<?php echo Helpers::e($value('titulo')); ?>" maxlength="180">
                </label>
                <label class="full">
                    Mensagem
                    <textarea name="mensagem" rows="6" required><?php echo Helpers::e($value('mensagem')); ?></textarea>
                </label>
            </div>

            <h2 class="full" style="margin:16px 0 8px;">Destino</h2>
            <div class="admin-form-grid">
                <label class="full">
                    Tipo de destino
                    <?php if ($destinoBloqueado): ?>
                        <input type="hidden" name="tipo_destino" value="<?php echo Helpers::e($tipoDestino); ?>">
                        <input type="text" value="<?php echo Helpers::e($tipoDestinoLabel); ?>" disabled>
                        <small class="muted" style="display:block;margin-top:6px;">Avisos já enviados não permitem trocar o destino.</small>
                    <?php else: ?>
                        <select name="tipo_destino" id="aviso-tipo-destino">
                            <?php foreach ($tiposDestino as $key => $text): ?>
                                <option value="<?php echo Helpers::e($key); ?>" <?php echo $tipoDestino === $key ? 'selected' : ''; ?>><?php echo Helpers::e($text); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </label>

                <label class="full" id="aviso-curso-bloco">
                    Curso
                    <?php if ($destinoBloqueado): ?>
                        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $cursoSelecionadoId; ?>">
                        <input type="text" value="<?php echo Helpers::e(!empty($aviso['curso_nome']) ? $aviso['curso_nome'] : '-'); ?>" disabled>
                    <?php else: ?>
                        <select name="curso_evento_id" id="aviso-curso-evento-id">
                            <option value="">Selecione um curso</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?php echo (int) $curso['id']; ?>" <?php echo $cursoSelecionadoId === (int) $curso['id'] ? 'selected' : ''; ?>>
                                    <?php echo Helpers::e($curso['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </label>

                <div class="full" id="aviso-aluno-bloco">
                    <label class="full">
                        Aluno
                        <?php if ($destinoBloqueado): ?>
                            <input type="text" value="<?php echo Helpers::e(!empty($aviso['usuario_nome']) ? $aviso['usuario_nome'] : '-'); ?>" disabled>
                        <?php else: ?>
                            <input
                                type="search"
                                id="aviso-aluno-busca"
                                placeholder="Digite nome, e-mail, CPF ou curso"
                                autocomplete="off"
                                value="<?php echo Helpers::e($alunoSelecionadoLabel); ?>"
                            >
                            <div class="avisos-autocomplete" id="aviso-aluno-sugestoes" hidden></div>
                            <button type="button" class="button-link button-link--ghost" id="aviso-aluno-limpar" style="margin-top:8px;">Limpar seleção</button>
                            <small class="muted" style="display:block;margin-top:6px;">A lista é carregada conforme você digita.</small>
                            <div class="status-card" id="aviso-aluno-resumo" style="margin-top:10px;<?php echo $alunoSelecionado ? '' : 'display:none;'; ?>">
                                <strong>Aluno selecionado</strong>
                                <p id="aviso-aluno-resumo-nome" style="margin:6px 0 0;"><?php echo Helpers::e($alunoSelecionado ? ($alunoSelecionado['nome'] ?? 'Aluno selecionado') : 'Selecione um aluno.'); ?></p>
                                <p id="aviso-aluno-resumo-email" class="muted" style="margin:4px 0 0;"><?php echo Helpers::e($alunoSelecionado && !empty($alunoSelecionado['email']) ? $alunoSelecionado['email'] : ''); ?></p>
                                <p id="aviso-aluno-resumo-cpf" class="muted" style="margin:4px 0 0;"><?php echo Helpers::e($alunoSelecionado && !empty($alunoSelecionado['cpf']) ? 'CPF ' . $alunoSelecionado['cpf'] : ''); ?></p>
                                <p id="aviso-aluno-resumo-cursos" class="muted" style="margin:4px 0 0;"><?php echo Helpers::e($alunoSelecionado && !empty($alunoSelecionado['cursos_confirmados_titulos']) ? 'Cursos confirmados: ' . $alunoSelecionado['cursos_confirmados_titulos'] : ''); ?></p>
                            </div>
                        <?php endif; ?>
                    </label>
                </div>
            </div>

            <h2 class="full" style="margin:16px 0 8px;">Exibição</h2>
            <div class="admin-form-grid">
                <label>
                    Mostrar início
                    <input type="datetime-local" name="mostrar_inicio" value="<?php echo Helpers::e($formatDateTimeLocal($value('mostrar_inicio'))); ?>">
                </label>
                <label>
                    Mostrar fim
                    <input type="datetime-local" name="mostrar_fim" value="<?php echo Helpers::e($formatDateTimeLocal($value('mostrar_fim'))); ?>">
                </label>
                <label>
                    Prioridade
                    <input type="number" name="prioridade" min="0" value="<?php echo Helpers::e((string) $value('prioridade', 0)); ?>">
                </label>
                <label>
                    Link URL
                    <input type="text" name="link_url" value="<?php echo Helpers::e($value('link_url')); ?>" maxlength="255">
                </label>
                <label>
                    Link rótulo
                    <input type="text" name="link_rotulo" value="<?php echo Helpers::e($value('link_rotulo')); ?>" maxlength="80">
                </label>
                <label>
                    Status
                    <select name="status">
                        <?php foreach ($statusOptions as $key => $text): ?>
                            <option value="<?php echo Helpers::e($key); ?>" <?php echo $avisoStatus === $key ? 'selected' : ''; ?>><?php echo Helpers::e($text); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="permitir_ocultar" value="1" <?php echo !empty($value('permitir_ocultar', 1)) ? 'checked' : ''; ?>>
                    Permitir ocultar
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="destaque" value="1" <?php echo !empty($value('destaque')) ? 'checked' : ''; ?>>
                    Destaque
                </label>
            </div>

            <div class="full" style="margin-top:16px;">
                <small class="muted">Avisos com status <strong>Enviado</strong> aparecem para os alunos automaticamente dentro do período configurado.</small>
            </div>

            <?php $cancelUrl = '/admin/avisos'; ?>
            <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
        </form>
    </section>
</section>

<script>
(function () {
    var tipoSelect = document.getElementById('aviso-tipo-destino');
    var cursoSelect = document.getElementById('aviso-curso-evento-id');
    var alunoIdInput = document.getElementById('aviso-usuario-id');
    var alunoBusca = document.getElementById('aviso-aluno-busca');
    var alunoSugestoes = document.getElementById('aviso-aluno-sugestoes');
    var alunoLimpar = document.getElementById('aviso-aluno-limpar');
    var alunoResumo = document.getElementById('aviso-aluno-resumo');
    var alunoResumoNome = document.getElementById('aviso-aluno-resumo-nome');
    var alunoResumoEmail = document.getElementById('aviso-aluno-resumo-email');
    var alunoResumoCpf = document.getElementById('aviso-aluno-resumo-cpf');
    var alunoResumoCursos = document.getElementById('aviso-aluno-resumo-cursos');
    var blocoCurso = document.getElementById('aviso-curso-bloco');
    var blocoAluno = document.getElementById('aviso-aluno-bloco');
    var timerBusca = null;
    var hasAlunoSelecionado = <?php echo $alunoSelecionado ? 'true' : 'false'; ?>;
    var ultimaBusca = '';

    function normalizar(texto) {
        return String(texto || '').toLowerCase().trim();
    }

    function escapeHtml(texto) {
        return String(texto || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toggle(elemento, ocultar) {
        if (!elemento) {
            return;
        }
        elemento.style.display = ocultar ? 'none' : '';
    }

    function atualizarBlocos() {
        var tipo = tipoSelect ? tipoSelect.value : '<?php echo Helpers::e($tipoDestino); ?>';
        var precisaCurso = tipo === 'alunos_curso' || tipo === 'aluno_curso';
        var precisaAluno = tipo === 'aluno_curso' || tipo === 'aluno_individual';

        toggle(blocoCurso, !precisaCurso);
        toggle(blocoAluno, !precisaAluno);
    }

    function atualizarResumo(aluno) {
        if (!alunoResumo) {
            return;
        }
        if (!aluno) {
            alunoResumo.style.display = 'none';
            return;
        }
        alunoResumo.style.display = '';
        if (alunoResumoNome) alunoResumoNome.textContent = aluno.nome || 'Aluno selecionado';
        if (alunoResumoEmail) alunoResumoEmail.textContent = aluno.email || '';
        if (alunoResumoCpf) alunoResumoCpf.textContent = aluno.cpf ? 'CPF ' + aluno.cpf : '';
        if (alunoResumoCursos) alunoResumoCursos.textContent = aluno.cursos_confirmados_titulos ? 'Cursos confirmados: ' + aluno.cursos_confirmados_titulos : '';
    }

    function selecionarAluno(aluno) {
        if (!alunoIdInput || !alunoBusca) {
            return;
        }
        alunoIdInput.value = String(aluno.id || '');
        alunoBusca.value = aluno.label || aluno.nome || '';
        ultimaBusca = aluno.busca || normalizar(alunoBusca.value);
        if (alunoSugestoes) {
            alunoSugestoes.hidden = true;
            alunoSugestoes.innerHTML = '';
        }
        atualizarResumo(aluno);
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
        lista.slice(0, 12).forEach(function (aluno) {
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'avisos-autocomplete__item';
            botao.innerHTML = '<strong>' + escapeHtml(aluno.nome || 'Aluno') + '</strong>' +
                '<span>' + escapeHtml(aluno.email || '') + '</span>' +
                '<small>' + escapeHtml(aluno.cpf ? 'CPF ' + aluno.cpf : '') + '</small>' +
                '<small>' + escapeHtml(aluno.cursos_confirmados_titulos ? 'Cursos: ' + aluno.cursos_confirmados_titulos : 'Sem curso confirmado') + '</small>';
            botao.addEventListener('click', function () {
                selecionarAluno(aluno);
            });
            alunoSugestoes.appendChild(botao);
        });
        alunoSugestoes.hidden = false;
    }

    function buscarAlunos() {
        if (!alunoSugestoes || !alunoBusca) {
            return;
        }
        var termo = normalizar(alunoBusca.value);
        var cursoId = cursoSelect ? String(cursoSelect.value || '') : '';
        var tipo = tipoSelect ? String(tipoSelect.value || '') : 'todos_alunos';

        if (termo.length < 2) {
            alunoSugestoes.hidden = true;
            alunoSugestoes.innerHTML = '';
            return;
        }

        var params = new URLSearchParams();
        params.set('q', termo);
        params.set('limit', '12');
        params.set('tipo_destino', tipo);
        if (cursoId) {
            params.set('curso_evento_id', cursoId);
        }

        fetch('/admin/avisos/alunos?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('Falha ao buscar alunos.'));
        })
        .then(function (payload) {
            if (!payload || !payload.ok || !Array.isArray(payload.alunos)) {
                throw new Error('Resposta inválida.');
            }
            if (normalizar(alunoBusca.value) !== termo) {
                return;
            }
            montarSugestoes(payload.alunos);
        })
        .catch(function () {
            if (normalizar(alunoBusca.value) === termo) {
                alunoSugestoes.hidden = false;
                alunoSugestoes.innerHTML = '<div class="avisos-autocomplete__empty">Não foi possível buscar alunos agora.</div>';
            }
        });
    }

    if (tipoSelect) {
        tipoSelect.addEventListener('change', function () {
            atualizarBlocos();
            buscarAlunos();
        });
    }

    if (cursoSelect) {
        cursoSelect.addEventListener('change', function () {
            buscarAlunos();
        });
    }

    if (alunoBusca) {
        alunoBusca.addEventListener('input', function () {
            if (alunoIdInput) {
                alunoIdInput.value = '';
            }
            if (timerBusca) {
                window.clearTimeout(timerBusca);
            }
            timerBusca = window.setTimeout(buscarAlunos, 250);
        });

        alunoBusca.addEventListener('focus', function () {
            if (normalizar(alunoBusca.value).length >= 2) {
                buscarAlunos();
            }
        });

        alunoBusca.addEventListener('blur', function () {
            window.setTimeout(function () {
                if (alunoSugestoes) {
                    alunoSugestoes.hidden = true;
                }
            }, 180);
        });
    }

    if (alunoLimpar) {
        alunoLimpar.addEventListener('click', function () {
            if (alunoIdInput) {
                alunoIdInput.value = '';
            }
            if (alunoBusca) {
                alunoBusca.value = '';
                alunoBusca.focus();
            }
            atualizarResumo(null);
            if (alunoSugestoes) {
                alunoSugestoes.hidden = true;
                alunoSugestoes.innerHTML = '';
            }
        });
    }

    atualizarBlocos();
    if (hasAlunoSelecionado) {
        atualizarResumo({
            nome: <?php echo json_encode((string) ($alunoSelecionado['nome'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
            email: <?php echo json_encode((string) ($alunoSelecionado['email'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
            cpf: <?php echo json_encode((string) ($alunoSelecionado['cpf'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
            cursos_confirmados_titulos: <?php echo json_encode((string) ($alunoSelecionado['cursos_confirmados_titulos'] ?? ''), JSON_UNESCAPED_UNICODE); ?>
        });
    }
})();
</script>
