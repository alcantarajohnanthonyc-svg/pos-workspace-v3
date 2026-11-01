<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper(['form', 'security']);

$rows       = $rows ?? [];
$branches   = $branches ?? [];
$statuses   = $statuses ?? [];
$ownerships = $ownerships ?? [];
$filters    = $filters ?? ['q' => '', 'company' => 0, 'branch' => 0, 'status' => ''];
$filters   += ['q' => '', 'company' => 0, 'branch' => 0, 'status' => '', 'pending' => 0];
$stats      = (array) ($stats ?? []);
$stats     += ['n' => 0, 'by' => []];
$scopeTotal = (int) ($scopeTotal ?? $stats['n']);   // safe even if the controller still sends the old stats
$can        = $can ?? [];   // deny by default: buttons show only when the controller sent true
$page       = $page ?? 1;
$pages      = $pages ?? 1;
$total      = $total ?? 0;
$perPage    = $perPage ?? 25;
$scoped     = ! empty($scoped);
$scopeFlags = $scopeFlags ?? ['company' => false, 'branch' => false];
$importRef  = $importRef ?? [];
$importMax  = $importMax ?? 5000;
$pending       = (array) ($pending ?? []);                                  // store id => action => [ref, by, when]
$pendingNew    = (array) ($pendingNew ?? []) + ['total' => 0, 'items' => []];   // pending "new store" requests
$pendingStores = (int) ($pendingStores ?? 0);                               // stores with a pending update / delete

$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');

$btnPrim  = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$btnDanger = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-600';
$closeBtn = 'flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$fInput   = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';
$fLabel   = 'mb-1 block text-xs font-medium text-slate-700';

$badge = static function (?string $s): string {
    $k = strtolower(trim((string) $s));
    if ($k === '') { return 'bg-slate-100 text-slate-500'; }
    // closed / inactive first: "IN-ACTIVE" also contains "active"
    if (preg_match('/clos|in-?active|terminat|stop/', $k)) { return 'bg-rose-50 text-rose-700 border border-rose-200'; }
    if (preg_match('/open|active|operat/', $k)) { return 'bg-emerald-50 text-emerald-700 border border-emerald-200'; }
    return 'bg-amber-50 text-amber-700 border border-amber-200';
};
$branchLabel = static fn (array $b): string => ($b['company'] ?? '') !== '' && $b['company'] !== null ? $b['name'] . ' (' . $b['company'] . ')' : (string) $b['name'];
// Companies for the filter modal come from the branches the user may see (already scoped by the controller)
$companies = [];
foreach ($branches as $b) {
    if (! empty($b['company_id'])) { $companies[(int) $b['company_id']] = (string) ($b['company'] ?? ''); }
}
asort($companies);
$branchJson   = array_map(static fn ($b) => ['id' => (int) $b['id'], 'name' => (string) $b['name'], 'company_id' => (int) ($b['company_id'] ?? 0)], $branches);
$activeFilter = (int) ($filters['company'] > 0) + (int) ($filters['branch'] > 0) + (int) ($filters['status'] !== '');
$statCard = ['fa-store', 'fa-circle-check', 'fa-circle-pause', 'fa-circle-xmark', 'fa-circle-question'];
$qs = static fn (array $extra) => http_build_query(array_filter(array_merge(['q' => $filters['q'], 'company' => $filters['company'], 'branch' => $filters['branch'], 'status' => $filters['status'], 'pending' => $filters['pending']], $extra), static fn ($v) => $v !== '' && $v !== 0 && $v !== null));
?>

