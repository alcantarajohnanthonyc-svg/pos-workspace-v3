<?php
/**
 * POS Workspace V3 - Workspace layout (compact)
 *
 * Optional view data:
 *   $filteredModules : permission-filtered module list (from controller)
 *   $title           : <title> text
 *   $pageTitle       : header title
 *   $breadcrumbs     : [['label' => 'Home', 'url' => 'dashboard'], ['label' => 'Current']]
 *   $settingsUrl     : URL for the Settings item (default: 'settings')
 *   $notifications   : optional list of ['title','message','time','url'] for the bell
 */
helper('module');
$modules     = $filteredModules ?? [];
$current     = trim(uri_string(), '/');
$settingsUrl = $settingsUrl ?? 'settings';

$isActive = static function (?string $url) use ($current): bool {
    $u = trim((string) $url, '/');
    if ($u === '') {
        return $current === '';
    }
    return $current === $u || str_starts_with($current, $u . '/');
};

$firstName   = (string) session()->get('first_name');
$lastName    = (string) session()->get('last_name');
$fullName    = trim($firstName . ' ' . $lastName);
$displayName = $fullName !== '' ? $fullName : (string) session()->get('email_workspace');
$initials    = $fullName !== ''
    ? strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1))
    : strtoupper(mb_substr($displayName, 0, 1));
$roleLabel   = session()->get('user_role_id') ? 'Role ID: ' . session()->get('user_role_id') : 'Pending Role';
$avatar      = session()->get('avatar');
// Uploaded avatars are relative paths; Google avatars are full URLs
if ($avatar && ! preg_match('#^(https?:)?//#i', (string) $avatar)) { $avatar = base_url(ltrim((string) $avatar, '/')); }
// Optional: [['title' => '', 'message' => '', 'time' => '', 'url' => '']]
$notifFromDb   = ! is_array($notifications ?? null);
$notifications = $notifFromDb ? [] : $notifications;
$notifCount    = count($notifications);
if ($notifFromDb) {
    // the bell reads the logged-in user's own unread notifications (table `notifications`); never break the page if that fails
    try {
        $bellId        = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $notifications = \App\Libraries\Notifier::forBell($bellId, 10);
        $notifCount    = \App\Libraries\Notifier::unreadCount($bellId);
    } catch (\Throwable $e) {
        $notifications = [];
        $notifCount    = 0;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Dashboard') ?> | POS Workspace V3</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/theme.css'), 'attr') ?>">
    <style>
        :root { color-scheme: light; }
        html { font-size: 15px; }
        @media (min-width: 1536px) { html { font-size: 16px; } }

        /* Desktop: static sidebar, collapsible to icon rail */
        @media (min-width: 1024px) {
            #sidebar { width: 14rem; }
            #sidebar.is-collapsed { width: 4rem; }
            #sidebar.is-collapsed .sb-label,
            #sidebar.is-collapsed .sb-children,
            #sidebar.is-collapsed .sb-chevron { display: none; }
            #sidebar.is-collapsed .sb-item { justify-content: center; padding-left: .25rem; padding-right: .25rem; }
            #sidebar.is-collapsed .sb-brand { justify-content: center; padding-left: 0; padding-right: 0; }
        }
        #sidebar, #sidebar-backdrop { transition: transform .2s ease, width .2s ease, opacity .2s ease; }
        @media (prefers-reduced-motion: reduce) {
            #sidebar, #sidebar-backdrop, .sb-chevron { transition: none !important; }
        }

        /* Force portrait/square orientation fix for avatar images */
    .avatar-img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        transform: rotate(0deg) !important; /* Inaalis ang anumang browser auto-rotation */
    }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased text-slate-800 text-sm">

