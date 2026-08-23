(function () {
    'use strict';

    /**
     * Norminha — cliente de chat.
     *
     * Preserva o comportamento legado: launcher, minimizar/reabrir, a chave
     * localStorage 'norminha_tutor_minimized_v1', a limpeza das chaves antigas,
     * a troca de avatar idle/speaking e o áudio contextual.
     *
     * Regras que valem a pena não esquecer:
     *
     * - NADA de innerHTML com texto do servidor. Toda mensagem entra por
     *   textContent. Se um dia a IA devolver "<img onerror=...>", ele aparece
     *   como texto na tela, que é exatamente o que deve acontecer.
     * - Links só vêm do payload do servidor E são revalidados aqui: precisam
     *   ser caminho interno começando por "/". O servidor já valida; esta é a
     *   segunda barreira, do lado de quem renderiza.
     * - O token CSRF viaja DENTRO do JSON, no campo _token. App\Core\Request
     *   decodifica application/json antes do CsrfMiddleware validar, então não
     *   é preciso cabeçalho customizado.
     * - usuario_id nunca é enviado. A identidade é a sessão.
     */

    var STORAGE_KEY = 'norminha_tutor_minimized_v1';
    var LEGACY_KEYS = ['norminha_tutor_closed_until', 'norminha_tutor_closed_v2', 'norminha_tutor_closed'];
    var ENDPOINT = '/api/norminha/chat';
    var TIMEOUT_MS = 20000;
    var LIMITE_MENSAGEM = 2000;

    function canUseStorage() {
        try {
            return !!window.localStorage;
        } catch (error) {
            return false;
        }
    }

    function readMinimized() {
        if (!canUseStorage()) { return false; }
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function writeMinimized(valor) {
        if (!canUseStorage()) { return; }
        try {
            if (valor) {
                window.localStorage.setItem(STORAGE_KEY, '1');
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (error) {
            // Preferência é conveniência: falhar aqui não pode quebrar o chat.
        }
    }

    function clearLegacyState() {
        if (!canUseStorage()) { return; }
        try {
            for (var i = 0; i < LEGACY_KEYS.length; i += 1) {
                window.localStorage.removeItem(LEGACY_KEYS[i]);
            }
        } catch (error) {
            // idem
        }
    }

    /** Caminho interno, sem esquema e sem "//" — segunda barreira de link. */
    function urlInternaSegura(url) {
        if (typeof url !== 'string' || url.length === 0) { return false; }
        if (url.charAt(0) !== '/') { return false; }
        if (url.indexOf('//') === 0) { return false; }
        if (/^[a-z][a-z0-9+.-]*:/i.test(url)) { return false; }
        return true;
    }

    function initTutor() {
        var container = document.getElementById('norminha-tutor');
        if (!container) { return; }

        var painel = container.querySelector('[data-norminha-card]');
        var closeButton = container.querySelector('[data-norminha-close]');
        var launcherButton = container.querySelector('[data-norminha-launcher]');
        var audioButton = container.querySelector('[data-norminha-audio-button]');
        var audio = container.querySelector('[data-norminha-audio]');
        var avatar = container.querySelector('[data-avatar-image]');
        var launcherAvatar = container.querySelector('[data-norminha-launcher-avatar]');
        var launcherFallback = container.querySelector('[data-norminha-launcher-fallback]');
        var listaMensagens = container.querySelector('[data-norminha-mensagens]');
        var form = container.querySelector('[data-norminha-form]');
        var input = container.querySelector('[data-norminha-input]');
        var botaoEnviar = container.querySelector('[data-norminha-enviar]');
        var pensando = container.querySelector('[data-norminha-pensando]');
        var quick = container.querySelector('[data-norminha-quick]');

        var avatarIdle = container.getAttribute('data-avatar-idle') || '';
        var avatarSpeaking = container.getAttribute('data-avatar-speaking') || '';
        var csrf = container.getAttribute('data-csrf') || '';

        var conversationId = null;
        var enviando = false;

        // O que o aluno pediu antes de a Norminha perguntar de qual curso se
        // trata. Depois da escolha, e este pedido que e refeito.
        var pedidoPendente = null;

        clearLegacyState();

        // ---------------------------------------------------------------
        // Avatar (comportamento legado preservado)
        // ---------------------------------------------------------------

        if (avatar) {
            avatar.addEventListener('error', function () {
                avatar.style.display = 'none';
            });
        }
        if (launcherAvatar && launcherFallback) {
            launcherAvatar.addEventListener('error', function () {
                launcherAvatar.style.display = 'none';
                launcherFallback.style.display = '';
            });
            launcherFallback.style.display = 'none';
        }

        function setAvatarState(estado) {
            if (!avatar) { return; }
            var alvo = estado === 'idle' ? avatarIdle : avatarSpeaking;
            if (alvo && avatar.getAttribute('src') !== alvo) {
                avatar.setAttribute('src', alvo);
            }
            container.setAttribute('data-estado-avatar', estado);
        }

        // ---------------------------------------------------------------
        // Minimizar / reabrir
        // ---------------------------------------------------------------

        function sincronizarVisibilidade(minimizado) {
            if (minimizado) {
                document.documentElement.classList.add('norminha-tutor-minimized');
            } else {
                document.documentElement.classList.remove('norminha-tutor-minimized');
            }
            if (launcherButton) {
                launcherButton.setAttribute('aria-expanded', minimizado ? 'false' : 'true');
                launcherButton.setAttribute('aria-label', minimizado ? 'Abrir a Norminha' : 'Minimizar a Norminha');
            }
        }

        function minimizar() {
            writeMinimized(true);
            sincronizarVisibilidade(true);
            if (audio && !audio.paused) { audio.pause(); }
            if (launcherButton) { launcherButton.focus(); }
        }

        function restaurar() {
            writeMinimized(false);
            sincronizarVisibilidade(false);
            if (input) { input.focus(); }
        }

        sincronizarVisibilidade(readMinimized());

        if (closeButton) {
            closeButton.addEventListener('click', minimizar);
        }
        if (launcherButton) {
            launcherButton.addEventListener('click', function () {
                if (document.documentElement.classList.contains('norminha-tutor-minimized')) {
                    restaurar();
                } else {
                    minimizar();
                }
            });
        }

        container.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !document.documentElement.classList.contains('norminha-tutor-minimized')) {
                minimizar();
            }
        });

        // ---------------------------------------------------------------
        // Áudio (comportamento legado preservado)
        // ---------------------------------------------------------------

        if (audioButton && audio) {
            var rotuloBase = container.getAttribute('data-texto-botao') || 'Ouvir orientação';
            audioButton.addEventListener('click', function () {
                if (audio.paused) {
                    audio.play().then(function () {
                        audioButton.textContent = 'Pausar';
                        setAvatarState('speaking');
                    }).catch(function () {
                        audioButton.textContent = rotuloBase;
                    });
                } else {
                    audio.pause();
                    audioButton.textContent = rotuloBase;
                    setAvatarState('idle');
                }
            });
            audio.addEventListener('ended', function () {
                audioButton.textContent = rotuloBase;
                setAvatarState('idle');
            });
        }

        // ---------------------------------------------------------------
        // Render das mensagens — textContent, nunca innerHTML
        // ---------------------------------------------------------------

        function criarBolha(classe) {
            var div = document.createElement('div');
            div.className = 'norminha-tutor__msg ' + classe;
            return div;
        }

        function adicionarTexto(bolha, texto) {
            // Quebras de linha viram <br> por criação de nó, não por markup.
            var partes = String(texto).split('\n');
            var p = document.createElement('p');
            p.className = 'norminha-tutor__text';
            for (var i = 0; i < partes.length; i += 1) {
                if (i > 0) { p.appendChild(document.createElement('br')); }
                p.appendChild(document.createTextNode(partes[i]));
            }
            bolha.appendChild(p);
        }

        function adicionarAcoes(bolha, acoes) {
            if (!acoes || !acoes.length) { return; }
            var caixa = document.createElement('div');
            caixa.className = 'norminha-tutor__acoes';
            var incluidas = 0;

            for (var i = 0; i < acoes.length; i += 1) {
                var acao = acoes[i];
                if (!acao || !urlInternaSegura(acao.url) || !acao.label) { continue; }
                var link = document.createElement('a');
                link.className = 'norminha-tutor__acao';
                link.setAttribute('href', acao.url);
                link.textContent = String(acao.label);
                caixa.appendChild(link);
                incluidas += 1;
            }

            if (incluidas > 0) { bolha.appendChild(caixa); }
        }

        function adicionarFeedback(bolha, messageId) {
            if (!messageId) { return; }
            var caixa = document.createElement('div');
            caixa.className = 'norminha-tutor__feedback';

            var pergunta = document.createElement('span');
            pergunta.className = 'norminha-tutor__feedback-label';
            pergunta.textContent = 'Isso ajudou?';
            caixa.appendChild(pergunta);

            ['sim', 'nao'].forEach(function (valor) {
                var botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'norminha-tutor__feedback-botao';
                botao.textContent = valor === 'sim' ? 'Sim' : 'Não';
                botao.setAttribute('aria-label', valor === 'sim' ? 'Resposta útil' : 'Resposta não útil');
                botao.addEventListener('click', function () {
                    enviarFeedback(messageId, valor === 'sim');
                    caixa.textContent = 'Obrigada pelo retorno.';
                });
                caixa.appendChild(botao);
            });

            bolha.appendChild(caixa);
        }

        function rolarParaFim() {
            if (!listaMensagens) { return; }
            // Rola só a lista; a página do aluno não pode pular.
            listaMensagens.scrollTop = listaMensagens.scrollHeight;
        }

        function mostrarMensagemAluno(texto) {
            var bolha = criarBolha('norminha-tutor__msg--aluno');
            adicionarTexto(bolha, texto);
            listaMensagens.appendChild(bolha);
            rolarParaFim();
        }

        /**
         * Escolha de curso quando o aluno tem mais de um.
         *
         * O servidor SEMPRE mandou a lista em `opcoes` — e ate 23/08/2026 este
         * arquivo a descartava. O resultado era um beco sem saida: a Norminha
         * perguntava "sobre qual deles quer falar?", listava os nomes como texto
         * e nao oferecia nenhuma forma de responder. Clicar nas acoes rapidas ou
         * digitar o nome do curso levava a mesma pergunta, para sempre.
         *
         * A escolha e gravada em data-inscricao-id do proprio container, que e de
         * onde hints() le. Assim ela vale para todas as mensagens seguintes sem
         * precisar de estado paralelo.
         */
        function escolherCurso(inscricaoId, rotulo) {
            container.setAttribute('data-inscricao-id', String(inscricaoId));

            // O nome do curso vira a fala do aluno: e o que ele acabou de dizer.
            mostrarMensagemAluno(rotulo);

            // Repete a pergunta original, agora sem ambiguidade. Sem pergunta
            // anterior (o aluno abriu o chat e caiu direto na escolha), pede o
            // panorama do curso escolhido.
            var repetir = pedidoPendente || { action: 'show_progress' };
            pedidoPendente = null;
            enviar(repetir, null);
        }

        function adicionarEscolhas(bolha, opcoes) {
            if (!opcoes || !opcoes.length) { return; }

            var caixa = document.createElement('div');
            caixa.className = 'norminha-tutor__escolhas';

            // Um aluno da casa tem dezenas de matriculas. Botao para cada uma
            // seria uma parede: mostram-se os mais recentes e diz-se como
            // chegar ao resto.
            var limite = Math.min(opcoes.length, 6);
            var incluidos = 0;

            for (var i = 0; i < limite; i += 1) {
                var opcao = opcoes[i];
                var id = opcao ? parseInt(opcao.inscricao_id, 10) : 0;
                if (!id || id <= 0 || !opcao.curso_titulo) { continue; }

                var botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'norminha-tutor__escolha';

                var titulo = document.createElement('span');
                titulo.className = 'norminha-tutor__escolha-curso';
                titulo.textContent = String(opcao.curso_titulo);
                botao.appendChild(titulo);

                if (opcao.turma_nome) {
                    var turma = document.createElement('span');
                    turma.className = 'norminha-tutor__escolha-turma';
                    turma.textContent = String(opcao.turma_nome);
                    botao.appendChild(turma);
                }

                (function (idEscolhido, rotuloEscolhido) {
                    botao.addEventListener('click', function () {
                        escolherCurso(idEscolhido, rotuloEscolhido);
                    });
                })(id, String(opcao.curso_titulo));

                caixa.appendChild(botao);
                incluidos += 1;
            }

            if (!incluidos) { return; }

            if (opcoes.length > incluidos) {
                var nota = document.createElement('p');
                nota.className = 'norminha-tutor__escolhas-nota';
                nota.textContent = 'Você tem ' + opcoes.length
                    + ' cursos ativos. Se o seu não está aqui, escreva o nome dele.';
                caixa.appendChild(nota);
            }

            bolha.appendChild(caixa);
        }

        function mostrarResposta(dados) {
            var bolha = criarBolha('norminha-tutor__msg--norminha');
            adicionarTexto(bolha, dados.message || '');
            adicionarEscolhas(bolha, dados.opcoes);
            adicionarAcoes(bolha, dados.actions);
            adicionarFeedback(bolha, dados.message_id);
            listaMensagens.appendChild(bolha);
            if (dados.avatar_state) { setAvatarState(dados.avatar_state === 'idle' ? 'idle' : 'speaking'); }
            rolarParaFim();
        }

        function mostrarErro(texto, permitirRepetir, repetir) {
            var bolha = criarBolha('norminha-tutor__msg--erro');
            adicionarTexto(bolha, texto);
            if (permitirRepetir && typeof repetir === 'function') {
                var botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'norminha-tutor__acao';
                botao.textContent = 'Tentar novamente';
                botao.addEventListener('click', function () {
                    bolha.parentNode && bolha.parentNode.removeChild(bolha);
                    repetir();
                });
                bolha.appendChild(botao);
            }
            listaMensagens.appendChild(bolha);
            rolarParaFim();
        }

        // ---------------------------------------------------------------
        // Envio
        // ---------------------------------------------------------------

        function hints() {
            var mapa = {
                route: container.getAttribute('data-rota') || '',
                inscricao_id: container.getAttribute('data-inscricao-id') || '',
                curso_id: container.getAttribute('data-curso-id') || '',
                turma_id: container.getAttribute('data-turma-id') || '',
                modulo_id: container.getAttribute('data-modulo-id') || '',
                item_id: container.getAttribute('data-item-id') || ''
            };
            var contexto = {};
            Object.keys(mapa).forEach(function (chave) {
                if (mapa[chave] !== '') {
                    contexto[chave] = chave === 'route' ? mapa[chave] : parseInt(mapa[chave], 10);
                }
            });
            return contexto;
        }

        function travar(travado) {
            enviando = travado;
            if (botaoEnviar) { botaoEnviar.disabled = travado; }
            if (input) { input.disabled = travado; }
            if (quick) {
                var chips = quick.querySelectorAll('button');
                for (var i = 0; i < chips.length; i += 1) { chips[i].disabled = travado; }
            }
            if (pensando) {
                pensando.hidden = !travado;
                pensando.setAttribute('aria-hidden', travado ? 'false' : 'true');
            }
        }

        function enviar(payload, ecoDoAluno) {
            if (enviando) { return; }
            travar(true);

            // Guardado para o caso de a resposta ser "de qual curso?": depois da
            // escolha, e esta pergunta que precisa ser refeita.
            pedidoPendente = payload;

            if (ecoDoAluno) { mostrarMensagemAluno(ecoDoAluno); }

            var corpo = {
                _token: csrf,
                context: hints()
            };
            if (payload.action) { corpo.action = payload.action; }
            if (payload.message) { corpo.message = payload.message; }
            if (conversationId) { corpo.conversation_id = conversationId; }

            // Uma tentativa só, com timeout. Repetir automaticamente poderia
            // gravar a mesma pergunta duas vezes; quem repete é o aluno.
            var controlador = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var expirou = window.setTimeout(function () {
                if (controlador) { controlador.abort(); }
            }, TIMEOUT_MS);

            var opcoes = {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(corpo)
            };
            if (controlador) { opcoes.signal = controlador.signal; }

            fetch(ENDPOINT, opcoes).then(function (resposta) {
                window.clearTimeout(expirou);
                return resposta.json().then(function (dados) {
                    return { status: resposta.status, dados: dados };
                }).catch(function () {
                    return { status: resposta.status, dados: null };
                });
            }).then(function (resultado) {
                travar(false);

                if (!resultado.dados) {
                    mostrarErro('Não consegui falar com o servidor agora.', true, function () {
                        enviar(payload, null);
                    });
                    return;
                }

                if (resultado.status === 401) {
                    mostrarErro('Sua sessão expirou. Recarregue a página e entre de novo.', false);
                    return;
                }
                if (resultado.status === 429) {
                    mostrarErro(resultado.dados.mensagem || 'Muitas mensagens seguidas. Aguarde um pouco.', false);
                    return;
                }
                if (!resultado.dados.ok) {
                    mostrarErro(resultado.dados.mensagem || 'Não consegui responder agora.', resultado.status >= 500, function () {
                        enviar(payload, null);
                    });
                    return;
                }

                if (resultado.dados.conversation_id) {
                    conversationId = resultado.dados.conversation_id;
                }
                mostrarResposta(resultado.dados);
            }).catch(function () {
                window.clearTimeout(expirou);
                travar(false);
                mostrarErro('A conexão demorou demais.', true, function () {
                    enviar(payload, null);
                });
            });
        }

        function enviarFeedback(messageId, util) {
            fetch('/api/norminha/feedback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ _token: csrf, message_id: messageId, useful: util })
            }).catch(function () {
                // Feedback é opcional: falhar aqui não merece alarme na tela.
            });
        }

        // ---------------------------------------------------------------
        // Eventos de entrada
        // ---------------------------------------------------------------

        if (form && input) {
            form.addEventListener('submit', function (evento) {
                evento.preventDefault();
                var texto = String(input.value || '').trim();
                if (texto === '' || texto.length > LIMITE_MENSAGEM) { return; }
                input.value = '';
                ajustarAltura();
                enviar({ message: texto }, texto);
            });

            input.addEventListener('keydown', function (evento) {
                // Enter envia; Shift+Enter quebra linha. Em tela pequena o
                // teclado virtual costuma mandar o próprio "enviar", então não
                // se rouba o Enter de quem está digitando em telefone.
                if (evento.key === 'Enter' && !evento.shiftKey && window.innerWidth > 640) {
                    evento.preventDefault();
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });

            function ajustarAltura() {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            }
            input.addEventListener('input', ajustarAltura);
        }

        if (quick) {
            quick.addEventListener('click', function (evento) {
                var alvo = evento.target;
                if (!alvo || !alvo.getAttribute) { return; }
                var acao = alvo.getAttribute('data-norminha-acao');
                if (!acao) { return; }
                enviar({ action: acao }, alvo.textContent);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTutor);
    } else {
        initTutor();
    }
})();
