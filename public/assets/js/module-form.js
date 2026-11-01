/**
 * Module form behaviour (Module Settings: Add / Edit).
 * Works on every [data-module-form] block: slug auto-fill, type toggle,
 * icon picker + upload preview, badge chips.
 */
(function () {
    'use strict';

    function slugify(s) {
        return s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    }
    function faClass(v) {
        v = (v || '').trim();
        if (!v) { return 'fa-solid fa-cube'; }
        return /\bfa-(solid|regular|brands)\b/.test(v) ? v : 'fa-solid ' + v;
    }

    document.querySelectorAll('[data-module-form]').forEach(function (root) {
        var form = root.closest('form');
        var $ = function (sel) { return root.querySelector(sel); };

        var name = $('[data-name]'), slug = $('[data-slug]'), url = $('[data-url]'), type = $('[data-type]');
        var iconInput = $('[data-icon-input]'), iconI = $('[data-icon-i]'), iconImg = $('[data-icon-img]');
        var iconFile = $('[data-icon-file]'), fileLabel = $('[data-file-label]'), iconErr = $('[data-icon-error]');
        var badgeInput = $('[data-badge-input]');
        var startImg = iconImg.getAttribute('src') || '';
        var blobUrl = null, slugTouched = false;

        /* Module type: category containers have no URL / parent */
        function syncType() {
            var isCat = type.value === '1';
            root.querySelectorAll('[data-when-module]').forEach(function (el) { el.classList.toggle('hidden', isCat); });
            if (url) { url.required = !isCat; }
        }
        type.addEventListener('change', syncType);

        /* Slug (create only): follows the name until the user edits it */
        if (slug) {
            slug.addEventListener('input', function () { slugTouched = slug.value !== ''; });
            name.addEventListener('input', function () {
                if (!slugTouched) {
                    slug.value = slugify(name.value);
                    if (url && !url.dataset.touched) { url.value = slug.value.replace(/_/g, '-'); }
                }
            });
            if (url) { url.addEventListener('input', function () { url.dataset.touched = '1'; }); }
        }

        /* Icon preview */
        function showClass(v) {
            iconImg.classList.add('hidden');
            iconI.classList.remove('hidden');
            iconI.className = faClass(v);
        }
        function showImage(src) {
            iconI.classList.add('hidden');
            iconImg.src = src;
            iconImg.classList.remove('hidden');
        }
        function clearFile() {
            if (blobUrl) { URL.revokeObjectURL(blobUrl); blobUrl = null; }
            iconFile.value = '';
            fileLabel.textContent = 'Choose image';
            iconErr.classList.add('hidden');
        }
        iconInput.addEventListener('input', function () {
            if (iconInput.value.trim() !== '') { clearFile(); showClass(iconInput.value); }
            else if (startImg) { showImage(startImg); }
            else { showClass(''); }
        });
        root.querySelectorAll('[data-icon]').forEach(function (b) {
            b.addEventListener('click', function () {
                clearFile();
                iconInput.value = b.dataset.icon;
                showClass(b.dataset.icon);
            });
        });
        var search = $('[data-icon-search]');
        if (search) {
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                root.querySelectorAll('[data-icon]').forEach(function (b) {
                    b.classList.toggle('hidden', q !== '' && b.dataset.label.indexOf(q) === -1 && b.dataset.icon.indexOf(q) === -1);
                });
            });
            search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        }
        iconFile.addEventListener('change', function () {
            var f = iconFile.files && iconFile.files[0];
            iconErr.classList.add('hidden');
            if (!f) { return; }
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) {
                iconErr.textContent = 'Please choose a JPG, PNG or WEBP image.';
            } else if (f.size > 2 * 1024 * 1024) {
                iconErr.textContent = 'Image is too large (max 2 MB).';
            } else {
                if (blobUrl) { URL.revokeObjectURL(blobUrl); }
                blobUrl = URL.createObjectURL(f);
                iconInput.value = '';
                showImage(blobUrl);
                fileLabel.textContent = f.name.length > 22 ? f.name.slice(0, 19) + '…' : f.name;
                return;
            }
            iconFile.value = '';
            iconErr.classList.remove('hidden');
        });

        /* Badge chips */
        root.querySelectorAll('[data-badge]').forEach(function (b) {
            b.addEventListener('click', function () { badgeInput.value = b.dataset.badge; badgeInput.focus(); });
        });

        /* Form reset (modal closed / Cancel) */
        if (form) {
            form.addEventListener('reset', function () {
                setTimeout(function () {
                    slugTouched = false;
                    if (url) { delete url.dataset.touched; }
                    clearFile();
                    if (iconInput.value.trim() !== '') { showClass(iconInput.value); }
                    else if (startImg) { showImage(startImg); }
                    else { showClass(''); }
                    syncType();
                }, 0);
            });
        }

        syncType();
    });
})();