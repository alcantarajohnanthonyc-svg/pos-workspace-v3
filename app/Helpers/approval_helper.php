<?php

/**
 * View helpers for the Approval Queue.  Load with:  helper('approval');
 */

if (! function_exists('aq_status_label')) {
    function aq_status_label(string $s): string
    {
        return match (strtolower($s)) {
            'pending'   => 'Pending',
            'approved'  => 'Approved',
            'rejected'  => 'Rejected',
            'cancelled' => 'Cancelled',
            default     => ucfirst($s),
        };
    }
}

if (! function_exists('aq_status_class')) {
    function aq_status_class(string $s): string
    {
        return match (strtolower($s)) {
            'approved'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'rejected'  => 'bg-rose-50 text-rose-700 border-rose-200',
            'cancelled' => 'bg-slate-100 text-slate-500 border-slate-200',
            default     => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }
}

if (! function_exists('aq_priority_class')) {
    function aq_priority_class(string $p): string
    {
        return match (strtolower($p)) {
            'high'  => 'bg-rose-50 text-rose-700 border-rose-200',
            'low'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            default => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }
}

if (! function_exists('aq_initials')) {
    function aq_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $out   = '';
        foreach (array_slice(array_filter($parts), 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }
}

if (! function_exists('aq_person_name')) {
    /** Best readable name of the requester from a row of the list query. */
    function aq_person_name(array $r): string
    {
        $n = trim((string) ($r['requester_name'] ?? ''));
        if ($n !== '') {
            return $n;
        }
        $u = trim((string) ($r['username'] ?? ''));
        if ($u !== '') {
            return $u;
        }
        $e = (string) ($r['email_workspace'] ?? '');

        return $e !== '' ? ucfirst(explode('@', $e)[0]) : 'Unknown user';
    }
}

if (! function_exists('aq_module_name')) {
    function aq_module_name(string $slug, ?string $name): string
    {
        $name = trim((string) $name);

        return $name !== '' ? $name : ucwords(str_replace(['_', '-'], ' ', $slug));
    }
}

if (! function_exists('aq_module_bg')) {
    /** Solid colour of the module chip (same slug = same colour). */
    function aq_module_bg(string $slug): string
    {
        $palette = ['bg-blue-600', 'bg-teal-600', 'bg-violet-600', 'bg-amber-500', 'bg-indigo-600', 'bg-pink-600', 'bg-cyan-600', 'bg-emerald-600'];

        /* md5 based: always a positive number (crc32 is negative on 32-bit PHP), so the index always exists */
        $i = (int) (hexdec(substr(md5($slug), 0, 6)) % count($palette));

        return $palette[$i];
    }
}

if (! function_exists('aq_module_icon')) {
    /** FontAwesome classes from rbac_modules.icon (any format), safe to print in a class attribute. */
    function aq_module_icon(?string $icon): string
    {
        $icon = trim((string) $icon);
        if ($icon === '' || ! preg_match('/^[a-z0-9\- ]+$/i', $icon)) {
            return 'fa-solid fa-cube';
        }
        if (preg_match('/\b(fa-solid|fa-regular|fa-brands|fas|far|fab)\b/i', $icon)) {
            return $icon;
        }
        if (str_starts_with($icon, 'fa-')) {
            return 'fa-solid ' . $icon;
        }

        return 'fa-solid fa-' . $icon;
    }
}

if (! function_exists('aq_when')) {
    /** @return array{0:string,1:string} [date, time] e.g. ["Oct 15, 2026", "10:24 AM"] */
    function aq_when(?string $ts): array
    {
        $t = $ts ? strtotime($ts) : false;

        return $t ? [date('M j, Y', $t), date('g:i A', $t)] : ['—', ''];
    }
}
