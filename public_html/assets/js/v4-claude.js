/* ==========================================================================
   V4 - Claude — Comportamentos progressivos (vanilla JS, sem dependências).
   Carregado apenas quando o template V4 está ativo. Aprimora a UX sobre o
   HTML já renderizado pelo servidor; nenhuma chamada de endpoint nova.
   ========================================================================== */
(function () {
    'use strict';

    if (!document.body || document.body.className.indexOf('theme-v4-claude') === -1) {
        return;
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /* Toggle mostrar/ocultar senha ----------------------------------------- */
    function initPasswordToggles() {
        var fields = document.querySelectorAll('input[type="password"]');
        Array.prototype.forEach.call(fields, function (input) {
            if (input.dataset.dcPassReady === '1') {
                return;
            }
            input.dataset.dcPassReady = '1';

            var wrap = document.createElement('div');
            wrap.className = 'dc-pass-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dc-pass-toggle';
            btn.textContent = 'Mostrar';
            btn.setAttribute('aria-label', 'Mostrar senha');
            wrap.appendChild(btn);

            btn.addEventListener('click', function () {
                var hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                btn.textContent = hidden ? 'Ocultar' : 'Mostrar';
                btn.setAttribute('aria-label', hidden ? 'Ocultar senha' : 'Mostrar senha');
            });
        });
    }

    /* Accordion de módulos (LMS) ------------------------------------------- */
    function initAccordions() {
        document.addEventListener('click', function (event) {
            var head = event.target.closest('.dc-accordion__head');
            if (!head) {
                return;
            }
            var item = head.closest('.dc-accordion__item');
            if (item) {
                item.classList.toggle('is-open');
                var expanded = item.classList.contains('is-open');
                head.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
        });
    }

    /* Tabs internas (data-dc-tab -> data-dc-panel) ------------------------- */
    function initTabs() {
        var groups = document.querySelectorAll('[data-dc-tabs]');
        Array.prototype.forEach.call(groups, function (group) {
            var tabs = group.querySelectorAll('[data-dc-tab]');
            Array.prototype.forEach.call(tabs, function (tab) {
                tab.addEventListener('click', function () {
                    var target = tab.getAttribute('data-dc-tab');
                    Array.prototype.forEach.call(tabs, function (t) {
                        t.classList.toggle('is-active', t === tab);
                    });
                    var panels = group.querySelectorAll('[data-dc-panel]');
                    Array.prototype.forEach.call(panels, function (panel) {
                        panel.hidden = panel.getAttribute('data-dc-panel') !== target;
                    });
                });
            });
        });
    }

    /* Chips de seleção (toggle visual, sem navegação) ---------------------- */
    function initChips() {
        var chips = document.querySelectorAll('.dc-chip[data-dc-toggle]');
        Array.prototype.forEach.call(chips, function (chip) {
            chip.addEventListener('click', function (event) {
                event.preventDefault();
                chip.classList.toggle('is-active');
            });
        });
    }

    /* Bottom sheet (filtros): [data-dc-sheet-open="id"] / [data-dc-sheet-close] */
    function initBottomSheets() {
        document.addEventListener('click', function (event) {
            var opener = event.target.closest('[data-dc-sheet-open]');
            if (opener) {
                var sheet = document.getElementById(opener.getAttribute('data-dc-sheet-open'));
                if (sheet) {
                    sheet.classList.add('is-open');
                    document.body.classList.add('dc-no-scroll');
                }
                return;
            }
            if (event.target.closest('[data-dc-sheet-close]') || event.target.classList.contains('dc-bottom-sheet__backdrop')) {
                var open = document.querySelector('.dc-bottom-sheet.is-open');
                if (open) {
                    open.classList.remove('is-open');
                    document.body.classList.remove('dc-no-scroll');
                }
            }
        });
    }

    /* Upload de comprovante: preview do arquivo selecionado ---------------- */
    function formatSize(bytes) {
        if (!bytes && bytes !== 0) {
            return '';
        }
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1048576) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function initUploadPreview() {
        var inputs = document.querySelectorAll('input[type="file"][data-dc-upload]');
        Array.prototype.forEach.call(inputs, function (input) {
            var previewId = input.getAttribute('data-dc-upload');
            var preview = previewId ? document.getElementById(previewId) : null;
            var submitSel = input.getAttribute('data-dc-upload-submit');
            var submitBtn = submitSel ? document.querySelector(submitSel) : null;

            function render() {
                var file = input.files && input.files[0];
                if (submitBtn) {
                    submitBtn.disabled = !file;
                }
                if (!preview) {
                    return;
                }
                if (!file) {
                    preview.hidden = true;
                    preview.textContent = '';
                    return;
                }
                preview.hidden = false;
                preview.textContent = file.name + ' · ' + formatSize(file.size);
            }

            input.addEventListener('change', render);
            render();
        });
    }

    /* Confirmação ao enviar respostas do quiz ------------------------------ */
    function initQuizConfirm() {
        var forms = document.querySelectorAll('form[data-dc-quiz-confirm]');
        Array.prototype.forEach.call(forms, function (form) {
            form.addEventListener('submit', function (event) {
                var msg = form.getAttribute('data-dc-quiz-confirm') || 'Deseja enviar suas respostas?';
                if (!window.confirm(msg)) {
                    event.preventDefault();
                }
            });
        });
    }

    ready(function () {
        initPasswordToggles();
        initAccordions();
        initTabs();
        initChips();
        initBottomSheets();
        initUploadPreview();
        initQuizConfirm();
    });
})();
