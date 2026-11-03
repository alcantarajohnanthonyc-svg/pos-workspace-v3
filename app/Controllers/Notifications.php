<?php

namespace App\Controllers;

use App\Libraries\Notifier;

/**
 * The bell in the header. Everybody who is logged in sees only THEIR OWN notifications (no RBAC action needed).
 *
 *   GET  notifications/latest      JSON: the latest unread ones + the unread count   (silent, no loading overlay)
 *   GET  notifications/open/{id}   marks it as read, then goes to its page
 *   POST notifications/read-all    marks all of mine as read
 */
class Notifications extends BaseController
{
    public function latest()
    {
        if (! session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }
        $uid   = $this->userId();
        $tab   = $this->request->getGet('tab') === 'read' ? 'read' : 'unread';
        $items = [];
        foreach (Notifier::forBell($uid, $tab === 'read' ? 15 : 10, $tab) as $n) {
            $n['url'] = base_url($n['url']);
            $items[]  = $n;
        }

        // the badge ALWAYS counts only what is not read yet, whatever tab is open
        return $this->response->setJSON(['ok' => true, 'tab' => $tab, 'unread' => Notifier::unreadCount($uid), 'items' => $items]);
    }

    public function open(int $id)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        $db  = \Config\Database::connect();
        $row = $db->table('notifications')->where('id', $id)->where('account_id', $this->userId())->get()->getRowArray();
        if (! $row) {
            return redirect()->to(base_url('dashboard'));
        }
        if (! (int) $row['is_read']) {
            $db->table('notifications')->where('id', $id)->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
        }
        $url = trim((string) ($row['url'] ?? ''), '/');

        return redirect()->to(base_url($url !== '' ? $url : 'dashboard'));
    }

    public function readAll()
    {
        helper('security');
        if (! session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'csrf' => csrf_hash()]);
        }
        \Config\Database::connect()->table('notifications')
            ->where('account_id', $this->userId())->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);

        return $this->response->setJSON(['ok' => true, 'unread' => 0, 'csrf' => csrf_hash()]);
    }

    private function userId(): int
    {
        return (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
    }
}