<div class="space-y-4">

    <?php if ($flashSuccess): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-800"><?= esc($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs text-rose-800"><?= esc($flashError) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-slate-800">Store Details</h1>
            <p class="text-xs text-slate-500">Stores, their branch and company.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if (! empty($can['export'])): ?>
                <a href="<?= base_url('store-details/export') . ($qs([]) ? '?' . $qs([]) : '') ?>" data-loading="Preparing export..." data-loading-for="2500" class="<?= $btnGhost ?>"><i class="fa-solid fa-file-export"></i> Export</a>
            <?php endif; ?>
            <?php if (! empty($can['import'])): ?>
                <button type="button" data-open="modal-import" class="<?= $btnGhost ?>"><i class="fa-solid fa-file-import"></i> Import</button>
            <?php endif; ?>
            <?php if (! empty($can['create'])): ?>
                <button type="button" data-open="modal-store" data-mode="create" class="<?= $btnPrim ?>"><i class="fa-solid fa-plus"></i> Add Store</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($scoped): ?>
        <div class="flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-xs text-sky-800">
            <i class="fa-solid fa-filter mt-0.5"></i>
            <span>
                Showing only stores from your assigned
                <?= $scopeFlags['company'] && $scopeFlags['branch'] ? 'companies and branches' : ($scopeFlags['company'] ? 'companies' : 'branches') ?>.
                <?= $scopeTotal === 0 ? 'No assigned branch was found for your account, so the list is empty.' : '' ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($pendingNew['total'] > 0): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-900" role="status">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-semibold"><i class="fa-regular fa-clock"></i> <?= (int) $pendingNew['total'] ?> new store request<?= $pendingNew['total'] === 1 ? '' : 's' ?> waiting for approval</span>
                <?php if (! empty($canQueue)): ?>
                    <a href="<?= base_url('approval-queue?module=store_details&status=pending') ?>" class="font-semibold text-amber-800 underline hover:text-amber-950">Open Approval Queue</a>
                <?php endif; ?>
            </div>
            <ul class="mt-1 space-y-0.5 text-[11px] text-amber-800">
                <?php foreach ($pendingNew['items'] as $pn): ?>
                    <li><span class="font-semibold"><?= esc($pn['name'] !== '' ? $pn['name'] : 'New store') ?></span> · <?= esc($pn['by']) ?> · <?= esc($pn['when']) ?> · <span class="font-mono"><?= esc($pn['ref']) ?></span></li>
                <?php endforeach; ?>
                <?php if ($pendingNew['total'] > count($pendingNew['items'])): ?><li>… and <?= (int) ($pendingNew['total'] - count($pendingNew['items'])) ?> more</li><?php endif; ?>
            </ul>
            <p class="mt-1 text-[11px] text-amber-700">These stores will appear in the list after they are approved.</p>
        </div>
    <?php endif; ?>

    <!-- Stats: "All" tab + one tab per status. When a status is selected, the other statuses are hidden. -->
    <?php
    $statAll    = array_sum(array_map('intval', (array) $stats['by']));   // stable total: search/company/branch applied, status ignored
    $statSelect = $filters['status'];
    $allLink    = $qs(['status' => '', 'page' => null]);
    ?>
    <div class="flex flex-wrap items-stretch gap-3">

        <!-- All stores (click = clear the status filter) -->
        <a href="<?= base_url('store-details') . ($allLink !== '' ? '?' . esc($allLink) : '') ?>"
           class="flex min-w-[170px] items-center gap-3 rounded-xl border bg-white px-4 py-3 transition-colors hover:bg-slate-50 <?= $statSelect === '' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200' ?>">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><i class="fa-solid fa-store"></i></span>
            <div>
                <div class="text-xl font-bold leading-none text-slate-800"><?= number_format($statAll) ?></div>
                <div class="mt-1 text-[11px] uppercase tracking-wide text-slate-500">All stores</div>
            </div>
        </a>

        <?php foreach ((array) $stats['by'] as $stName => $stCount): ?>
            <?php
            $stActive = $statSelect !== '' && $statSelect === $stName;
            if ($statSelect !== '' && ! $stActive) { continue; }          // a status is selected -> hide the other statuses
            $stPct  = $statAll > 0 ? round(((int) $stCount) / $statAll * 100) : 0;
            $stLink = $stActive ? '' : ($stName === 'No status' ? '' : $stName);
            ?>
            <a href="<?= base_url('store-details') . '?' . esc($qs(['status' => $stLink, 'page' => null])) ?>"
               class="flex min-w-[190px] items-center gap-3 rounded-xl border bg-white px-4 py-3 transition-colors hover:bg-slate-50 <?= $stActive ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200' ?>">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg <?= $badge($stName === 'No status' ? '' : $stName) ?>"><i class="fa-solid fa-circle-dot"></i></span>
                <div class="min-w-0">
                    <div class="flex items-baseline gap-2">
                        <span class="text-xl font-bold leading-none text-slate-800"><?= number_format((int) $stCount) ?></span>
                        <span class="text-[11px] font-semibold text-slate-400"><?= $stPct ?>%</span>
                    </div>
                    <div class="mt-1 truncate text-[11px] uppercase tracking-wide text-slate-500">
                        <?= esc($stName) ?>
                        <?php if ($stActive): ?><i class="fa-solid fa-xmark ml-1" title="Clear"></i><?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>

        <?php if ($pendingStores > 0 || ! empty($filters['pending'])): ?>
            <!-- Pending approval (click = show only the stores that have a request waiting) -->
            <a href="<?= base_url('store-details') . '?' . esc($qs(['pending' => $filters['pending'] ? 0 : 1, 'page' => null])) ?>"
               class="flex min-w-[190px] items-center gap-3 rounded-xl border bg-white px-4 py-3 transition-colors hover:bg-amber-50/50 <?= $filters['pending'] ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-amber-200' ?>">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600"><i class="fa-regular fa-clock"></i></span>
                <div>
                    <div class="text-xl font-bold leading-none text-slate-800"><?= number_format($pendingStores) ?></div>
                    <div class="mt-1 text-[11px] uppercase tracking-wide text-slate-500">Pending approval <?php if ($filters['pending']): ?><i class="fa-solid fa-xmark ml-1" title="Clear"></i><?php endif; ?></div>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- Search + Filters (the filter modal lives inside this form, so Search and Apply send the same GET) -->
    <form id="sd-filter-form" method="get" action="<?= base_url('store-details') ?>" class="rounded-xl border border-slate-200 bg-white p-3">
        <?php if (! empty($filters['pending'])): ?><input type="hidden" name="pending" value="1"><?php endif; ?>
        <div class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Search code, ERP code, outlet, address, branch…" class="<?= $fInput ?> min-w-[200px] flex-1">
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            <button type="button" data-open="modal-filter" class="<?= $btnGhost ?>">
                <i class="fa-solid fa-sliders"></i> Filter
                <?php if ($activeFilter > 0): ?><span class="rounded-full bg-emerald-600 px-1.5 text-[10px] font-bold text-white"><?= $activeFilter ?></span><?php endif; ?>
            </button>
            <a href="<?= base_url('store-details') ?>" class="<?= $btnGhost ?>">Reset</a>
        </div>

        <!-- Filter modal -->
        <div id="modal-filter" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="flt-title">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h2 id="flt-title" class="text-sm font-bold text-slate-800">Filter stores</h2>
                    <button type="button" data-close class="<?= $closeBtn ?>" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <div>
                        <label for="flt-company" class="<?= $fLabel ?>">Company</label>
                        <select id="flt-company" name="company" class="<?= $fInput ?>">
                            <option value="">All companies</option>
                            <?php foreach ($companies as $cid => $cname): ?>
                                <option value="<?= (int) $cid ?>" <?= (int) $filters['company'] === (int) $cid ? 'selected' : '' ?>><?= esc($cname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="flt-branch" class="<?= $fLabel ?>">Branch</label>
                        <select id="flt-branch" name="branch" class="<?= $fInput ?>" data-selected="<?= (int) $filters['branch'] ?>">
                            <option value="">All branches</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">Shows only the branches of the selected company.</p>
                    </div>
                    <div>
                        <label for="flt-status" class="<?= $fLabel ?>">Status</label>
                        <select id="flt-status" name="status" class="<?= $fInput ?>">
                            <option value="">All statuses</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= esc($s, 'attr') ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" id="flt-clear" class="<?= $btnGhost ?>">Clear</button>
                    <div class="flex gap-2">
                        <button type="button" data-close class="<?= $btnGhost ?>">Cancel</button>
                        <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-check"></i> Apply filters</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
    (function () {
        var branches = <?= json_encode($branchJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var co = document.getElementById('flt-company'), br = document.getElementById('flt-branch'), st = document.getElementById('flt-status');
        function fillBranches(keep) {
            var cid = parseInt(co.value || '0', 10);
            br.innerHTML = '';
            var all = document.createElement('option'); all.value = ''; all.textContent = 'All branches'; br.appendChild(all);
            branches.forEach(function (b) {
                if (cid && b.company_id !== cid) { return; }
                var o = document.createElement('option'); o.value = b.id; o.textContent = b.name;
                if (keep && String(b.id) === String(keep)) { o.selected = true; }
                br.appendChild(o);
            });
        }
        co.addEventListener('change', function () { fillBranches(''); });
        document.getElementById('flt-clear').addEventListener('click', function () {
            co.value = ''; st.value = ''; fillBranches('');
            document.getElementById('sd-filter-form').submit();
        });
        fillBranches(br.getAttribute('data-selected'));
    })();
    </script>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2.5">Store</th>
                        <th class="px-3 py-2.5">Codes</th>
                        <th class="px-3 py-2.5">Branch / Company</th>
                        <th class="px-3 py-2.5">Ownership</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Opened</th>
                        <th class="px-3 py-2.5">Last POS Sale</th>
                        <?php if (! empty($can['update']) || ! empty($can['delete'])): ?><th class="px-3 py-2.5 text-right">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r):
                        $pp   = $pending[(int) $r['id']] ?? [];
                        $pUpd = $pp['update'] ?? null;
                        $pDel = $pp['delete'] ?? null;
                        ?>
                        <tr class="hover:bg-slate-50 align-top <?= $pp ? 'bg-amber-50/40' : '' ?>">
                            <td class="px-3 py-2.5">
                                <div class="font-semibold text-slate-800"><?= esc($r['outlet_description']) ?></div>
                                <?php if (! empty($r['address'])): ?><div class="mt-0.5 max-w-[260px] truncate text-[11px] text-slate-400" title="<?= esc($r['address'], 'attr') ?>"><?= esc($r['address']) ?></div><?php endif; ?>
                                <?php foreach (['update' => 'update', 'delete' => 'delete'] as $ak => $al): ?>
                                    <?php if (! empty($pp[$ak])): ?>
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                              title="<?= esc($pp[$ak]['ref'] . ' · sent by ' . $pp[$ak]['by'] . ' · ' . $pp[$ak]['when'], 'attr') ?>">
                                            <i class="fa-regular fa-clock"></i> Pending <?= $al ?> approval · <?= esc($pp[$ak]['by']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-[11px] text-slate-600">
                                <div><?= esc($r['store_code'] ?? '—') ?></div>
                                <div class="text-slate-400"><?= esc($r['erp_code'] ?? '') ?></div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="text-slate-700"><?= esc($r['branch_name'] ?? '—') ?></div>
                                <div class="text-[11px] text-slate-400"><?= esc($r['company_name'] ?? '') ?></div>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600"><?= esc($r['ownership_type'] ?? '—') ?></td>
                            <td class="px-3 py-2.5"><span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $badge($r['store_status'] ?? '') ?>"><?= esc($r['store_status'] ?: '—') ?></span></td>
                            <td class="px-3 py-2.5 text-slate-600"><?= esc($r['date_opened'] ?? '—') ?></td>
                            <td class="px-3 py-2.5 text-slate-600"><?= esc($r['last_pos_selling_date'] ?? '—') ?></td>
                            <?php if (! empty($can['update']) || ! empty($can['delete'])): ?>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <?php if (! empty($can['update'])): ?>
                                        <?php if ($pUpd || $pDel): ?>
                                            <span class="<?= $btnGhost ?> cursor-not-allowed opacity-50" aria-disabled="true" title="Waiting for approval (<?= esc((($pUpd ?? $pDel)['ref'] ?? ''), 'attr') ?>)"><i class="fa-solid fa-pen"></i></span>
                                        <?php else: ?>
                                        <button type="button" data-open="modal-store" data-mode="edit"
                                                data-row="<?= esc(json_encode([
                                                    'id' => (int) $r['id'], 'branch_id' => (int) $r['branch_id'], 'store_code' => $r['store_code'], 'erp_code' => $r['erp_code'],
                                                    'outlet_description' => $r['outlet_description'], 'address' => $r['address'], 'ownership_type' => $r['ownership_type'],
                                                    'store_status' => $r['store_status'], 'date_opened' => $r['date_opened'], 'last_pos_selling_date' => $r['last_pos_selling_date'],
                                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP), 'attr') ?>"
                                                class="<?= $btnGhost ?>" aria-label="Edit <?= esc($r['outlet_description'], 'attr') ?>"><i class="fa-solid fa-pen"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (! empty($can['delete'])): ?>
                                        <?php if ($pDel): ?>
                                            <span class="<?= $btnGhost ?> cursor-not-allowed text-rose-600 opacity-50" aria-disabled="true" title="Waiting for approval (<?= esc($pDel['ref'], 'attr') ?>)"><i class="fa-solid fa-trash"></i></span>
                                        <?php else: ?>
                                        <button type="button" data-open="modal-delete" data-id="<?= (int) $r['id'] ?>" data-name="<?= esc($r['outlet_description'], 'attr') ?>"
                                                class="<?= $btnGhost ?> text-rose-600" aria-label="Delete <?= esc($r['outlet_description'], 'attr') ?>"><i class="fa-solid fa-trash"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $rows): ?>
                        <tr><td colspan="8" class="px-3 py-10 text-center text-slate-400">No stores found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-2.5 text-xs text-slate-500">
            <span>
                <?= $total ? number_format(($page - 1) * $perPage + 1) . '–' . number_format(min($page * $perPage, $total)) . ' of ' . number_format($total) : '0 results' ?>
            </span>
            <?php if ($pages > 1): ?>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?><a class="<?= $btnGhost ?>" href="?<?= esc($qs(['page' => $page - 1])) ?>">Prev</a><?php endif; ?>
                    <span class="px-2">Page <?= $page ?> / <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="<?= $btnGhost ?>" href="?<?= esc($qs(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (! empty($can['create']) || ! empty($can['update'])): ?>
