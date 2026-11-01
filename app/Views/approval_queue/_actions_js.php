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

        if (decision === 'reject' && note === '') {
            showError(panel, 'Please write the reason for rejecting this request.');
            if (noteEl) { noteEl.focus(); }
            return;
        }
        if (decision === 'approve' && !window.confirm('Approve this request?')) { return; }

        var buttons = panel.querySelectorAll('[data-aq-decide]');
        buttons.forEach(function (b) { b.disabled = true; });

        post(decision + '/' + id, { note: note }).then(function (j) {
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
