<?php

/**
 * Module icon helpers (rbac_modules.icon).
 * The icon column holds either a FontAwesome class ("fa-chart-line") or an uploaded image path ("uploads/modules/x.png").
 * Load with:  helper('module');
 */

if (! function_exists('module_icon_is_image')) {
    function module_icon_is_image(?string $icon): bool
    {
        $icon = (string) $icon;

        return str_starts_with($icon, 'uploads/') && ! str_contains($icon, '..');
    }
}

if (! function_exists('module_icon_class')) {
    /** "fa-cube" => "fa-solid fa-cube"; keeps fa-regular / fa-brands when given. */
    function module_icon_class(?string $icon): string
    {
        $i = trim((string) preg_replace('/[^a-z0-9\- ]/i', '', (string) $icon));
        if ($i === '') {
            return 'fa-solid fa-cube';
        }

        return preg_match('/\bfa-(solid|regular|brands)\b/', $i) ? $i : 'fa-solid ' . $i;
    }
}

if (! function_exists('module_icon_html')) {
    /** Ready-to-print <i> or <img> for a module icon. */
    function module_icon_html(?string $icon, string $imgClass = 'h-4 w-4', string $extraClass = ''): string
    {
        if (module_icon_is_image($icon)) {
            return '<img src="' . esc(base_url((string) $icon), 'attr') . '" alt="" class="' . esc($imgClass, 'attr') . ' object-contain">';
        }

        return '<i class="' . esc(module_icon_class($icon) . ' ' . $extraClass, 'attr') . '" aria-hidden="true"></i>';
    }
}