<!-- Add / Edit store -->
<div id="modal-store" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="store-title">
    <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="store-title" class="text-sm font-bold text-slate-800">Add Store</h2>
            <button type="button" data-close class="<?= $closeBtn ?>" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="store-form" data-loading="Saving store..." method="post" action="<?= base_url('store-details/create') ?>" class="max-h-[75vh] space-y-3 overflow-y-auto px-5 py-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="f_outlet" class="<?= $fLabel ?>">Outlet Description <span class="text-red-500">*</span></label>
                    <input id="f_outlet" name="outlet_description" required maxlength="255" class="<?= $fInput ?>">
                </div>
                <div>
                    <label for="f_branch" class="<?= $fLabel ?>">Branch <span class="text-red-500">*</span></label>
                    <select id="f_branch" name="branch_id" required class="<?= $fInput ?>">
                        <option value="">Select branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= esc($branchLabel($b)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_status" class="<?= $fLabel ?>">Store Status</label>
                    <input id="f_status" name="store_status" maxlength="50" list="dl-status" class="<?= $fInput ?>" placeholder="e.g. Open">
                    <datalist id="dl-status"><?php foreach ($statuses as $s): ?><option value="<?= esc($s, 'attr') ?>"><?php endforeach; ?></datalist>
                </div>
                <div>
                    <label for="f_code" class="<?= $fLabel ?>">Store Code</label>
                    <input id="f_code" name="store_code" maxlength="50" class="<?= $fInput ?> font-mono">
                </div>
                <div>
                    <label for="f_erp" class="<?= $fLabel ?>">ERP Code</label>
                    <input id="f_erp" name="erp_code" maxlength="50" class="<?= $fInput ?> font-mono">
                </div>
                <div>
                    <label for="f_own" class="<?= $fLabel ?>">Ownership Type</label>
                    <input id="f_own" name="ownership_type" maxlength="100" list="dl-own" class="<?= $fInput ?>">
                    <datalist id="dl-own"><?php foreach ($ownerships as $o): ?><option value="<?= esc($o, 'attr') ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="f_open" class="<?= $fLabel ?>">Date Opened</label>
                        <input id="f_open" name="date_opened" type="date" class="<?= $fInput ?>">
                    </div>
                    <div>
                        <label for="f_last" class="<?= $fLabel ?>">Last POS Sale</label>
                        <input id="f_last" name="last_pos_selling_date" type="date" class="<?= $fInput ?>">
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label for="f_addr" class="<?= $fLabel ?>">Address</label>
                    <textarea id="f_addr" name="address" rows="2" class="<?= $fInput ?>"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                <button type="button" data-close class="<?= $btnGhost ?>">Cancel</button>
                <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (! empty($can['delete'])): ?>
<!-- Delete -->
<div id="modal-delete" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="del-title">
    <form id="delete-form" data-loading="Deleting store..." method="post" action="" class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
        <?= csrf_field() ?>
        <h2 id="del-title" class="text-sm font-bold text-slate-800">Delete store?</h2>
        <p class="mt-2 text-xs text-slate-600">This permanently deletes <b id="del-name"></b>.</p>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" data-close class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnDanger ?>"><i class="fa-solid fa-trash"></i> Delete</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (! empty($can['import'])):
    $allBranches = array_values(array_unique(array_merge([], ...array_values($importRef))));
    $impBtn      = $btnPrim;
?>
<!-- Import (wizard): 1 choose file  2 review and edit  3 importing  4 report -->
<div id="modal-import" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-3" role="dialog" aria-modal="true" aria-labelledby="imp-title">
    <div class="flex max-h-[94vh] w-full max-w-6xl flex-col rounded-2xl bg-white shadow-2xl">

        <!-- header + stepper -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i class="fa-solid fa-file-import"></i></span>
                <div>
                    <h2 id="imp-title" class="text-sm font-bold text-slate-800">Import stores</h2>
                    <p class="text-[11px] text-slate-500">Company and branch are typed by <b>name</b>, not id.</p>
                </div>
            </div>
            <ol class="flex items-center gap-2" aria-label="Import steps">
                <?php foreach (['Choose file', 'Review & edit', 'Importing', 'Report'] as $i => $lbl): ?>
                    <li class="flex items-center gap-2">
                        <span id="imp-dot-<?= $i + 1 ?>" class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-[11px] font-bold text-slate-500"><?= $i + 1 ?></span>
                        <span id="imp-lbl-<?= $i + 1 ?>" class="hidden text-xs font-semibold text-slate-400 sm:inline"><?= $lbl ?></span>
                        <?php if ($i < 3): ?><span class="hidden h-px w-6 bg-slate-200 sm:block"></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <button type="button" id="imp-x" class="<?= $closeBtn ?>" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- body -->
        <div class="min-h-0 flex-1 overflow-y-auto">

            <!-- 1. choose file -->
            <div data-imp-step="1" class="space-y-4 p-5">
                <label id="imp-drop" for="imp-file" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center transition-colors hover:border-emerald-500 hover:bg-emerald-50/40">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-600"><i class="fa-solid fa-file-csv"></i></span>
                    <span class="text-sm font-semibold text-slate-700">Drop your CSV here, or click to choose a file</span>
                    <span class="text-xs text-slate-500">.csv only, up to 2 MB and <?= number_format($importMax) ?> lines. In Excel: Save As, CSV UTF-8.</span>
                    <input id="imp-file" type="file" accept=".csv,.txt,text/csv" class="sr-only">
                </label>
                <p id="imp-err" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700" role="alert"></p>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-4 text-xs text-slate-600">
                        <div class="mb-1 font-semibold text-slate-800">What the file needs</div>
                        <p>
                            Columns: <span class="font-mono text-[11px]">company, branch, store_code, erp_code, outlet_description, address, ownership_type, store_status, date_opened, last_pos_selling_date</span>.
                        </p>
                        <p class="mt-2">Company: its name or short name (e.g. <span class="font-mono">ctgi</span>). Branch: its name (e.g. <span class="font-mono">GENSAN</span>). Dates: <span class="font-mono">YYYY-MM-DD</span>.</p>
                        <a href="<?= base_url('store-details/export?template=1') ?>" data-loading="Preparing template..." data-loading-for="2500" class="mt-3 inline-flex items-center gap-1.5 font-semibold text-emerald-700 hover:underline"><i class="fa-solid fa-download"></i> Download template</a>
                    </div>
                    <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs text-sky-800">
                        <div class="mb-1 font-semibold">How it works</div>
                        <ol class="list-decimal space-y-0.5 pl-4">
                            <li>Every line is checked on the server<?= $scoped ? ' against <b>your assigned company / branch</b>' : '' ?>.</li>
                            <li>You can fix or remove lines before anything is saved.</li>
                            <li>Only the valid lines are imported, with progress and time left.</li>
                            <li>You get a report, and the failed lines can be downloaded.</li>
                        </ol>
                    </div>
                </div>

                <?php if ($importRef): ?>
                    <details class="rounded-xl border border-slate-200 text-xs">
                        <summary class="cursor-pointer px-4 py-2.5 font-semibold text-slate-700">Companies and branches you can use</summary>
                        <div class="max-h-48 space-y-2 overflow-y-auto border-t border-slate-100 px-4 py-3">
                            <?php foreach ($importRef as $co => $list): ?>
                                <div><div class="font-semibold text-slate-700"><?= esc($co) ?></div><div class="text-[11px] text-slate-500"><?= esc(implode(', ', $list)) ?></div></div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <!-- 2. review and edit -->
            <div data-imp-step="2" class="hidden">
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3">
                    <span class="text-xs text-slate-500"><i class="fa-solid fa-file-csv text-emerald-600"></i> <span id="imp-file-name" class="font-semibold text-slate-700"></span></span>
                    <span class="mx-1 h-4 w-px bg-slate-200"></span>
                    <button type="button" id="imp-f-all" class="rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800">All <span id="imp-c-all">0</span></button>
                    <button type="button" id="imp-f-ready" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-500">Ready <span id="imp-c-ready">0</span></button>
                    <button type="button" id="imp-f-failed" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-500">Failed <span id="imp-c-failed">0</span></button>
                    <span id="imp-checking" class="hidden text-xs font-semibold text-slate-500"></span>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" id="imp-rm-failed" class="hidden <?= $btnGhost ?> text-rose-600"><i class="fa-solid fa-trash-can"></i> Remove failed</button>
                        <input id="imp-q" type="search" placeholder="Search the list…" aria-label="Search the list" class="w-48 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                    </div>
                </div>
                <p class="px-5 pt-2 text-[11px] text-slate-500"><i class="fa-solid fa-pen-to-square"></i> Click any cell to edit it; the line is checked again. Use <i class="fa-solid fa-xmark"></i> to remove a line. Nothing is saved yet.</p>
                <div class="mx-5 mb-2 mt-2 max-h-[48vh] overflow-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-2 py-2">Line</th>
                                <th class="px-2 py-2">Result</th>
                                <th class="px-2 py-2">Reason</th>
                                <?php foreach (['Company', 'Branch', 'Store code', 'ERP code', 'Outlet description', 'Address', 'Ownership', 'Status', 'Date opened', 'Last POS sale'] as $h): ?>
                                    <th class="px-2 py-2"><?= $h ?></th>
                                <?php endforeach; ?>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody id="imp-rows" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between px-5 pb-3 text-xs text-slate-500">
                    <span id="imp-pager-info"></span>
                    <div class="flex items-center gap-2">
                        <button type="button" id="imp-prev" class="<?= $btnGhost ?> disabled:opacity-40">Prev</button>
                        <span id="imp-page"></span>
                        <button type="button" id="imp-next" class="<?= $btnGhost ?> disabled:opacity-40">Next</button>
                    </div>
                </div>
            </div>

            <!-- 3. importing -->
            <div data-imp-step="3" class="hidden px-8 py-12">
                <div class="mx-auto max-w-xl text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-600"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    <h3 class="mt-3 text-base font-bold text-slate-800">Importing your stores…</h3>
                    <p class="text-xs text-slate-500">Please keep this window open until it finishes.</p>

                    <div class="mt-6 h-3 w-full overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                        <div id="imp-bar" class="h-full rounded-full bg-emerald-600 transition-all duration-300" style="width:0%"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
                        <span><b id="imp-done">0</b> of <b id="imp-total">0</b> lines processed</span>
                        <span class="font-bold text-emerald-700" id="imp-pct">0%</span>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-slate-200 p-3"><div id="imp-ok" class="text-lg font-bold text-emerald-700">0</div><div class="text-[11px] uppercase tracking-wide text-slate-500">Imported</div></div>
                        <div class="rounded-xl border border-slate-200 p-3"><div id="imp-bad" class="text-lg font-bold text-rose-600">0</div><div class="text-[11px] uppercase tracking-wide text-slate-500">Failed</div></div>
                        <div class="rounded-xl border border-slate-200 p-3"><div id="imp-elapsed" class="text-lg font-bold text-slate-800">0:00</div><div class="text-[11px] uppercase tracking-wide text-slate-500">Elapsed</div></div>
                        <div class="rounded-xl border border-slate-200 p-3"><div id="imp-eta" class="text-lg font-bold text-slate-800">calculating…</div><div class="text-[11px] uppercase tracking-wide text-slate-500">Time left</div></div>
                    </div>
                </div>
            </div>

            <!-- 4. report -->
            <div data-imp-step="4" class="hidden p-5">
                <div class="flex items-center gap-3">
                    <span id="rep-icon" class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 text-lg text-emerald-600"><i class="fa-solid fa-circle-check"></i></span>
                    <div>
                        <h3 id="rep-title" class="text-base font-bold text-slate-800">Import finished</h3>
                        <p id="rep-removed" class="text-xs text-slate-500"></p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 p-3"><div id="rep-total" class="text-xl font-bold text-slate-800">0</div><div class="text-[11px] uppercase tracking-wide text-slate-500">Total lines</div></div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3"><div id="rep-ok" class="text-xl font-bold text-emerald-700">0</div><div class="text-[11px] uppercase tracking-wide text-emerald-700">Imported</div></div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3"><div id="rep-bad" class="text-xl font-bold text-rose-600">0</div><div class="text-[11px] uppercase tracking-wide text-rose-600">Failed</div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div id="rep-time" class="text-xl font-bold text-slate-800">0:00</div><div class="text-[11px] uppercase tracking-wide text-slate-500">Time taken</div></div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <button type="button" id="rep-f-all" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-500">All</button>
                    <button type="button" id="rep-f-imported" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-500">Imported</button>
                    <button type="button" id="rep-f-failed" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-500">Failed</button>
                </div>
                <div class="mt-2 max-h-[38vh] overflow-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr><th class="px-3 py-2">Line</th><th class="px-3 py-2">Result</th><th class="px-3 py-2">Company</th><th class="px-3 py-2">Branch</th><th class="px-3 py-2">Store code</th><th class="px-3 py-2">Outlet</th><th class="px-3 py-2">Reason</th></tr>
                        </thead>
                        <tbody id="rep-rows" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
                <p id="rep-more" class="mt-1 text-[11px] text-slate-400"></p>
            </div>
        </div>

        <!-- footers -->
        <div data-imp-step="1" class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 rounded-b-2xl">
            <button type="button" data-close class="<?= $btnGhost ?>">Cancel</button>
        </div>
        <div data-imp-step="2" class="hidden flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 rounded-b-2xl">
            <button type="button" id="imp-back" class="<?= $btnGhost ?>"><i class="fa-solid fa-arrow-left"></i> Choose another file</button>
            <button type="button" id="imp-go" class="<?= $impBtn ?> disabled:cursor-not-allowed disabled:opacity-50" disabled><i class="fa-solid fa-play"></i> Import <span id="imp-go-n">0</span> valid lines</button>
        </div>
        <div data-imp-step="3" class="hidden flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 rounded-b-2xl">
            <button type="button" id="imp-cancel" class="<?= $btnGhost ?>">Stop</button>
        </div>
        <div data-imp-step="4" class="hidden flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 rounded-b-2xl">
            <div class="flex flex-wrap gap-2">
                <button type="button" id="rep-dl-full" class="<?= $btnGhost ?>"><i class="fa-solid fa-file-arrow-down"></i> Download report</button>
                <button type="button" id="rep-dl-failed" class="<?= $btnGhost ?> text-rose-600 disabled:opacity-40"><i class="fa-solid fa-file-circle-exclamation"></i> Download failed lines</button>
            </div>
            <button type="button" id="rep-done" class="<?= $impBtn ?>"><i class="fa-solid fa-check"></i> Done</button>
        </div>
    </div>

    <datalist id="imp-dl-company"><?php foreach (array_keys($importRef) as $co): ?><option value="<?= esc($co, 'attr') ?>"><?php endforeach; ?></datalist>
    <datalist id="imp-dl-branch"><?php foreach ($allBranches as $br): ?><option value="<?= esc($br, 'attr') ?>"><?php endforeach; ?></datalist>
    <datalist id="imp-dl-status"><?php foreach ($statuses as $x): ?><option value="<?= esc($x, 'attr') ?>"><?php endforeach; ?></datalist>
    <datalist id="imp-dl-own"><?php foreach ($ownerships as $x): ?><option value="<?= esc($x, 'attr') ?>"><?php endforeach; ?></datalist>
</div>

<script>
window.SD_IMPORT = <?= json_encode([
    'checkUrl'   => base_url('store-details/import/check'),
    'commitUrl'  => base_url('store-details/import/commit'),
    'csrfHeader' => csrf_header(),
    'token'      => csrf_hash(),
    'maxRows'    => (int) $importMax,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script>
(function () {
    'use strict';
    var CFG = window.SD_IMPORT;
    if (!CFG) { return; }

    var FIELDS = ['company', 'branch', 'store_code', 'erp_code', 'outlet_description', 'address', 'ownership_type', 'store_status', 'date_opened', 'last_pos_selling_date'];
    var COLS = [
        ['company', 'Company', 150, 'imp-dl-company'],
        ['branch', 'Branch', 130, 'imp-dl-branch'],
        ['store_code', 'Store code', 105, ''],
        ['erp_code', 'ERP code', 95, ''],
        ['outlet_description', 'Outlet description', 230, ''],
        ['address', 'Address', 230, ''],
        ['ownership_type', 'Ownership', 125, 'imp-dl-own'],
        ['store_status', 'Status', 105, 'imp-dl-status'],
        ['date_opened', 'Date opened', 110, ''],
        ['last_pos_selling_date', 'Last POS sale', 110, '']
    ];
    var PAGE = 50;
    var CHECK_CHUNK = 200;
    var COMMIT_CHUNK = 100;
    var REPORT_SHOW = 300;

    var token = CFG.token;
    var state;

    function $(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fresh() {
        return { step: 1, rows: [], nextId: 1, page: 1, filter: 'all', q: '', file: '', cancel: false, started: 0, took: 0, removed: 0, timer: null, checkTimer: null, queue: {}, rfilter: 'failed', checking: 0 };
    }
    state = fresh();

    // ------------------------------------------------------------ status of a row
    // sv (server answer): pending | checking | ready | skipped | imported
    function st(r) {
        if (r.sv === 'imported') { return 'imported'; }
        if (r.sv === 'pending' || r.sv === 'checking') { return 'checking'; }
        if (r.sv === 'skipped') { return 'failed'; }
        if (r.dupLine) { return 'failed'; }
        return 'ready';
    }
    function why(r) {
        if (r.sv === 'skipped') { return r.reason || 'Not accepted.'; }
        if (r.dupLine) { return 'Store code is repeated in this file (first on line ' + r.dupLine + ').'; }
        return '';
    }
    function fieldBad(reason, f) {
        return reason.toLowerCase().indexOf(f.replace(/_/g, ' ')) >= 0;
    }
    function counts() {
        var c = { all: 0, ready: 0, failed: 0, checking: 0 };
        state.rows.forEach(function (r) {
            var s = st(r);
            c.all++;
            if (s === 'ready') { c.ready++; } else if (s === 'failed') { c.failed++; } else if (s === 'checking') { c.checking++; }
        });
        return c;
    }
    function markDup() {
        var seen = {};
        state.rows.forEach(function (r) {
            r.dupLine = 0;
            var k = (r.data.store_code || '').trim().toLowerCase();
            if (!k || r.sv === 'skipped') { return; }
            if (seen[k]) { r.dupLine = seen[k]; } else { seen[k] = r.line; }
        });
    }

    // ---------------------------------------------------------------------- steps
    function setStep(n) {
        state.step = n;
        document.querySelectorAll('[data-imp-step]').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.getAttribute('data-imp-step'), 10) !== n);
        });
        for (var i = 1; i <= 4; i++) {
            var dot = $('imp-dot-' + i);
            if (!dot) { continue; }
            var done = i < n, cur = i === n;
            dot.className = 'flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold ' +
                (done ? 'bg-emerald-600 text-white' : (cur ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-500'));
            dot.innerHTML = done ? '<i class="fa-solid fa-check"></i>' : String(i);
            var lbl = $('imp-lbl-' + i);
            if (lbl) { lbl.className = 'hidden text-xs font-semibold sm:inline ' + (cur ? 'text-slate-800' : 'text-slate-400'); }
        }
        window.onbeforeunload = n === 3 ? function () { return 'The import is still running.'; } : null;
    }
    function showErr(msg) {
        var e = $('imp-err');
        e.textContent = msg;
        e.classList.toggle('hidden', !msg);
    }

    // ------------------------------------------------------------------ CSV parse
    function parseCSV(text, delim) {
        if (text.charCodeAt(0) === 0xFEFF) { text = text.slice(1); }
        var rows = [], row = [], field = '', inQ = false, i = 0, n = text.length, c;
        while (i < n) {
            c = text.charAt(i);
            if (inQ) {
                if (c === '"') {
                    if (text.charAt(i + 1) === '"') { field += '"'; i += 2; continue; }
                    inQ = false; i++; continue;
                }
                field += c; i++; continue;
            }
            if (c === '"') { inQ = true; i++; continue; }
            if (c === delim) { row.push(field); field = ''; i++; continue; }
            if (c === '\r') { i++; continue; }
            if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; i++; continue; }
            field += c; i++;
        }
        if (field !== '' || row.length) { row.push(field); rows.push(row); }
        return rows;
    }
    function detectDelim(text) {
        var first = text.split(/\r?\n/)[0] || '';
        var best = ',', bestN = -1;
        [',', ';', '\t'].forEach(function (d) {
            var n = first.split(d).length - 1;
            if (n > bestN) { best = d; bestN = n; }
        });
        return best;
    }

    function readFile(file) {
        showErr('');
        if (!file) { return; }
        if (!/\.(csv|txt)$/i.test(file.name)) { showErr('Please choose a .csv file. In Excel: File > Save As > CSV UTF-8.'); return; }
        if (file.size > 2 * 1024 * 1024) { showErr('The file is bigger than 2 MB.'); return; }
        var fr = new FileReader();
        fr.onload = function () { loadText(String(fr.result), file.name); };
        fr.onerror = function () { showErr('Could not read the file.'); };
        fr.readAsText(file, 'UTF-8');
    }

    function loadText(text, name) {
        var recs = parseCSV(text, detectDelim(text));
        if (!recs.length) { showErr('The file is empty.'); return; }
        var map = {};
        recs[0].forEach(function (h, idx) {
            var k = String(h).replace(/^\uFEFF/, '').trim().toLowerCase().replace(/\s+/g, '_');
            if (k === 'company_name') { k = 'company'; }
            if (k === 'branch_name') { k = 'branch'; }
            if (FIELDS.indexOf(k) >= 0 && map[k] === undefined) { map[k] = idx; }
        });
        if (map.branch === undefined || map.outlet_description === undefined) {
            showErr('The file needs at least the "branch" and "outlet_description" columns (and "company"). Download the template to see the format.');
            return;
        }
        var rows = [], id = 1;
        for (var i = 1; i < recs.length; i++) {
            var rec = recs[i];
            var empty = rec.every(function (v) { return String(v).trim() === ''; });
            if (empty) { continue; }
            if (rows.length >= CFG.maxRows) {
                showErr('Only the first ' + CFG.maxRows.toLocaleString() + ' lines are loaded (limit per file). Split the rest into another file.');
                break;
            }
            var data = {};
            FIELDS.forEach(function (f) { data[f] = map[f] === undefined ? '' : String(rec[map[f]] == null ? '' : rec[map[f]]).trim(); });
            rows.push({ id: id++, line: i + 1, data: data, sv: 'pending', reason: '', dupLine: 0 });
        }
        if (!rows.length) { showErr('The file has no data rows.'); return; }

        state = fresh();
        state.rows = rows;
        state.file = name;
        $('imp-file-name').textContent = name;
        setStep(2);
        renderTable();
        renderSummary();
        runChecks(rows);
    }

    // -------------------------------------------------------------------- server
    function post(url, body) {
        var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        headers[CFG.csrfHeader] = token;
        return fetch(url, { method: 'POST', headers: headers, body: JSON.stringify(body), credentials: 'same-origin', loading: false }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (j) {
                if (j && j.csrf) { token = j.csrf; }
                if (!res.ok || !j || !j.ok) {
                    throw new Error((j && j.error) || ('Server error (' + res.status + '). Refresh the page and try again.'));
                }
                return j;
            });
        });
    }
    function payload(r) {
        var o = { line: r.id };
        FIELDS.forEach(function (f) { o[f] = r.data[f]; });
        return o;
    }
    function applyCheck(part, j) {
        var by = {};
        (j.results || []).forEach(function (x) { by[x.line] = x; });
        part.forEach(function (r) {
            var x = by[r.id];
            if (!x) { r.sv = 'skipped'; r.reason = 'No answer from the server for this line.'; return; }
            r.sv = x.status === 'ready' ? 'ready' : 'skipped';
            r.reason = x.reason || '';
        });
    }

    // Check rows on the server in chunks (nothing is saved)
    function runChecks(list) {
        list.forEach(function (r) { r.sv = 'checking'; });
        state.checking += list.length;
        refreshRows(); renderSummary();
        var idx = 0;
        function next() {
            if (idx >= list.length) { return Promise.resolve(); }
            var part = list.slice(idx, idx + CHECK_CHUNK);
            idx += CHECK_CHUNK;
            return post(CFG.checkUrl, { rows: part.map(payload) }).then(function (j) {
                applyCheck(part, j);
            }).catch(function (e) {
                part.forEach(function (r) { r.sv = 'skipped'; r.reason = 'Could not check this line: ' + e.message; });
            }).then(function () {
                state.checking -= part.length;
                markDup(); refreshRows(); renderSummary();
                return next();
            });
        }
        return next();
    }
    function queueCheck(r) {
        state.queue[r.id] = true;
        clearTimeout(state.checkTimer);
        state.checkTimer = setTimeout(function () {
            var ids = Object.keys(state.queue);
            state.queue = {};
            var list = state.rows.filter(function (x) { return ids.indexOf(String(x.id)) >= 0 && x.sv === 'pending'; });
            if (list.length) { runChecks(list); }
        }, 500);
    }

    // ---------------------------------------------------------------- review table
    function badge(s) {
        var m = {
            ready: ['Ready', 'bg-sky-50 text-sky-700 border border-sky-200', 'fa-circle-check'],
            failed: ['Failed', 'bg-rose-50 text-rose-700 border border-rose-200', 'fa-circle-xmark'],
            checking: ['Checking…', 'bg-slate-100 text-slate-500 border border-slate-200', 'fa-spinner fa-spin'],
            imported: ['Imported', 'bg-emerald-50 text-emerald-700 border border-emerald-200', 'fa-circle-check']
        }[s];
        return '<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ' + m[1] + '"><i class="fa-solid ' + m[2] + '"></i> ' + m[0] + '</span>';
    }
    function inputCls(bad) {
        return 'w-full rounded border px-1.5 py-1 text-xs text-slate-800 focus:border-emerald-600 focus:bg-white focus:outline-none ' +
            (bad ? 'border-rose-400 bg-rose-50' : 'border-transparent bg-transparent hover:border-slate-300');
    }
    function rowHTML(r) {
        var s = st(r), w = why(r);
        var h = '<tr data-id="' + r.id + '" class="' + (s === 'failed' ? 'bg-rose-50/40' : '') + '">';
        h += '<td class="px-2 py-1 font-mono text-[11px] text-slate-400">' + r.line + '</td>';
        h += '<td class="px-2 py-1 whitespace-nowrap" data-cell="badge">' + badge(s) + '</td>';
        h += '<td class="px-2 py-1 text-[11px] text-rose-700" style="min-width:200px" data-cell="why">' + esc(w) + '</td>';
        COLS.forEach(function (c) {
            var bad = s === 'failed' && fieldBad(w, c[0]);
            h += '<td class="px-1 py-1"><input data-f="' + c[0] + '" ' + (c[3] ? 'list="' + c[3] + '" ' : '') + 'value="' + esc(r.data[c[0]]) + '" style="min-width:' + c[2] + 'px" class="' + inputCls(bad) + '" aria-label="' + c[1] + ' of line ' + r.line + '"></td>';
        });
        h += '<td class="px-2 py-1 text-right"><button type="button" data-act="remove" class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Remove line ' + r.line + '"><i class="fa-solid fa-xmark"></i></button></td></tr>';
        return h;
    }
    function visibleRows() {
        var q = state.q.toLowerCase();
        return state.rows.filter(function (r) {
            var s = st(r);
            if (state.filter === 'ready' && s !== 'ready') { return false; }
            if (state.filter === 'failed' && s !== 'failed') { return false; }
            if (q) {
                var hay = FIELDS.map(function (f) { return r.data[f]; }).join(' ') + ' ' + why(r);
                if (hay.toLowerCase().indexOf(q) < 0) { return false; }
            }
            return true;
        });
    }
    function renderTable() {
        var list = visibleRows();
        var pages = Math.max(1, Math.ceil(list.length / PAGE));
        if (state.page > pages) { state.page = pages; }
        var slice = list.slice((state.page - 1) * PAGE, state.page * PAGE);
        $('imp-rows').innerHTML = slice.length ? slice.map(rowHTML).join('') :
            '<tr><td colspan="14" class="px-3 py-10 text-center text-xs text-slate-400">No lines to show.</td></tr>';
        $('imp-pager-info').textContent = list.length ? ((state.page - 1) * PAGE + 1) + '–' + Math.min(state.page * PAGE, list.length) + ' of ' + list.length.toLocaleString() : '0 lines';
        $('imp-page').textContent = 'Page ' + state.page + ' / ' + pages;
        $('imp-prev').disabled = state.page <= 1;
        $('imp-next').disabled = state.page >= pages;
    }
    function rowById(id) {
        for (var i = 0; i < state.rows.length; i++) { if (state.rows[i].id === id) { return state.rows[i]; } }
        return null;
    }
    // update status cells in place (keeps the cursor where the user is typing)
    function refreshRows() {
        var body = $('imp-rows');
        if (!body) { return; }
        body.querySelectorAll('tr[data-id]').forEach(function (tr) {
            var r = rowById(parseInt(tr.getAttribute('data-id'), 10));
            if (!r) { return; }
            var s = st(r), w = why(r);
            tr.className = s === 'failed' ? 'bg-rose-50/40' : '';
            tr.querySelector('[data-cell="badge"]').innerHTML = badge(s);
            tr.querySelector('[data-cell="why"]').textContent = w;
            tr.querySelectorAll('input[data-f]').forEach(function (inp) {
                inp.className = inputCls(s === 'failed' && fieldBad(w, inp.getAttribute('data-f')));
            });
        });
    }
    function renderSummary() {
        var c = counts();
        $('imp-c-all').textContent = c.all.toLocaleString();
        $('imp-c-ready').textContent = c.ready.toLocaleString();
        $('imp-c-failed').textContent = c.failed.toLocaleString();
        var chk = $('imp-checking');
        chk.classList.toggle('hidden', c.checking === 0);
        chk.textContent = 'Checking ' + c.checking.toLocaleString() + ' line' + (c.checking === 1 ? '' : 's') + '…';
        var btn = $('imp-go');
        btn.disabled = c.ready === 0 || c.checking > 0;
        $('imp-go-n').textContent = c.ready.toLocaleString();
        $('imp-rm-failed').classList.toggle('hidden', c.failed === 0);
        ['all', 'ready', 'failed'].forEach(function (f) {
            var b = $('imp-f-' + f);
            b.className = 'rounded-full border px-3 py-1 text-xs font-semibold ' + (state.filter === f ? 'border-slate-300 bg-slate-100 text-slate-800' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50');
        });
    }

    // ---------------------------------------------------------------- importing
    function fmt(ms) {
        var s = Math.max(0, Math.round(ms / 1000)), m = Math.floor(s / 60);
        s = s % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }
    function runImport() {
        var todo = state.rows.filter(function (r) { return st(r) === 'ready'; });
        if (!todo.length) { return; }
        setStep(3);
        state.cancel = false;
        state.started = Date.now();
        var total = todo.length, done = 0, ok = 0, bad = 0, idx = 0, fatal = '';

        function paint() {
            var pct = Math.round(done / total * 100);
            $('imp-bar').style.width = pct + '%';
            $('imp-pct').textContent = pct + '%';
            $('imp-done').textContent = done.toLocaleString();
            $('imp-total').textContent = total.toLocaleString();
            $('imp-ok').textContent = ok.toLocaleString();
            $('imp-bad').textContent = bad.toLocaleString();
            var el = Date.now() - state.started;
            $('imp-elapsed').textContent = fmt(el);
            $('imp-eta').textContent = done > 0 && done < total ? fmt(el / done * (total - done)) : (done >= total ? '0:00' : 'calculating…');
        }
        paint();
        state.timer = setInterval(paint, 500);
        $('imp-cancel').disabled = false;
        $('imp-cancel').textContent = 'Stop';

        function next() {
            if (idx >= total || state.cancel || fatal) { return Promise.resolve(); }
            var part = todo.slice(idx, idx + COMMIT_CHUNK);
            idx += COMMIT_CHUNK;
            return post(CFG.commitUrl, { rows: part.map(payload) }).then(function (j) {
                var by = {};
                (j.results || []).forEach(function (x) { by[x.line] = x; });
                part.forEach(function (r) {
                    var x = by[r.id];
                    if (x && x.status === 'imported') { r.sv = 'imported'; r.reason = ''; ok++; }
                    else { r.sv = 'skipped'; r.reason = x ? (x.reason || 'Not saved.') : 'No answer from the server for this line.'; bad++; }
                });
            }).catch(function (e) {
                part.forEach(function (r) { r.sv = 'skipped'; r.reason = 'Request failed: ' + e.message; bad++; });
                if (/expired|40[13]|Refresh the page/i.test(e.message)) { fatal = e.message; }
            }).then(function () {
                done += part.length;
                paint();
                return next();
            });
        }

        next().then(function () {
            clearInterval(state.timer);
            // lines that were never sent (stopped, or a session problem)
            todo.slice(idx).forEach(function (r) {
                r.sv = 'skipped';
                r.reason = fatal ? 'Not processed: ' + fatal : 'Not processed: the import was stopped.';
                bad++;
            });
            state.took = Date.now() - state.started;
            markDup();
            setStep(4);
            renderReport();
        });
    }

    // -------------------------------------------------------------------- report
    function fin(r) { return r.sv === 'imported' ? 'imported' : 'failed'; }
    function renderReport() {
        var ok = 0, bad = 0;
        state.rows.forEach(function (r) { if (fin(r) === 'imported') { ok++; } else { bad++; } });
        $('rep-total').textContent = state.rows.length.toLocaleString();
        $('rep-ok').textContent = ok.toLocaleString();
        $('rep-bad').textContent = bad.toLocaleString();
        $('rep-time').textContent = fmt(state.took);
        $('rep-removed').textContent = state.removed ? state.removed.toLocaleString() + ' line(s) were removed by you before importing.' : '';
        $('rep-dl-failed').disabled = bad === 0;
        $('rep-title').textContent = bad === 0 ? 'All lines were imported' : (ok === 0 ? 'Nothing was imported' : 'Import finished with some failed lines');
        $('rep-icon').className = 'flex h-11 w-11 items-center justify-center rounded-full text-lg ' + (bad === 0 ? 'bg-emerald-100 text-emerald-600' : (ok === 0 ? 'bg-rose-100 text-rose-600' : 'bg-amber-100 text-amber-600'));
        $('rep-icon').innerHTML = '<i class="fa-solid ' + (bad === 0 ? 'fa-circle-check' : (ok === 0 ? 'fa-circle-xmark' : 'fa-triangle-exclamation')) + '"></i>';
        if (bad === 0 && state.rfilter === 'failed') { state.rfilter = 'all'; }
        renderReportTable();
    }
    function renderReportTable() {
        var list = state.rows.filter(function (r) { return state.rfilter === 'all' || fin(r) === state.rfilter; });
        var shown = list.slice(0, REPORT_SHOW);
        $('rep-rows').innerHTML = shown.length ? shown.map(function (r) {
            var f = fin(r);
            return '<tr class="' + (f === 'failed' ? 'bg-rose-50/40' : '') + '">' +
                '<td class="px-3 py-2 font-mono text-slate-500">' + r.line + '</td>' +
                '<td class="px-3 py-2 whitespace-nowrap">' + badge(f) + '</td>' +
                '<td class="px-3 py-2 text-slate-700">' + esc(r.data.company) + '</td>' +
                '<td class="px-3 py-2 text-slate-700">' + esc(r.data.branch) + '</td>' +
                '<td class="px-3 py-2 font-mono text-[11px] text-slate-600">' + esc(r.data.store_code) + '</td>' +
                '<td class="px-3 py-2 text-slate-800">' + esc(r.data.outlet_description) + '</td>' +
                '<td class="px-3 py-2 text-rose-700">' + esc(f === 'failed' ? (why(r) || r.reason) : '') + '</td></tr>';
        }).join('') : '<tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">Nothing to show.</td></tr>';
        $('rep-more').textContent = list.length > REPORT_SHOW ? 'Showing the first ' + REPORT_SHOW + ' of ' + list.length.toLocaleString() + ' lines. Download the report to see all of them.' : '';
        ['all', 'imported', 'failed'].forEach(function (f) {
            $('rep-f-' + f).className = 'rounded-full border px-3 py-1 text-xs font-semibold ' + (state.rfilter === f ? 'border-slate-300 bg-slate-100 text-slate-800' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50');
        });
    }
    function csvCell(v) {
        v = v == null ? '' : String(v);
        return /[",\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    }
    function download(name, rows) {
        var text = '\uFEFF' + rows.map(function (r) { return r.map(csvCell).join(','); }).join('\r\n');
        var blob = new Blob([text], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }
    function stamp() {
        var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '_' + p(d.getHours()) + p(d.getMinutes());
    }
    function downloadFull() {
        var rows = [['line', 'result', 'reason'].concat(FIELDS)];
        state.rows.forEach(function (r) {
            var f = fin(r);
            rows.push([r.line, f === 'imported' ? 'IMPORTED' : 'FAILED', f === 'failed' ? (why(r) || r.reason) : ''].concat(FIELDS.map(function (k) { return r.data[k]; })));
        });
        download('store_import_report_' + stamp() + '.csv', rows);
    }
    // failed lines in the import-template format (+ reason) so they can be fixed and imported again
    function downloadFailed() {
        var rows = [FIELDS.concat(['reason'])];
        state.rows.forEach(function (r) {
            if (fin(r) === 'failed') { rows.push(FIELDS.map(function (k) { return r.data[k]; }).concat([why(r) || r.reason])); }
        });
        download('store_import_FAILED_' + stamp() + '.csv', rows);
    }

    // ---------------------------------------------------------------- wiring
    function resetAll() {
        clearInterval(state.timer);
        clearTimeout(state.checkTimer);
        var imported = state.rows.some(function (r) { return r.sv === 'imported'; });
        if (state.step === 4 && imported) { window.location.reload(); return; }
        state = fresh();
        showErr('');
        $('imp-file').value = '';
        setStep(1);
    }
    window.__sdCanClose = function () {
        if (state.step === 3) { return false; }
        if (state.step === 2 && state.rows.length) { return window.confirm('Close and discard this list? Nothing has been saved yet.'); }
        return true;
    };
    window.__sdReset = resetAll;

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') { init(); }
    var started = false;
    function init() {
        if (started || !$('imp-file')) { return; }
        started = true;

        var drop = $('imp-drop');
        $('imp-file').addEventListener('change', function (e) { readFile(e.target.files[0]); });
        ['dragenter', 'dragover'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-emerald-500', 'bg-emerald-50/40'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-emerald-500', 'bg-emerald-50/40'); });
        });
        drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) { readFile(e.dataTransfer.files[0]); } });

        // edit a cell
        $('imp-rows').addEventListener('change', function (e) {
            var inp = e.target;
            if (!inp.getAttribute || !inp.getAttribute('data-f')) { return; }
            var tr = inp.closest('tr');
            var r = rowById(parseInt(tr.getAttribute('data-id'), 10));
            if (!r) { return; }
            r.data[inp.getAttribute('data-f')] = inp.value.trim();
            r.sv = 'pending';
            r.reason = '';
            markDup(); refreshRows(); renderSummary();
            queueCheck(r);
        });
        // remove a line
        $('imp-rows').addEventListener('click', function (e) {
            var b = e.target.closest('[data-act="remove"]');
            if (!b) { return; }
            var id = parseInt(b.closest('tr').getAttribute('data-id'), 10);
            state.rows = state.rows.filter(function (r) { return r.id !== id; });
            state.removed++;
            markDup(); renderTable(); renderSummary();
        });
        $('imp-rm-failed').addEventListener('click', function () {
            var n = counts().failed;
            if (!n || !window.confirm('Remove ' + n + ' failed line(s) from this list?')) { return; }
            state.rows = state.rows.filter(function (r) { return st(r) !== 'failed'; });
            state.removed += n;
            markDup(); renderTable(); renderSummary();
        });
        ['all', 'ready', 'failed'].forEach(function (f) {
            $('imp-f-' + f).addEventListener('click', function () { state.filter = f; state.page = 1; renderTable(); renderSummary(); });
        });
        var qt;
        $('imp-q').addEventListener('input', function (e) {
            var v = e.target.value;
            clearTimeout(qt);
            qt = setTimeout(function () { state.q = v.trim(); state.page = 1; renderTable(); }, 250);
        });
        $('imp-prev').addEventListener('click', function () { state.page--; renderTable(); });
        $('imp-next').addEventListener('click', function () { state.page++; renderTable(); });
        $('imp-back').addEventListener('click', function () {
            if (state.rows.length && !window.confirm('Go back and discard this list?')) { return; }
            state = fresh(); showErr(''); $('imp-file').value = ''; setStep(1);
        });
        $('imp-go').addEventListener('click', runImport);
        $('imp-cancel').addEventListener('click', function () {
            state.cancel = true;
            this.disabled = true;
            this.textContent = 'Stopping…';
        });
        ['all', 'imported', 'failed'].forEach(function (f) {
            $('rep-f-' + f).addEventListener('click', function () { state.rfilter = f; renderReportTable(); });
        });
        $('rep-dl-full').addEventListener('click', downloadFull);
        $('rep-dl-failed').addEventListener('click', downloadFailed);
        $('rep-done').addEventListener('click', function () { window.location.reload(); });
        $('imp-x').addEventListener('click', function () {
            if (window.__sdCanClose()) { window.__sdReset(); var m = $('modal-import'); m.classList.add('hidden'); m.classList.remove('flex'); }
        });
        setStep(1);
    }
})();

