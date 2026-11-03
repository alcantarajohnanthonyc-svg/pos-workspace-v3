<?php

namespace App\Libraries;

/**
 * Applies an APPROVED request to its module. Called by ApprovalQueue::decide() inside the same transaction as the
 * approval, so if applying fails (e.g. the store code is already used) nothing is approved and the user sees why.
 *
 * Add one private method per module that sends requests; modules without one are simply marked approved.
 *
 * @return string|null  an error message, or null when everything was applied
 */
class ApprovalApplier
{
    /** Columns of store_details that a request may write (anything else in the payload is only for display). */
    private const STORE_COLS = ['branch_id', 'store_code', 'erp_code', 'outlet_description', 'address', 'ownership_type', 'store_status', 'date_opened', 'last_pos_selling_date'];

    /** @param string|null $summary set to a short sentence about what was done (shown in the notification) */
    public static function apply($db, array $req, ?string &$summary = null): ?string
    {
        $summary = null;

        return match ((string) $req['module_slug']) {
            'store_details' => self::store($db, $req, $summary),
            default         => null,
        };
    }

    private static function store($db, array $req, ?string &$summary): ?string
    {
        $action = strtolower((string) $req['action_key']);
        $after  = json_decode((string) ($req['payload_after'] ?? ''), true);
        $after  = is_array($after) ? $after : [];
        $data   = array_intersect_key($after, array_flip(self::STORE_COLS));
        $id     = (int) ($req['target_id'] ?? 0);

        if ($action === 'export') {
            return null;   // nothing to apply: the requester downloads the file with the one-time link
        }
        if ($action === 'import') {
            return self::storeImport($db, $after, $summary);
        }
        if (($after['mode'] ?? '') === 'bulk') {
            return self::storeBulk($db, $action, $after, $summary);
        }

        if ($action === 'create') {
            if (! $data || empty($data['outlet_description']) || empty($data['branch_id'])) {
                return 'The request has no store data to add.';
            }
            if (self::codeTaken($db, $data, 0)) {
                return 'Store code "' . $data['store_code'] . '" already exists, so this store cannot be added.';
            }
            $db->table('store_details')->insert($data);
            $newId = (int) $db->insertID();
            if ($newId > 0) {
                $db->table('approval_requests')->where('id', (int) $req['id'])->update(['target_id' => $newId]);
            }

            return null;
        }

        if ($id < 1) {
            return 'The request does not point to a store.';
        }
        $exists = $db->table('store_details')->where('id', $id)->countAllResults();
        if ($exists < 1) {
            return 'The store no longer exists.';
        }

        if ($action === 'update') {
            if (! $data) {
                return 'The request has no changes to apply.';
            }
            if (self::codeTaken($db, $data, $id)) {
                return 'Store code "' . $data['store_code'] . '" is now used by another store, so this update cannot be applied.';
            }
            $db->table('store_details')->where('id', $id)->update($data);

            return null;
        }

        if ($action === 'delete') {
            $db->table('store_details')->where('id', $id)->delete();

            return null;
        }

        return null;
    }

    /** Many stores in one request: update (status / ownership) or delete them all. */
    private static function storeBulk($db, string $action, array $after, ?string &$summary): ?string
    {
        $items = is_array($after['items'] ?? null) ? $after['items'] : [];
        $ids   = [];
        foreach ($items as $it) {
            $i = (int) ($it['id'] ?? 0);
            if ($i > 0) {
                $ids[$i] = $i;
            }
        }
        $ids = array_values($ids);
        if (! $ids) {
            return 'The request has no stores.';
        }
        $existing = array_map('intval', array_column($db->table('store_details')->select('id')->whereIn('id', $ids)->get()->getResultArray(), 'id'));
        if (! $existing) {
            return 'None of the stores exist anymore.';
        }

        if ($action === 'delete') {
            $db->table('store_details')->whereIn('id', $existing)->delete();
            $summary = 'Deleted ' . count($existing) . ' of ' . count($ids) . ' stores.';

            return null;
        }

        $changes = [];
        foreach (['store_status', 'ownership_type'] as $k) {
            if (isset($after['changes'][$k]) && trim((string) $after['changes'][$k]) !== '') {
                $changes[$k] = trim((string) $after['changes'][$k]);
            }
        }
        if (! $changes) {
            return 'The request has no changes to apply.';
        }
        $db->table('store_details')->whereIn('id', $existing)->update($changes);
        $summary = 'Updated ' . count($existing) . ' of ' . count($ids) . ' stores.';

        return null;
    }

    /** Insert the lines of an approved import. Store codes that exist by now are skipped, not an error. */
    private static function storeImport($db, array $after, ?string &$summary): ?string
    {
        $rows = is_array($after['rows'] ?? null) ? $after['rows'] : [];
        if (! $rows) {
            return 'The request has no lines to import.';
        }

        $seen = [];
        foreach ($db->table('store_details')->select('store_code')->where("store_code IS NOT NULL AND store_code <> ''")->get()->getResultArray() as $r) {
            $seen[strtolower((string) $r['store_code'])] = true;
        }

        $insert  = [];
        $skipped = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;

                continue;
            }
            $d = [];
            foreach (self::STORE_COLS as $c) {
                $d[$c] = $row[$c] ?? null;
            }
            if (empty($d['outlet_description']) || empty($d['branch_id'])) {
                $skipped++;

                continue;
            }
            $code = $d['store_code'];
            if ($code !== null && $code !== '') {
                $k = strtolower((string) $code);
                if (isset($seen[$k])) {
                    $skipped++;

                    continue;
                }
                $seen[$k] = true;
            }
            $insert[] = $d;
        }

        if (! $insert) {
            return 'None of the lines can be imported (their store codes already exist).';
        }
        foreach (array_chunk($insert, 200) as $chunk) {
            $db->table('store_details')->insertBatch($chunk);
        }
        $summary = 'Imported ' . count($insert) . ' of ' . count($rows) . ' lines' . ($skipped ? '; ' . $skipped . ' skipped (store code already exists)' : '') . '.';

        return null;
    }

    private static function codeTaken($db, array $data, int $ignoreId): bool
    {
        $code = $data['store_code'] ?? null;
        if ($code === null || $code === '') {
            return false;
        }

        return $db->table('store_details')->where('store_code', $code)->where('id !=', $ignoreId)->countAllResults() > 0;
    }
}
