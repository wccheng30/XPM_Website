<?php
// Email Configuration
// Update these settings with your email details

// Programme Registration Emails
define('PROGRAMME_REGISTRATION_EMAIL', 'training@xpresspointservices.com'); // Email for programme registrations

// Job Application Emails
define('JOB_APPLICATION_EMAIL', 'june.lim@xpresspointservices.com'); // Email for job applications
define('JOB_APPLICATION_EMAIL_2', 'xpjob@xpresspointservices.com'); // Second email for job applications

// Real mailbox on this domain. Confirmations are sent as this address.
define('FROM_EMAIL', 'noreply@xpresspointservices.com');
define('FROM_NAME', 'Xpress Point Management Services Sdn Bhd');

// Authenticated SMTP. The mailbox password lives in config/email.local.php,
// which is not committed. Leave it empty and the site falls back to PHP mail().
$xpm_smtp_pass = '';
$xpm_email_local = __DIR__ . '/email.local.php';
if (is_file($xpm_email_local)) {
    require $xpm_email_local;
}
define('SMTP_HOST', 'mail.xpresspointservices.com');
define('SMTP_PORT', 465); // 465 = SSL, 587 = STARTTLS
define('SMTP_USER', FROM_EMAIL);
define('SMTP_PASS', $xpm_smtp_pass);
define('SMTP_SECURE', 'ssl'); // 'ssl' for 465, 'tls' for 587

// Company information
define('COMPANY_NAME', 'Xpress Point Management Services Sdn Bhd');
define('COMPANY_WEBSITE', 'https://xpresspointservices.com');

function xpm_mail_from_header() {
    $name = trim((string) FROM_NAME);
    $email = trim((string) FROM_EMAIL);
    if ($name === '') {
        return $email;
    }
    // Encode display name so special characters stay valid in headers.
    if (function_exists('mb_encode_mimeheader')) {
        $encoded = mb_encode_mimeheader($name, 'UTF-8', 'Q', "\r\n");
    } else {
        $encoded = addcslashes($name, '"\\');
    }
    return sprintf('"%s" <%s>', $encoded, $email);
}

function xpm_last_mail_error() {
    return isset($GLOBALS['xpm_last_mail_error']) ? (string) $GLOBALS['xpm_last_mail_error'] : '';
}

function xpm_smtp_targets() {
    // The site and the mailbox are on the same machine. Connecting to the public
    // hostname is often blocked, while localhost is accepted.
    $targets = [
        [SMTP_HOST, 465, 'ssl'],
        [SMTP_HOST, 587, 'tls'],
        ['127.0.0.1', 587, 'tls'],
        ['127.0.0.1', 465, 'ssl'],
    ];
    $unique = [];
    foreach ($targets as $target) {
        $key = strtolower($target[0]) . ':' . $target[1] . ':' . $target[2];
        if ($target[0] === '' || isset($unique[$key])) {
            continue;
        }
        $unique[$key] = $target;
    }
    return array_values($unique);
}

function xpm_send_mail_smtp($to, $subject, $message, $replyTo) {
    $GLOBALS['xpm_last_mail_error'] = '';
    if (SMTP_PASS === '' || SMTP_USER === '' || SMTP_HOST === '') {
        $GLOBALS['xpm_last_mail_error'] = 'SMTP password is empty, so the site did not log in to the mailbox.';
        return false;
    }

    $base = dirname(__DIR__) . '/wp-includes/PHPMailer';
    $files = [
        $base . '/Exception.php',
        $base . '/PHPMailer.php',
        $base . '/SMTP.php',
    ];
    foreach ($files as $file) {
        if (!is_readable($file)) {
            $GLOBALS['xpm_last_mail_error'] = 'Mail library is missing on the server.';
            error_log('SMTP send skipped: PHPMailer not found at ' . $file);
            return false;
        }
        require_once $file;
    }

    $failures = [];
    foreach (xpm_smtp_targets() as $target) {
        list($host, $port, $secure) = $target;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) $port;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = $secure;
            $mail->SMTPAutoTLS = ($secure === 'tls');
            $mail->Timeout = 8;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($to);
            if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message)));
            $mail->send();
            return true;
        } catch (Throwable $e) {
            $detail = $host . ':' . $port . ' ' . $e->getMessage();
            if (SMTP_PASS !== '') {
                $detail = str_replace(SMTP_PASS, '***', $detail);
            }
            $failures[] = $detail;
            error_log('SMTP confirmation email failed: ' . $detail);
        }
    }

    $GLOBALS['xpm_last_mail_error'] = $failures ? $failures[0] : 'SMTP send failed.';
    return false;
}

function xpm_send_mail($to, $subject, $message, $replyTo) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['xpm_last_mail_error'] = 'The applicant email address was rejected before sending.';
        error_log('Confirmation email skipped: invalid recipient.');
        return false;
    }

    // Prefer authenticated SMTP when a mailbox password is configured.
    if (SMTP_PASS !== '') {
        $smtpSent = xpm_send_mail_smtp($to, $subject, $message, $replyTo);
        if ($smtpSent) {
            return true;
        }
    } else {
        $GLOBALS['xpm_last_mail_error'] = 'SMTP password is empty, so the site did not log in to the mailbox.';
    }

    $fromHeader = xpm_mail_from_header();
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $fromHeader . "\r\n";
    if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: " . $replyTo . "\r\n";
    }
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $previousFrom = ini_get('sendmail_from');
    if (filter_var(FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        ini_set('sendmail_from', FROM_EMAIL);
    }

    $timeout = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', '15');
    $sent = false;

    // Envelope sender (-f) must be a mailbox on this domain. Without it, same-domain
    // mail can still deliver while external applicant addresses are dropped or rejected.
    if (PHP_OS_FAMILY !== 'Windows' && filter_var(FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        $sent = @mail($to, $subject, $message, $headers, '-f' . FROM_EMAIL);
    }
    if (!$sent) {
        $sent = @mail($to, $subject, $message, $headers);
    }

    ini_set('default_socket_timeout', (string) $timeout);
    if ($previousFrom !== false) {
        ini_set('sendmail_from', $previousFrom);
    }

    if (!$sent) {
        $last = error_get_last();
        $detail = is_array($last) && !empty($last['message']) ? $last['message'] : 'the server refused the message';
        if (SMTP_PASS !== '') {
            $detail = str_replace(SMTP_PASS, '***', $detail);
        }
        $smtpDetail = xpm_last_mail_error();
        $GLOBALS['xpm_last_mail_error'] = $smtpDetail !== '' ? $smtpDetail : $detail;
        error_log('Confirmation email was not accepted for ' . $to . ': ' . $GLOBALS['xpm_last_mail_error']);
    }
    return (bool) $sent;
}
?>
