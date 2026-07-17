(function () {
    'use strict';

    function onMessage(event) {
        var data = event.data;
        if (!data || data.source !== 'desbloqueia-html-embed' || !data.frameId) {
            return;
        }

        var frame = document.getElementById(data.frameId);
        if (!frame || frame.contentWindow !== event.source) {
            return;
        }

        var height = parseInt(data.height, 10);
        if (!isFinite(height) || height <= 0) {
            return;
        }

        var maxHeight = Math.round(window.innerHeight * 0.85);
        frame.style.height = Math.min(height + 8, maxHeight) + 'px';
    }

    window.addEventListener('message', onMessage);
})();
