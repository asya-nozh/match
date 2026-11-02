<?php

declare(strict_types=1);

require_once __DIR__ . '/../modules/helpers.php';
require_once __DIR__ . '/mail_config.php';

function mail_log(string $message): void
{
    if (function_exists('app_log')) {
        app_log($message);
        return;
    }

    error_log($message);
}

function encode_mail_header(string $value): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_read_response($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $expectedCodes, string $step): string
{
    $response = smtp_read_response($socket);
    $code = (int)substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException($step . '. Ответ SMTP: ' . trim($response));
    }

    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes, string $step): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expectedCodes, $step);
}

function smtp_prepare_data(string $headers, string $body): string
{
    $data = $headers . "\r\n\r\n" . $body;
    $data = str_replace(["\r\n", "\r"], "\n", $data);
    $lines = explode("\n", $data);

    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines) . "\r\n.";
}

function smtp_send_mail(string $toEmail, string $subject, string $body): bool
{
    if (MAIL_SMTP_PASSWORD === '' || MAIL_SMTP_PASSWORD === 'ВСТАВЬ_ПАРОЛЬ_ОТ_ПОЧТЫ') {
        mail_log('SMTP-пароль не указан в php/mail_config.php. Письмо не отправлено.');
        return false;
    }

    $socket = @stream_socket_client(
        'ssl://' . MAIL_SMTP_HOST . ':' . MAIL_SMTP_PORT,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        mail_log('SMTP-подключение не выполнено: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, [220], 'SMTP-сервер не отправил приветствие');
        smtp_command($socket, 'EHLO my-match.ru', [250], 'Ошибка EHLO');
        smtp_command($socket, 'AUTH LOGIN', [334], 'SMTP не принял AUTH LOGIN');
        smtp_command($socket, base64_encode(MAIL_SMTP_USERNAME), [334], 'SMTP не принял логин');
        smtp_command($socket, base64_encode(MAIL_SMTP_PASSWORD), [235], 'SMTP не принял пароль');
        smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', [250], 'SMTP не принял отправителя');
        smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251], 'SMTP не принял получателя');
        smtp_command($socket, 'DATA', [354], 'SMTP не перешёл в режим DATA');

        $encodedSubject = encode_mail_header($subject);
        $encodedFromName = encode_mail_header(MAIL_FROM_NAME);
        $encodedBody = chunk_split(base64_encode($body));

        $headers = implode("\r\n", [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedFromName . ' <' . MAIL_FROM_EMAIL . '>',
            'Reply-To: ' . MAIL_FROM_EMAIL,
            'To: <' . $toEmail . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: PHP/' . PHP_VERSION
        ]);

        fwrite($socket, smtp_prepare_data($headers, $encodedBody) . "\r\n");
        smtp_expect($socket, [250], 'SMTP не принял письмо');
        smtp_command($socket, 'QUIT', [221, 250], 'Ошибка завершения SMTP-сеанса');

        fclose($socket);
        return true;
    } catch (Throwable $exception) {
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
        mail_log('Ошибка отправки письма через SMTP: ' . $exception->getMessage());
        return false;
    }
}

function send_email_confirmation_code(string $email, string $code): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'Код подтверждения регистрации — МЭТЧ';
    $message = "Здравствуйте!\n\n" .
        "Ваш код подтверждения регистрации в МЭТЧ: {$code}\n\n" .
        "Код действует 15 минут.\n" .
        "Если вы не регистрировались на сайте МЭТЧ, просто проигнорируйте это письмо.\n";

    if (MAIL_USE_SMTP) {
        return smtp_send_mail($email, $subject, $message);
    }

    $encodedSubject = encode_mail_header($subject);
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . encode_mail_header(MAIL_FROM_NAME) . ' <' . MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . MAIL_FROM_EMAIL,
        'X-Mailer: PHP/' . PHP_VERSION
    ]);

    return @mail($email, $encodedSubject, $message, $headers);
}
