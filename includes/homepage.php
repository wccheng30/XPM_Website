<?php
/**
 * Shared helpers for the homepage testimonials and client-logo marquee.
 */

function xpm_text_length($value) {
    $value = (string) $value;
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
}

function xpm_public_image($path) {
    $path = str_replace('\\', '/', trim((string) $path));
    if ($path === '' || strpos($path, '..') !== false) {
        return '';
    }
    if (!preg_match('#^assets/images/[A-Za-z0-9._/-]+$#', $path)) {
        return '';
    }
    return $path;
}

function xpm_logo_rows(array $logos) {
    $safe = [];
    foreach ($logos as $logo) {
        $path = xpm_public_image(is_array($logo) ? ($logo['image_path'] ?? '') : $logo);
        if ($path !== '') {
            $safe[] = $path;
        }
    }
    $count = count($safe);
    if ($count === 0) {
        return [];
    }
    $rowCount = min(3, $count);
    $rows = array_fill(0, $rowCount, []);
    foreach ($safe as $index => $path) {
        $rows[$index % $rowCount][] = $path;
    }
    return $rows;
}

function xpm_testimonial_fields(array $post) {
    $highlight = trim((string) ($post['highlight'] ?? ''));
    $highlight = trim(preg_replace('/\s+/', ' ', $highlight));
    $values = [
        'author_name' => trim((string) ($post['author_name'] ?? '')),
        'author_role' => trim((string) ($post['author_role'] ?? '')),
        'highlight' => $highlight,
        'quote' => trim((string) ($post['quote'] ?? '')),
        'display_order' => (int) ($post['display_order'] ?? 0),
    ];
    if ($values['display_order'] < 0) {
        $values['display_order'] = 0;
    }
    if ($values['display_order'] > 9999) {
        $values['display_order'] = 9999;
    }

    $errors = [];
    if ($values['author_name'] === '') {
        $errors['author_name'] = 'Name is required.';
    } elseif (xpm_text_length($values['author_name']) > 255) {
        $errors['author_name'] = 'Use a shorter name.';
    }
    if (xpm_text_length($values['author_role']) > 255) {
        $errors['author_role'] = 'Use a shorter role.';
    }
    if (xpm_text_length($values['highlight']) > 255) {
        $errors['highlight'] = 'Use a shorter opening line.';
    }
    if ($values['quote'] === '') {
        $errors['quote'] = 'Testimonial text is required.';
    } elseif (xpm_text_length($values['quote']) > 4000) {
        $errors['quote'] = 'Use a shorter testimonial.';
    }

    return ['values' => $values, 'errors' => $errors];
}

function xpm_excerpt($text, $length = 110) {
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    $size = xpm_text_length($text);
    if ($size <= $length) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }
    return rtrim(substr($text, 0, $length - 1)) . '…';
}
