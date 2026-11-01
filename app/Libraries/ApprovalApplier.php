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

    public static function apply($db, array $req): ?string
    {
        return match ((string) $req['module_slug']) {
            'store_details' => self::store($db, $req),
            default         => null,
        };
    }

    private static function store($db, array $req): ?string
    {
        $action = strtolower((string) $req['action_key']);
        $after  = json_decode((string) ($req['payload_after'] ?? ''), true);
        $after  = is_array($after) ? $after : [];
        $data   = array_intersect_key($after, array_flip(self::STORE_COLS));
        $id     = (int) ($req['target_id'] ?? 0);

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

    private static function codeTaken($db, array $data, int $ignoreId): bool
    {
        $code = $data['store_code'] ?? null;
        if ($code === null || $code === '') {
            return false;
        }

        return $db->table('store_details')->where('store_code', $code)->where('id !=', $ignoreId)->countAllResults() > 0;
    }
}
