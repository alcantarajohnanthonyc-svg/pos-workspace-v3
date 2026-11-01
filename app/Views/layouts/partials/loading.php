
<?php
/**
 * Global loading overlay. Included once by the layout (layouts/main.php).
 * Behaviour lives in public/assets/js/loading.js
 *
 * It is a <dialog> on purpose: dialogs/modals use the browser "top layer", so a
 * normal z-index overlay would hide BEHIND an open modal. A dialog opened later
 * always stacks above the earlier one.
 */
?>
<style>
    #app-loading { border: 0; padding: 0; background: transparent; max-width: none; max-height: none;
                   width: 100%; height: 100%; overflow: hidden; }
    #app-loading::backdrop { background: rgba(15, 23, 42, .45); }
    #app-loading .al-box { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; }
    #app-loading .al-card { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.1rem;
                            background: #fff; border: 1px solid #E2E8F0; border-radius: .75rem;
                            box-shadow: 0 10px 25px rgba(15, 23, 42, .15); color: #1E293B; font-size: .8125rem; font-weight: 500; }
    #app-loading .al-spin { width: 1.25rem; height: 1.25rem; border-radius: 9999px;
                            border: 2px solid #D1FAE5; border-top-color: #059669; animation: al-rot .8s linear infinite; }
    @keyframes al-rot { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { #app-loading .al-spin { animation-duration: 2s; } }
</style>

<dialog id="app-loading" aria-label="Loading">
    <div class="al-box" role="status" aria-live="polite">
        <div class="al-card">
            <span class="al-spin" aria-hidden="true"></span>
            <span>Loading, please wait…</span>
        </div>
    </div>
</dialog>