<?php
/**
 * Concatenate and minify the admin shell CSS.
 * Run: php admin/build-css.php
 */
$root = __DIR__;
$parts = [
    'css/base.css',
    'css/layout.css',
    'css/components.css',
    'css/utilities.css',
];
$css = '';
foreach ($parts as $part) {
    $file = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $part);
    if (!is_file($file)) {
        fwrite(STDERR, "Missing {$part}\n");
        exit(1);
    }
    $css .= file_get_contents($file) . "\n";
}

$css = preg_replace('#/\\*(?!!).*?\\*/#s', '', $css);
$parts = preg_split('/("[^"]*"|\'[^\']*\')/', $css, -1, PREG_SPLIT_DELIM_CAPTURE);
foreach ($parts as $index => $part) {
    if ($part === '' || $part[0] === '"' || $part[0] === "'") {
        continue;
    }
    $part = preg_replace('/\s+/', ' ', $part);
    $part = preg_replace('/\s*([{}:;,])\s*/', '$1', $part);
    $part = str_replace(';}', '}', $part);
    $parts[$index] = $part;
}
$css = trim(implode('', $parts)) . "\n";

$out = $root . DIRECTORY_SEPARATOR . 'admin.min.css';
file_put_contents($out, $css);
echo 'Wrote admin.min.css (' . strlen($css) . " bytes)\n";
