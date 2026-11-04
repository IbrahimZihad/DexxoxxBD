<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use Throwable;

/**
 * Transactional mail. The `log` transport renders and records the message so
 * local XAMPP runs work without an SMTP server; `smtp` speaks the protocol
 * directly (no external library) with STARTTLS and AUTH support.
 *
 * Every send is also queued in `background_jobs` when the queue driver is used,
 * so a failed transport can be retried by the cron worker.
 */
final class MailService
{
    private string $transport;

    public function __construct(?string $transport = null)
    {
        $this->transport = $transport ?? (string) Config::get('mail.mailer', 'log');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $attachments unused placeholder-free: names only
     */
    public function send(
        string $subject,
        string $htmlTemplate,
        array $data,
        string $recipientEmail,
        ?string $recipientName = null,
        bool $queue = false
    ): bool {
        if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            Logger::warning('mail_rejected_invalid_recipient', ['template' => $htmlTemplate]);
            return false;
        }

        if ($queue) {
            return $this->queue($subject, $htmlTemplate, $data, $recipientEmail, $recipientName);
        }

        return $this->dispatchNow($subject, $htmlTemplate, $data, $recipientEmail, $recipientName ?? '');
    }

    private function dispatchNow(
        string $subject,
        string $template,
        array $data,
        string $recipientEmail,
        string $recipientName
    ): bool {
        [$html, $text] = $this->render($template, $data);

        $from = (string) Config::get('mail.from.address', 'no-reply@localhost');
        $fromName = (string) Config::get('mail.from.name', 'DexxoxxBD');

        try {
            $ok = match ($this->transport) {
                'smtp' => $this->smtpSend($from, $fromName, $recipientEmail, $recipientName, $subject, $html, $text),
                default => $this->logSend($from, $recipientEmail, $subject, $text, $template),
            };
        } catch (Throwable $exception) {
            Logger::error('mail_send_failed', [
                'template' => $template,
                'recipient' => $recipientEmail,
                'error' => $exception->getMessage(),
            ], 'error');
            $this->recordDelivery($template, $recipientEmail, $subject, 'failed', $exception->getMessage());
            return false;
        }

        $this->recordDelivery($template, $recipientEmail, $subject, $ok ? 'sent' : 'failed', null);
        return $ok;
    }

