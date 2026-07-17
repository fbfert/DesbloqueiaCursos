(function () {
    'use strict';

    // Editor visual para modelos de e-mail que são DOCUMENTOS HTML COMPLETOS.
    //
    // Estratégia: o shell do documento (prefixo até o fim de <body ...> e sufixo
    // a partir de </body>) é preservado por concatenação de strings e NUNCA passa
    // pelo editor. Apenas o conteúdo interno do <body> é editado, em um elemento
    // contenteditable que mantém o HTML original (tabelas, estilos inline,
    // imagens, links e placeholders) intacto, alterando somente o que o
    // administrador editar. Um modo "Código HTML completo" permite edição
    // avançada do documento inteiro.
    var SELECTOR = '[data-email-doc-editor]';
    var INIT_ATTR = 'data-doc-editor-initialized';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    }

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function exec(command, value) {
        try {
            document.execCommand(command, false, typeof value === 'undefined' ? null : value);
        } catch (error) {
            if (window.console && window.console.warn) {
                window.console.warn('[email-doc-editor] Comando não suportado: ' + command, error);
            }
        }
    }

    // Divide um documento HTML em prefixo (até o fim de <body ...>), conteúdo
    // interno do corpo e sufixo (a partir de </body>). Espelha a lógica do
    // App\Support\EmailHtmlDocument no PHP.
    function splitDocument(html) {
        html = String(html || '');
        var openMatch = /<body\b[^>]*>/i.exec(html);
        if (!openMatch) {
            return { prefix: '', inner: html, suffix: '', isFull: false };
        }

        var innerStart = openMatch.index + openMatch[0].length;
        var closeStart = html.toLowerCase().indexOf('</body', innerStart);
        if (closeStart === -1) {
            return { prefix: html.slice(0, innerStart), inner: html.slice(innerStart), suffix: '', isFull: true };
        }

        return {
            prefix: html.slice(0, innerStart),
            inner: html.slice(innerStart, closeStart),
            suffix: html.slice(closeStart),
            isFull: true
        };
    }

    // Placeholders válidos: {{chave_dupla}} ou {chave.simples}.
    var PLACEHOLDER_RE = /\{\{[a-zA-Z0-9_.]+\}\}|\{[a-zA-Z0-9_.]+\}/g;

    // Envolve cada placeholder em um token atômico não editável, para que a
    // formatação (cor/negrito/itálico) não consiga parti-lo. O literal original
    // fica em data-ph e é restaurado na serialização.
    function tokenizeElement(root) {
        if (!root) {
            return;
        }

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while ((node = walker.nextNode())) {
            // Não re-tokeniza dentro de um token já existente.
            if (node.parentNode && node.parentNode.classList && node.parentNode.classList.contains('email-ph-token')) {
                continue;
            }
            if (node.nodeValue && node.nodeValue.indexOf('{') !== -1) {
                textNodes.push(node);
            }
        }

        for (var i = 0; i < textNodes.length; i++) {
            var textNode = textNodes[i];
            var text = textNode.nodeValue;
            PLACEHOLDER_RE.lastIndex = 0;
            if (!PLACEHOLDER_RE.test(text)) {
                continue;
            }

            var frag = document.createDocumentFragment();
            var lastIndex = 0;
            PLACEHOLDER_RE.lastIndex = 0;
            var match;
            while ((match = PLACEHOLDER_RE.exec(text))) {
                if (match.index > lastIndex) {
                    frag.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
                }
                var token = document.createElement('span');
                token.className = 'email-ph-token';
                token.setAttribute('contenteditable', 'false');
                token.setAttribute('data-ph', match[0]);
                token.textContent = match[0];
                frag.appendChild(token);
                lastIndex = match.index + match[0].length;
            }
            if (lastIndex < text.length) {
                frag.appendChild(document.createTextNode(text.slice(lastIndex)));
            }
            if (textNode.parentNode) {
                textNode.parentNode.replaceChild(frag, textNode);
            }
        }
    }

    // Serializa o conteúdo do editor devolvendo os placeholders como texto literal
    // (sem os wrappers de token). Nenhuma marcação do editor é persistida.
    function serializeEditable(el) {
        if (!el) {
            return '';
        }
        var clone = el.cloneNode(true);
        var tokens = clone.querySelectorAll('.email-ph-token');
        for (var i = 0; i < tokens.length; i++) {
            var t = tokens[i];
            var literal = t.getAttribute('data-ph') || t.textContent || '';
            t.parentNode.replaceChild(document.createTextNode(literal), t);
        }
        return clone.innerHTML;
    }

    // Validação estrutural de placeholders (espelha App\Support\EmailPlaceholders).
    function structuralPlaceholderIssues(html) {
        html = String(html || '');
        var work = html.replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');
        var semValidos = work.replace(/\{\{[a-zA-Z0-9_.]+\}\}|\{[a-zA-Z0-9_.]+\}/g, '');
        var issues = [];
        if (/\{[^{}]*<[^{}]*>[^{}]*\}/.test(semValidos)) {
            issues.push('placeholder dividido por formatação/tag HTML');
        }
        if (/\{\{[a-zA-Z0-9_.]+\}(?!\})/.test(html)) {
            issues.push('chave dupla fechada com uma chave (ex.: {{nome})');
        }
        if (/(^|[^{])\{[a-zA-Z0-9_.]+\}\}/.test(html)) {
            issues.push('chave simples fechada com duas chaves (ex.: {nome}})');
        }
        if (/\{\{\s*\}\}/.test(html)) {
            issues.push('placeholder vazio {{}}');
        }
        return issues;
    }

    function initOne(wrapper) {
        if (!wrapper || wrapper.getAttribute(INIT_ATTR) === '1') {
            return;
        }
        wrapper.setAttribute(INIT_ATTR, '1');

        var visual = wrapper.querySelector('[data-role="visual"]');
        var sourceBox = wrapper.querySelector('[data-role="source"]');
        var sourceTextarea = wrapper.querySelector('[data-role="source-textarea"]');
        var output = wrapper.querySelector('[data-role="output"]');
        var toggleBtn = wrapper.querySelector('[data-role="toggle-source"]');
        var colorInput = wrapper.querySelector('[data-role="forecolor"]');

        var state = {
            mode: 'visual',
            // Enquanto o administrador não editar, o documento original é mantido
            // byte a byte (evita reserialização desnecessária do corpo já salvo).
            dirty: false,
            prefix: wrapper.getAttribute('data-prefix') || '',
            suffix: wrapper.getAttribute('data-suffix') || ''
        };

        // Prefere estilos via CSS (span style="...") a elementos <font> depreciados.
        try { document.execCommand('styleWithCSS', false, true); } catch (e) {}

        // Blindagem: transforma os placeholders do corpo em tokens atômicos.
        if (visual) {
            tokenizeElement(visual);
        }

        function buildFromVisual() {
            return state.prefix + (visual ? serializeEditable(visual) : '') + state.suffix;
        }

        function markDirty() {
            state.dirty = true;
        }

        function syncOutput() {
            if (!output) {
                return;
            }
            if (state.mode === 'source' && sourceTextarea) {
                output.value = sourceTextarea.value;
            } else if (state.dirty) {
                output.value = buildFromVisual();
            }
            // Modo visual sem edição: mantém o valor original inalterado.
        }

        // Botões de formatação (contenteditable via execCommand).
        toArray(wrapper.querySelectorAll('[data-cmd]')).forEach(function (btn) {
            // Evita perder a seleção ao clicar no botão.
            btn.addEventListener('mousedown', function (event) { event.preventDefault(); });
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                if (state.mode !== 'visual' || !visual) {
                    return;
                }

                var command = btn.getAttribute('data-cmd');
                var value = btn.getAttribute('data-value');

                if (command === 'createLink') {
                    var url = window.prompt('Endereço do link (URL). Deixe em branco para remover o link:', 'https://');
                    if (url === null) {
                        return;
                    }
                    if (url.trim() === '') {
                        exec('unlink');
                    } else {
                        exec('createLink', url.trim());
                    }
                } else {
                    exec(command, value);
                }

                markDirty();
                visual.focus();
                syncOutput();
            });
        });

        if (colorInput) {
            colorInput.addEventListener('input', function () {
                if (state.mode !== 'visual' || !visual) {
                    return;
                }
                exec('foreColor', colorInput.value);
                markDirty();
                visual.focus();
                syncOutput();
            });
        }

        if (visual) {
            visual.addEventListener('input', function () { markDirty(); syncOutput(); });
            // Ao sair do campo, atomiza placeholders recém-digitados e sincroniza.
            visual.addEventListener('blur', function () { tokenizeElement(visual); syncOutput(); });
        }

        if (sourceTextarea) {
            sourceTextarea.addEventListener('input', function () { markDirty(); syncOutput(); });
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (event) {
                event.preventDefault();

                if (state.mode === 'visual') {
                    // Mostra o documento completo: se houve edição visual, reconstrói;
                    // caso contrário, usa o valor original preservado.
                    if (sourceTextarea) {
                        sourceTextarea.value = state.dirty ? buildFromVisual() : (output ? output.value : buildFromVisual());
                    }
                    state.mode = 'source';
                    if (sourceBox) { sourceBox.hidden = false; }
                    if (visual) { visual.style.display = 'none'; }
                    wrapper.setAttribute('data-mode', 'source');
                    toggleBtn.textContent = 'Voltar ao editor visual';
                } else {
                    var parts = splitDocument(sourceTextarea ? sourceTextarea.value : buildFromVisual());
                    state.prefix = parts.prefix;
                    state.suffix = parts.suffix;
                    if (visual) { visual.innerHTML = parts.inner; tokenizeElement(visual); }
                    state.mode = 'visual';
                    if (sourceBox) { sourceBox.hidden = true; }
                    if (visual) { visual.style.display = ''; }
                    wrapper.setAttribute('data-mode', 'visual');
                    toggleBtn.textContent = 'Editar código HTML completo';
                }

                syncOutput();
            });
        }

        // Sincroniza e valida placeholders imediatamente antes do envio do formulário.
        // Bloqueia o salvamento quando houver placeholder estruturalmente inválido
        // (inclusive no modo "Código HTML completo"). O servidor revalida por segurança.
        var form = wrapper.closest('form');
        if (form) {
            form.addEventListener('submit', function (event) {
                syncOutput();
                var issues = structuralPlaceholderIssues(output ? output.value : '');
                if (issues.length) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.alert('Não foi possível salvar: ' + issues.join('; ') + '. Corrija os placeholders antes de salvar.');
                }
            }, true);
        }

        syncOutput();
    }

    function initAll() {
        toArray(document.querySelectorAll(SELECTOR)).forEach(initOne);
    }

    window.initEmailDocEditors = initAll;

    ready(initAll);
    window.addEventListener('load', initAll);
    window.addEventListener('pageshow', initAll);
})();
