<?php
/**
 * Shared module form fields (Add Module + Edit Module).
 * Expects: $prefix (unique id prefix), $mode ('create'|'edit'), $m (module row or []), $parentModules.
 * Behaviour (slug auto-fill, icon picker, badge chips, type toggle) is in public/assets/js/module-form.js
 */
helper('module');

$m      = $m ?? [];
$p      = $prefix ?? 'mod';
$mode   = $mode ?? 'create';
$isEdit = $mode === 'edit';

$curIcon     = (string) ($m['icon'] ?? '');
$iconIsImage = module_icon_is_image($curIcon);
$iconText    = $iconIsImage ? '' : $curIcon;
$isCategory  = (int) ($m['is_category'] ?? 0) === 1;

$fInput = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';
$fRO    = 'w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[13px] text-slate-600';
$fLabel = 'mb-1 block text-xs font-medium text-slate-700';
$fHint  = 'mt-1 text-[11px] text-slate-400';

$iconChoices = [
    'fa-chart-line' => 'Chart line', 'fa-chart-pie' => 'Chart pie', 'fa-chart-column' => 'Chart column', 'fa-gauge' => 'Gauge', 'fa-house' => 'Home',
    'fa-users' => 'Users', 'fa-users-gear' => 'Users gear', 'fa-user-shield' => 'User shield', 'fa-user-gear' => 'User gear', 'fa-id-card' => 'ID card',
    'fa-cubes-stacked' => 'Cubes', 'fa-cube' => 'Cube', 'fa-boxes-stacked' => 'Boxes', 'fa-box' => 'Box', 'fa-warehouse' => 'Warehouse',
    'fa-cash-register' => 'Cash register', 'fa-receipt' => 'Receipt', 'fa-file-invoice' => 'Invoice', 'fa-file-invoice-dollar' => 'Invoice dollar', 'fa-credit-card' => 'Credit card',
    'fa-wallet' => 'Wallet', 'fa-money-bill' => 'Money', 'fa-percent' => 'Percent', 'fa-tags' => 'Tags', 'fa-barcode' => 'Barcode',
    'fa-qrcode' => 'QR code', 'fa-store' => 'Store', 'fa-shop' => 'Shop', 'fa-cart-shopping' => 'Cart', 'fa-basket-shopping' => 'Basket',
    'fa-truck' => 'Truck', 'fa-handshake' => 'Handshake', 'fa-building' => 'Building', 'fa-headset' => 'Support', 'fa-life-ring' => 'Life ring',
    'fa-gear' => 'Gear', 'fa-sliders' => 'Sliders', 'fa-wrench' => 'Wrench', 'fa-screwdriver-wrench' => 'Tools', 'fa-database' => 'Database',
    'fa-server' => 'Server', 'fa-shield-halved' => 'Shield', 'fa-lock' => 'Lock', 'fa-key' => 'Key', 'fa-bell' => 'Bell',
    'fa-envelope' => 'Envelope', 'fa-calendar' => 'Calendar', 'fa-clipboard-list' => 'Clipboard', 'fa-list-check' => 'Checklist', 'fa-folder' => 'Folder',
    'fa-book' => 'Book', 'fa-print' => 'Print', 'fa-clock-rotate-left' => 'History', 'fa-rotate' => 'Sync', 'fa-circle-question' => 'Help',
];
$badgeChoices = ['New', 'Beta', 'Updated', 'Hot', 'Soon', 'Pro', 'Dev'];
?>
<div data-module-form class="space-y-4">

    <!-- Name / slug -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="<?= $p ?>_name" class="<?= $fLabel ?>">Module Name <span class="text-red-500">*</span></label>
            <input id="<?= $p ?>_name" name="name" type="text" required maxlength="100" data-name
                   value="<?= esc($m['name'] ?? '', 'attr') ?>" class="<?= $fInput ?>">
        </div>
        <div>
            <label for="<?= $p ?>_slug" class="<?= $fLabel ?>">Module Slug</label>
            <?php if ($isEdit): ?>
                <input id="<?= $p ?>_slug" type="text" value="<?= esc($m['slug'] ?? '', 'attr') ?>" class="<?= $fRO ?> font-mono" readonly>
                <p class="<?= $fHint ?>">The slug is fixed: RBAC permissions use it.</p>
            <?php else: ?>
                <input id="<?= $p ?>_slug" name="slug" type="text" maxlength="100" data-slug pattern="[a-z0-9_]+"
                       placeholder="auto from the name" class="<?= $fInput ?> font-mono">
                <p class="<?= $fHint ?>">Lowercase letters, numbers and underscores. Cannot be changed later.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Type / parent / order -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
            <label for="<?= $p ?>_type" class="<?= $fLabel ?>">Module Type</label>
            <select id="<?= $p ?>_type" name="is_category" data-type class="<?= $fInput ?>">
                <option value="0" <?= $isCategory ? '' : 'selected' ?>>Functional module (own page)</option>
                <option value="1" <?= $isCategory ? 'selected' : '' ?>>Category container (group header)</option>
            </select>
        </div>
        <div data-when-module>
            <label for="<?= $p ?>_parent" class="<?= $fLabel ?>">Parent Category</label>
            <select id="<?= $p ?>_parent" name="parent_id" class="<?= $fInput ?>">
                <option value="">None (top level)</option>
                <?php foreach (($parentModules ?? []) as $pm): ?>
                    <option value="<?= (int) $pm['id'] ?>" <?= (int) ($m['parent_id'] ?? 0) === (int) $pm['id'] ? 'selected' : '' ?>><?= esc($pm['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="<?= $p ?>_order" class="<?= $fLabel ?>">Display Order</label>
            <input id="<?= $p ?>_order" name="short_order" type="number" min="0" max="99999" value="<?= (int) ($m['short_order'] ?? 0) ?>" class="<?= $fInput ?>">
            <p class="<?= $fHint ?>">Smaller numbers appear first in the sidebar.</p>
        </div>
    </div>

    <!-- URL -->
    <div data-when-module>
        <label for="<?= $p ?>_url" class="<?= $fLabel ?>">Page URL <span class="text-red-500">*</span></label>
        <div class="flex">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-xs text-slate-500">/</span>
            <input id="<?= $p ?>_url" name="url" type="text" maxlength="255" pattern="[a-zA-Z0-9_\-\/]*" data-url
                   value="<?= esc($m['url'] ?? '', 'attr') ?>" placeholder="e.g. modules" class="<?= $fInput ?> rounded-l-none font-mono">
        </div>
        <p class="<?= $fHint ?>">The route path of the page. It must exist in Routes.php.</p>
    </div>

    <!-- Icon -->
    <fieldset class="space-y-3 rounded-lg border border-slate-200 p-3">
        <legend class="px-1 text-xs font-semibold text-slate-700">Icon</legend>
        <div class="flex items-start gap-3">
            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-emerald-50 text-lg text-emerald-600" data-icon-preview>
                <i data-icon-i class="<?= $iconIsImage ? 'hidden ' : '' ?><?= esc(module_icon_class($iconText), 'attr') ?>" aria-hidden="true"></i>
                <img data-icon-img src="<?= $iconIsImage ? esc(base_url($curIcon), 'attr') : '' ?>" alt="" class="<?= $iconIsImage ? '' : 'hidden ' ?>h-full w-full object-contain p-1">
            </div>
            <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="<?= $p ?>_icon" class="<?= $fLabel ?>">Icon class (FontAwesome)</label>
                    <input id="<?= $p ?>_icon" name="icon" type="text" maxlength="50" data-icon-input
                           value="<?= esc($iconText, 'attr') ?>" placeholder="fa-cube" class="<?= $fInput ?> font-mono">
                </div>
                <div>
                    <span class="<?= $fLabel ?>">Or upload an image</span>
                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-emerald-600">
                        <i class="fa-solid fa-image"></i> <span data-file-label>Choose image</span>
                        <input type="file" name="icon_file" accept="image/png,image/jpeg,image/webp" data-icon-file class="sr-only">
                    </label>
                    <p class="mt-1 hidden text-[11px] text-red-600" data-icon-error></p>
                </div>
            </div>
        </div>
        <?php if ($iconIsImage): ?>
            <p class="text-[11px] text-slate-500">An uploaded image is currently used. Pick an icon or upload a new image to replace it.</p>
        <?php endif; ?>
        <div>
            <input type="search" data-icon-search placeholder="Search icons…" aria-label="Search icons"
                   class="mb-2 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
            <div class="grid max-h-32 grid-cols-[repeat(auto-fill,minmax(2.25rem,1fr))] gap-1 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-1.5" data-icon-grid>
                <?php foreach ($iconChoices as $cls => $lbl): ?>
                    <button type="button" data-icon="<?= esc($cls, 'attr') ?>" data-label="<?= esc(strtolower($lbl), 'attr') ?>" title="<?= esc($lbl, 'attr') ?>" aria-label="<?= esc($lbl, 'attr') ?>"
                            class="flex h-9 items-center justify-center rounded-md border border-transparent text-slate-600 transition-colors hover:border-emerald-200 hover:bg-white hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                        <i class="fa-solid <?= esc($cls, 'attr') ?>"></i>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </fieldset>

    <!-- Badge -->
    <div>
        <label for="<?= $p ?>_badge" class="<?= $fLabel ?>">Badge</label>
        <input id="<?= $p ?>_badge" name="badge" type="text" maxlength="50" data-badge-input
               value="<?= esc($m['badge'] ?? '', 'attr') ?>" placeholder="Choose below or type your own" class="<?= $fInput ?>">
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <?php foreach ($badgeChoices as $b): ?>
                <button type="button" data-badge="<?= esc($b, 'attr') ?>"
                        class="rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"><?= esc($b) ?></button>
            <?php endforeach; ?>
            <button type="button" data-badge="" class="text-[11px] font-medium text-slate-500 underline hover:text-slate-700">Clear</button>
        </div>
        <p class="<?= $fHint ?>">Optional short label shown beside the module name in the sidebar.</p>
    </div>
</div>