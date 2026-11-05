<?php

namespace App\Libraries;

/**
 * Statuses of a module (table module_statuses, managed in Module Settings > Statuses).
 *
 * A module with NO statuses defined is not restricted (any text is accepted), so nothing breaks before the table is filled.
 */
class ModuleStatuses
{
    /** Colours the tab offers (the view maps each one to fixed Tailwind classes). */
    public const COLORS = ['emerald', 'amber', 'rose', 'sky', 'violet', 'orange', 'teal', 'slate'];

    /** @return list<array{id:int,name:string,color:string,sort_order:int,is_active:int}> */
    public static function all(string $slug, bool $activeOnly = false): array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('module_statuses')) {
            return [];
        }
        $b = $db->table('module_statuses')->where('module_slug', $slug)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'name' => (string) $r['name'], 'color' => (string) $r['color'],
            'sort_order' => (int) $r['sort_order'], 'is_active' => (int) $r['is_active'],
        ], $b->get()->getResultArray());
    }

    /** @return list<string> active status names in order */
    public static function names(string $slug): array
    {
        return array_column(self::all($slug, true), 'name');
    }

    /** lowercase name => colour, for every status (also the disabled ones, so old stores keep their colour) */
    public static function colorMap(string $slug): array
    {
        $out = [];
        foreach (self::all($slug) as $s) {
            $out[strtolower(self::squash($s['name']))] = $s['color'];
        }

        return $out;
    }

    /**
     * The exact spelling of a status ("open " -> "OPEN"), or null when it is not an allowed status.
     * A module without any active status accepts everything (returned as typed).
     */
    public static function canonical(string $slug, string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $names = self::names($slug);
        if (! $names) {
            return $text;
        }
        $k = strtolower(self::squash($text));
        foreach ($names as $n) {
            if (strtolower(self::squash($n)) === $k) {
                return $n;
            }
        }

        return null;
    }

    /**
     * Save the Statuses tab.
     *
     * @param array<int,array<string,mixed>> $existing id => [name,color,sort,active]
     * @param list<array<string,mixed>>      $new      [name,color,sort]
     * @param callable|null                  $onRename function(string $old, string $new): void
     * @return string|null error message
     */
    public static function save(string $slug, array $existing, array $new, ?callable $onRename = null): ?string
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $cur = [];
        foreach ($db->table('module_statuses')->where('module_slug', $slug)->get()->getResultArray() as $r) {
            $cur[(int) $r['id']] = $r;
        }

        $names = [];
        $ops   = [];
        foreach ($existing as $id => $f) {
            $id = (int) $id;
            if (! isset($cur[$id])) {
                continue;
            }
            $name = mb_substr(trim((string) ($f['name'] ?? '')), 0, 50);
            if ($name === '') {
                return 'A status name cannot be empty.';
            }
            if (isset($names[strtolower($name)])) {
                return 'The status "' . $name . '" is written twice.';
            }
            $names[strtolower($name)] = true;
            $ops[] = ['id' => $id, 'name' => $name, 'color' => self::color($f['color'] ?? ''), 'sort' => (int) ($f['sort'] ?? 0), 'active' => ! empty($f['active']) ? 1 : 0];
        }
        $adds = [];
        foreach ($new as $f) {
            $name = mb_substr(trim((string) ($f['name'] ?? '')), 0, 50);
            if ($name === '') {
                continue;
            }
            if (isset($names[strtolower($name)])) {
                return 'The status "' . $name . '" is written twice.';
            }
            $names[strtolower($name)] = true;
            $adds[] = ['name' => $name, 'color' => self::color($f['color'] ?? ''), 'sort' => (int) ($f['sort'] ?? 0)];
        }
        if (! array_filter($ops, static fn ($o) => $o['active']) && ! $adds) {
            return 'Keep at least one active status.';
        }

        $db->transBegin();
        try {
            foreach ($ops as $o) {
                $old = (string) $cur[$o['id']]['name'];
                $db->table('module_statuses')->where('id', $o['id'])->update(['name' => $o['name'], 'color' => $o['color'], 'sort_order' => $o['sort'], 'is_active' => $o['active'], 'updated_at' => $now]);
                if ($old !== $o['name'] && $onRename) {
                    $onRename($old, $o['name']);
                }
            }
            foreach ($adds as $a) {
                $db->table('module_statuses')->insert(['module_slug' => $slug, 'name' => $a['name'], 'color' => $a['color'], 'sort_order' => $a['sort'], 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($db->transStatus() === false) {
                $db->transRollback();

                return 'The statuses could not be saved.';
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'ModuleStatuses::save failed: {m}', ['m' => $e->getMessage()]);

            return 'The statuses could not be saved.';
        }

        return null;
    }

    private static function color($c): string
    {
        $c = strtolower(trim((string) $c));

        return in_array($c, self::COLORS, true) ? $c : 'slate';
    }

    private static function squash(string $v): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($v));
    }
}