    private function queue(
        string $subject,
        string $template,
        array $data,
        string $recipientEmail,
        ?string $recipientName
    ): bool {
        Database::connection()->insert('background_jobs', [
            'queue' => 'default',
            'job' => 'SendMail',
            'payload' => json_encode([
                'subject' => $subject,
                'template' => $template,
                'data' => $data,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
            ], JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
            'available_at' => now(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{string, string}
     */
    private function render(string $template, array $data): array
    {
        $viewFactory = App::viewFactory();
        $emailBody = $viewFactory->exists($template) ? $viewFactory->render($template, $data) : '';

        if ($emailBody === '') {
            $emailBody = $viewFactory->render('emails.generic', [
                'heading' => (string) ($data['heading'] ?? 'Notification'),
                'lines' => (array) ($data['lines'] ?? []),
                'button' => $data['button'] ?? null,
            ]);
        }

        $html = $viewFactory->exists('emails.layout')
            ? $viewFactory->render('emails.layout', [
                'title' => (string) ($data['subject'] ?? Config::get('app.name', 'DexxoxxBD')),
                'body' => $emailBody,
                'preheader' => (string) ($data['preheader'] ?? ''),
            ])
            : $emailBody;

        return [$html, strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", $html))];
    }

    private function logSend(
        string $from,
        string $recipient,
        string $subject,
        string $text,
        string $template
    ): bool {
        $path = (string) Config::get('mail.log_path', base_path('storage/logs/mail.log'));
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }

        $entry = sprintf(
            "=== %s ===\nFrom: %s\nTo: %s\nSubject: %s\nTemplate: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $from,
            $recipient,
            $subject,
            $template,
            $text
        );

        return @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private function smtpSend(
        string $from,
        string $fromName,
        string $to,
        string $toName,
        string $subject,
        string $html,
        string $text
    ): bool {
        $host = (string) Config::get('mail.host', '');
        $port = (int) Config::get('mail.port', 587);
        $encryption = strtolower((string) Config::get('mail.encryption', 'tls'));
        $username = (string) Config::get('mail.username', '');
        $password = (string) Config::get('mail.password', '');

        if ($host === '') {
            throw new Throwable('MAIL_HOST is not configured for the smtp transport.');
        }

        $timeout = (int) Config::get('mail.timeout', 15);
        $socket = @stream_socket_client(
            ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );

        if ($socket === false) {
            throw new Throwable(sprintf('SMTP connect failed: %s (%d)', $errstr ?? '', $errno ?? 0));
        }

        stream_set_timeout($socket, $timeout);
        $connection = new SmtpConnection($socket);
        $connection->expect('220');

        $hostname = (string) ($this->serverHostname());
        $connection->command('EHLO ' . $hostname, ['250']);

        if ($encryption === 'tls') {
            $connection->command('STARTTLS', ['220']);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Throwable('STARTTLS negotiation failed.');
            }
            $connection->command('EHLO ' . $hostname, ['250']);
        }

        if ($username !== '' && $password !== '') {
            $connection->command('AUTH LOGIN', ['334']);
            $connection->command(base64_encode($username), ['334']);
            $connection->command(base64_encode($password), ['235']);
        }

        $connection->command('MAIL FROM:<' . $from . '>', ['250']);
        $connection->command('RCPT TO:<' . $to . '>', ['250', '251']);
        $connection->command('DATA', ['354']);

        $boundary = 'b_' . bin2hex(random_bytes(12));
        $message = $this->buildMessage($from, $fromName, $to, $toName, $subject, $html, $text, $boundary);

        // Dot-stuffing per RFC 5321.
        $message = preg_replace('/^\./m', '..', $message) ?? $message;
        $connection->write($message . "\r\n.");
        $connection->expect('250');

        $connection->command('QUIT', ['221']);
        fclose($socket);

        return true;
    }

    private function serverHostname(): string
    {
        $host = (string) (php_uname('n') ?: 'localhost');
        return $host === '' ? 'localhost' : $host;
    }

    private function buildMessage(
        string $from,
        string $fromName,
        string $to,
        string $toName,
        string $subject,
        string $html,
        string $text,
        string $boundary
    ): string {
        $headers = [
            'Date: ' => date('r'),
            'From: ' => sprintf('%s <%s>', $this->encodeHeader($fromName), $from),
            'To: ' => $toName === '' ? $to : sprintf('%s <%s>', $this->encodeHeader($toName), $to),
            'Subject: ' => $this->encodeHeader($subject),
            'Message-ID: ' => sprintf(
                '<%s@%s>',
                bin2hex(random_bytes(12)),
                (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_HOST) ?: 'localhost')
            ),
            'MIME-Version: ' => '1.0',
            'Content-Type: ' => 'multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: ' => 'DexxoxxBD Marketplace',
            'List-Unsubscribe: ' => '<' . url('account/notifications') . '>',
        ];

        $head = '';
        foreach ($headers as $name => $value) {
            $head .= $name . $value . "\r\n";
        }

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$boundary}--\r\n";

        return $head . "\r\n" . $body;
    }

    private function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        // Non-ASCII names/subjects must be RFC 2047 encoded.
        return preg_match('/^[\x20-\x7E]*$/', $value) === 1
            ? $value
            : '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function recordDelivery(
        string $template,
        string $recipient,
        string $subject,
        string $status,
        ?string $error
    ): void {
        try {
            Logger::write($status === 'sent' ? Logger::INFO : Logger::ERROR, 'mail_' . $status, [
                'template' => $template,
                'recipient' => $recipient,
                'subject' => $subject,
                'error' => $error,
            ], 'mail');
        } catch (Throwable) {
            // Logging must never break the request.
        }
    }

    /** Used by the queue worker to flush a queued SendMail job. */
    public function sendFromPayload(array $payload): bool
    {
        return $this->dispatchNow(
            (string) ($payload['subject'] ?? 'Notification'),
            (string) ($payload['template'] ?? 'emails.generic'),
            (array) ($payload['data'] ?? []),
            (string) ($payload['recipient_email'] ?? ''),
            (string) ($payload['recipient_name'] ?? '')
        );
    }
}
