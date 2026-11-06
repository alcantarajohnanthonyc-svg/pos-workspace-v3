<?php

namespace App\Libraries;

use Throwable;

/**
 * Status lists of every module (tables status_types + status_items, managed in the module "Status / Remarks").
 *
 *   ModuleStatuses::names('store_details', 'store_status')              -> ['OPEN', 'TEMPORARY CLOSED', ...]
 *   ModuleStatuses::canonical('store_details', 'open ', 'store_status') -> 'OPEN'   (null = not an allowed status)
 *   ModuleStatuses::colorMap('inventory', 'phone_status')               -> ['received' => 'emerald', ...]
 *
 * A list with NO active status does not restrict anything (any text is accepted), so nothing breaks before it is filled.
 */
class ModuleStatuses
{
    public const COLORS = ['emerald', 'amber', 'rose', 'sky', 'violet', 'orange', 'teal', 'slate'];

    /**
     * Where a status is stored in a module's own table (so renaming / deleting a status is safe).
     * module_slug => type_key => [table, column]. Add a line when another module starts to use a list.
     */
    private const USAGE = [
        'store_details' => ['store_status' => ['store_details', 'store_status']],
    ];

    // ------------------------------------------------------------ reading

    /** @return list<array<string,mixed>> the lists of a module (or of all modules) with their items */
    public static function types(?string $slug = null): array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('status_types')) {
            return [];
        }
        $b = $db->table('status_types')->orderBy('module_slug', 'ASC')->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC');
        if ($slug !== null) {
            $b->where('module_slug', $slug);
        }
        $types = $b->get()->getResultArray();
        if (! $types) {
            return [];
        }
        $items = [];
        foreach ($db->table('status_items')->whereIn('type_id', array_map('intval', array_column($types, 'id')))->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray() as $i) {
            $items[(int) $i['type_id']][] = $i;
        }
        foreach ($types as &$t) {
            $t['items'] = $items[(int) $t['id']] ?? [];
        }
        unset($t);

        return $types;
    }

    private static function typeId(string $slug, string $typeKey): ?int
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('status_types')) {
            return null;
        }
        $b = $db->table('status_types')->select('id')->where('module_slug', $slug)->where('is_active', 1)->orderBy('sort_order', 'ASC');
        if ($typeKey !== '') {
            $b->where('type_key', $typeKey);
        }
        $r = $b->get(1)->getRowArray();

        return $r ? (int) $r['id'] : null;
    }

    /** @return list<array{id:int,name:string,color:string,sort_order:int,is_active:int}> */
    public static function all(string $slug, bool $activeOnly = false, string $typeKey = ''): array
    {
        $id = self::typeId($slug, $typeKey);
        if ($id === null) {
            return [];
        }
        $b = \Config\Database::connect()->table('status_items')->where('type_id', $id)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'name' => (string) $r['name'], 'color' => (string) $r['color'],
            'sort_order' => (int) $r['sort_order'], 'is_active' => (int) $r['is_active'],
        ], $b->get()->getResultArray());
    }

    /** @return list<string> */
    public static function names(string $slug, string $typeKey = ''): array
    {
        return array_column(self::all($slug, true, $typeKey), 'name');
    }

    /** lowercase name => colour (also the disabled ones, so old records keep their colour) */
    public static function colorMap(string $slug, string $typeKey = ''): array
    {
        $out = [];
        foreach (self::all($slug, false, $typeKey) as $s) {
            $out[strtolower(self::squash($s['name']))] = $s['color'];
        }

        return $out;
    }

    /** The exact spelling of a status ("open " -> "OPEN"), '' for empty, null when it is not an allowed status. */
    public static function canonical(string $slug, string $text, string $typeKey = ''): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $names = self::names($slug, $typeKey);
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

    /** How many records of the module use this status (0 when the module is not registered in USAGE). */
    public static function usage(string $slug, string $typeKey, string $name): int
    {
        $u = self::USAGE[$slug][$typeKey] ?? null;
        if (! $u) {
            return 0;
        }

        return (int) \Config\Database::connect()->table($u[0])->where($u[1], $name)->countAllResults();
    }

    // ------------------------------------------------------------ writing (used by the Status / Remarks page)

    public static function slugify(string $name): string
    {
        $s = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'));

        return $s !== '' ? mb_substr($s, 0, 60) : 'status';
    }

    /**
     * @param  list<string> $firstItems
     * @return int|string  the new list id, or an error message
     */
    public static function createType(string $slug, string $name, string $description, array $firstItems): int|string
    {
        $db   = \Config\Database::connect();
        $name = mb_substr(trim($name), 0, 100);
        if ($name === '') {
            return 'Give the list a name (for example "Store Status" or "Phone Status").';
        }
        $key = self::slugify($name);
        if ($db->table('status_types')->where('module_slug', $slug)->where('type_key', $key)->countAllResults() > 0) {
            return 'This module already has a list called "' . $name . '".';
        }
        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        try {
            $max = (int) ($db->table('status_types')->selectMax('sort_order', 'm')->where('module_slug', $slug)->get()->getRowArray()['m'] ?? 0);
            $db->table('status_types')->insert([
                'module_slug' => $slug, 'type_key' => $key, 'name' => $name,
                'description' => trim($description) !== '' ? mb_substr(trim($description), 0, 255) : null,
                'sort_order' => $max + 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id   = (int) $db->insertID();
            $seen = [];
            foreach ($firstItems as $i => $n) {
                $n = mb_substr(trim((string) $n), 0, 50);
                if ($n === '' || isset($seen[strtolower($n)])) {
                    continue;
                }
                $seen[strtolower($n)] = true;
                $db->table('status_items')->insert(['type_id' => $id, 'name' => $n, 'color' => 'slate', 'sort_order' => $i + 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($db->transStatus() === false) {
                $db->transRollback();

                return 'The list could not be saved.';
            }
            $db->transCommit();

            return $id;
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'ModuleStatuses::createType failed: {m}', ['m' => $e->getMessage()]);

            return 'The list could not be saved.';
        }
    }

    /**
     * Save one list: its name, its items (edit), new items (add) and the items to delete.
     *
     * @param array<int,array<string,mixed>> $existing id => [name,color,sort,active]
     * @param list<array<string,mixed>>      $new      [name,color,sort]
     * @param list<int>                      $delete   item ids to delete
     * @return string|null error message
     */
    public static function saveItems(int $typeId, ?string $newTypeName, array $existing, array $new, array $delete): ?string
    {
        $db   = \Config\Database::connect();
        $type = $db->table('status_types')->where('id', $typeId)->get()->getRowArray();
        if (! $type) {
            return 'This list was not found.';
        }
        $now = date('Y-m-d H:i:s');
        $cur = [];
        foreach ($db->table('status_items')->where('type_id', $typeId)->get()->getResultArray() as $r) {
            $cur[(int) $r['id']] = $r;
        }

        $names = [];
        $ops   = [];
        foreach ($existing as $id => $f) {
            $id = (int) $id;
            if (! isset($cur[$id]) || in_array($id, $delete, true)) {
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

        // a status that records still use cannot be deleted (untick Active instead)
        foreach ($delete as $id) {
            if (isset($cur[(int) $id])) {
                $n = self::usage((string) $type['module_slug'], (string) $type['type_key'], (string) $cur[(int) $id]['name']);
                if ($n > 0) {
                    return '"' . $cur[(int) $id]['name'] . '" is used by ' . number_format($n) . ' record(s), so it cannot be deleted. Untick Active instead.';
                }
            }
        }

        $u = self::USAGE[$type['module_slug']][$type['type_key']] ?? null;
        $db->transBegin();
        try {
            if ($newTypeName !== null && trim($newTypeName) !== '') {
                $db->table('status_types')->where('id', $typeId)->update(['name' => mb_substr(trim($newTypeName), 0, 100), 'updated_at' => $now]);
            }
            foreach ($ops as $o) {
                $old = (string) $cur[$o['id']]['name'];
                $db->table('status_items')->where('id', $o['id'])->update(['name' => $o['name'], 'color' => $o['color'], 'sort_order' => $o['sort'], 'is_active' => $o['active'], 'updated_at' => $now]);
                if ($old !== $o['name'] && $u) {
                    $db->table($u[0])->where($u[1], $old)->update([$u[1] => $o['name']]);   // renamed: the records follow
                }
            }
            foreach ($adds as $a) {
                $db->table('status_items')->insert(['type_id' => $typeId, 'name' => $a['name'], 'color' => $a['color'], 'sort_order' => $a['sort'], 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($delete) {
                $db->table('status_items')->where('type_id', $typeId)->whereIn('id', array_map('intval', $delete))->delete();
            }
            if ($db->transStatus() === false) {
                $db->transRollback();

                return 'The statuses could not be saved.';
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'ModuleStatuses::saveItems failed: {m}', ['m' => $e->getMessage()]);

            return 'The statuses could not be saved.';
        }

        return null;
    }

    /** @return string|null error message */
    public static function deleteType(int $typeId): ?string
    {
        $db   = \Config\Database::connect();
        $type = $db->table('status_types')->where('id', $typeId)->get()->getRowArray();
        if (! $type) {
            return 'This list was not found.';
        }
        foreach ($db->table('status_items')->where('type_id', $typeId)->get()->getResultArray() as $i) {
            $n = self::usage((string) $type['module_slug'], (string) $type['type_key'], (string) $i['name']);
            if ($n > 0) {
                return 'The list is still used: "' . $i['name'] . '" is on ' . number_format($n) . ' record(s).';
            }
        }
        $db->table('status_items')->where('type_id', $typeId)->delete();
        $db->table('status_types')->where('id', $typeId)->delete();

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