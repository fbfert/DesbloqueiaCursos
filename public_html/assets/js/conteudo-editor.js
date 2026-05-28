(function () {
    'use strict';

    var SELECTOR = 'textarea.js-conteudo-rich-editor';
    var BUTTON_CLASS = 'conteudo-rich-editor__button';
    var COMMAND_BUTTON_CLASS = 'conteudo-rich-editor__button--command';
    var HTML_BUTTON_CLASS = 'conteudo-rich-editor__button--html';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
            return;
        }
        fn();
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function looksLikeHtml(value) {
        return /<\s*\/?\s*[a-z][\s\S]*>/i.test(String(value || ''));
    }

    function sanitizeUrl(value) {
        var raw = String(value || '').trim();
        if (raw === '') {
            return '';
        }

        if (raw.charAt(0) === '#') {
            return raw;
        }

        if (/^(https?:|mailto:)/i.test(raw)) {
            return raw;
        }

        return '';
    }

    function createButton(label, title, command, extraClass) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = BUTTON_CLASS + ' ' + COMMAND_BUTTON_CLASS + (extraClass ? ' ' + extraClass : '');
        button.textContent = label;
        button.title = title;
        button.setAttribute('aria-label', title);
        button.setAttribute('data-editor-command', command);
        return button;
    }

    function execCommand(surface, command, value) {
        surface.focus();
        document.execCommand(command, false, value);
    }

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function ensureFormBinding(form) {
        if (!form || form.dataset.conteudoEditorFormBound === '1') {
            return;
        }

        form.dataset.conteudoEditorFormBound = '1';
        form.addEventListener('submit', function () {
            var editors = toArray(form.querySelectorAll(SELECTOR));
            for (var i = 0; i < editors.length; i++) {
                syncTextarea(editors[i]);
            }
        });
    }

    function getEditorState(textarea) {
        return textarea._conteudoEditorState || null;
    }

    function syncTextarea(textarea) {
        var state = getEditorState(textarea);
        if (!state) {
            return;
        }

        if (state.htmlMode) {
            textarea.value = state.textarea.value;
            return;
        }

        textarea.value = state.surface.innerHTML;
    }

    function syncSurfaceFromTextarea(textarea) {
        var state = getEditorState(textarea);
        if (!state) {
            return;
        }

        var value = textarea.value || '';
        if (looksLikeHtml(value)) {
            state.surface.innerHTML = value;
            return;
        }

        state.surface.innerHTML = escapeHtml(value).replace(/\r?\n/g, '<br>');
    }

    function setHtmlMode(textarea, enabled) {
        var state = getEditorState(textarea);
        if (!state || state.htmlMode === enabled) {
            return;
        }

        state.htmlMode = enabled;
        state.wrapper.classList.toggle('is-html-mode', enabled);
        state.surface.hidden = enabled;
        state.textarea.hidden = !enabled;
        state.htmlButton.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        state.htmlButton.textContent = enabled ? 'Visual' : 'HTML';

        for (var i = 0; i < state.commandButtons.length; i++) {
            state.commandButtons[i].disabled = enabled;
        }

        if (enabled) {
            state.textarea.style.display = 'block';
            state.textarea.value = state.surface.innerHTML;
            state.textarea.focus();
            state.textarea.select();
            return;
        }

        state.textarea.style.display = 'none';
        syncSurfaceFromTextarea(textarea);
        state.surface.focus();
    }

    function updateButtonState(state) {
        var isHtml = state.htmlMode;
        state.htmlButton.setAttribute('aria-pressed', isHtml ? 'true' : 'false');
        state.htmlButton.textContent = isHtml ? 'Visual' : 'HTML';
        for (var i = 0; i < state.commandButtons.length; i++) {
            state.commandButtons[i].disabled = isHtml;
        }
    }

    function handleCommand(textarea, command) {
        var state = getEditorState(textarea);
        if (!state) {
            return;
        }

        if (command === 'html') {
            if (state.htmlMode) {
                state.textarea.value = state.textarea.value || state.surface.innerHTML;
            } else {
                state.textarea.value = state.surface.innerHTML;
            }
            setHtmlMode(textarea, !state.htmlMode);
            updateButtonState(state);
            return;
        }

        if (state.htmlMode) {
            return;
        }

        switch (command) {
        case 'bold':
            execCommand(state.surface, 'bold');
            break;
        case 'italic':
            execCommand(state.surface, 'italic');
            break;
        case 'h2':
            execCommand(state.surface, 'formatBlock', '<H2>');
            break;
        case 'p':
            execCommand(state.surface, 'formatBlock', '<P>');
            break;
        case 'ul':
            execCommand(state.surface, 'insertUnorderedList');
            break;
        case 'ol':
            execCommand(state.surface, 'insertOrderedList');
            break;
        case 'link':
            var url = sanitizeUrl(window.prompt('Informe o link', 'https://'));
            if (url) {
                execCommand(state.surface, 'createLink', url);
            }
            break;
        case 'clean':
            execCommand(state.surface, 'removeFormat');
            execCommand(state.surface, 'unlink');
            execCommand(state.surface, 'formatBlock', '<P>');
            break;
        default:
            break;
        }

        syncTextarea(textarea);
    }

    function initEditor(textarea) {
        if (!textarea || textarea.dataset.editorInitialized === '1') {
            return;
        }

        textarea.dataset.editorInitialized = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'conteudo-rich-editor';

        var toolbar = document.createElement('div');
        toolbar.className = 'conteudo-rich-editor__toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Editor de conteúdo');

        var buttons = [
            createButton('Negrito', 'Negrito', 'bold'),
            createButton('Itálico', 'Itálico', 'italic'),
            createButton('Título', 'Título H2', 'h2'),
            createButton('Parágrafo', 'Parágrafo', 'p'),
            createButton('Lista', 'Lista com marcadores', 'ul'),
            createButton('Lista 1', 'Lista numerada', 'ol'),
            createButton('Link', 'Inserir link', 'link'),
            createButton('Limpar', 'Limpar formatação', 'clean'),
            createButton('HTML', 'Alternar para edição HTML', 'html', HTML_BUTTON_CLASS),
        ];

        var surface = document.createElement('div');
        surface.className = 'conteudo-rich-editor__surface';
        surface.contentEditable = 'true';
        surface.spellcheck = true;
        surface.setAttribute('role', 'textbox');
        surface.setAttribute('aria-multiline', 'true');
        surface.setAttribute('data-placeholder', textarea.getAttribute('data-editor-placeholder') || 'Digite o conteúdo aqui.');

        var parent = textarea.parentNode;
        if (!parent) {
            return;
        }

        parent.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(surface);

        for (var i = 0; i < buttons.length; i++) {
            toolbar.appendChild(buttons[i]);
        }

        var state = {
            wrapper: wrapper,
            toolbar: toolbar,
            surface: surface,
            textarea: textarea,
            htmlButton: buttons[buttons.length - 1],
            commandButtons: buttons.slice(0, buttons.length - 1),
            htmlMode: false,
        };

        textarea._conteudoEditorState = state;
        textarea.hidden = true;
        textarea.classList.add('conteudo-rich-editor__source');
        textarea.style.display = 'none';

        syncSurfaceFromTextarea(textarea);

        surface.addEventListener('input', function () {
            syncTextarea(textarea);
        });

        surface.addEventListener('blur', function () {
            syncTextarea(textarea);
        });

        textarea.addEventListener('input', function () {
            if (!state.htmlMode) {
                syncSurfaceFromTextarea(textarea);
            }
        });

        toolbar.addEventListener('mousedown', function (event) {
            var target = event.target;
            if (target && target.tagName === 'BUTTON') {
                event.preventDefault();
            }
        });

        toolbar.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || target.tagName !== 'BUTTON') {
                return;
            }

            handleCommand(textarea, target.getAttribute('data-editor-command') || '');
        });

        ensureFormBinding(textarea.form);
        updateButtonState(state);
    }

    function initEditors() {
        var textareas = toArray(document.querySelectorAll(SELECTOR));
        for (var i = 0; i < textareas.length; i++) {
            initEditor(textareas[i]);
        }

        if (window.location.search.indexOf('debug_editor=1') !== -1 && window.console && typeof window.console.log === 'function') {
            window.console.log('[conteudo-editor] inicializado', textareas.length);
        }
    }

    window.initConteudoRichEditors = initEditors;
    window.initAreaCursoWysiwyg = initEditors;

    ready(initEditors);
    window.addEventListener('load', initEditors);
    window.addEventListener('pageshow', initEditors);
})();
