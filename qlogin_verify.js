(function() {
    'use strict';

    var root = document.getElementById('qlogin-verification');
    if (!root) {
        return;
    }

    var storagekey = 'local_qlogin_shomokh_whatsapp_return';
    var link = root.querySelector('[data-qlogin-whatsapp="1"]');
    if (root.dataset.phonePending !== '1') {
        window.sessionStorage.removeItem(storagekey);
        return;
    }

    if (link) {
        link.addEventListener('click', function() {
            window.sessionStorage.setItem(storagekey, String(Date.now()));
        });
    }

    var started = Number(window.sessionStorage.getItem(storagekey) || 0);
    if (!started || Date.now() - started > 10 * 60 * 1000) {
        window.sessionStorage.removeItem(storagekey);
        return;
    }

    var lastrefresh = 0;
    var refresh = function() {
        if (Date.now() - started < 2500 || Date.now() - lastrefresh < 3000) {
            return;
        }
        lastrefresh = Date.now();
        window.location.reload();
    };

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            refresh();
        }
    });
    window.addEventListener('focus', refresh);
    window.setTimeout(refresh, 12000);
}());
