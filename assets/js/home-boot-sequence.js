(function () {
    var root = document.documentElement;
    if (!root.classList.contains('home-boot-ready')) {
        return;
    }

    var section = document.querySelector('[data-home-boot-root]');
    if (!section) {
        return;
    }

    var reducedMotion = root.classList.contains('home-boot-reduced-motion');
    var alreadyComplete = root.classList.contains('home-boot-complete');
    var terminal = section.querySelector('[data-home-boot-terminal]');
    var actions = section.querySelector('.home-boot-actions');
    var lines = Array.prototype.slice.call(section.querySelectorAll('[data-home-boot-line]'));
    var skipLink = section.querySelector('[data-home-boot-skip]');
    var primaryCta = section.querySelector('[data-home-boot-primary]');
    var storageKey = 'dbc_home_boot_complete';
    var timers = [];
    var finished = false;

    if (reducedMotion || alreadyComplete) {
        root.classList.remove('home-boot-playing');
        root.classList.add('home-boot-ready');
        if (actions) {
            actions.classList.add('is-visible');
        }
        if (primaryCta) {
            primaryCta.classList.add('is-ready');
        }
        return;
    }

    root.classList.add('home-boot-playing');

    function setSessionComplete() {
        try {
            if (window.sessionStorage) {
                sessionStorage.setItem(storageKey, '1');
            }
        } catch (error) {
            // Ignorar falhas de storage.
        }
    }

    function clearTimers() {
        while (timers.length) {
            window.clearTimeout(timers.pop());
        }
    }

    function focusOrientation() {
        var target = document.getElementById('orientacao-novo-usuario');
        if (!target) {
            return;
        }

        if (!target.hasAttribute('tabindex')) {
            target.setAttribute('tabindex', '-1');
        }

        if (target.scrollIntoView) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        if (target.focus) {
            target.focus({ preventScroll: true });
        }
    }

    function finish(scrollNow) {
        if (finished) {
            return;
        }

        finished = true;
        clearTimers();
        root.classList.remove('home-boot-playing');
        root.classList.add('home-boot-complete', 'home-boot-ready');
        section.classList.add('is-complete', 'is-revealed');
        if (actions) {
            actions.classList.add('is-visible');
        }

        if (primaryCta) {
            primaryCta.classList.add('is-ready');
        }

        setSessionComplete();

        if (scrollNow) {
            focusOrientation();
        }
    }

    function schedule(fn, delay) {
        timers.push(window.setTimeout(fn, delay));
    }

    if (skipLink) {
        skipLink.addEventListener('click', function (event) {
            event.preventDefault();
            finish(true);
        });
    }

    if (primaryCta) {
        primaryCta.addEventListener('click', function (event) {
            event.preventDefault();
            finish(true);
        });
    }

    schedule(function () {
        section.classList.add('is-revealed');
    }, 120);

    schedule(function () {
        if (terminal) {
            terminal.classList.add('is-visible');
        }
    }, 420);

    lines.forEach(function (line, index) {
        schedule(function () {
            line.classList.add('is-visible');

            if (index === lines.length - 1 && primaryCta) {
                if (actions) {
                    actions.classList.add('is-visible');
                }
                primaryCta.classList.add('is-ready');
                schedule(function () {
                    finish(false);
                }, 520);
            }
        }, 760 + (index * 250));
    });

    if (lines.length === 0) {
        schedule(function () {
            finish(false);
        }, 900);
    }
})();
