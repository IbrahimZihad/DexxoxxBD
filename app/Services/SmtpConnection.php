<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Line-level SMTP dialogue helper used by MailService's built-in transport.
 */
final class SmtpConnection
{
    /** @var resource */
    private $socket;

    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        $this->socket = $socket;
    }

    public function write(string $payload): void
    {
        $remaining = strlen($payload);
        while ($remaining > 0) {
            $written = fwrite($this->socket, substr($payload, strlen($payload) - $remaining));
            if ($written === false || $written === 0) {
                throw new RuntimeException('SMTP write failed.');
            }
            $remaining -= $written;
        }
    }

    /**
     * @param list<string> $acceptable leading reply codes
     */
    public function command(string $line, array $acceptable = []): string
    {
        $this->write($line . "\r\n");
        $reply = $this->expect(null);

        if ($acceptable !== []) {
            $code = substr($reply, 0, 3);
            if (!in_array($code, $acceptable, true)) {
                throw new RuntimeException('Unexpected SMTP reply to "' . self::redact($line) . '": ' . $reply);
            }
        }

        return $reply;
    }

    public function expect(?string $code): string
    {
        $reply = '';

        while (true) {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP connection closed unexpectedly.');
            }

            $reply .= $line;

            // A space after the 3-digit code means this is the final line.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($code !== null && substr($reply, 0, 3) !== $code) {
            throw new RuntimeException('Expected SMTP reply ' . $code . ', got: ' . $reply);
        }

        return trim($reply);
    }

    private static function redact(string $line): string
    {
        // Never put credentials into an exception message.
        return preg_match('/^(AUTH|PASS|USER)\b/i', $line) === 1 ? 'AUTH [redacted]' : $line;
    }
}
