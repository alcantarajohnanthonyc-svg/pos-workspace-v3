<?php helper('security'); ?>
<script>
/* Approve / Reject buttons (drawer + full page).
   POST = action, so the global loading overlay shows by itself; the page then reloads to show the new status. */
(function () {
    var CFG = <?= json_encode([
        'base'       => base_url('approval-queue'),
        'csrfHeader' => csrf_header(),
        'token'      => csrf_hash(),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var token = CFG.token;

    function post(path, body) {
        var h = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        h[CFG.csrfHeader] = token;
        return fetch(CFG.base + '/' + path, { method: 'POST', headers: h, body: JSON.stringify(body || {}), credentials: 'same-origin' })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (j) {
                    if (j && j.csrf) { token = j.csrf; }
                    j.__status = res.status;
                    return j;
                });
            });
    }
    window.AQ = { post: post };

    function showError(panel, msg) {
        var el = panel.querySelector('[data-aq-error]');
        if (!el) { window.alert(msg); return; }
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    /* tick / untick lines */
    function lineCount(panel) {
        var boxes = panel.querySelectorAll('[data-aq-line]');
        var on = 0;
        boxes.forEach(function (c) { if (c.checked) { on++; } });
        var out = panel.querySelector('[data-aq-line-count]');
        if (out) { out.textContent = on + ' of ' + boxes.length + ' will be approved'; }
        var all = panel.querySelector('[data-aq-line-all]');
        if (all) { all.checked = on === boxes.length; all.indeterminate = on > 0 && on < boxes.length; }
    }
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.matches) { return; }
        var panel = t.closest('[data-aq-panel]');
        if (!panel) { return; }
        if (t.matches('[data-aq-line-all]')) {
            panel.querySelectorAll('[data-aq-line]').forEach(function (c) { c.checked = t.checked; });
            lineCount(panel);
        } else if (t.matches('[data-aq-line]')) {
            lineCount(panel);
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-aq-decide]') : null;
        if (!btn) { return; }

        var panel    = btn.closest('[data-aq-panel]') || document;
        var decision = btn.getAttribute('data-aq-decide');
        var id       = btn.getAttribute('data-id');
        var noteEl   = panel.querySelector('[data-aq-note]');
        var note     = noteEl ? noteEl.value.trim() : '';
        var errEl    = panel.querySelector('[data-aq-error]');
        if (errEl) { errEl.classList.add('hidden'); }

        /* lines the approver unticked (import / bulk requests) */
        var lineBoxes = panel.querySelectorAll('[data-aq-line]');
        var exclude   = [];
        lineBoxes.forEach(function (c) { if (!c.checked) { exclude.push(parseInt(c.value, 10)); } });
        if (decision === 'approve' && lineBoxes.length && exclude.length === lineBoxes.length) {
            showError(panel, 'Tick at least one line to approve, or reject the whole request.');
            return;
        }

        if (decision === 'reject' && note === '') {
            showError(panel, 'Please write the reason for rejecting this request.');
            if (noteEl) { noteEl.focus(); }
            return;
        }
        var ask = 'Approve this request?';
        if (decision === 'approve' && exclude.length) {
            ask = 'Approve ' + (lineBoxes.length - exclude.length) + ' of ' + lineBoxes.length + ' lines? ' + exclude.length + ' will be left out.';
        }
        if (decision === 'approve' && !window.confirm(ask)) { return; }

        var buttons = panel.querySelectorAll('[data-aq-decide]');
        buttons.forEach(function (b) { b.disabled = true; });

        post(decision + '/' + id, { note: note, exclude: decision === 'approve' ? exclude : [] }).then(function (j) {
            if (j && j.ok) {
                window.location.reload();
                return;
            }
            showError(panel, (j && j.error) || 'Something went wrong. Please try again.');
            buttons.forEach(function (b) { b.disabled = false; });
        }).catch(function () {
            showError(panel, 'Network error. Please try again.');
            buttons.forEach(function (b) { b.disabled = false; });
        });
    });
})();
</script>
