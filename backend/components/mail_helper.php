<?php
/**
 * Отправка почты: SMTP (Space Web и др.) или mail().
 * Настройки в .env: MAIL_SMTP_HOST, MAIL_SMTP_PORT, MAIL_SMTP_USER, MAIL_SMTP_PASS, MAIL_FROM.
 */
declare(strict_types=1);

function mail_send(string $to, string $subject, string $bodyPlain, ?string $fromEmail = null, ?string $fromName = null): bool {
    $fromEmail = $fromEmail ?? trim((string)(getenv('MAIL_FROM') ?: ($_ENV['MAIL_FROM'] ?? 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))));
    $fromName = $fromName ?? 'Travel Hub';

    $host = trim((string)(getenv('MAIL_SMTP_HOST') ?: ($_ENV['MAIL_SMTP_HOST'] ?? '')));
    if ($host !== '') {
        return mail_send_smtp($to, $subject, $bodyPlain, $fromEmail, $fromName, $host);
    }
    return mail_send_native($to, $subject, $bodyPlain, $fromEmail);
}

function mail_send_native(string $to, string $subject, string $bodyPlain, string $fromEmail): bool {
    $headers = "From: {$fromEmail}\r\nReply-To: {$fromEmail}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encodedSubject, $bodyPlain, $headers);
}

function mail_send_smtp(string $to, string $subject, string $bodyPlain, string $fromEmail, string $fromName, string $host): bool {
    $port = (int)(getenv('MAIL_SMTP_PORT') ?: ($_ENV['MAIL_SMTP_PORT'] ?? 465));
    $user = trim((string)(getenv('MAIL_SMTP_USER') ?: ($_ENV['MAIL_SMTP_USER'] ?? $fromEmail)));
    $pass = getenv('MAIL_SMTP_PASS') ?: ($_ENV['MAIL_SMTP_PASS'] ?? '');
    $ssl = filter_var(getenv('MAIL_SMTP_SSL') ?: ($_ENV['MAIL_SMTP_SSL'] ?? '1'), FILTER_VALIDATE_BOOLEAN);

    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $addr = ($ssl ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($addr, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        error_log('[MAIL] SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $line = @fgets($fp, 515);
        return $line !== false ? trim($line) : '';
    };
    $write = function (string $s) use ($fp): void {
        @fwrite($fp, $s . "\r\n");
    };

    $r = $read();
    if (substr($r, 0, 3) !== '220') {
        @fclose($fp);
        error_log('[MAIL] SMTP greeting: ' . $r);
        return false;
    }
    $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    while ($line = $read()) {
        if (substr($line, 0, 4) === '250 ') break;
    }
    $write('AUTH LOGIN');
    $read();
    $write(base64_encode($user));
    $read();
    $write(base64_encode($pass));
    $r = $read();
    if (substr($r, 0, 3) !== '235') {
        @fclose($fp);
        error_log('[MAIL] SMTP auth failed');
        return false;
    }
    $write('MAIL FROM:<' . $fromEmail . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $read();
    $write('DATA');
    $read();
    $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $write($headers . "\r\n" . $bodyPlain . "\r\n.");
    $r = $read();
    $write('QUIT');
    @fclose($fp);
    return substr($r, 0, 3) === '250';
}