</script>
<?php endif; ?>

<script>
(function () {
    var base = <?= json_encode(rtrim(base_url('store-details'), '/')) ?>;
    function open(id)  { var m = document.getElementById(id); if (m) { m.classList.remove('hidden'); m.classList.add('flex'); var f = m.querySelector('input:not([type=hidden]),select,textarea'); if (f) { f.focus(); } } }
    function closeAll() {
        var im = document.getElementById('modal-import');
        var importOpen = im && ! im.classList.contains('hidden');
        if (importOpen && window.__sdCanClose && ! window.__sdCanClose()) { return; }
        document.querySelectorAll('[id^="modal-"]').forEach(function (m) { m.classList.add('hidden'); m.classList.remove('flex'); });
        if (importOpen && window.__sdReset) { window.__sdReset(); }
    }

    document.addEventListener('click', function (e) {
        var o = e.target.closest('[data-open]');
        if (o) {
            var id = o.getAttribute('data-open');
            if (id === 'modal-store') {
                var form = document.getElementById('store-form'), title = document.getElementById('store-title');
                form.reset();
                if (o.getAttribute('data-mode') === 'edit') {
                    var r = JSON.parse(o.getAttribute('data-row'));
                    title.textContent = 'Edit Store';
                    form.action = base + '/' + r.id + '/update';
                    form.elements['outlet_description'].value = r.outlet_description || '';
                    form.elements['branch_id'].value = r.branch_id || '';
                    form.elements['store_status'].value = r.store_status || '';
                    form.elements['store_code'].value = r.store_code || '';
                    form.elements['erp_code'].value = r.erp_code || '';
                    form.elements['ownership_type'].value = r.ownership_type || '';
                    form.elements['date_opened'].value = r.date_opened || '';
                    form.elements['last_pos_selling_date'].value = r.last_pos_selling_date || '';
                    form.elements['address'].value = r.address || '';
                } else {
                    title.textContent = 'Add Store';
                    form.action = base + '/create';
                }
            }
            if (id === 'modal-delete') {
                document.getElementById('delete-form').action = base + '/' + o.getAttribute('data-id') + '/delete';
                document.getElementById('del-name').textContent = o.getAttribute('data-name');
            }
            open(id);
            return;
        }
        if (e.target.closest('[data-close]') || (e.target.id && e.target.id.indexOf('modal-') === 0)) { closeAll(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); } });

})();
</script>
<?php
/* "Sent for approval" popup: shown once after an action that needs the administrator's approval */
$approvalSent = session()->getFlashdata('approval_sent');
if (is_array($approvalSent)):
    $apVerb = ['create' => 'add', 'update' => 'update', 'delete' => 'delete'][$approvalSent['action'] ?? ''] ?? 'change';
    ?>
