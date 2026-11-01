/**
 * Global loading indicator (POS Workspace V3)
 * Shows automatically on: link navigation, form submit, fetch() and XMLHttpRequest.
 *
 * Opt out:   <a data-no-loading>, <form data-no-loading>, fetch(url, {noLoading: true})
 * Manual:    AppLoading.show();  AppLoading.hide();  AppLoading.reset();
 */
(function () {
    'use strict';

    var dlg = document.getElementById('app-loading');
    if (!dlg || typeof dlg.showModal !== 'function') { return; }

    var DELAY  = 150;     // ms before the overlay appears (no flicker on fast requests)
    var SAFETY = 30000;   // ms; auto-hide if something never finishes (e.g. file download)
    var count = 0, timer = null, safety = null, lockedButtons = [];

    function open() {
        timer = null;
        if (!dlg.open) { dlg.showModal(); }
    }
    function show() {
        count++;
        if (count === 1) {
            timer  = setTimeout(open, DELAY);
            safety = setTimeout(reset, SAFETY);
        }
    }
    function hide() {
        count = Math.max(0, count - 1);
        if (count === 0) { reset(); }
    }
    function reset() {
        count = 0;
        clearTimeout(timer); clearTimeout(safety);
        timer = safety = null;
        if (dlg.open) { dlg.close(); }
        lockedButtons.forEach(function (b) { b.disabled = false; });
        lockedButtons = [];
    }

    dlg.addEventListener('cancel', function (e) { e.preventDefault(); });   // Esc must not close it

    /* ---- Link navigation ---- */
    document.addEventListener('click', function (e) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.hasAttribute('data-no-loading') || a.hasAttribute('download')) { return; }
        if (a.target && a.target !== '_self') { return; }
        var raw = a.getAttribute('href');
        if (!raw || raw.charAt(0) === '#') { return; }
        var u;
        try { u = new URL(a.href, location.href); } catch (err) { return; }
        if (!/^https?:$/.test(u.protocol) || u.origin !== location.origin) { return; }
        if (u.pathname === location.pathname && u.search === location.search && u.hash) { return; }
        setTimeout(function () { if (!e.defaultPrevented) { show(); } }, 0);   // after other handlers ran
    });

    /* ---- Form submit (fires only when the browser validation passed) ---- */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || f.hasAttribute('data-no-loading') || f.method === 'dialog' || (f.target && f.target !== '_self')) { return; }
        setTimeout(function () {
            if (e.defaultPrevented) { return; }
            show();
            // stop double submits
            f.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (b) {
                if (!b.disabled) { b.disabled = true; lockedButtons.push(b); }
            });
        }, 0);
    });

    /* ---- fetch() ---- */
    if (window.fetch) {
        var nativeFetch = window.fetch;
        window.fetch = function (input, init) {
            if (init && init.noLoading) { return nativeFetch.apply(this, arguments); }
            show();
            return nativeFetch.apply(this, arguments).then(
                function (r) { hide(); return r; },
                function (err) { hide(); throw err; }
            );
        };
    }

    /* ---- XMLHttpRequest (jQuery $.ajax, axios, ...) ---- */
    var nativeSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function () {
        show();
        this.addEventListener('loadend', hide, { once: true });
        return nativeSend.apply(this, arguments);
    };

    /* ---- Back/forward cache: page restored with overlay still open ---- */
    window.addEventListener('pageshow', function (e) { if (e.persisted) { reset(); } });

    window.AppLoading = { show: show, hide: hide, reset: reset };
})();