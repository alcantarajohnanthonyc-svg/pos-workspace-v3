/*
 * Global loading overlay - ACTIONS ONLY.
 *
 * Shows automatically for user ACTIONS:
 *   - form submit (POST forms: Save, Delete, Login, ...)
 *   - fetch / XMLHttpRequest with a non-GET method (POST, PUT, PATCH, DELETE)
 * Never shows for:
 *   - page load, normal links, GET forms (search / filter)
 *   - GET fetch requests (the session heartbeat, polling)
 *
 * Opt out:
 *   <form data-loading="off">                          no overlay for this form
 *   fetch(url, { method: 'POST', loading: false })     silent fetch
 *   xhr.setRequestHeader('X-Loading', 'off')           silent XHR
 *
 * Opt in extras:
 *   <a data-loading="Preparing export..." data-loading-for="2500" href="...">   link / download
 *   <form data-loading="Saving store...">              custom text
 *
 * Manual:
 *   AppLoading.show('text'); AppLoading.hide(); AppLoading.wrap(promise, 'text');
 */
(function () {
    'use strict';

    var dlg = document.getElementById('app-loading');
    if (!dlg) { return; }
    var label = dlg.querySelector('.al-card span:last-child');
    var DEFAULT_TEXT = label ? label.textContent : 'Loading, please wait...';
    var count = 0;
    var safety = null;
    var SAFETY_MS = 60000;
    var FETCH_DELAY = 150;

    function open() {
        if (typeof dlg.showModal === 'function') {
            if (!dlg.open) { dlg.showModal(); }
        } else {
            dlg.setAttribute('open', '');
        }
    }
    function close() {
        if (typeof dlg.close === 'function') {
            if (dlg.open) { dlg.close(); }
        } else {
            dlg.removeAttribute('open');
        }
    }

    function show(text) {
        count++;
        if (label) { label.textContent = text || DEFAULT_TEXT; }
        open();
        clearTimeout(safety);
        safety = setTimeout(hideAll, SAFETY_MS);
    }
    function hide() {
        count = Math.max(0, count - 1);
        if (count === 0) { hideAll(); }
    }
    function hideAll() {
        count = 0;
        clearTimeout(safety);
        close();
    }

    /* show after a short delay so very fast requests do not flash the overlay */
    function begin(text) {
        var t = { shown: false };
        t.timer = setTimeout(function () { t.shown = true; show(text); }, FETCH_DELAY);
        return t;
    }
    function end(t) {
        clearTimeout(t.timer);
        if (t.shown) { hide(); }
    }

    function wrap(promise, text) {
        show(text);
        var done = function () { hide(); };
        Promise.resolve(promise).then(done, done);
        return promise;
    }

    window.AppLoading = { show: show, hide: hide, hideAll: hideAll, wrap: wrap };

    dlg.addEventListener('cancel', function (e) { e.preventDefault(); });

    /* ---------- fetch ---------- */
    function headerOff(h) {
        if (!h) { return false; }
        var v = '';
        if (typeof h.get === 'function') { v = h.get('X-Loading'); }
        else { v = h['X-Loading'] || h['x-loading']; }
        return String(v || '').toLowerCase() === 'off';
    }

    if (typeof window.fetch === 'function') {
        var nativeFetch = window.fetch;
        window.fetch = function (input, init) {
            var method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
            var silent = (init && init.loading === false) ||
                         headerOff(init && init.headers) ||
                         headerOff(input && input.headers);

            if (silent || method === 'GET' || method === 'HEAD' || method === 'OPTIONS') {
                return nativeFetch.apply(this, arguments);
            }

            var t = begin('');
            var p = nativeFetch.apply(this, arguments);
            var done = function () { end(t); };
            p.then(done, done);
            return p;
        };
    }

    /* ---------- XMLHttpRequest ---------- */
    if (window.XMLHttpRequest) {
        var xOpen = XMLHttpRequest.prototype.open;
        var xSend = XMLHttpRequest.prototype.send;
        var xHeader = XMLHttpRequest.prototype.setRequestHeader;

        XMLHttpRequest.prototype.open = function (method) {
            this._alMethod = String(method || 'GET').toUpperCase();
            this._alSilent = false;
            return xOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            if (String(name).toLowerCase() === 'x-loading' && String(value).toLowerCase() === 'off') {
                this._alSilent = true;
            }
            return xHeader.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
            var m = this._alMethod || 'GET';
            if (!this._alSilent && m !== 'GET' && m !== 'HEAD' && m !== 'OPTIONS') {
                var t = begin('');
                this.addEventListener('loadend', function () { end(t); });
            }
            return xSend.apply(this, arguments);
        };
    }

    /* ---------- forms ---------- */
    function textOf(el) {
        var v = el.getAttribute('data-loading');
        return (v && v !== 'off' && v !== 'true') ? v : '';
    }

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!(f instanceof HTMLFormElement)) { return; }
        if (e.defaultPrevented) { return; }
        if (f.hasAttribute('data-loading-skip') || f.closest('[data-loading="off"]')) { return; }
        if ((f.method || 'get').toLowerCase() === 'get' && !f.hasAttribute('data-loading')) { return; }
        if (f.target === '_blank') { return; }

        show(textOf(f));
        var ms = parseInt(f.getAttribute('data-loading-for'), 10);
        if (ms > 0) { setTimeout(hide, ms); }
    });

    /* ---------- links / buttons (opt-in only) ---------- */
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-loading], button[data-loading]') : null;
        if (!a || a.getAttribute('data-loading') === 'off' || a.hasAttribute('data-loading-skip')) { return; }
        if (a.tagName === 'BUTTON' && a.type === 'submit' && a.form) { return; }
        if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) { return; }
        if (a.tagName === 'A' && (a.target === '_blank' || /^(#|javascript:)/i.test(a.getAttribute('href') || ''))) { return; }
        show(textOf(a));
        var ms = parseInt(a.getAttribute('data-loading-for'), 10);
        if (ms > 0) { setTimeout(hide, ms); }
    });

    window.addEventListener('pageshow', hideAll);
})();