<dialog id="modal-approval-sent" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-900/50" aria-labelledby="approval-sent-title">
    <div class="p-6 text-center">
        <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-600"><i class="fa-regular fa-clock"></i></span>
        <h2 id="approval-sent-title" class="text-lg font-bold text-slate-800">Sent for approval</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Your request to <?= esc($apVerb) ?> <strong class="text-slate-800">“<?= esc($approvalSent['name'] ?? '') ?>”</strong> needs an administrator's approval.
            <strong>Nothing has changed yet</strong> — it will take effect after it is approved.
        </p>
        <dl class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 text-left text-xs">
            <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Reference</dt><dd class="font-mono font-semibold text-slate-800"><?= esc($approvalSent['ref'] ?? '') ?></dd></div>
            <?php if (! empty($approvalSent['approver'])): ?>
                <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Waiting for</dt><dd class="font-semibold text-slate-800"><?= esc($approvalSent['approver']) ?></dd></div>
            <?php endif; ?>
            <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Status</dt><dd><span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold text-amber-700">Pending approval</span></dd></div>
        </dl>
        <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
            <?php if (! empty($canQueue)): ?>
                <a href="<?= base_url('approval-queue?status=all&q=' . rawurlencode((string) ($approvalSent['ref'] ?? ''))) ?>" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">View in Approval Queue</a>
            <?php endif; ?>
            <button type="button" id="approval-sent-ok" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Got it</button>
        </div>
    </div>
</dialog>
<script>
(function () {
    var d = document.getElementById('modal-approval-sent');
    if (!d) { return; }
    if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); }
    var ok = document.getElementById('approval-sent-ok');
    if (ok) { ok.addEventListener('click', function () { d.close(); }); ok.focus(); }
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>