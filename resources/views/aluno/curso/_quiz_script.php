<?php
/**
 * Script compartilhado da execução do quiz na área do aluno.
 *
 * Usado pelos partials de quiz dos templates padrão e v4-claude: rascunho
 * automático, marcação para revisão, índice de questões, cronômetro conferido
 * no servidor e envio (manual ou automático por expiração do tempo).
 *
 * Variáveis esperadas: $tentativaAtiva, $itemId, $inscricaoId, $cursoId,
 * $turmaId, $temBlocos.
 */
?>
        <script>
        (function () {
            var form      = document.getElementById('quiz-form');
            var saveBtn   = document.getElementById('quiz-save-btn');
            var submitBtn = document.getElementById('quiz-submit-btn');
            if (!form) { return; }

            var tentativaId = <?php echo (int) $tentativaAtiva['id']; ?>;
            var itemId      = <?php echo $itemId; ?>;
            var inscricaoId = <?php echo $inscricaoId; ?>;
            var cursoId     = <?php echo $cursoId; ?>;
            var turmaId     = <?php echo $turmaId > 0 ? $turmaId : 0; ?>;
            var temBlocos   = <?php echo $temBlocos ? 'true' : 'false'; ?>;
            var enviando    = false;

            function csrfToken() {
                var campo = form.querySelector('input[name="_token"], input[name="csrf_token"]');
                return campo ? campo.value : '';
            }

            function coletarRespostas() {
                var dados = { respostas: {}, discursivas: {}, revisoes: {} };
                var formData = new FormData(form);
                formData.forEach(function (valor, chave) {
                    var objetiva = chave.match(/^respostas\[(\d+)\]$/);
                    if (objetiva) { dados.respostas[objetiva[1]] = valor; return; }
                    var discursiva = chave.match(/^discursivas\[(\d+)\]$/);
                    if (discursiva) { dados.discursivas[discursiva[1]] = valor; return; }
                });
                Array.prototype.forEach.call(form.querySelectorAll('.quiz-revisao'), function (caixa) {
                    dados.revisoes[caixa.getAttribute('data-pergunta')] = caixa.checked ? 1 : 0;
                });
                return dados;
            }

            function salvarRascunho(aoSair) {
                var dados = coletarRespostas();
                var opcoes = {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({
                        tentativa_id: tentativaId,
                        item_id: itemId,
                        inscricao_id: inscricaoId,
                        curso_id: cursoId,
                        turma_id: turmaId,
                        respostas: dados.respostas,
                        discursivas: dados.discursivas,
                        revisoes: dados.revisoes,
                        _token: csrfToken()
                    })
                };
                // keepalive garante a gravação mesmo se a aba estiver fechando.
                if (aoSair) { opcoes.keepalive = true; }
                return fetch('/aluno/cursos/quiz/rascunho', opcoes).then(function (r) { return r.json(); });
            }

            if (saveBtn) {
                saveBtn.addEventListener('click', function () {
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Salvando...';
                    salvarRascunho().then(function (data) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = data && data.ok ? 'Salvo!' : 'Erro ao salvar';
                        if (data && data.expirada) { window.location.reload(); return; }
                        setTimeout(function () { saveBtn.textContent = 'Salvar e continuar depois'; }, 2000);
                    }).catch(function () {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Erro ao salvar';
                    });
                });
            }

            // ---------------- Salvamento automático ----------------
            // Cada resposta é gravada logo após ser dada: sair da página nunca
            // pode custar o trabalho já feito.
            var autosaveTimer = null;
            var autosaveAviso = document.getElementById('quiz-autosave');

            function avisarAutosave(texto, erro) {
                if (!autosaveAviso) { return; }
                autosaveAviso.textContent = texto;
                autosaveAviso.style.color = erro ? '#dc2626' : '';
            }

            function salvarAgora(aoSair) {
                return salvarRascunho(aoSair).then(function (data) {
                    if (data && data.ok) {
                        avisarAutosave('Respostas salvas');
                    } else if (data && data.expirada) {
                        window.location.reload();
                    } else {
                        avisarAutosave('Não foi possível salvar agora. Tentaremos de novo.', true);
                    }
                    return data;
                }).catch(function () {
                    avisarAutosave('Sem conexão. Suas respostas serão salvas assim que voltar.', true);
                });
            }

            function agendarAutosave(atraso) {
                if (autosaveTimer) { clearTimeout(autosaveTimer); }
                autosaveTimer = setTimeout(function () {
                    avisarAutosave('Salvando…');
                    salvarAgora(false);
                }, atraso);
            }

            form.addEventListener('change', function (evento) {
                var alvo = evento.target;
                if (alvo && alvo.type === 'radio') { agendarAutosave(600); }
            });
            form.addEventListener('input', function (evento) {
                var alvo = evento.target;
                if (alvo && String(alvo.tagName).toLowerCase() === 'textarea') { agendarAutosave(2000); }
            });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) { salvarAgora(true); }
            });
            window.addEventListener('pagehide', function () { salvarAgora(true); });

            // ---------------- Índice de questões ----------------
            var itensIndice = {};
            Array.prototype.forEach.call(document.querySelectorAll('[data-indice-pergunta]'), function (link) {
                itensIndice[link.getAttribute('data-indice-pergunta')] = link;
            });

            function estaRespondida(bloco) {
                if (bloco.getAttribute('data-tipo') === 'discursiva') {
                    var area = bloco.querySelector('textarea');
                    return !!(area && area.value.trim().length >= 3);
                }
                return !!bloco.querySelector('input[type="radio"]:checked');
            }

            function atualizarIndice() {
                var respondidas = 0, marcadas = 0, total = 0;
                Array.prototype.forEach.call(form.querySelectorAll('[data-pergunta-id]'), function (bloco) {
                    total++;
                    var pid       = bloco.getAttribute('data-pergunta-id');
                    var link      = itensIndice[pid];
                    var respondida = estaRespondida(bloco);
                    var revisao   = bloco.querySelector('.quiz-revisao');
                    var marcada   = !!(revisao && revisao.checked);

                    if (respondida) { respondidas++; }
                    if (marcada) { marcadas++; }

                    if (link) {
                        link.style.background   = respondida ? 'rgba(34,197,94,0.18)' : 'transparent';
                        link.style.borderColor  = marcada ? '#f59e0b' : 'var(--color-border, #e5e7eb)';
                        link.style.borderWidth  = marcada ? '2px' : '1px';
                        link.setAttribute('aria-current', respondida ? 'true' : 'false');
                        link.title = (respondida ? 'Respondida' : 'Pendente') + (marcada ? ' · marcada para revisão' : '');
                    }
                });

                var resumo = document.getElementById('quiz-indice-resumo');
                if (resumo) {
                    resumo.textContent = respondidas + ' de ' + total + ' respondidas · '
                        + (total - respondidas) + ' pendente(s) · ' + marcadas + ' marcada(s) para revisão';
                }
            }

            form.addEventListener('change', atualizarIndice);
            form.addEventListener('input', atualizarIndice);
            atualizarIndice();

            // Marcação de revisão é persistida sem alterar a resposta.
            Array.prototype.forEach.call(form.querySelectorAll('.quiz-revisao'), function (caixa) {
                caixa.addEventListener('change', function () {
                    fetch('/aluno/cursos/quiz/revisao', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                        body: JSON.stringify({
                            tentativa_id: tentativaId,
                            pergunta_id: parseInt(caixa.getAttribute('data-pergunta'), 10),
                            marcada: caixa.checked ? 1 : 0,
                            _token: csrfToken()
                        })
                    }).catch(function () { /* a marcação também vai junto no rascunho */ });
                });
            });

            // ---------------- Envio ----------------
            form.addEventListener('submit', function (event) {
                if (enviando) { return; }
                var faltando = [];
                Array.prototype.forEach.call(form.querySelectorAll('[data-pergunta-id]'), function (bloco) {
                    if (!estaRespondida(bloco)) { faltando.push(bloco); }
                });
                if (faltando.length > 0) {
                    event.preventDefault();
                    alert('Responda todas as questões antes de enviar. Faltam ' + faltando.length + '.');
                    faltando[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                enviando = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Enviando...';
                }
            });

            // ---------------- Cronômetro (prazo do servidor) ----------------
            var cronometro = document.getElementById('quiz-cronometro');
            if (cronometro) {
                var restante = parseInt(cronometro.getAttribute('data-restante'), 10) || 0;
                var valor    = document.getElementById('quiz-cronometro-valor');
                var encerrando = false;

                function formatar(segundos) {
                    if (segundos < 0) { segundos = 0; }
                    var h = Math.floor(segundos / 3600);
                    var m = Math.floor((segundos % 3600) / 60);
                    var s = segundos % 60;
                    return [h, m, s].map(function (n) { return String(n).padStart(2, '0'); }).join(':');
                }

                function encerrarPorTempo() {
                    if (encerrando) { return; }
                    encerrando = true;
                    valor.textContent = '00:00:00';
                    // Salva o que estiver na tela e deixa o servidor aplicar a
                    // regra de expiração (envio automático).
                    salvarRascunho().catch(function () {}).then(function () {
                        return fetch('/aluno/cursos/quiz/tempo', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                            body: JSON.stringify({ tentativa_id: tentativaId, _token: csrfToken() })
                        });
                    }).catch(function () {}).then(function () {
                        alert('O tempo da prova terminou. Suas respostas salvas foram enviadas automaticamente.');
                        window.location.reload();
                    });
                }

                function tique() {
                    restante--;
                    valor.textContent = formatar(restante);
                    if (restante <= 300) { cronometro.style.borderColor = '#dc2626'; }
                    if (restante <= 0) { encerrarPorTempo(); }
                }

                valor.textContent = formatar(restante);
                var intervalo = setInterval(tique, 1000);

                // O relógio do navegador é só visual: a cada 60s o servidor
                // confirma o tempo real restante.
                setInterval(function () {
                    if (encerrando) { return; }
                    fetch('/aluno/cursos/quiz/tempo', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                        body: JSON.stringify({ tentativa_id: tentativaId, _token: csrfToken() })
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (!data || !data.ok) { return; }
                        if (data.encerrada) {
                            clearInterval(intervalo);
                            encerrando = true;
                            window.location.reload();
                            return;
                        }
                        if (data.tempo && typeof data.tempo.segundos_restantes === 'number') {
                            restante = data.tempo.segundos_restantes;
                        }
                    }).catch(function () {});
                }, 60000);

                // Rascunho automático a cada 2 minutos em provas cronometradas.
                setInterval(function () {
                    if (!encerrando) { salvarRascunho().catch(function () {}); }
                }, 120000);
            }

            // ---------------- Assistente passo a passo ----------------
            // Só para quizzes curtos: em prova longa a navegação é por blocos.
            function initQuizWizard() {
                if (temBlocos) { return; }
                var perguntasWrap = document.getElementById('quiz-perguntas');
                var progress      = document.getElementById('quiz-progress');
                var progressFill  = document.getElementById('quiz-progress-fill');
                var progressText  = document.getElementById('quiz-progress-text');
                var dotsWrap      = document.getElementById('quiz-progress-dots');
                var prevBtn       = document.getElementById('quiz-prev-btn');
                var nextBtn       = document.getElementById('quiz-next-btn');
                if (!perguntasWrap || !progress || !prevBtn || !nextBtn) { return; }

                var steps = Array.prototype.slice.call(perguntasWrap.querySelectorAll('.conteudo-quiz-pergunta'));
                if (steps.length <= 1) { return; }

                var current = steps.length - 1;
                for (var i = 0; i < steps.length; i++) {
                    if (!estaRespondida(steps[i])) { current = i; break; }
                }
                var maxReached = current;

                var dots = steps.map(function (step, i) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'conteudo-quiz-progress__dot';
                    dot.textContent = String(i + 1);
                    dot.setAttribute('aria-label', 'Ir para a pergunta ' + (i + 1));
                    dot.addEventListener('click', function () {
                        if (i <= maxReached) { current = i; render(); }
                    });
                    dotsWrap.appendChild(dot);
                    return dot;
                });

                function render() {
                    steps.forEach(function (step, i) {
                        step.style.display = i === current ? '' : 'none';
                    });
                    progressFill.style.width = (((current + 1) / steps.length) * 100) + '%';
                    progressText.textContent = 'Pergunta ' + (current + 1) + ' de ' + steps.length;
                    dots.forEach(function (dot, i) {
                        dot.classList.toggle('is-current', i === current);
                        dot.classList.toggle('is-answered', estaRespondida(steps[i]));
                        dot.disabled = i > maxReached;
                    });
                    // .button-link define display:inline-flex, que sobrescreve o
                    // [hidden] nativo — por isso alterna via style.display.
                    prevBtn.style.display = current === 0 ? 'none' : '';
                    var isLast = current === steps.length - 1;
                    nextBtn.style.display = isLast ? 'none' : '';
                    if (submitBtn) { submitBtn.style.display = isLast ? '' : 'none'; }
                }

                prevBtn.addEventListener('click', function () {
                    if (current > 0) { current--; render(); }
                });

                nextBtn.addEventListener('click', function () {
                    if (!estaRespondida(steps[current])) {
                        alert('Responda esta questão antes de continuar.');
                        return;
                    }
                    if (current < steps.length - 1) {
                        current++;
                        if (current > maxReached) { maxReached = current; }
                        render();
                    }
                });

                progress.hidden = false;
                prevBtn.hidden  = false;
                nextBtn.hidden  = false;
                render();
            }

            initQuizWizard();
        })();
        </script>
