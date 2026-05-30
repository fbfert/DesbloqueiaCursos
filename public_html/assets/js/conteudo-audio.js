(function () {
    'use strict';

    var TARGET_SELECTOR = '[data-audio-texto="1"], .js-conteudo-texto-audio';
    var INITIALIZED_ATTR = 'data-conteudo-audio-initialized';
    var CONTROLS_ATTR = 'data-conteudo-audio-controls';
    var NOTICE_ATTR = 'data-conteudo-audio-notice';
    var READY_CLASS = 'conteudo-audio--ready';
    var ACTIVE_CLASS = 'conteudo-audio--active';
    var PAUSED_CLASS = 'conteudo-audio--paused';
    var FINISHED_CLASS = 'conteudo-audio--finished';
    var STATE_IDLE = 'idle';
    var STATE_PLAYING = 'playing';
    var STATE_PAUSED = 'paused';
    var STATE_FINISHED = 'finished';
    var MAX_CHUNK_LENGTH = 260;
    var SPEAK_DELAY_MS = 60;

    var runtime = {
        currentBlock: null,
        currentUtterance: null,
        currentState: STATE_IDLE,
        initScheduled: false,
        observerBound: false
    };

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    }

    function supportsSpeech() {
        return !!(window.speechSynthesis && window.SpeechSynthesisUtterance);
    }

    function decodeEntities(text) {
        var value = text == null ? '' : String(text);
        var previous = value;
        var i;

        for (i = 0; i < 4; i++) {
            var textarea = document.createElement('textarea');
            textarea.innerHTML = previous;
            var decoded = textarea.value || textarea.textContent || '';
            if (decoded === previous) {
                break;
            }
            previous = decoded;
        }

        return previous;
    }

    function normalizeText(text) {
        return decodeEntities(text)
            .replace(/\u00a0/g, ' ')
            .replace(/\r?\n+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function matchesSelector(node, selector) {
        return !!(node && node.matches && node.matches(selector));
    }

    function isIgnoredTextContainer(element) {
        if (!element || element.nodeType !== 1) {
            return false;
        }

        if (matchesSelector(element, [
            'script',
            'style',
            'noscript',
            'button',
            'form',
            'input',
            'select',
            'textarea',
            'label',
            'summary',
            'audio',
            'video',
            'iframe',
            '[hidden]',
            '[aria-hidden="true"]',
            '[data-conteudo-audio-controls="1"]',
            '[data-conteudo-audio-notice="1"]',
            '.conteudo-item-action-summary',
            '.conteudo-item-actions',
            '.conteudo-item-return',
            '.conteudo-item-orientacao',
            '.conteudo-item-feedback',
            '.conteudo-item-history',
            '.conteudo-item-form',
            '.conteudo-item-concluir-form'
        ].join(','))) {
            return true;
        }

        if (element.className && typeof element.className === 'string') {
            if (/\bbutton-link\b/.test(element.className)) {
                return true;
            }
        }

        return false;
    }

    function isVisibleElement(element) {
        var current = element;

        while (current && current.nodeType === 1) {
            if (isIgnoredTextContainer(current)) {
                return false;
            }

            if (typeof window.getComputedStyle === 'function') {
                var style = window.getComputedStyle(current);
                if (style && (style.display === 'none' || style.visibility === 'hidden' || style.visibility === 'collapse')) {
                    return false;
                }
            }

            current = current.parentElement;
        }

        return true;
    }

    function collectVisibleText(block) {
        var text = '';
        var walker;
        var node;

        if (!document.createTreeWalker || !window.NodeFilter) {
            return '';
        }

        walker = document.createTreeWalker(block, window.NodeFilter.SHOW_TEXT, null, false);
        node = walker.nextNode();

        while (node) {
            if (node.nodeValue && node.nodeValue.trim() !== '' && node.parentElement && isVisibleElement(node.parentElement)) {
                text += ' ' + node.nodeValue;
            }
            node = walker.nextNode();
        }

        return text;
    }

    function extractReadableText(block) {
        if (!block) {
            return '';
        }

        var text = collectVisibleText(block);
        if (!text) {
            text = typeof block.innerText === 'string' ? block.innerText : (block.textContent || '');
        }

        return normalizeText(text);
    }

    function isChunkElement(element) {
        if (!element || element.nodeType !== 1) {
            return false;
        }

        return matchesSelector(element, [
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'p',
            'li',
            'blockquote',
            'pre',
            'figcaption',
            'caption',
            'dt',
            'dd',
            'th',
            'td'
        ].join(','));
    }

    function isWrapperChunkCandidate(element) {
        if (!element || element.nodeType !== 1) {
            return false;
        }

        return matchesSelector(element, 'div,section,article,aside,main,header,footer,figure');
    }

    function hasStructuralDescendant(element) {
        if (!element || !element.querySelector) {
            return false;
        }

        return !!element.querySelector([
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'p',
            'li',
            'blockquote',
            'pre',
            'figcaption',
            'caption',
            'dt',
            'dd',
            'th',
            'td'
        ].join(','));
    }

    function splitByWords(text, maxLength) {
        var words = normalizeText(text).split(/\s+/);
        var chunks = [];
        var current = '';
        var i;

        for (i = 0; i < words.length; i++) {
            if (!words[i]) {
                continue;
            }

            if (!current) {
                current = words[i];
                continue;
            }

            if ((current.length + 1 + words[i].length) <= maxLength) {
                current += ' ' + words[i];
            } else {
                chunks.push(current);
                current = words[i];
            }
        }

        if (current) {
            chunks.push(current);
        }

        return chunks;
    }

    function splitSentenceText(text) {
        var normalized = normalizeText(text);
        var sentences;
        var chunks = [];
        var current = '';
        var i;

        if (!normalized) {
            return [];
        }

        if (normalized.length <= MAX_CHUNK_LENGTH) {
            return [normalized];
        }

        sentences = normalized.match(/[^.!?]+[.!?]+(?:\s+|$)|[^.!?]+$/g);
        if (!sentences || !sentences.length) {
            return splitByWords(normalized, MAX_CHUNK_LENGTH);
        }

        for (i = 0; i < sentences.length; i++) {
            var sentence = normalizeText(sentences[i]);
            var parts;
            var j;

            if (!sentence) {
                continue;
            }

            if (sentence.length > MAX_CHUNK_LENGTH) {
                if (current) {
                    chunks.push(current);
                    current = '';
                }
                parts = splitByWords(sentence, MAX_CHUNK_LENGTH);
                for (j = 0; j < parts.length; j++) {
                    if (parts[j]) {
                        chunks.push(parts[j]);
                    }
                }
                continue;
            }

            if (!current) {
                current = sentence;
                continue;
            }

            if ((current.length + 1 + sentence.length) <= MAX_CHUNK_LENGTH) {
                current += ' ' + sentence;
            } else {
                chunks.push(current);
                current = sentence;
            }
        }

        if (current) {
            chunks.push(current);
        }

        return chunks.length ? chunks : splitByWords(normalized, MAX_CHUNK_LENGTH);
    }

    function collectSegmentsFromElement(node, segments) {
        var childNodes;
        var buffer = '';
        var i;

        if (!node || node.nodeType !== 1) {
            return;
        }

        if (!isVisibleElement(node) || isIgnoredTextContainer(node)) {
            return;
        }

        if (isChunkElement(node)) {
            var chunkText = extractReadableText(node);
            var chunkParts = splitSentenceText(chunkText);
            var j;
            for (j = 0; j < chunkParts.length; j++) {
                if (chunkParts[j]) {
                    segments.push(chunkParts[j]);
                }
            }
            return;
        }

        if (isWrapperChunkCandidate(node) && !hasStructuralDescendant(node)) {
            var wrapperText = extractReadableText(node);
            var wrapperParts = splitSentenceText(wrapperText);
            var k;
            for (k = 0; k < wrapperParts.length; k++) {
                if (wrapperParts[k]) {
                    segments.push(wrapperParts[k]);
                }
            }
            return;
        }

        function flushBuffer() {
            var bufferedText = normalizeText(buffer);
            var bufferedParts;
            var m;

            if (!bufferedText) {
                buffer = '';
                return;
            }

            bufferedParts = splitSentenceText(bufferedText);
            for (m = 0; m < bufferedParts.length; m++) {
                if (bufferedParts[m]) {
                    segments.push(bufferedParts[m]);
                }
            }

            buffer = '';
        }

        childNodes = node.childNodes;
        for (i = 0; i < childNodes.length; i++) {
            if (childNodes[i].nodeType === 3) {
                if (childNodes[i].nodeValue && childNodes[i].nodeValue.trim() !== '') {
                    buffer += ' ' + childNodes[i].nodeValue;
                }
                continue;
            }

            if (childNodes[i].nodeType !== 1) {
                continue;
            }

            if (!isVisibleElement(childNodes[i]) || isIgnoredTextContainer(childNodes[i])) {
                continue;
            }

            if (isChunkElement(childNodes[i])) {
                flushBuffer();
                var nestedChunkText = extractReadableText(childNodes[i]);
                var nestedChunkParts = splitSentenceText(nestedChunkText);
                var n;
                for (n = 0; n < nestedChunkParts.length; n++) {
                    if (nestedChunkParts[n]) {
                        segments.push(nestedChunkParts[n]);
                    }
                }
                continue;
            }

            if (isWrapperChunkCandidate(childNodes[i]) && !hasStructuralDescendant(childNodes[i])) {
                flushBuffer();
                var nestedWrapperText = extractReadableText(childNodes[i]);
                var nestedWrapperParts = splitSentenceText(nestedWrapperText);
                var o;
                for (o = 0; o < nestedWrapperParts.length; o++) {
                    if (nestedWrapperParts[o]) {
                        segments.push(nestedWrapperParts[o]);
                    }
                }
                continue;
            }

            flushBuffer();
            collectSegmentsFromElement(childNodes[i], segments);
        }

        flushBuffer();
    }

    function buildSegments(block) {
        var segments = [];
        var i;
        var text;

        if (!block) {
            return segments;
        }

        collectSegmentsFromElement(block, segments);

        if (!segments.length) {
            text = extractReadableText(block);
            segments = splitSentenceText(text);
        }

        for (i = segments.length - 1; i >= 0; i--) {
            if (!segments[i]) {
                segments.splice(i, 1);
                continue;
            }

            segments[i] = normalizeText(segments[i]);
            if (!segments[i]) {
                segments.splice(i, 1);
            }
        }

        return segments;
    }

    function speakerIcon() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M11 5L6 9H3v6h3l5 4z"></path><path d="M15 9a4 4 0 010 6"></path><path d="M17.5 6.5a8 8 0 010 11"></path></svg>';
    }

    function pauseIcon() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14"></path><path d="M16 5v14"></path></svg>';
    }

    function playIcon() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l10 7-10 7z"></path></svg>';
    }

    function stopIcon() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="7" y="7" width="10" height="10" rx="2"></rect></svg>';
    }

    function previousIcon() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"></path><path d="M9 6v12"></path></svg>';
    }

    function nextIcon() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"></path><path d="M15 6v12"></path></svg>';
    }

    function setButtonContent(button, iconSvg, label) {
        button.innerHTML = '<span class="conteudo-audio__icon" aria-hidden="true">' + iconSvg + '</span><span class="conteudo-audio__label sr-only">' + label + '</span>';
    }

    function createButton(kind, label, title, iconSvg, className) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('title', title);
        button.setAttribute('data-conteudo-audio-button', kind);
        setButtonContent(button, iconSvg, label);
        return button;
    }

    function createNotice(text) {
        var notice = document.createElement('div');
        notice.className = 'conteudo-audio-notice';
        notice.setAttribute(NOTICE_ATTR, '1');
        notice.setAttribute('role', 'note');
        notice.textContent = text;
        return notice;
    }

    function getVoice() {
        if (!supportsSpeech()) {
            return null;
        }

        var voices = window.speechSynthesis.getVoices ? window.speechSynthesis.getVoices() : [];
        var preferred = null;
        var fallback = null;
        var i;

        for (i = 0; i < voices.length; i++) {
            if (!voices[i]) {
                continue;
            }

            if (!fallback) {
                fallback = voices[i];
            }

            if (voices[i].lang === 'pt-BR') {
                return voices[i];
            }

            if (!preferred && typeof voices[i].lang === 'string' && voices[i].lang.indexOf('pt') === 0) {
                preferred = voices[i];
            }
        }

        return preferred || fallback || null;
    }

    function getControls(block) {
        return block ? block.__conteudoAudioControls : null;
    }

    function getBlockState(block) {
        return getControls(block);
    }

    function updateButtonsByState(controls, stateName) {
        var total = controls.segments.length;
        var currentIndex = total ? Math.min(Math.max(controls.currentIndex, 0), total - 1) : 0;
        var hasSegments = total > 0;
        var canNavigate = hasSegments && (stateName === STATE_PLAYING || stateName === STATE_PAUSED);
        var playLabel = 'Ouvir texto';
        var playTitle = 'Ouvir texto';
        var playIconMarkup = speakerIcon();
        var statusText = 'Pronto para ouvir.';
        var segmentText = hasSegments ? ('Trecho ' + (currentIndex + 1) + ' de ' + total) : 'Nenhum trecho disponível.';
        var pressed = 'false';
        var root = controls.root;

        root.classList.remove(READY_CLASS, ACTIVE_CLASS, PAUSED_CLASS, FINISHED_CLASS);

        if (stateName === STATE_PLAYING) {
            playLabel = 'Pausar leitura';
            playTitle = 'Pausar a leitura';
            playIconMarkup = pauseIcon();
            statusText = 'Lendo o conteúdo em voz alta.';
            pressed = 'true';
            root.classList.add(READY_CLASS, ACTIVE_CLASS);
        } else if (stateName === STATE_PAUSED) {
            playLabel = 'Continuar leitura';
            playTitle = 'Continuar a leitura';
            playIconMarkup = playIcon();
            statusText = 'Leitura pausada.';
            pressed = 'true';
            root.classList.add(READY_CLASS, ACTIVE_CLASS, PAUSED_CLASS);
        } else if (stateName === STATE_FINISHED) {
            statusText = 'Leitura concluída.';
            if (hasSegments) {
                segmentText = 'Trecho ' + total + ' de ' + total + ' concluído.';
            }
            root.classList.add(READY_CLASS, FINISHED_CLASS);
        } else {
            root.classList.add(READY_CLASS);
        }

        setButtonContent(controls.playButton, playIconMarkup, playLabel);
        controls.playButton.setAttribute('aria-label', playLabel);
        controls.playButton.setAttribute('title', playTitle);
        controls.playButton.setAttribute('aria-pressed', pressed);
        controls.playButton.classList.remove('conteudo-audio__button--play', 'conteudo-audio__button--pause', 'conteudo-audio__button--resume');
        if (stateName === STATE_PLAYING) {
            controls.playButton.classList.add('conteudo-audio__button--pause');
        } else if (stateName === STATE_PAUSED) {
            controls.playButton.classList.add('conteudo-audio__button--resume');
        } else {
            controls.playButton.classList.add('conteudo-audio__button--play');
        }

        setButtonContent(controls.prevButton, previousIcon(), 'Voltar trecho');
        controls.prevButton.setAttribute('aria-label', 'Voltar trecho');
        controls.prevButton.setAttribute('title', 'Voltar trecho');
        controls.prevButton.disabled = !canNavigate || currentIndex <= 0;
        controls.prevButton.classList.add('conteudo-audio__button--prev');

        setButtonContent(controls.nextButton, nextIcon(), 'Avançar trecho');
        controls.nextButton.setAttribute('aria-label', 'Avançar trecho');
        controls.nextButton.setAttribute('title', 'Avançar trecho');
        controls.nextButton.disabled = !canNavigate || currentIndex >= (total - 1);
        controls.nextButton.classList.add('conteudo-audio__button--next');

        setButtonContent(controls.stopButton, stopIcon(), 'Parar');
        controls.stopButton.setAttribute('aria-label', 'Parar');
        controls.stopButton.setAttribute('title', 'Parar a leitura');
        controls.stopButton.disabled = !hasSegments;
        controls.stopButton.classList.add('conteudo-audio__button--stop');

        controls.status.textContent = statusText;
        controls.segmentIndicator.textContent = segmentText;
        controls.root.setAttribute('data-conteudo-audio-state', stateName);
        controls.root.setAttribute('data-conteudo-audio-segment', hasSegments ? String(currentIndex + 1) : '0');
        controls.root.setAttribute('data-conteudo-audio-total', String(total));
        controls.root.setAttribute('aria-busy', stateName === STATE_PLAYING ? 'true' : 'false');
    }

    function updateControlState(block, stateName) {
        var controls = getControls(block);
        if (!controls) {
            return;
        }

        controls.state = stateName;
        updateButtonsByState(controls, stateName);
        block.setAttribute('data-conteudo-audio-state', stateName);
    }

    function finishPlayback(block, sessionId) {
        var controls = getControls(block);
        if (!controls) {
            return;
        }

        if (sessionId && controls.session !== sessionId) {
            return;
        }

        controls.currentUtterance = null;
        runtime.currentBlock = null;
        runtime.currentUtterance = null;
        runtime.currentState = STATE_FINISHED;

        if (controls.segments.length) {
            controls.currentIndex = controls.segments.length - 1;
        } else {
            controls.currentIndex = 0;
        }

        updateControlState(block, STATE_FINISHED);
    }

    function resetCurrentState(clearSpeech, keepIndex) {
        var block = runtime.currentBlock;
        var controls = block ? getControls(block) : null;

        if (clearSpeech && supportsSpeech()) {
            try {
                window.speechSynthesis.cancel();
            } catch (e) {
                // Ignora falhas pontuais do navegador.
            }
        }

        if (controls) {
            controls.session += 1;
            controls.currentUtterance = null;
            if (!keepIndex) {
                controls.currentIndex = 0;
            }
            updateControlState(block, STATE_IDLE);
            block.classList.remove(ACTIVE_CLASS, PAUSED_CLASS, FINISHED_CLASS);
        }

        runtime.currentBlock = null;
        runtime.currentUtterance = null;
        runtime.currentState = STATE_IDLE;
    }

    function speakChunk(block, index, options) {
        var controls = getControls(block);
        var sessionId;
        var shouldCancel;

        if (!supportsSpeech() || !controls) {
            return;
        }

        if (!controls.segments.length) {
            updateControlState(block, STATE_IDLE);
            return;
        }

        if (index < 0) {
            index = 0;
        }

        if (index >= controls.segments.length) {
            finishPlayback(block, controls.session);
            return;
        }

        options = options || {};
        shouldCancel = options.cancel !== false;

        if (shouldCancel) {
            controls.session += 1;
            sessionId = controls.session;
            try {
                window.speechSynthesis.cancel();
            } catch (e) {
                // Sem ação.
            }
        } else {
            sessionId = options.sessionId || controls.session;
            controls.session = sessionId;
        }

        controls.currentIndex = index;
        controls.state = STATE_PLAYING;
        controls.currentUtterance = null;
        runtime.currentBlock = block;
        runtime.currentState = STATE_PLAYING;
        updateControlState(block, STATE_PLAYING);

        window.setTimeout(function () {
            var currentControls = getControls(block);
            var utterance;
            var voice;

            if (!currentControls || currentControls.session !== sessionId || currentControls.currentIndex !== index || currentControls.state !== STATE_PLAYING) {
                return;
            }

            if (!currentControls.segments[index]) {
                finishPlayback(block, sessionId);
                return;
            }

            utterance = new SpeechSynthesisUtterance(currentControls.segments[index]);
            utterance.lang = 'pt-BR';
            utterance.rate = 1;
            utterance.pitch = 1;
            utterance.volume = 1;

            voice = getVoice();
            if (voice) {
                utterance.voice = voice;
            }

            utterance.onend = function () {
                var nextIndex;
                var nextControls = getControls(block);

                if (!nextControls || nextControls.session !== sessionId || nextControls.currentUtterance !== utterance) {
                    return;
                }

                nextControls.currentUtterance = null;

                nextIndex = index + 1;
                if (nextIndex < nextControls.segments.length) {
                    nextControls.currentIndex = nextIndex;
                    updateControlState(block, STATE_PLAYING);
                    speakChunk(block, nextIndex, {
                        cancel: false,
                        sessionId: sessionId
                    });
                    return;
                }

                finishPlayback(block, sessionId);
            };

            utterance.onerror = function () {
                var errorControls = getControls(block);
                if (!errorControls || errorControls.session !== sessionId || errorControls.currentUtterance !== utterance) {
                    return;
                }

                errorControls.currentUtterance = null;
                resetCurrentState(false, true);
            };

            currentControls.currentUtterance = utterance;
            runtime.currentUtterance = utterance;

            try {
                window.speechSynthesis.speak(utterance);
            } catch (error) {
                resetCurrentState(false, true);
            }
        }, SPEAK_DELAY_MS);
    }

    function pauseCurrent(block) {
        if (!supportsSpeech() || runtime.currentBlock !== block) {
            return;
        }

        if (!window.speechSynthesis.speaking || window.speechSynthesis.paused) {
            return;
        }

        try {
            window.speechSynthesis.pause();
            runtime.currentState = STATE_PAUSED;
            updateControlState(block, STATE_PAUSED);
        } catch (e) {
            resetCurrentState(false, true);
        }
    }

    function resumeCurrent(block) {
        if (!supportsSpeech() || runtime.currentBlock !== block) {
            return;
        }

        if (!window.speechSynthesis.paused) {
            return;
        }

        try {
            window.speechSynthesis.resume();
            runtime.currentState = STATE_PLAYING;
            updateControlState(block, STATE_PLAYING);
        } catch (e) {
            resetCurrentState(false, true);
        }
    }

    function stopCurrent(block, shouldCancelSpeech) {
        var controls = getControls(block);

        if (!block || !controls) {
            return;
        }

        if (shouldCancelSpeech !== false && supportsSpeech()) {
            try {
                window.speechSynthesis.cancel();
            } catch (e) {
                // Ignora.
            }
        }

        controls.session += 1;
        controls.currentUtterance = null;
        controls.currentIndex = 0;

        if (runtime.currentBlock === block) {
            runtime.currentBlock = null;
            runtime.currentUtterance = null;
        }

        runtime.currentState = STATE_IDLE;
        updateControlState(block, STATE_IDLE);
        block.classList.remove(ACTIVE_CLASS, PAUSED_CLASS, FINISHED_CLASS);
    }

    function startBlockFromBeginning(block) {
        var controls = getControls(block);

        if (!controls || !controls.segments.length) {
            updateControlState(block, STATE_IDLE);
            return;
        }

        if (runtime.currentBlock && runtime.currentBlock !== block) {
            stopCurrent(runtime.currentBlock, true);
        }

        if (runtime.currentBlock === block && runtime.currentState === STATE_PAUSED) {
            resumeCurrent(block);
            return;
        }

        if (runtime.currentBlock === block && runtime.currentState === STATE_PLAYING) {
            pauseCurrent(block);
            return;
        }

        controls.currentIndex = 0;
        speakChunk(block, 0, {
            cancel: true
        });
    }

    function jumpToNeighborChunk(block, direction) {
        var controls = getControls(block);
        var targetIndex;

        if (!controls || !controls.segments.length) {
            return;
        }

        if (controls.state !== STATE_PLAYING && controls.state !== STATE_PAUSED) {
            return;
        }

        targetIndex = controls.currentIndex + direction;
        if (targetIndex < 0 || targetIndex >= controls.segments.length) {
            return;
        }

        if (runtime.currentBlock && runtime.currentBlock !== block) {
            stopCurrent(runtime.currentBlock, true);
        }

        speakChunk(block, targetIndex, {
            cancel: true
        });
    }

    function buildControls(block) {
        if (!block || block.getAttribute(INITIALIZED_ATTR) === '1') {
            return null;
        }

        block.setAttribute(INITIALIZED_ATTR, '1');

        var segments = buildSegments(block);
        if (!segments.length) {
            var emptyNotice = createNotice('Não foi possível identificar texto legível para leitura neste conteúdo.');
            if (block.parentNode) {
                block.parentNode.insertBefore(emptyNotice, block);
            }
            block.setAttribute('data-conteudo-audio-supported', '1');
            block.setAttribute('data-conteudo-audio-empty', '1');
            return null;
        }

        if (!supportsSpeech()) {
            var unsupportedNotice = createNotice('Leitura em voz alta indisponível neste navegador.');
            if (block.parentNode) {
                block.parentNode.insertBefore(unsupportedNotice, block);
            }
            block.setAttribute('data-conteudo-audio-supported', '0');
            return null;
        }

        var controls = document.createElement('div');
        controls.className = 'conteudo-audio-controls';
        controls.setAttribute(CONTROLS_ATTR, '1');

        var toolbar = document.createElement('div');
        toolbar.className = 'conteudo-audio-controls__toolbar';

        var playButton = createButton('play', 'Ouvir texto', 'Ouvir texto', speakerIcon(), 'button-link button-link--primary conteudo-audio__button conteudo-audio__button--play');
        var prevButton = createButton('prev', 'Voltar trecho', 'Voltar trecho', previousIcon(), 'button-link button-link--ghost conteudo-audio__button conteudo-audio__button--prev');
        var nextButton = createButton('next', 'Avançar trecho', 'Avançar trecho', nextIcon(), 'button-link button-link--ghost conteudo-audio__button conteudo-audio__button--next');
        var stopButton = createButton('stop', 'Parar', 'Parar a leitura', stopIcon(), 'button-link button-link--ghost conteudo-audio__button conteudo-audio__button--stop');

        var meta = document.createElement('div');
        meta.className = 'conteudo-audio-controls__meta';

        var segmentIndicator = document.createElement('span');
        segmentIndicator.className = 'conteudo-audio-controls__segment';
        segmentIndicator.setAttribute('aria-live', 'polite');
        segmentIndicator.textContent = 'Trecho 1 de ' + segments.length;

        var status = document.createElement('span');
        status.className = 'conteudo-audio-controls__status';
        status.setAttribute('aria-live', 'polite');
        status.textContent = 'Pronto para ouvir.';

        toolbar.appendChild(playButton);
        toolbar.appendChild(prevButton);
        toolbar.appendChild(nextButton);
        toolbar.appendChild(stopButton);

        meta.appendChild(segmentIndicator);
        meta.appendChild(status);

        controls.appendChild(toolbar);
        controls.appendChild(meta);

        if (block.parentNode) {
            block.parentNode.insertBefore(controls, block);
        }

        var controlState = {
            root: controls,
            playButton: playButton,
            prevButton: prevButton,
            nextButton: nextButton,
            stopButton: stopButton,
            status: status,
            segmentIndicator: segmentIndicator,
            segments: segments,
            currentIndex: 0,
            state: STATE_IDLE,
            session: 0,
            currentUtterance: null
        };

        block.__conteudoAudioControls = controlState;
        controls.__conteudoAudioBlock = block;
        block.classList.add('js-conteudo-texto-audio--audio-ready');
        block.classList.add(READY_CLASS);
        block.setAttribute('data-conteudo-audio-total', String(segments.length));
        updateControlState(block, STATE_IDLE);

        playButton.addEventListener('click', function () {
            var controlsState = getControls(block);

            if (!supportsSpeech() || !controlsState) {
                return;
            }

            if (controlsState.state === STATE_PLAYING && runtime.currentBlock === block && window.speechSynthesis.speaking && !window.speechSynthesis.paused) {
                pauseCurrent(block);
                return;
            }

            if (controlsState.state === STATE_PAUSED && runtime.currentBlock === block && window.speechSynthesis.paused) {
                resumeCurrent(block);
                return;
            }

            if (runtime.currentBlock && runtime.currentBlock !== block) {
                stopCurrent(runtime.currentBlock, true);
            }

            if (controlsState.state === STATE_FINISHED) {
                controlsState.currentIndex = 0;
            }

            startBlockFromBeginning(block);
        });

        prevButton.addEventListener('click', function () {
            jumpToNeighborChunk(block, -1);
        });

        nextButton.addEventListener('click', function () {
            jumpToNeighborChunk(block, 1);
        });

        stopButton.addEventListener('click', function () {
            stopCurrent(block, true);
        });

        return controlState;
    }

    function initConteudoAudio(root) {
        var base = root && root.querySelectorAll ? root : document;
        var nodes = [];
        var i;
        var initialized = 0;

        if (base.nodeType === 1 && typeof base.matches === 'function' && base.matches(TARGET_SELECTOR)) {
            nodes.push(base);
        }

        nodes = nodes.concat(toArray(base.querySelectorAll(TARGET_SELECTOR)));

        for (i = 0; i < nodes.length; i++) {
            if (!nodes[i] || nodes[i].getAttribute(INITIALIZED_ATTR) === '1') {
                continue;
            }

            if (buildControls(nodes[i])) {
                initialized++;
            }
        }

        return initialized;
    }

    function stopOnPageHide() {
        if (!supportsSpeech()) {
            return;
        }

        try {
            window.speechSynthesis.cancel();
        } catch (e) {
            // Ignora.
        }

        if (runtime.currentBlock) {
            stopCurrent(runtime.currentBlock, false);
        }
    }

    function bindLifecycle() {
        if (runtime.observerBound) {
            return;
        }

        runtime.observerBound = true;

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopOnPageHide();
            }
        });

        window.addEventListener('beforeunload', stopOnPageHide);
        window.addEventListener('pagehide', stopOnPageHide);
        window.addEventListener('pageshow', function () {
            window.initConteudoAudio();
        });

        document.addEventListener('conteudo:atualizado', function (event) {
            window.initConteudoAudio(event && event.detail && event.detail.root ? event.detail.root : document);
        });

        document.addEventListener('area-curso:conteudo-atualizado', function (event) {
            window.initConteudoAudio(event && event.detail && event.detail.root ? event.detail.root : document);
        });

        document.addEventListener('area-curso:aba-alterada', function (event) {
            window.initConteudoAudio(event && event.detail && event.detail.root ? event.detail.root : document);
        });

        document.addEventListener('shown.bs.tab', function () {
            window.initConteudoAudio();
        });

        document.addEventListener('click', function (event) {
            var target = event.target;
            if (!target) {
                return;
            }

            if (target.matches && target.matches('[data-audio-refresh="1"]')) {
                window.initConteudoAudio();
            }
        });

        if (window.MutationObserver && document.body) {
            var schedule = function () {
                if (runtime.initScheduled) {
                    return;
                }

                runtime.initScheduled = true;
                window.setTimeout(function () {
                    runtime.initScheduled = false;
                    window.initConteudoAudio();
                }, 80);
            };

            var observer = new MutationObserver(function (mutations) {
                var shouldInit = false;
                var i;
                var j;

                for (i = 0; i < mutations.length && !shouldInit; i++) {
                    var added = mutations[i].addedNodes || [];
                    for (j = 0; j < added.length; j++) {
                        if (!added[j] || added[j].nodeType !== 1) {
                            continue;
                        }

                        if (typeof added[j].matches === 'function' && added[j].matches(TARGET_SELECTOR)) {
                            shouldInit = true;
                            break;
                        }

                        if (added[j].querySelector && added[j].querySelector(TARGET_SELECTOR)) {
                            shouldInit = true;
                            break;
                        }
                    }
                }

                if (shouldInit) {
                    schedule();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    window.initConteudoAudio = function (root) {
        bindLifecycle();
        return initConteudoAudio(root || document);
    };

    ready(function () {
        window.initConteudoAudio();
    });

    window.addEventListener('load', function () {
        window.initConteudoAudio();
    });
})();
