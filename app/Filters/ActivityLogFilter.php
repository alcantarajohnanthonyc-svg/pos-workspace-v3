<?php

namespace App\Filters;

use App\Libraries\ActivityLog;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Logs EVERY action that changes something (POST / PUT / PATCH / DELETE) in EVERY module, automatically.
 * Register it once as a global "after" filter (see app/Config/Filters.php). A module that already wrote a detailed
 * entry in the same request (ActivityLog::record) is not logged twice.
 */
class ActivityLogFilter implements FilterInterface
{
    /** paths that are only noise */
    private const SKIP = ['notifications/read-all', 'auth/check_token'];

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $method = strtoupper((string) $request->getMethod());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) || ActivityLog::wasWritten()) {
            return null;
        }
        $path = trim((string) $request->getUri()->getPath(), '/');
        if (in_array($path, self::SKIP, true)) {
            return null;
        }

        $segs   = $path === '' ? [] : explode('/', $path);
        $first  = $segs[0] ?? '';
        $module = $this->moduleOf($first);
        $id     = null;
        $words  = [];
        foreach (array_slice($segs, 1) as $s) {
            if (ctype_digit($s)) {
                $id ??= $s;
            } else {
                $words[] = $s;
            }
        }
        $action = $words ? implode('.', $words) : strtolower($method);

        $body = [];
        try {
            $json = $request->getJSON(true);
            $body = is_array($json) ? $json : (array) $request->getPost();
        } catch (\Throwable $e) {
            $body = (array) $request->getPost();
        }
        unset($body['csrf_test_name']);

        ActivityLog::request($module, $action, $id, $method . ' /' . $path . ' (' . $response->getStatusCode() . ')', $body ? ['input' => $body] : [], (int) $response->getStatusCode());

        return null;
    }

    /** the first URL segment -> the module slug (rbac_modules.url), else the segment itself */
    private function moduleOf(string $first): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            try {
                $db = \Config\Database::connect();
                if ($db->tableExists('rbac_modules')) {
                    foreach ($db->table('rbac_modules')->select('slug, url')->get()->getResultArray() as $r) {
                        $u = trim((string) ($r['url'] ?? ''), " /\t\n\r");
                        if ($u !== '') {
                            $map[strtolower(explode('/', $u)[0])] = (string) $r['slug'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                $map = [];
            }
        }

        return $map[strtolower($first)] ?? ($first !== '' ? $first : 'app');
    }
}