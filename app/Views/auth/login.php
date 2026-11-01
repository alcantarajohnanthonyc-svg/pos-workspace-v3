<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In | POS Workspace V3</title>
<link rel="stylesheet" href="<?= esc(base_url('assets/css/theme.css'), 'attr') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
    --navy: var(--pw-navy);
    --muted: var(--pw-muted);
    --emerald: #047857;
}

* { box-sizing: border-box; }

html, body {
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    width: 100%;
    height: auto;
    overflow-x: hidden;
    overflow-y: auto;
    font-family: Inter, "Segoe UI", Arial, sans-serif;
    color: var(--navy);
    background: var(--pw-canvas-blue) url('/assets/images/pos-workspace-login-bgv1.png') no-repeat;
    background-size: 100% 100%;
    background-position: center;
    position: relative;
}

.login-wrapper {
    width: 100%;
    min-height: 100vh;
    height: auto;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 20px 2vw 20px 0;
}

/* Semi-transparent Glassmorphism Card sa Kanang Bahagi */
.login-card {
    width: 100%;
    max-width: 370px;
    margin-right: 2vw;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 18px;
    padding: 24px 22px;
    box-shadow: 0 15px 35px rgba(16, 45, 99, 0.15);
    overflow: visible;
    text-align: center;
}

.brand-mark {
    width: 44px;
    height: 44px;
    margin: 0 auto 8px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: transparent;
}

.brand-mark img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-title {
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -.5px;
    line-height: 1.2;
    margin: 0;
    color: var(--pw-navy-deep);
}

.brand-title span { color: var(--pw-primary); }

.subtitle {
    margin: 4px 0 14px;
    color: var(--muted);
    font-size: 13px;
}

