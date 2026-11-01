<?php
/**
 * Module Settings > Notifications tab.
 * Include inside the tab panel of module_settings/configure:
 *
 *     <?= view('module_settings/_tab_notifications', ['moduleId' => $mid, 'canEdit' => ($canEdit || $canApproval)], ['saveData' => false]) ?>
 *
 * Needs: $moduleId.  Optional: $canEdit (default: Super Admin, module administrator, or "update" on Module Settings).
 */
use App\Libraries\ApprovalRequests;
use App\Libraries\Notifier;
use App\Libraries\Rbac;

helper('form');

$moduleId = (int) ($moduleId ?? 0);   // passed with view(..., $data) - NOT with $this->include(), which ignores data
$module   = \Config\Database::connect()->table('rbac_modules')->where('id', $moduleId)->get()->getRowArray() ?: [];
$slug     = (string) ($module['slug'] ?? '');

if (! isset($canEdit)) {
    $uid     = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
    $isSuper = (int) (session()->get('user_role_id') ?? session()->get('role_id')) === 1;
    $canEdit = $isSuper || ApprovalRequests::isModuleAdmin($slug, $uid) || Rbac::can('module_setting', 'update');
}

$settings = Notifier::settingsForModule($moduleId);
$pref     = Notifier::defaultPref($moduleId);
$dis      = $canEdit ? '' : 'disabled';

$switch = static function (string $name, bool $on, string $label, string $dis): string {
    return '<label class="relative inline-flex cursor-pointer items-center">'
        . '<input type="checkbox" name="' . esc($name, 'attr') . '" value="1" class="peer sr-only" ' . ($on ? 'checked' : '') . ' ' . $dis . ' aria-label="' . esc($label, 'attr') . '">'
        . '<span class="h-5 w-9 rounded-full bg-slate-300 transition-colors peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600 peer-focus-visible:ring-offset-2 peer-disabled:opacity-50 '
        . 'after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform after:content-[\'\'] peer-checked:after:translate-x-4"></span>'
        . '</label>';
};
?>
<form method="post" action="<?= base_url('modules/' . $moduleId . '/notifications') ?>" class="space-y-5">
    <?= csrf_field() ?>

    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-800">Notifications</h3>
            <p class="mt-1 text-xs text-slate-500">Choose how the people of this module are told about each event: in the app (the bell), by email, or both.</p>
        </div>
        <?php if ($canEdit): ?>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[2fr_1fr]">

        <!-- events -->
        <section class="rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3.5">
                <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                <h4 class="text-sm font-bold text-slate-800">Notification Settings</h4>
            </div>
            <p class="px-5 pt-3 text-xs text-slate-500">Turn <strong>In-App</strong> and <strong>Email</strong> on or off for each event. When both are off, nobody is notified for that event.</p>
            <div class="overflow-x-auto p-5">
                <table class="w-full min-w-[520px] text-left text-xs">
                    <thead class="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Event</th>
                            <th class="px-3 py-2 text-center">In-App</th>
                            <th class="px-3 py-2 text-center">Email</th>
                            <th class="py-2 pl-3">Recipients</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach (Notifier::EVENTS as $key => $ev): $s = $settings[$key]; ?>
                            <tr class="align-middle">
                                <td class="py-3 pr-3">
                                    <span class="block font-semibold text-slate-800"><?= esc($ev['label']) ?>
                                        <?php if (empty($ev['wired'])): ?><span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500">Coming soon</span><?php endif; ?>
                                    </span>
                                    <span class="block text-[11px] text-slate-400"><?= esc($ev['help']) ?></span>
                                </td>
                                <td class="px-3 py-3 text-center"><?= $switch('ev[' . $key . '][in_app]', $s['in_app'], $ev['label'] . ' - in-app', $dis) ?></td>
                                <td class="px-3 py-3 text-center"><?= $switch('ev[' . $key . '][email]', $s['email'], $ev['label'] . ' - email', $dis) ?></td>
                                <td class="py-3 pl-3 text-slate-500"><?= esc($ev['recipients']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- default preference -->
        <aside class="space-y-4">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h4 class="text-sm font-bold text-slate-800">Default Notification Preference</h4>
                <p class="mt-1 text-xs text-slate-500">Used for events whose switches were never saved yet.</p>
                <div class="mt-3 space-y-2.5" role="radiogroup" aria-label="Default notification preference">
                    <?php foreach (['in_app' => 'In-App only', 'email' => 'Email only', 'both' => 'Both (In-App + Email)'] as $val => $text): ?>
                        <label class="flex items-center gap-2.5 text-[13px] text-slate-700">
                            <input type="radio" name="default_pref" value="<?= $val ?>" <?= $pref === $val ? 'checked' : '' ?> <?= $dis ?> class="h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-600">
                            <?= esc($text) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="flex gap-2.5 rounded-xl border border-sky-100 bg-sky-50/70 p-4 text-xs leading-relaxed text-slate-600">
                <i class="fa-solid fa-circle-info mt-0.5 text-sky-500"></i>
                <p>Recipients come from the module configuration and the user assignments (administrators, approvers, requester). Only active accounts are notified. Email is sent to the user's workspace email.</p>
            </div>
            <?php if (! $canEdit): ?>
                <p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500">You can view these settings, but only a module administrator can change them.</p>
            <?php endif; ?>
        </aside>
    </div>
</form>