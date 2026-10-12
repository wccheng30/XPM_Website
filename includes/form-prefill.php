<?php
/**
 * Fill empty public forms from the logged-in account and earlier submissions.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/phone-field.php';

function xpm_h($key) {
    return htmlspecialchars((string) ($_POST[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

function xpm_apply_account_prefill(PDO $pdo, $context) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return false;
    }

    auth_start();
    $email = trim((string) ($_SESSION['user_email'] ?? ''));
    $name = trim((string) ($_SESSION['user_name'] ?? ''));
    if ($email === '' && $name === '') {
        return false;
    }

    $registration = null;
    $application = null;
    if ($email !== '') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM programme_registrations WHERE email = ? ORDER BY created_at DESC, id DESC LIMIT 1");
            $stmt->execute([$email]);
            $registration = $stmt->fetch() ?: null;

            $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY created_at DESC, id DESC LIMIT 1");
            $stmt->execute([$email]);
            $application = $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            return false;
        }
    }

    $values = [];
    if ($name !== '') {
        $values['name'] = $name;
    }
    if ($email !== '') {
        $values['email'] = $email;
    }

    $shared = ['name', 'email', 'phone', 'ic_number'];
    $secondary = $context === 'programme' ? $application : $registration;
    $primary = $context === 'programme' ? $registration : $application;
    $skip = ['id', 'programme_id', 'job_id', 'status', 'created_at', 'resume_path', 'cover_letter_path', 'interviewer_comment'];

    if ($secondary) {
        foreach ($shared as $key) {
            if (!empty($secondary[$key])) {
                $values[$key] = $secondary[$key];
            }
        }
    }

    if ($primary) {
        foreach ($primary as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            if ($value !== null && $value !== '') {
                $values[$key] = $value;
            }
        }
    }

    $phoneFields = ['phone', 'billing_contact_no', 'reference1_phone', 'reference2_phone', 'reference3_phone'];
    foreach ($phoneFields as $field) {
        if (empty($values[$field])) {
            continue;
        }
        [$code, $local] = split_phone($values[$field]);
        if (!isset($_POST[$field . '_code']) || $_POST[$field . '_code'] === '') {
            $_POST[$field . '_code'] = $code;
        }
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            $_POST[$field] = $local;
        }
        unset($values[$field]);
    }

    $applied = false;
    foreach ($values as $key => $value) {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            $_POST[$key] = $value;
            $applied = true;
        }
    }

    return $applied || $email !== '';
}