.auth-button {
    min-height: 44px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 9px;
    border: 0;
    background: linear-gradient(135deg, #059669, #047857);
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 2px 5px rgba(20, 50, 90, .04);
    transition: all .18s ease;
}

.auth-button:hover {
    background: linear-gradient(135deg, #047857, #065f46);
    color: #fff;
    transform: translateY(-1px);
}

.auth-divider { display:flex; align-items:center; gap:12px; margin:13px 0; color:var(--pw-muted); font-size:11px; font-weight:700; }
.auth-divider::before, .auth-divider::after { content:""; height:1px; flex:1; background:var(--pw-border); }
.credential-form { text-align:left; }
.credential-form label { color:var(--pw-navy-deep); font-size:12px; font-weight:700; }
.credential-form .form-control { min-height:42px; border-radius:9px; }
.credential-back { width:100%; margin-top:9px; color:var(--pw-primary-hover); font-size:12px; font-weight:700; }
[hidden] { display:none !important; }

.google-g {
    font-size: 18px;
    font-weight: 800;
    line-height: 1;
    background: conic-gradient(from -45deg, var(--pw-google-blue) 0 25%, var(--pw-google-green) 25% 50%, var(--pw-google-yellow) 50% 75%, var(--pw-google-red) 75%);
    color: transparent;
    background-clip: text;
    -webkit-background-clip: text;
}

.contact-line {
    font-size: 12px;
    color: var(--pw-muted-strong);
    margin: 12px 0 0;
}

.contact-line a {
    color: var(--pw-primary-hover);
    font-weight: 700;
    text-decoration: none;
}

.contact-line button {
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--pw-primary-hover);
    font: inherit;
    font-weight: 700;
    text-decoration: none;
}

.contact-modal .modal-content {
    border: 1px solid rgba(255, 255, 255, .8);
    border-radius: 18px;
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 18px 48px rgba(16, 45, 99, .22);
}

.btn-primary {
    border: 0;
    background: linear-gradient(135deg, #059669, #047857);
    font-weight: 700;
}
.btn-primary:hover, .btn-primary:focus { background: linear-gradient(135deg, #047857, #065f46); }
.session-conflict .modal-content { border: 1px solid var(--pw-border-field); border-radius: 18px; box-shadow: 0 18px 48px rgba(16, 45, 50, .22); }
.session-conflict .modal-title { color: var(--pw-navy-deep); font-weight: 800; }
.session-conflict .modal-body { color: var(--pw-muted-strong); line-height: 1.55; }
.session-conflict .confirm-replacement { min-height: 42px; border: 0; border-radius: 9px; padding: 9px 16px; color: #fff; background: linear-gradient(135deg, #059669, #047857); font-weight: 700; }
.modal-footer form { display: flex; max-width: 100%; }
.modal-footer button { min-height: 44px; }
.modal-header .btn-close { min-width: 44px; min-height: 44px; background-size: 13px; }
.contact-modal .btn, .pending-modal .btn { min-height: 44px; }

.contact-modal .modal-header { border-bottom-color: var(--line); }
.contact-modal .modal-title { color: var(--navy); font-weight: 800; }
.contact-modal .modal-body { color: var(--pw-muted-strong); }
.contact-modal a { color: var(--pw-primary-hover); font-weight: 700; }

.pending-modal .modal-content {
    border: 1px solid var(--pw-border-field);
    border-radius: 16px;
    box-shadow: 0 18px 48px rgba(16, 45, 99, .2);
}

.pending-modal .modal-header { border-bottom: 0; padding-bottom: 0; }
.pending-modal .modal-body { padding: 10px 22px 20px; }

.pending-avatar {
    width: 72px;
    height: 72px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 3px solid var(--pw-primary-tint, #059669);
    border-radius: 50%;
    background: #f3f4f6;
    color: var(--pw-primary, #059669);
    font-size: 26px;
}

.pending-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    border-radius: 50%;
}

.pending-copy { color: var(--pw-muted); font-size: 12px; line-height: 1.5; }

.pending-steps {
    margin: 14px 0;
    padding: 4px 12px;
    border: 1px solid var(--pw-border);
    border-radius: 10px;
    background: var(--pw-surface-subtle);
    text-align: left;
}

.pending-step {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid var(--pw-border-soft);
    font-size: 11px;
}

.pending-step:last-child { border-bottom: 0; }
.pending-step-label { color: var(--pw-text-secondary); font-weight: 700; }
.pending-step-status { margin-left: auto; color: var(--pw-muted-strong); text-align: right; }
.pending-step-icon { color: var(--pw-muted-light); }

.pending-info {
    padding: 10px;
    border: 1px solid var(--pw-primary-tint);
    border-radius: 9px;
    background: var(--pw-primary-pale);
    color: var(--pw-text-tertiary);
    font-size: 11px;
    line-height: 1.5;
    text-align: left;
}

.pending-info a { color: var(--pw-primary-hover); font-weight: 700; }

.security-note {
    margin-top: 12px;
    text-align: left;
    display: flex;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(241, 247, 255, 0.85);
    border: 1px solid var(--pw-primary-pale);
    color: var(--pw-muted-strong);
    font-size: 10px;
    line-height: 1.35;
}

.security-note strong { color: var(--pw-text-secondary); }

.footer {
    margin-top: 12px;
    margin-bottom: 0;
    color: var(--pw-muted-faint);
    font-size: 10.5px;
}

@media(max-width: 576px) {
    .login-wrapper {
        justify-content: center;
        padding: 20px 16px;
    }
    .login-card {
        margin-right: 0;
        padding: 20px 16px;
    }
}
</style>
</head>
<body>
<main class="login-wrapper">
    <div class="login-card">
        <div class="brand-mark">
            <img src="/assets/images/P-logo.png" alt="POS Workspace Logo">
        </div>
        <h1 class="brand-title">POS Workspace <span>V3</span></h1>
        <p class="subtitle">Sign in to your account</p>
        
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger text-start small" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('message')): ?>
            <div class="alert alert-success text-start small" role="status"><?= esc(session()->getFlashdata('message')) ?></div>
        <?php endif; ?>
        
        <?php
            $showCredentialForm = (bool) ($showCredentialForm ?? false);
            $showAccountSetupModal = (bool) session()->getFlashdata('pending_approval_email');
            $showSessionConflict = (bool) session()->getFlashdata('session_conflict');
            $pendingAvatarUrl = session()->getFlashdata('pending_avatar_url');
        ?>
        
        <div id="login-options" <?= $showCredentialForm ? 'hidden' : '' ?>>
            <a class="auth-button" href="<?= esc(base_url('auth/google'), 'attr') ?>">
                <span class="google-g" aria-hidden="true">G</span>
                <span>Sign in with Google Workspace</span>
            </a>
            <div class="auth-divider" aria-label="or">OR</div>
            <button class="auth-button" type="button" id="show-credentials">Sign in with POS Workspace Credentials</button>
        </div>

        <section id="credential-panel" <?= $showCredentialForm ? '' : 'hidden' ?> aria-labelledby="credential-heading">
            <h2 id="credential-heading" class="h6 fw-bold mb-3">Sign in with POS Workspace Credentials</h2>
            <form class="credential-form" method="post" action="<?= esc(base_url('auth/loginProcess'), 'attr') ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <input class="form-control" id="username" name="login_identity" type="text" autocomplete="username" maxlength="50" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" required>
                </div>
                <button class="auth-button" type="submit">Sign In</button>
            </form>
            <button class="btn btn-link credential-back" id="back-to-options" type="button">Back to sign-in options</button>
        </section>

        <p class="contact-line">Need access or an account? <button type="button" data-bs-toggle="modal" data-bs-target="#contact-admin-modal">Contact Admin</button></p>
        
        <div class="security-note">
            <span aria-hidden="true">ⓘ</span>
            <div><strong>Authorized access only.</strong><br>Use an approved sign-in method. Your account must be active and assigned permissions by an admin.</div>
        </div>
        
        <p class="footer">© <?= date('Y') ?> POS Workspace V3. All rights reserved.</p>
    </div>
</main>

<!-- Contact Admin Modal -->
<div class="modal fade contact-modal" id="contact-admin-modal" tabindex="-1" aria-labelledby="contact-admin-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="contact-admin-title">Contact Admin</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>For account access or setup assistance, contact the administrator:</p>
                <a href="mailto:jcalcantara@bounty.com.ph">jcalcantara@bounty.com.ph</a>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close Panel</button>
            </div>
        </div>
    </div>
</div>

<!-- Session Conflict Modal -->
<div class="modal fade session-conflict" id="session-conflict-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="session-conflict-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-5" id="session-conflict-title">Account Already Signed In</h2>
            </div>
            <div class="modal-body">This account is already signed in on another device. Do you want to sign out the existing session and continue?</div>
            <div class="modal-footer border-0 pt-0">
                <form method="post" action="<?= site_url('login/session/cancel') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-outline-secondary">Cancel</button></form>
                <form method="post" action="<?= site_url('login/session/replace') ?>"><?= csrf_field() ?><button type="submit" class="confirm-replacement">Sign Out &amp; Continue</button></form>
            </div>
        </div>
    </div>
</div>

<!-- Account Setup Pending Modal -->
<div class="modal fade pending-modal" id="account-setup-modal" tabindex="-1" aria-labelledby="account-setup-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <span class="small fw-bold text-primary">POS Workspace V3</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="pending-avatar" aria-label="Account profile picture">
                    <?php if (!empty($pendingAvatarUrl)): ?>
                        <img src="<?= esc($pendingAvatarUrl, 'attr') ?>" alt="Profile picture">
                    <?php else: ?>
                        <span aria-hidden="true">&#128100;</span>
                    <?php endif; ?>
                </div>
                <h2 class="h5 fw-bold mb-2" id="account-setup-title">Account Setup Pending</h2>
                <p class="pending-copy">Your sign-in identity has been verified, but your POS Workspace account setup is not yet complete.</p>

                <div class="pending-steps" aria-label="Account setup status">
                    <div class="pending-step"><span class="pending-step-icon" aria-hidden="true">&#10003;</span><span class="pending-step-label">Account Identity</span><span class="pending-step-status">Verified</span></div>
                    <div class="pending-step"><span class="pending-step-icon" aria-hidden="true">&#9675;</span><span class="pending-step-label">Account Activation</span><span class="pending-step-status">Waiting for administrator</span></div>
                    <div class="pending-step"><span class="pending-step-icon" aria-hidden="true">&#9675;</span><span class="pending-step-label">System Role</span><span class="pending-step-status">Waiting for assignment</span></div>
                </div>

                <div class="pending-info mb-3"><strong>Next step</strong><br>Please contact your system administrator to complete your account activation and role assignment. The workspace remains unavailable until your account and role are active.</div>
                <a href="mailto:jcalcantara@bounty.com.ph">Contact Admin</a>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Close Panel</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const loginOptions = document.getElementById('login-options');
const credentialPanel = document.getElementById('credential-panel');
document.getElementById('show-credentials')?.addEventListener('click', function () {
    loginOptions.hidden = true;
    credentialPanel.hidden = false;
    document.getElementById('username').focus();
});
document.getElementById('back-to-options')?.addEventListener('click', function () {
    credentialPanel.hidden = true;
    loginOptions.hidden = false;
    document.getElementById('show-credentials').focus();
});

<?php if ($showAccountSetupModal): ?>
document.addEventListener('DOMContentLoaded', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('account-setup-modal')).show();
});
<?php endif; ?>

<?php if ($showSessionConflict): ?>
document.addEventListener('DOMContentLoaded', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('session-conflict-modal')).show();
});
<?php endif; ?>
</script>
</body>
</html>