<div class="flex h-screen h-[100dvh] overflow-hidden">

    <!-- Mobile backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 bg-slate-900/50 opacity-0 pointer-events-none lg:hidden" aria-hidden="true"></div>

    <!-- SIDEBAR (drawer below lg, static from lg up) -->
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 w-56 max-w-[85vw] -translate-x-full lg:translate-x-0 lg:static
                  bg-white text-slate-600 border-r border-slate-200 flex flex-col flex-shrink-0"
           aria-label="Main navigation">

        <!-- Brand (compact) -->
        <div class="sb-brand h-12 flex items-center justify-between px-3 bg-white gap-2 border-b border-slate-200 flex-shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <img src="<?= esc(base_url('assets/images/P-logo.png'), 'attr') ?>" alt="POS Workspace Logo" class="w-6 h-6 object-contain flex-shrink-0">
                <span class="sb-label font-bold text-slate-800 text-[13px] tracking-wide truncate">POS Workspace <span class="text-emerald-600">V3</span></span>
            </div>
            <button type="button" id="sidebar-close"
                    class="lg:hidden w-8 h-8 flex items-center justify-center rounded-md text-slate-500 hover:text-slate-800 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                    aria-label="Close navigation">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Navigation (generated from permission-filtered modules) -->
        <nav class="flex-1 px-2 py-2 space-y-0.5 overflow-y-auto overscroll-contain">
            <?php foreach ($modules as $i => $module):
                $hasChildren = ! empty($module['children']);
                $childActive = false;
                if ($hasChildren) {
                    foreach ($module['children'] as $c) {
                        if ($isActive($c['url'] ?? null)) { $childActive = true; break; }
                    }
                }
                $active    = $hasChildren ? $childActive : $isActive($module['url'] ?? null);
                $itemClass = $active
                    ? 'bg-emerald-600 text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
            ?>
                <?php if (! $hasChildren): ?>
                    <a href="<?= esc(base_url($module['url'] ?? ''), 'attr') ?>"
                       title="<?= esc($module['name'], 'attr') ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>
                       class="sb-item flex items-center gap-2.5 px-2.5 py-2 rounded-md text-[13px] font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 <?= $itemClass ?>">
                        <span class="w-4 flex-shrink-0 text-center text-[13px]"><?= module_icon_html($module['icon'] ?? '', 'mx-auto h-4 w-4') ?></span>
                        <span class="sb-label truncate"><?= esc($module['name']) ?></span>
                        <?php if (! empty($module['badge'])): ?><span class="sb-label ml-auto rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-emerald-700"><?= esc($module['badge']) ?></span><?php endif; ?>
                    </a>
                <?php else: ?>
                    <div data-group>
                        <button type="button"
                                data-group-toggle
                                aria-expanded="<?= $childActive ? 'true' : 'false' ?>"
                                aria-controls="group-<?= (int) $i ?>"
                                title="<?= esc($module['name'], 'attr') ?>"
                                class="sb-item w-full flex items-center justify-between gap-2.5 px-2.5 py-2 rounded-md text-[13px] font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 <?= $childActive ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span class="flex items-center gap-2.5 min-w-0">
                                <span class="w-4 flex-shrink-0 text-center text-[13px]"><?= module_icon_html($module['icon'] ?? '', 'mx-auto h-4 w-4') ?></span>
                                <span class="sb-label truncate"><?= esc($module['name']) ?></span>
                            </span>
                            <i class="sb-chevron fa-solid fa-chevron-down text-[10px] transition-transform <?= $childActive ? 'rotate-180' : '' ?>"></i>
                        </button>
                        <div id="group-<?= (int) $i ?>" class="sb-children mt-0.5 ml-4 pl-3 border-l border-slate-200 space-y-0.5 <?= $childActive ? '' : 'hidden' ?>">
                            <?php foreach ($module['children'] as $child):
                                $cActive = $isActive($child['url'] ?? null); ?>
                                <a href="<?= esc(base_url($child['url'] ?? ''), 'attr') ?>"
                                   <?= $cActive ? 'aria-current="page"' : '' ?>
                                   class="block px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 <?= $cActive ? 'bg-emerald-600 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' ?>">
                                    <?= esc($child['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="flex flex-col flex-1 min-w-0 h-full overflow-hidden">

        <!-- TOPBAR (compact) -->
        <header class="flex items-center gap-2 h-12 px-3 sm:px-4 bg-white border-b border-slate-200 shadow-sm flex-shrink-0">
            <button type="button" id="sidebar-open"
                    class="lg:hidden w-9 h-9 -ml-1 flex items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                    aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>
            <button type="button" id="sidebar-collapse"
                    class="hidden lg:flex w-9 h-9 -ml-1 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                    aria-label="Collapse navigation" aria-controls="sidebar" aria-expanded="true">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="min-w-0 flex-1">
                <?php if (! empty($breadcrumbs) && is_array($breadcrumbs)): ?>
                    <nav aria-label="Breadcrumb" class="hidden sm:block">
                        <ol class="flex items-center gap-1.5 text-[11px] text-slate-500 leading-none">
                            <?php foreach ($breadcrumbs as $n => $crumb):
                                $last = $n === array_key_last($breadcrumbs); ?>
                                <li class="flex items-center gap-1.5 min-w-0">
                                    <?php if (! $last && ! empty($crumb['url'])): ?>
                                        <a href="<?= esc(base_url($crumb['url']), 'attr') ?>" class="hover:text-emerald-600 focus:outline-none focus-visible:underline"><?= esc($crumb['label']) ?></a>
                                        <i class="fa-solid fa-chevron-right text-[9px] text-slate-400"></i>
                                    <?php else: ?>
                                        <span class="truncate text-slate-700 font-medium" <?= $last ? 'aria-current="page"' : '' ?>><?= esc($crumb['label']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                <?php endif; ?>
                <h1 class="text-sm font-semibold text-slate-800 truncate"><?= esc($pageTitle ?? 'Dashboard') ?></h1>
            </div>

            <!-- Right side: notifications + profile -->
            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">

                <!-- Notifications -->
                <div class="relative">
                    <button type="button" id="notif-btn"
                            aria-haspopup="true" aria-expanded="false" aria-controls="notif-panel"
                            aria-label="Notifications<?= $notifCount ? ' (' . $notifCount . ' new)' : '' ?>"
                            class="relative w-9 h-9 flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                        <i class="fa-solid fa-bell"></i>
                        <span id="notif-badge" class="absolute top-0.5 right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold leading-4 text-center <?= $notifCount ? '' : 'hidden' ?>"><?= $notifCount > 9 ? '9+' : (int) $notifCount ?></span>
                    </button>
                    <div id="notif-panel"
                         class="hidden absolute right-0 top-full mt-1 w-[22rem] max-w-[calc(100vw-1.5rem)] z-50 bg-white rounded-lg border border-slate-200 shadow-xl overflow-hidden">
                        <div class="px-3 py-2 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                            <span class="text-[13px] font-semibold">Notifications</span>
                            <button type="button" id="notif-readall" class="text-[11px] font-medium text-emerald-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 rounded <?= $notifCount ? '' : 'hidden' ?>">Mark all as read</button>
                        </div>
                        <div class="flex border-b border-slate-200 bg-white px-2" role="tablist" aria-label="Notifications">
                            <button type="button" role="tab" id="notif-tab-unread" data-notif-tab="unread" aria-selected="true" class="flex items-center gap-1.5 border-b-2 border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                                Unread <span id="notif-tab-count" class="rounded-full bg-emerald-100 px-1.5 text-[10px] font-bold text-emerald-700 <?= $notifCount ? '' : 'hidden' ?>"><?= (int) $notifCount ?></span>
                            </button>
                            <button type="button" role="tab" id="notif-tab-read" data-notif-tab="read" aria-selected="false" class="border-b-2 border-transparent px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                                Read
                            </button>
                        </div>
                        <ul id="notif-list" class="max-h-[24rem] overflow-y-auto divide-y divide-slate-100">
                            <?php
                            $bellStyle = static fn (string $e): array => match ($e) {
                                'approval_request'  => ['fa-clock', 'bg-amber-100 text-amber-600'],
                                'approval_approved' => ['fa-circle-check', 'bg-emerald-100 text-emerald-600'],
                                'approval_rejected' => ['fa-circle-xmark', 'bg-rose-100 text-rose-600'],
                                'record_changed'    => ['fa-pen', 'bg-sky-100 text-sky-600'],
                                default             => ['fa-bell', 'bg-slate-100 text-slate-500'],
                            };
                            foreach ($notifications as $n):
                                [$bIcon, $bTone] = $bellStyle((string) ($n['event'] ?? ''));
                                ?>
                                <li>
                                    <a href="<?= esc(base_url($n['url'] ?? '#'), 'attr') ?>" class="flex gap-3 px-4 py-3 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full <?= $bTone ?>"><i class="fa-solid <?= $bIcon ?> text-xs"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13px] font-semibold leading-snug text-slate-800"><?= esc($n['title'] ?? '') ?></span>
                                            <?php if (! empty($n['message'])): ?><span class="mt-0.5 block whitespace-pre-line break-words text-xs leading-relaxed text-slate-600"><?= esc($n['message']) ?></span><?php endif; ?>
                                            <?php if (! empty($n['time'])): ?><span class="mt-1 block text-[11px] text-slate-400"><?= esc($n['time']) ?></span><?php endif; ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div id="notif-empty" class="px-4 py-6 text-center text-slate-500 <?= $notifications ? 'hidden' : '' ?>">
                            <i class="fa-regular fa-bell-slash text-lg mb-1"></i>
                            <p id="notif-empty-text" class="text-xs">No unread notifications</p>
                        </div>
                    </div>
                </div>

                <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                <!-- Profile -->
                <div class="relative">
                    <button type="button" id="profile-btn"
                            aria-haspopup="menu" aria-expanded="false" aria-controls="profile-menu"
                            class="flex items-center gap-2 pl-1 pr-1 sm:pr-2 py-1 rounded-full hover:bg-slate-100 transition-colors text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                        <span class="w-8 h-8 rounded-full overflow-hidden bg-emerald-600 text-white flex items-center justify-center text-xs font-semibold flex-shrink-0">
                            <?php if ($avatar): ?>
<img src="<?= esc($avatar, 'attr') ?>" alt="User Avatar" class="avatar-img">                            <?php else: ?>
                                <?= esc($initials) ?>
                            <?php endif; ?>
                        </span>
                        <span class="hidden md:block min-w-0 max-w-[10rem]">
                            <span class="block text-[13px] font-semibold text-slate-800 truncate leading-tight"><?= esc($displayName) ?></span>
                            <span class="block text-[11px] text-slate-500 truncate leading-tight"><?= esc($roleLabel) ?></span>
                        </span>
                        <i class="hidden sm:block fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                    </button>

                    <div id="profile-menu" role="menu" aria-labelledby="profile-btn"
                         class="hidden absolute right-0 top-full mt-1 w-52 max-w-[calc(100vw-1.5rem)] z-50
                                bg-white text-slate-800 rounded-lg border border-slate-200 shadow-xl overflow-hidden">
                        <div class="px-3 py-2 border-b border-slate-200 bg-slate-50">
                            <div class="text-[13px] font-semibold truncate"><?= esc($displayName) ?></div>
                            <div class="text-[11px] text-slate-500 truncate"><?= esc($roleLabel) ?></div>
                        </div>
                        <div class="py-1">
                            <a href="<?= esc(base_url($settingsUrl), 'attr') ?>" role="menuitem"
                               class="flex items-center gap-2 px-3 py-2 text-[13px] hover:bg-slate-100 focus:bg-slate-100 focus:outline-none transition-colors">
                                <i class="fa-solid fa-gear w-4 text-center text-slate-500"></i>
                                <span>Settings</span>
                            </a>
                            <a href="<?= esc(base_url('auth/logout'), 'attr') ?>" role="menuitem"
                               class="flex items-center gap-2 px-3 py-2 text-[13px] text-red-600 hover:bg-red-50 focus:bg-red-50 focus:outline-none transition-colors">
                                <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT RENDER -->
        <main id="main-content" class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-3 sm:p-4 lg:p-5">
            <div class="mx-auto w-full max-w-[1600px] min-w-0">
                <?= $this->renderSection('content') ?>
            </div>
        </main>

    </div>
</div>

<script>
(function () {
    var sidebar   = document.getElementById('sidebar');
    var backdrop  = document.getElementById('sidebar-backdrop');
    var openBtn   = document.getElementById('sidebar-open');
    var closeBtn  = document.getElementById('sidebar-close');
    var collapseB = document.getElementById('sidebar-collapse');
    var profileBtn  = document.getElementById('profile-btn');
    var profileMenu = document.getElementById('profile-menu');
    var notifBtn    = document.getElementById('notif-btn');
    var notifPanel  = document.getElementById('notif-panel');

    /* Mobile / tablet drawer */
    function openDrawer() {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        openBtn.setAttribute('aria-expanded', 'true');
    }
    function closeDrawer() {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('opacity-0', 'pointer-events-none');
        openBtn.setAttribute('aria-expanded', 'false');
    }
    openBtn.addEventListener('click', openDrawer);
    closeBtn.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) { closeDrawer(); }
    });

    /* Desktop collapse (remembered) */
    function setCollapsed(state) {
        sidebar.classList.toggle('is-collapsed', state);
        collapseB.setAttribute('aria-expanded', state ? 'false' : 'true');
        try { localStorage.setItem('pos_sidebar_collapsed', state ? '1' : '0'); } catch (e) {}
        closeProfile();
    }
    try {
        if (localStorage.getItem('pos_sidebar_collapsed') === '1') { setCollapsed(true); }
    } catch (e) {}
    collapseB.addEventListener('click', function () {
        setCollapsed(!sidebar.classList.contains('is-collapsed'));
    });

    /* Expand/collapse nav groups */
    document.querySelectorAll('[data-group-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = document.getElementById(btn.getAttribute('aria-controls'));
            var open  = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            panel.classList.toggle('hidden', open);
            var chev = btn.querySelector('.sb-chevron');
            if (chev) { chev.classList.toggle('rotate-180', !open); }
        });
    });

    /* Avatar floating menu */
    function closeNotif() {
        notifPanel.classList.add('hidden');
        notifBtn.setAttribute('aria-expanded', 'false');
    }
    notifBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = notifPanel.classList.contains('hidden');
        closeProfile();
        notifPanel.classList.toggle('hidden', !willOpen);
        notifBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    function openProfile() {
        closeNotif();
        profileMenu.classList.remove('hidden');
        profileBtn.setAttribute('aria-expanded', 'true');
        var first = profileMenu.querySelector('[role="menuitem"]');
        if (first) { first.focus(); }
    }
    function closeProfile() {
        profileMenu.classList.add('hidden');
        profileBtn.setAttribute('aria-expanded', 'false');
    }
    profileBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        profileMenu.classList.contains('hidden') ? openProfile() : closeProfile();
    });
    document.addEventListener('click', function (e) {
        if (!profileMenu.contains(e.target) && !profileBtn.contains(e.target)) { closeProfile(); }
        if (!notifPanel.contains(e.target) && !notifBtn.contains(e.target)) { closeNotif(); }
    });
    profileMenu.addEventListener('keydown', function (e) {
        var items = Array.prototype.slice.call(profileMenu.querySelectorAll('[role="menuitem"]'));
        var idx = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); items[(idx + 1) % items.length].focus(); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); items[(idx - 1 + items.length) % items.length].focus(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!profileMenu.classList.contains('hidden')) { closeProfile(); profileBtn.focus(); }
            if (!notifPanel.classList.contains('hidden')) { closeNotif(); notifBtn.focus(); }
            closeDrawer();
        }
    });
})();
</script>

