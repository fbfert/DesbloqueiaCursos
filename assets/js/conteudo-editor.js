(function () {
    'use strict';

    var SELECTOR = 'textarea.js-conteudo-rich-editor';
    var INITIALIZED_ATTR = 'data-ckeditor-initialized';
    var PENDING_ATTR = 'data-ckeditor-pending';
    var REGISTRY_KEY = '__conteudoEditors';

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

    function getRegistry() {
        if (!window[REGISTRY_KEY]) {
            window[REGISTRY_KEY] = [];
        }

        return window[REGISTRY_KEY];
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

    function getToolbarItems(pluginNames) {
        var items = ['heading', '|', 'bold', 'italic'];

        if (hasPlugin(pluginNames, 'Underline')) {
            items.push('underline');
        }

        items.push('link', 'bulletedList', 'numberedList', 'blockQuote');

        if (hasPlugin(pluginNames, 'Table')) {
            items.push('insertTable');
        }

        items.push('|', 'undo', 'redo');

        return items;
    }

    function getHeadingOptions() {
        return [
            {
                model: 'paragraph',
                title: 'Parágrafo',
                class: 'ck-heading_paragraph'
            },
            {
                model: 'heading2',
                view: 'h2',
                title: 'Título 2',
                class: 'ck-heading_heading2'
            },
            {
                model: 'heading3',
                view: 'h3',
                title: 'Título 3',
                class: 'ck-heading_heading3'
            },
            {
                model: 'heading4',
                view: 'h4',
                title: 'Título 4',
                class: 'ck-heading_heading4'
            }
        ];
    }

    function syncEditor(textarea) {
        var editor = textarea && textarea._ckeditorInstance;
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
        var textareas = toArray(document.querySelectorAll(SELECTOR));
        for (var i = 0; i < textareas.length; i++) {
            syncEditor(textareas[i]);
        }
    }

    function bindEditorEvents(textarea, editor) {
        if (!editor || !editor.model || !editor.model.document) {
            return;
        }

        editor.model.document.on('change:data', function () {
            syncEditor(textarea);
        });

        if (editor.editing && editor.editing.view && editor.editing.view.document) {
            editor.editing.view.document.on('blur', function () {
                syncEditor(textarea);
            });
        }
    }

    function markAsInitialized(textarea, editor) {
        textarea._ckeditorInstance = editor;
        textarea.setAttribute(INITIALIZED_ATTR, '1');
        textarea.removeAttribute(PENDING_ATTR);

        var registry = getRegistry();
        if (registry.indexOf(editor) === -1) {
            registry.push(editor);
        }
    }

    function initEditor(textarea) {
        if (!textarea || textarea.getAttribute(INITIALIZED_ATTR) === '1' || textarea.getAttribute(PENDING_ATTR) === '1' || textarea._ckeditorInstance) {
            return null;
        }

        if (!window.ClassicEditor || typeof window.ClassicEditor.create !== 'function') {
            return null;
        }

        textarea.setAttribute(PENDING_ATTR, '1');

        var pluginNames = getBuiltinPluginNames();
        var config = {
            language: 'pt-br',
            toolbar: {
                items: getToolbarItems(pluginNames)
            },
            heading: {
                options: getHeadingOptions()
            },
            link: {
                addTargetToExternalLinks: true,
                defaultProtocol: 'https://'
            }
        };

        return window.ClassicEditor.create(textarea, config)
            .then(function (editor) {
                markAsInitialized(textarea, editor);
                bindEditorEvents(textarea, editor);
                syncEditor(textarea);
                return editor;
            })
            .catch(function (error) {
                textarea.removeAttribute(PENDING_ATTR);
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('[conteudo-editor] Falha ao inicializar o CKEditor 5.', error);
                }
                return null;
            });
    }

    function initEditors() {
        var textareas = toArray(document.querySelectorAll(SELECTOR));
        var editors = [];

        for (var i = 0; i < textareas.length; i++) {
            var editor = initEditor(textareas[i]);
            if (editor) {
                editors.push(editor);
            }
        }

        return editors;
    }

    function bindGlobalSubmitSync() {
        if (window.__conteudoEditorSubmitBound) {
            return;
        }

        window.__conteudoEditorSubmitBound = true;

        document.addEventListener('submit', function () {
            syncAllEditors();
        }, true);
    }

    window.initConteudoRichEditors = function () {
        return initEditors();
    };

    window.initAreaCursoWysiwyg = window.initConteudoRichEditors;

    bindGlobalSubmitSync();
    ready(initEditors);
    window.addEventListener('load', initEditors);
    window.addEventListener('pageshow', initEditors);
})();
