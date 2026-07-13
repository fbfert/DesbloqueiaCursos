(function () {
    'use strict';

    // Dispara o envio de um comunicado de turma em lotes pequenos.
    // O servidor envia alguns e-mails por requisicao e devolve o placar; repetimos
    // ate nao restar nenhum pendente. Isso evita estourar o tempo limite do PHP
    // quando a turma tem dezenas de alunos.

    var ENDPOINT = '/admin/turmas/emails/processar';
    var LOTE = 10;
    var MAX_FALHAS_SEGUIDAS = 3;

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    }

    function init() {
        var container = document.querySelector('[data-turma-email-progresso]');
        if (!container) {
            return;
        }

        var pendentes = parseInt(container.getAttribute('data-pendentes'), 10) || 0;
        if (pendentes <= 0) {
            return;
        }

        var loteId = parseInt(container.getAttribute('data-lote-id'), 10) || 0;
        var token = container.getAttribute('data-token') || '';
        var total = parseInt(container.getAttribute('data-total'), 10) || 0;

        var barra = container.querySelector('[data-progresso-barra]');
        var texto = container.querySelector('[data-progresso-texto]');
        var aviso = container.querySelector('[data-progresso-aviso]');
        var falhasSeguidas = 0;

        function atualizar(resumo) {
            var processados = resumo.enviados + resumo.falhas;
            var percentual = total > 0 ? Math.round((processados / total) * 100) : 100;

            if (barra) {
                barra.style.width = percentual + '%';
            }

            if (texto) {
                texto.textContent = 'Enviando... ' + processados + ' de ' + total
                    + ' (' + resumo.enviados + ' enviado(s), ' + resumo.falhas + ' falha(s))';
            }
        }

        function concluir(resumo) {
            if (barra) {
                barra.style.width = '100%';
            }

            if (texto) {
                texto.textContent = 'Envio concluído: ' + resumo.enviados + ' enviado(s), '
                    + resumo.falhas + ' falha(s), de ' + total + ' destinatário(s).';
            }

            if (aviso) {
                aviso.hidden = true;
            }

            // Recarrega para mostrar o status final de cada destinatário na tabela.
            window.setTimeout(function () {
                window.location.reload();
            }, 1200);
        }

        function interromper(mensagem) {
            if (texto) {
                texto.textContent = mensagem;
            }

            if (aviso) {
                aviso.hidden = true;
            }
        }

        function processar() {
            var corpo = new URLSearchParams();
            corpo.set('_token', token);
            corpo.set('lote_id', String(loteId));
            corpo.set('limite', String(LOTE));

            window.fetch(ENDPOINT, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: corpo.toString()
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha na requisição (' + response.status + ').');
                }

                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.ok || !payload.resumo) {
                    throw new Error(payload && payload.message ? payload.message : 'Resposta inválida do servidor.');
                }

                falhasSeguidas = 0;
                atualizar(payload.resumo);

                if (payload.concluido) {
                    concluir(payload.resumo);
                    return;
                }

                // Nenhum e-mail saiu neste lote e ainda restam pendentes: algo travou
                // (SMTP fora do ar, por exemplo). Melhor parar do que girar em falso.
                if (payload.enviados_agora === 0 && payload.falhas_agora === 0) {
                    interromper('O envio foi interrompido: nenhum e-mail pôde ser processado. '
                        + 'Verifique a configuração de SMTP e reenvie os pendentes pela fila de e-mails.');
                    return;
                }

                processar();
            }).catch(function (erro) {
                falhasSeguidas++;

                if (falhasSeguidas >= MAX_FALHAS_SEGUIDAS) {
                    interromper('O envio foi interrompido após várias tentativas: ' + erro.message + ' '
                        + 'Os e-mails que restaram continuam pendentes e podem ser reenviados pela fila de e-mails.');
                    return;
                }

                window.setTimeout(processar, 2000);
            });
        }

        processar();
    }

    ready(init);
})();