<?= $this->include('layouts/partials/loading') ?>
<?php $loadingJs = FCPATH . 'assets/js/loading.js'; ?>
<script src="<?= esc(base_url('assets/js/loading.js') . '?v=' . (is_file($loadingJs) ? filemtime($loadingJs) : '1'), 'attr') ?>" defer></script>
<script>
/* Bell: badge follows the heartbeat (every 30 s); the list is refreshed when the panel opens. GET = silent (no loading overlay). */
(function () {
    var btn = document.getElementById('notif-btn');
    var panel = document.getElementById('notif-panel');
    var badge = document.getElementById('notif-badge');
    var list = document.getElementById('notif-list');
    var empty = document.getElementById('notif-empty');
    var readAll = document.getElementById('notif-readall');
    if (!btn || !panel || !badge || !list) { return; }
    var latestUrl = '<?= site_url("notifications/latest") ?>';
    var readAllUrl = '<?= site_url("notifications/read-all") ?>';
    var csrfName = <?= json_encode(csrf_header(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var csrfToken = <?= json_encode(csrf_hash(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var known = <?= (int) $notifCount ?>;   /* unread count the bell is showing now (the badge counts ONLY unread) */
    var tab = 'unread';                      /* 'unread' | 'read' */
    var tabCount = document.getElementById('notif-tab-count');
    var emptyText = document.getElementById('notif-empty-text');

    function setCount(n) {
        known = n;
        badge.textContent = n > 9 ? '9+' : String(n);
        badge.classList.toggle('hidden', n < 1);
        if (tabCount) { tabCount.textContent = n; tabCount.classList.toggle('hidden', n < 1); }
        if (readAll) { readAll.classList.toggle('hidden', n < 1 || tab !== 'unread'); }
    }
    /* called by the heartbeat: when the unread count changed, fetch the list FIRST (unread tab), then badge and list appear together */
    function sync(n) {
        if (n === known) { return; }
        if (tab === 'unread') { refresh(); } else { setCount(n); }
    }

    function setTab(t) {
        tab = t;
        document.querySelectorAll('[data-notif-tab]').forEach(function (b) {
            var on = b.getAttribute('data-notif-tab') === t;
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.className = on
                ? 'flex items-center gap-1.5 border-b-2 border-emerald-600 px-3 py-2 text-xs font-semibold text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600'
                : 'flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
        });
        if (emptyText) { emptyText.textContent = t === 'read' ? 'No read notifications yet' : 'No unread notifications'; }
        if (readAll) { readAll.classList.toggle('hidden', known < 1 || t !== 'unread'); }
        refresh();
    }
    window.AppNotif = { setCount: setCount, refresh: refresh, sync: sync };

    function el(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text) { n.textContent = text; } return n; }

    var STYLE = {
        approval_request:  ['fa-clock', 'bg-amber-100 text-amber-600'],
        approval_approved: ['fa-circle-check', 'bg-emerald-100 text-emerald-600'],
        approval_rejected: ['fa-circle-xmark', 'bg-rose-100 text-rose-600'],
        record_changed:    ['fa-pen', 'bg-sky-100 text-sky-600']
    };

    function render(items) {
        list.textContent = '';
        items.forEach(function (n) {
            var st = STYLE[n.event] || ['fa-bell', 'bg-slate-100 text-slate-500'];
            var li = el('li', tab === 'read' ? 'opacity-70' : '');
            var a = el('a', 'flex gap-3 px-4 py-3 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none');
            a.href = n.url;
            var ic = el('span', 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ' + st[1]);
            ic.appendChild(el('i', 'fa-solid ' + st[0] + ' text-xs'));
            var box = el('span', 'min-w-0 flex-1');
            box.appendChild(el('span', 'block text-[13px] font-semibold leading-snug text-slate-800', n.title));
            if (n.message) { box.appendChild(el('span', 'mt-0.5 block whitespace-pre-line break-words text-xs leading-relaxed text-slate-600', n.message)); }
            if (n.time) { box.appendChild(el('span', 'mt-1 block text-[11px] text-slate-400', n.time)); }
            a.appendChild(ic); a.appendChild(box); li.appendChild(a); list.appendChild(li);
        });
        if (empty) { empty.classList.toggle('hidden', items.length > 0); }
    }

    function refresh() {
        fetch(latestUrl + '?tab=' + tab, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.ok && j.tab === tab) { render(j.items || []); setCount(j.unread || 0); } })
            .catch(function () {});
    }

    btn.addEventListener('click', function () { if (!panel.classList.contains('hidden')) { refresh(); } });
    document.querySelectorAll('[data-notif-tab]').forEach(function (b) {
        b.addEventListener('click', function (e) { e.stopPropagation(); setTab(b.getAttribute('data-notif-tab')); });
    });

    if (readAll) {
        readAll.addEventListener('click', function () {
            var h = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
            h[csrfName] = csrfToken;
            fetch(readAllUrl, { method: 'POST', headers: h, credentials: 'same-origin', loading: false })
                .then(function (r) { return r.json(); })
                .then(function (j) { if (j && j.csrf) { csrfToken = j.csrf; } setCount(0); refresh(); })
                .catch(function () {});
        });
    }
})();
</script>

<script>
/* Single-login heartbeat + idle timeout.
   Bawat 15 s tinatanong ang server. Kung may galaw ang user (mouse / keyboard / scroll / touch)
   mula sa huling tanong, may kasamang ?active=1 para i-reset ng server ang idle timer.
   401 = naka-login sa ibang device, nag-logout, na-deactivate, o idle na. Ang network error ay hindi nagla-logout. */
(function () {
    var url = '<?= site_url("auth/check_token") ?>';
    var loginUrl = '<?= base_url("auth/login") ?>';
    var busy = false, active = false;

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'].forEach(function (ev) {
        document.addEventListener(ev, function () { active = true; }, { passive: true, capture: true });
    });

    function check() {
        if (busy) { return; }
        busy = true;
        var wasActive = active;
        active = false;
        fetch(url + (wasActive ? '?active=1' : ''), { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (r.status === 401) {
                    window.location.href = loginUrl;
                    return null;
                }
                return r.ok ? r.json() : null;
            })
            .then(function (j) {
                if (j && typeof j.unread === 'number' && window.AppNotif) { window.AppNotif.sync(j.unread); }
            })
            .catch(function () { if (wasActive) { active = true; } })
            .finally(function () { busy = false; });
    }

    setInterval(check, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { active = true; check(); } });
})();
</script>

</body>
</html>