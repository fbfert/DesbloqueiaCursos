(function () {
    'use strict';

    // Editor visual para o corpo de modelos de e-mail (fragmentos HTML).
    // Reutiliza o build local do CKEditor 5 já carregado pelo layout, com uma
    // barra de ferramentas adequada a e-mails. Modelos que são documentos HTML
    // completos (com doctype/head/body) continuam sendo editados como código
    // fonte e NÃO recebem a classe deste editor (ver form.php).
    var SELECTOR = 'textarea.js-email-html-editor';
    var INITIALIZED_ATTR = 'data-email-editor-initialized';
    var PENDING_ATTR = 'data-email-editor-pending';

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

    function getBuiltinPluginNames() {
        var editorClass = window.ClassicEditor;
        var plugins = editorClass && editorClass.builtinPlugins ? editorClass.builtinPlugins : [];
        var names = [];

        for (var i = 0; i < plugins.length; i++) {
            var plugin = plugins[i];
            var name = plugin && (plugin.pluginName || plugin.name) ? String(plugin.pluginName || plugin.name) : '';
            if (name) {
                names.push(name);
            }
        }

        return names;
    }

    function hasPlugin(pluginNames, name) {
        return pluginNames.indexOf(name) >= 0;
    }

    // Monta a barra de ferramentas apenas com os itens cujos plugins existem no
    // build local, evitando falha de inicialização quando algum não está presente.
    function getToolbarItems(pluginNames) {
        var items = [];

        if (hasPlugin(pluginNames, 'Heading')) {
            items.push('heading', '|');
        }

        items.push('bold', 'italic');

        if (hasPlugin(pluginNames, 'Underline')) {
            items.push('underline');
        }

        if (hasPlugin(pluginNames, 'FontColor')) {
            items.push('fontColor');
        }

        items.push('|');

        if (hasPlugin(pluginNames, 'Alignment')) {
            items.push('alignment');
        }

        items.push('bulletedList', 'numberedList', '|', 'link');

        if (hasPlugin(pluginNames, 'HorizontalLine')) {
            items.push('horizontalLine');
        }

        if (hasPlugin(pluginNames, 'RemoveFormat')) {
            items.push('removeFormat');
        }

        items.push('|', 'undo', 'redo');

        if (hasPlugin(pluginNames, 'SourceEditing')) {
            items.push('|', 'sourceEditing');
        }

        return items;
    }

    function getHeadingOptions() {
        return [
            { model: 'paragraph', title: 'Parágrafo', class: 'ck-heading_paragraph' },
            { model: 'heading2', view: 'h2', title: 'Título 2', class: 'ck-heading_heading2' },
            { model: 'heading3', view: 'h3', title: 'Título 3', class: 'ck-heading_heading3' },
            { model: 'heading4', view: 'h4', title: 'Título 4', class: 'ck-heading_heading4' }
        ];
    }

    function syncEditor(textarea) {
        var editor = textarea && textarea._emailEditorInstance;
        if (!editor) {
            return;
        }

        if (typeof editor.updateSourceElement === 'function') {
            editor.updateSourceElement();
            return;
        }

        if (typeof editor.getData === 'function') {
            textarea.value = editor.getData();
        }
    }

    function syncAllEditors() {
        toArray(document.querySelectorAll(SELECTOR)).forEach(syncEditor);
    }

    function initEditor(textarea) {
        if (!textarea || textarea.getAttribute(INITIALIZED_ATTR) === '1' || textarea.getAttribute(PENDING_ATTR) === '1' || textarea._emailEditorInstance) {
            return;
        }

        if (!window.ClassicEditor || typeof window.ClassicEditor.create !== 'function') {
            return;
        }

        textarea.setAttribute(PENDING_ATTR, '1');

        var pluginNames = getBuiltinPluginNames();
        var config = {
            language: 'pt-br',
            toolbar: { items: getToolbarItems(pluginNames) },
            heading: { options: getHeadingOptions() },
            link: {
                addTargetToExternalLinks: true,
                defaultProtocol: 'https://'
            }
        };

        window.ClassicEditor.create(textarea, config)
            .then(function (editor) {
                textarea._emailEditorInstance = editor;
                textarea.setAttribute(INITIALIZED_ATTR, '1');
                textarea.removeAttribute(PENDING_ATTR);

                if (editor.model && editor.model.document) {
                    editor.model.document.on('change:data', function () {
                        syncEditor(textarea);
                    });
                }

                if (editor.editing && editor.editing.view && editor.editing.view.document) {
                    editor.editing.view.document.on('blur', function () {
                        syncEditor(textarea);
                    });
                }

                syncEditor(textarea);
            })
            .catch(function (error) {
                // Falha ao inicializar: mantém o textarea de código utilizável.
                textarea.removeAttribute(PENDING_ATTR);
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('[email-modelo-editor] Falha ao inicializar o editor visual.', error);
                }
            });
    }

    function initEditors() {
        toArray(document.querySelectorAll(SELECTOR)).forEach(initEditor);
    }

    function bindGlobalSubmitSync() {
        if (window.__emailEditorSubmitBound) {
            return;
        }

        window.__emailEditorSubmitBound = true;
        document.addEventListener('submit', syncAllEditors, true);
    }

    function bindFullscreenToggle() {
        if (window.__emailEditorFullscreenBound) {
            return;
        }

        window.__emailEditorFullscreenBound = true;

        document.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-email-editor-fullscreen]') : null;
            if (!trigger) {
                return;
            }

            event.preventDefault();
            var wrapper = trigger.closest('[data-email-editor]');
            if (!wrapper) {
                return;
            }

            var isFull = wrapper.classList.toggle('email-editor--fullscreen');
            document.body.classList.toggle('email-editor-fullscreen-lock', isFull);
            trigger.textContent = isFull ? 'Sair da tela cheia' : 'Tela cheia';
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            var open = toArray(document.querySelectorAll('.email-editor--fullscreen'));
            if (!open.length) {
                return;
            }

            open.forEach(function (wrapper) {
                wrapper.classList.remove('email-editor--fullscreen');
                var trigger = wrapper.querySelector('[data-email-editor-fullscreen]');
                if (trigger) {
                    trigger.textContent = 'Tela cheia';
                }
            });
            document.body.classList.remove('email-editor-fullscreen-lock');
        });
    }

    window.initEmailModeloEditors = initEditors;

    bindGlobalSubmitSync();
    bindFullscreenToggle();
    ready(initEditors);
    window.addEventListener('load', initEditors);
    window.addEventListener('pageshow', initEditors);
})();
