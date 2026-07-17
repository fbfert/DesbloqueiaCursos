(function () {
    var instances = [];

    function getMode(textarea) {
        var mode = (textarea.getAttribute('data-wysiwyg') || 'basic').toLowerCase();
        return ['minimal', 'basic', 'full'].indexOf(mode) >= 0 ? mode : 'basic';
    }

    function createToolbar(mode) {
        var toolbar = document.createElement('div');
        toolbar.className = 'admin-wysiwyg__toolbar ql-toolbar ql-snow';

        function addGroup(children) {
            var group = document.createElement('span');
            group.className = 'ql-formats';
            children.forEach(function (child) {
                group.appendChild(child);
            });
            toolbar.appendChild(group);
        }

        function button(className, title, innerHtml) {
            var buttonElement = document.createElement('button');
            buttonElement.type = 'button';
            buttonElement.className = className;
            buttonElement.setAttribute('title', title);
            if (innerHtml) {
                buttonElement.innerHTML = innerHtml;
            }
            return buttonElement;
        }

        function select(className, options) {
            var selectElement = document.createElement('select');
            selectElement.className = className;
            options.forEach(function (option) {
                var optionElement = document.createElement('option');
                if (option.value !== null) {
                    optionElement.setAttribute('value', option.value);
                }
                optionElement.textContent = option.label;
                selectElement.appendChild(optionElement);
            });
            return selectElement;
        }

        addGroup([
            button('ql-bold', 'Negrito'),
            button('ql-italic', 'Itálico')
        ]);

        if (mode !== 'minimal') {
            addGroup([
                button('ql-underline', 'Sublinhar')
            ]);
        }

        addGroup([
            button('ql-link', 'Inserir link')
        ]);

        addGroup([
            (function () {
                var bullet = button('ql-list', 'Lista com marcadores');
                bullet.setAttribute('value', 'bullet');
                return bullet;
            })(),
            (function () {
                var ordered = button('ql-list', 'Lista numerada');
                ordered.setAttribute('value', 'ordered');
                return ordered;
            })()
        ]);

        if (mode === 'full') {
            addGroup([
                select('ql-header', [
                    { value: '', label: 'Normal' },
                    { value: '2', label: 'Título 2' },
                    { value: '3', label: 'Título 3' },
                    { value: '4', label: 'Título 4' }
                ])
            ]);

            addGroup([
                button('ql-blockquote', 'Citação')
            ]);

            addGroup([
                select('ql-align', [
                    { value: '', label: 'Alinhar' },
                    { value: 'center', label: 'Centro' },
                    { value: 'right', label: 'Direita' },
                    { value: 'justify', label: 'Justificar' }
                ])
            ]);

        }

        addGroup([
            button('ql-clean', 'Limpar formatação')
        ]);

        return toolbar;
    }

    function createEditor(textarea) {
        if (typeof window.Quill === 'undefined') {
            if (window.console && typeof window.console.warn === 'function') {
                console.warn('Quill não está disponível. O campo continuará como textarea simples.');
            }
            return;
        }

        var mode = getMode(textarea);
        var wrapper = document.createElement('div');
        var toolbar = createToolbar(mode);
        var editor = document.createElement('div');
        var hint = document.createElement('div');
        var actions = null;
        var htmlDetails = null;
        var htmlTextarea = null;
        var htmlCopyButton = null;
        var htmlRefreshButton = null;
        var quill = null;

        wrapper.className = 'admin-wysiwyg admin-wysiwyg--' + mode + ' is-ready';
        editor.className = 'admin-wysiwyg__editor';
        hint.className = 'admin-wysiwyg__hint';
        hint.textContent = 'Conteúdo formatado. Use apenas HTML seguro.';

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(editor);
        wrapper.appendChild(hint);

        if (mode === 'full') {
            actions = document.createElement('div');
            actions.className = 'admin-wysiwyg__actions';

            htmlRefreshButton = document.createElement('button');
            htmlRefreshButton.type = 'button';
            htmlRefreshButton.className = 'button-link button-link--ghost admin-wysiwyg__action-button';
            htmlRefreshButton.textContent = 'Ver HTML';

            htmlCopyButton = document.createElement('button');
            htmlCopyButton.type = 'button';
            htmlCopyButton.className = 'button-link button-link--ghost admin-wysiwyg__action-button';
            htmlCopyButton.textContent = 'Copiar HTML';

            actions.appendChild(htmlRefreshButton);
            actions.appendChild(htmlCopyButton);

            htmlDetails = document.createElement('details');
            htmlDetails.className = 'admin-wysiwyg__html-panel';

            var summary = document.createElement('summary');
            summary.textContent = 'HTML puro gerado pelo editor';
            htmlDetails.appendChild(summary);

            htmlTextarea = document.createElement('textarea');
            htmlTextarea.className = 'admin-wysiwyg__html-output';
            htmlTextarea.setAttribute('readonly', 'readonly');
            htmlTextarea.setAttribute('spellcheck', 'false');
            htmlTextarea.setAttribute('aria-label', 'HTML puro gerado pelo editor');
            htmlDetails.appendChild(htmlTextarea);
        }

        wrapper.appendChild(textarea);
        textarea.classList.add('admin-wysiwyg__textarea');
        if (actions && htmlDetails) {
            wrapper.appendChild(actions);
            wrapper.appendChild(htmlDetails);
        }

        quill = new window.Quill(editor, {
            theme: 'snow',
            placeholder: textarea.getAttribute('placeholder') || 'Digite o conteúdo formatado aqui.',
            modules: {
                toolbar: toolbar
            },
            formats: getFormatsForMode(mode)
        });

        if (textarea.value) {
            quill.clipboard.dangerouslyPasteHTML(0, textarea.value, 'api');
        }

        function sync() {
            var html = typeof quill.getSemanticHTML === 'function' ? quill.getSemanticHTML() : quill.root.innerHTML;
            textarea.value = html;
            if (htmlTextarea) {
                htmlTextarea.value = html;
            }
        }

        function refreshHtmlPanel() {
            if (!htmlTextarea) {
                return;
            }

            sync();
            htmlTextarea.value = textarea.value || '';
            if (htmlDetails) {
                htmlDetails.open = true;
            }
            htmlTextarea.focus();
            htmlTextarea.select();
        }

        if (htmlRefreshButton) {
            htmlRefreshButton.addEventListener('click', function () {
                refreshHtmlPanel();
            });
        }

        if (htmlCopyButton) {
            htmlCopyButton.addEventListener('click', function () {
                sync();
                var html = textarea.value || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(html).then(function () {
                        htmlCopyButton.textContent = 'Copiado';
                        window.setTimeout(function () {
                            htmlCopyButton.textContent = 'Copiar HTML';
                        }, 1200);
                    }).catch(function () {
                        refreshHtmlPanel();
                    });
                } else {
                    refreshHtmlPanel();
                }
            });
        }

        quill.on('text-change', sync);
        textarea.form && textarea.form.addEventListener('submit', sync);
        sync();

        instances.push({
            textarea: textarea,
            quill: quill,
            sync: sync
        });
    }

    function getFormatsForMode(mode) {
        if (mode === 'minimal') {
            return ['bold', 'italic', 'link', 'list'];
        }

        if (mode === 'full') {
            return ['bold', 'italic', 'underline', 'link', 'list', 'blockquote', 'header', 'align'];
        }

        return ['bold', 'italic', 'underline', 'link', 'list'];
    }

    function initAll() {
        document.querySelectorAll('textarea.js-wysiwyg, textarea[data-wysiwyg]').forEach(function (textarea) {
            if (textarea.dataset.wysiwygReady === '1') {
                return;
            }

            textarea.dataset.wysiwygReady = '1';
            createEditor(textarea);
        });
    }

    document.addEventListener('DOMContentLoaded', initAll);
})();
