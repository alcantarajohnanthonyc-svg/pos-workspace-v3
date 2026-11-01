<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 max-w-lg mx-auto text-center mt-10">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Welcome, <?= esc(session()->get('first_name') ?: session()->get('email_workspace')) ?>!</h1>
        <p class="text-gray-600 mb-6">You have successfully signed in to POS Workspace V3.</p>
        <a href="<?= base_url('auth/logout') ?>" class="inline-block px-6 py-2.5 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">Sign Out</a>
    </div>
<?= $this->endSection() ?>