<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper(['security', 'approval']);
$req = $req ?? [];
?>
<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Request <?= esc($req['reference_no'] ?? '') ?></h1>
            <p class="text-xs text-slate-500"><?= esc($req['action_label'] ?? '') ?></p>
        </div>
        <a href="<?= base_url('approval-queue') ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
            <i class="fa-solid fa-arrow-left"></i> Back to queue
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <?= view('approval_queue/_details', [
            'req'    => $req,
            'steps'  => $steps ?? [],
            'diff'   => $diff ?? [],
            'canAct' => $canAct ?? false,
            'can'    => $can ?? [],
            'logs'   => $logs ?? [],
            'list'   => $list ?? null,
            'mode'   => 'page',
        ], ['saveData' => false]) ?>
    </div>
</div>

<?= $this->include('approval_queue/_actions_js') ?>
<?= $this->endSection() ?>
