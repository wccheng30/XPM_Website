<?php
/**
 * Cache-busted admin asset URLs. $url is relative to /admin/
 * (css/list.css, admin.min.css, ../assets/js/shell paths).
 */
function admin_asset($url, $escape = true) {
    $url = str_replace('\\', '/', (string) $url);
    $admin = dirname(__DIR__);
    if (strncmp($url, '../', 3) === 0) {
        $full = dirname($admin) . '/' . substr($url, 3);
    } else {
        $full = $admin . '/' . ltrim($url, '/');
    }
    $full = str_replace('/', DIRECTORY_SEPARATOR, $full);
    $version = is_file($full) ? filemtime($full) : 1;
    $out = $url . '?v=' . $version;
    return $escape ? htmlspecialchars($out, ENT_QUOTES, 'UTF-8') : $out;
}

function admin_icons_begin() {
    if (!defined('ADMIN_ICON_BUFFER')) {
        define('ADMIN_ICON_BUFFER', true);
        ob_start('admin_rewrite_icons');
    }
}

function admin_rewrite_icons($html) {
    $sprite = admin_asset('assets/icons.svg');
    return preg_replace_callback(
        '/<i\b([^>]*)class=(["\'])(.*?)\2([^>]*)>\s*<\/i>/is',
        function ($match) use ($sprite) {
            $class = $match[3];
            if (!preg_match('/\bfa-([a-z0-9-]+)/', $class, $name)) {
                return $match[0];
            }
            $keep = [];
            foreach (preg_split('/\s+/', trim($class)) as $token) {
                if ($token === '' || $token === 'fa' || $token === 'fas' || $token === 'far' || $token === 'fab' || $token === 'fal' || strpos($token, 'fa-') === 0) {
                    continue;
                }
                $keep[] = $token;
            }
            $cls = trim('icon ' . implode(' ', $keep));
            return '<svg class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true" focusable="false"><use href="' . $sprite . '#' . htmlspecialchars($name[1], ENT_QUOTES, 'UTF-8') . '"></use></svg>';
        },
        $html
    );
}
