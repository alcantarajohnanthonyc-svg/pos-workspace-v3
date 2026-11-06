<?php

namespace App\Libraries;

/**
 * Builds an email from app/Config/EmailTemplates.php.
 *
 *   EmailTemplate::render('approval_approved', 'requester', ['actor' => 'John', ...], 'approval-queue?open=5')
 *     -> ['subject' => ..., 'html' => ..., 'text' => ...]   or null when there is no template for the event
 */
class EmailTemplate
{
    /** @return array{subject:string,html:string,text:string}|null */
    public static function render(string $event, string $audience, array $vars, ?string $url): ?array
    {
        $cfg = config('EmailTemplates');
        $t   = $cfg->templates[$event . '.' . $audience] ?? $cfg->templates[$event] ?? null;
        if (! is_array($t)) {
            return null;
        }

        $vars += ['datetime' => date('M j, Y g:i A')];
        $subject = trim(strip_tags(self::fill((string) ($t['subject'] ?? '{title}'), $vars, false)));
        $heading = trim(strip_tags(self::fill((string) ($t['heading'] ?? '{title}'), $vars, false)));
        $lines   = array_values(array_filter(array_map('trim', explode("\n", self::fill((string) ($t['body'] ?? '{body}'), $vars, true))), static fn ($l) => $l !== ''));
        $button  = trim((string) ($t['button'] ?? $cfg->layout['button']));

        return [
            'subject' => $subject !== '' ? $subject : 'POS Workspace',
            'html'    => self::layout($cfg->layout, $heading, $lines, $button, $url),
            'text'    => $heading . "\n\n" . implode("\n", $lines) . ($url ? "\n\n" . base_url($url) : ''),
        ];
    }

    /**
     * Replace {placeholders}. With $dropEmptyLines a line is removed when one of its placeholders has no value.
     */
    private static function fill(string $text, array $vars, bool $dropEmptyLines): string
    {
        $out = [];
        foreach (explode("\n", $text) as $line) {
            $skip = false;
            $line = (string) preg_replace_callback('/\{(\w+)\}/', static function (array $m) use ($vars, &$skip, $dropEmptyLines): string {
                $v = trim((string) ($vars[$m[1]] ?? ''));
                if ($v === '' && $dropEmptyLines) {
                    $skip = true;
                }

                return $v;
            }, $line);
            if (! $skip) {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    private static function layout(array $l, string $heading, array $lines, string $button, ?string $url): string
    {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($l['color'] ?? '')) ? $l['color'] : '#059669';
        $paras = '';
        foreach ($lines as $line) {
            $paras .= '<p style="margin:0 0 10px;font-size:14px;line-height:1.55;color:#334155">' . esc($line) . '</p>';
        }
        $btn = $url
            ? '<p style="margin:20px 0 0"><a href="' . esc(base_url($url), 'attr') . '" style="background:' . $color . ';color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;display:inline-block">' . esc($button) . '</a></p>'
            : '';

        return '<div style="font-family:Segoe UI,Arial,sans-serif;background:#f1f5f9;padding:24px">'
            . '<div style="max-width:540px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;color:#1e293b">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#64748b">' . esc((string) ($l['brand'] ?? '')) . '</p>'
            . '<h2 style="margin:0 0 14px;font-size:18px;color:#0f172a">' . esc($heading) . '</h2>'
            . $paras . $btn
            . '</div><p style="text-align:center;font-size:11px;color:#94a3b8;margin:12px 0 0">' . esc((string) ($l['footer'] ?? '')) . '</p></div>';
    }
}