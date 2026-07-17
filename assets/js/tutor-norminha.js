(function () {
    var STORAGE_KEY = 'norminha_tutor_minimized_v1';
    var LEGACY_KEYS = ['norminha_tutor_closed_until', 'norminha_tutor_closed_v2', 'norminha_tutor_closed'];
    var DEFAULT_TTL_HOURS = 24;

    function canUseStorage() {
        try {
            return !!window.localStorage;
        } catch (error) {
            return false;
        }
    }

    function getTtlHours(container) {
        if (!container) {
            return DEFAULT_TTL_HOURS;
        }

        var raw = container.getAttribute('data-ttl-hours');
        var ttlHours = parseInt(raw, 10);
        if (!ttlHours || ttlHours < 1 || ttlHours > 168) {
            return DEFAULT_TTL_HOURS;
        }

        return ttlHours;
    }

    function getButtonBaseLabel(container) {
        if (!container) {
            return 'Ouvir orientação';
        }

        var label = (container.getAttribute('data-texto-botao') || '').trim();
        return label || 'Ouvir orientação';
    }

    function clearLegacyState() {
        if (!canUseStorage()) {
            return;
        }

        try {
            for (var i = 0; i < LEGACY_KEYS.length; i += 1) {
                window.localStorage.removeItem(LEGACY_KEYS[i]);
            }
        } catch (error) {
        }
    }

    function readMinimizedState() {
        if (!canUseStorage()) {
            return false;
        }

        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function writeMinimizedState() {
        if (!canUseStorage()) {
            return;
        }

        try {
            window.localStorage.setItem(STORAGE_KEY, '1');
        } catch (error) {
            // Ignora falhas de storage sem quebrar a página.
        }
    }

    function clearMinimizedState() {
        if (!canUseStorage()) {
            return;
        }

        try {
            window.localStorage.removeItem(STORAGE_KEY);
        } catch (error) {
        }
    }

    function setAvatarState(img, container, state) {
        if (!img || !container) {
            return;
        }

        var idleSrc = container.getAttribute('data-avatar-idle') || '/assets/norminha/norminha-idle.webp';
        var speakingSrc = container.getAttribute('data-avatar-speaking') || '/assets/norminha/norminha-speaking.webp';
        img.src = state === 'speaking' ? speakingSrc : idleSrc;
    }

    function updateButtonState(button, audio, buttonBaseLabel) {
        if (!button || !audio) {
            return;
        }

        if (audio.paused) {
            if (audio.currentTime > 0 && !audio.ended) {
                button.textContent = 'Continuar';
                return;
            }

            if (audio.ended) {
                button.textContent = 'Ouvir novamente';
                return;
            }

            button.textContent = buttonBaseLabel;
            return;
        }

        button.textContent = 'Pausar';
    }

    function initTutor() {
        var container = document.getElementById('norminha-tutor');
        if (!container) {
            return;
        }

        clearLegacyState();

        var closeButton = container.querySelector('[data-norminha-close]');
        var launcherButton = container.querySelector('[data-norminha-launcher]');
        var audioButton = container.querySelector('[data-norminha-audio-button]');
        var audio = container.querySelector('[data-norminha-audio]');
        var avatar = container.querySelector('[data-avatar-image]');
        var avatarWrap = container.querySelector('.norminha-tutor__avatar-wrap');
        var launcherAvatar = container.querySelector('[data-norminha-launcher-avatar]');
        var launcherFallback = container.querySelector('[data-norminha-launcher-fallback]');
        var buttonBaseLabel = getButtonBaseLabel(container);
        var minimized = readMinimizedState();

        if (avatar) {
            avatar.addEventListener('error', function () {
                avatar.style.display = 'none';
                if (avatarWrap) {
                    avatarWrap.classList.add('is-fallback');
                }
            });
        }

        if (launcherAvatar) {
            launcherAvatar.addEventListener('error', function () {
                launcherAvatar.style.display = 'none';
                if (launcherButton) {
                    launcherButton.classList.add('is-fallback');
                }
            });
        } else if (launcherFallback && launcherButton) {
            launcherButton.classList.add('is-fallback');
        }

        if (!audio || !audio.getAttribute('src')) {
            if (audioButton && audioButton.parentNode) {
                audioButton.parentNode.removeChild(audioButton);
            }
        }

        function syncVisibility() {
            var isMinimized = container.classList.contains('is-minimized');
            container.classList.toggle('is-minimized', isMinimized);
            document.documentElement.classList.toggle('norminha-tutor-minimized', isMinimized);
            if (launcherButton) {
                launcherButton.setAttribute('aria-hidden', isMinimized ? 'false' : 'true');
                launcherButton.tabIndex = isMinimized ? 0 : -1;
            }
            if (launcherButton && launcherAvatar && launcherFallback) {
                if (launcherAvatar.style.display === 'none') {
                    launcherButton.classList.add('is-fallback');
                } else if (!launcherAvatar.getAttribute('src')) {
                    launcherButton.classList.add('is-fallback');
                }
            }
        }

        function minimizeTutor() {
            writeMinimizedState();
            container.classList.add('is-minimized');
            document.documentElement.classList.add('norminha-tutor-minimized');
            if (audio && !audio.paused) {
                audio.pause();
            }
            syncVisibility();
        }

        function restoreTutor() {
            clearMinimizedState();
            container.classList.remove('is-minimized');
            document.documentElement.classList.remove('norminha-tutor-minimized');
            syncVisibility();
        }

        if (minimized) {
            container.classList.add('is-minimized');
            document.documentElement.classList.add('norminha-tutor-minimized');
        } else {
            container.classList.remove('is-minimized');
            document.documentElement.classList.remove('norminha-tutor-minimized');
        }

        syncVisibility();

        function speakMode() {
            container.classList.add('is-speaking');
            setAvatarState(avatar, container, 'speaking');
        }

        function idleMode() {
            container.classList.remove('is-speaking');
            setAvatarState(avatar, container, 'idle');
        }

        if (audio) {
            audio.addEventListener('play', function () {
                speakMode();
                updateButtonState(audioButton, audio, buttonBaseLabel);
            });

            audio.addEventListener('pause', function () {
                if (!audio.ended) {
                    idleMode();
                }
                updateButtonState(audioButton, audio, buttonBaseLabel);
            });

            audio.addEventListener('ended', function () {
                idleMode();
                updateButtonState(audioButton, audio, buttonBaseLabel);
            });

            audio.addEventListener('loadedmetadata', function () {
                updateButtonState(audioButton, audio, buttonBaseLabel);
            });

            audio.addEventListener('seeked', function () {
                updateButtonState(audioButton, audio, buttonBaseLabel);
            });
        }

        if (audioButton && audio) {
            updateButtonState(audioButton, audio, buttonBaseLabel);

            audioButton.addEventListener('click', function () {
                if (!audio.getAttribute('src')) {
                    return;
                }

                if (audio.ended) {
                    audio.currentTime = 0;
                }

                if (audio.paused) {
                    try {
                        audio.load();
                    } catch (error) {
                        // Continua sem quebrar a página.
                    }
                    var promise = audio.play();
                    if (promise && typeof promise.catch === 'function') {
                        promise.catch(function () {
                            idleMode();
                            updateButtonState(audioButton, audio, buttonBaseLabel);
                        });
                    }
                } else {
                    audio.pause();
                }
            });
        }

        if (closeButton) {
            closeButton.addEventListener('click', function () {
                minimizeTutor();
            });
        }

        if (launcherButton) {
            launcherButton.addEventListener('click', function () {
                restoreTutor();
            });
        }

        setAvatarState(avatar, container, container.getAttribute('data-estado-avatar') || 'speaking');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTutor);
    } else {
        initTutor();
    }
})();
