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
    --blue: var(--pw-primary);
    --muted: var(--pw-muted);
    --line: var(--pw-border-field);
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
    min-height: 40px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 9px;
    border: 1px solid rgba(220, 231, 245, 0.8);
    background: rgba(255, 255, 255, 0.9);
    color: var(--pw-text-deep);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 2px 5px rgba(20, 50, 90, .04);
    transition: all .18s ease;
}

.auth-button:hover {
    background: var(--pw-surface);
    border-color: var(--pw-border-blue);
    color: var(--pw-primary-deep);
    transform: translateY(-1px);
}

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

.login-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 14px 0 12px;
    color: var(--pw-muted-strong);
    font-size: 11px;
    font-weight: 700;
}

.login-divider::before,
.login-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--line);
}

.credential-form { text-align: left; }

#credential-panel[hidden] { display: none; }

.credential-trigger {
    cursor: pointer;
    font-family: inherit;
}

.credential-form label {
    display: block;
    margin: 8px 0 4px;
    color: var(--pw-text-secondary);
    font-size: 12px;
    font-weight: 700;
}

.credential-form input {
    width: 100%;
    min-height: 40px;
    padding: 8px 10px;
    border: 1px solid var(--pw-border-soft);
    border-radius: 8px;
    background: rgba(255, 255, 255, .94);
    color: var(--pw-text-deep);
    font-size: 14px;
}

.credential-form input:focus {
    outline: 3px solid rgba(8, 102, 233, .16);
    border-color: var(--pw-primary-hover);
}

.password-field { position: relative; }

.password-field input { padding-right: 62px; }

.password-toggle {
    position: absolute;
    top: 50%;
    right: 9px;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: var(--pw-primary-hover);
    font-size: 12px;
    font-weight: 700;
}

.credential-submit {
    width: 100%;
    min-height: 40px;
    margin-top: 12px;
    border: 0;
    border-radius: 9px;
    background: var(--blue);
    color: var(--pw-surface);
    font-size: 13px;
    font-weight: 700;
}

.credential-submit:hover { background: var(--pw-primary-strong); }

.contact-modal .modal-content {
    border: 1px solid rgba(255, 255, 255, .8);
    border-radius: 18px;
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 18px 48px rgba(16, 45, 99, .22);
}

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
.pending-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 10px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--pw-primary-pale);
    color: var(--pw-primary);
    font-size: 22px;
}

.pending-avatar {
    width: 64px;
    height: 64px;
    margin: 0 auto 10px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 3px solid var(--pw-primary-tint);
    border-radius: 50%;
    background: var(--pw-primary-pale);
    color: var(--pw-primary);
    font-size: 26px;
}

.pending-avatar img { width: 100%; height: 100%; object-fit: cover; }

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
            $showAccountSetupModal = (bool) session()->getFlashdata('account_setup_pending');
            $pendingAvatar = session()->getFlashdata('pending_avatar_url');
            $pendingAvatarUrl = \App\Models\UserAccountModel::normalizeGooglePictureUrl(
                is_string($pendingAvatar) ? $pendingAvatar : null
            );
        ?>
        
        <a class="auth-button" href="<?= site_url('login/google') ?>">
            <span class="google-g" aria-hidden="true">G</span>
            <span>Sign in with Google Workspace</span>
        </a>

        <div class="login-divider" aria-label="or">OR</div>

        <?php $credentialPanelOpen = (bool) session()->getFlashdata('credential_login_open'); ?>
        <button class="auth-button credential-trigger" type="button" id="credential-trigger"
                aria-controls="credential-panel" aria-expanded="<?= $credentialPanelOpen ? 'true' : 'false' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="8" cy="15" r="4"></circle>
                <path d="m10.8 12.2 8-8a2.1 2.1 0 0 1 3 3l-1.8 1.8-2-2-2 2 2 2-2.2 2.2"></path>
            </svg>
            <span>Sign in with Masterfile Credentials</span>
        </button>

        <div id="credential-panel" <?= $credentialPanelOpen ? '' : 'hidden' ?>>
            <form class="credential-form" method="post" action="<?= site_url('login/credentials') ?>">
                <?= csrf_field() ?>
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" maxlength="50" required>

                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" id="password-toggle" aria-controls="password" aria-pressed="false">Show</button>
                </div>

                <button class="credential-submit" type="submit">Sign In</button>
            </form>
        </div>
        
        <p class="contact-line">Need access or an account? <button type="button" data-bs-toggle="modal" data-bs-target="#contact-admin-modal">Contact Admin</button></p>
        
        <div class="security-note">
            <span aria-hidden="true">ⓘ</span>
            <div><strong>Authorized access only.</strong><br>Use your company Google Workspace account. Account must be assigned permissions by an admin.</div>
        </div>
        
        <p class="footer">© <?= date('Y') ?> POS Workspace V3. All rights reserved.</p>
    </div>
</main>

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

<div class="modal fade pending-modal" id="account-setup-modal" tabindex="-1" aria-labelledby="account-setup-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="small fw-bold text-primary">POS Workspace V3</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="pending-avatar" aria-label="Account profile picture">
                    <?php if ($pendingAvatarUrl !== null): ?>
                        <img id="pending-avatar-image" src="<?= esc($pendingAvatarUrl, 'attr') ?>" alt="Account profile picture">
                        <span id="pending-avatar-fallback" aria-hidden="true" hidden>&#128100;</span>
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
document.getElementById('credential-trigger').addEventListener('click', function () {
    const panel = document.getElementById('credential-panel');
    const expanded = this.getAttribute('aria-expanded') === 'true';
    panel.hidden = expanded;
    this.setAttribute('aria-expanded', expanded ? 'false' : 'true');

    if (!expanded) {
        document.getElementById('username').focus();
    }
});

document.getElementById('password-toggle').addEventListener('click', function () {
    const password = document.getElementById('password');
    const visible = password.type === 'password';
    password.type = visible ? 'text' : 'password';
    this.textContent = visible ? 'Hide' : 'Show';
    this.setAttribute('aria-pressed', visible ? 'true' : 'false');
    this.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
});
<?php if ($showAccountSetupModal): ?>
document.addEventListener('DOMContentLoaded', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('account-setup-modal')).show();
});

const pendingAvatar = document.getElementById('pending-avatar-image');
const pendingAvatarFallback = document.getElementById('pending-avatar-fallback');
const showPendingAvatarFallback = function () {
    if (pendingAvatar) pendingAvatar.hidden = true;
    if (pendingAvatarFallback) pendingAvatarFallback.hidden = false;
};

if (pendingAvatar) {
    pendingAvatar.addEventListener('error', showPendingAvatarFallback, { once: true });
    if (pendingAvatar.complete && pendingAvatar.naturalWidth === 0) {
        showPendingAvatarFallback();
    }
}
<?php endif; ?>
</script>
</body>
</html>
