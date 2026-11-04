<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use Throwable;

/**
 * Every administrative and money-touching action is written to audit_logs.
 * The writer never throws: an audit failure must not corrupt the business
 * transaction it is describing, and Logger keeps the second copy.
 */
final class AuditService
{
    /**
     * @param array<string, mixed> $context
     */
    public static function record(
        string $action,
        ?int $userId,
        array $context = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $request = self::safeRequest();
            $ip = self::ip($request);
            $userAgent = $request === null ? 'cli' : substr($request->userAgent(), 0, 512);

            Database::connection()->insert('audit_logs', [
                'user_id' => $userId,
                'action' => substr($action, 0, 100),
                'description' => self::describe($action, $context),
                'subject_type' => $subjectType === null ? null : substr($subjectType, 0, 80),
                'subject_id' => $subjectId,
                'old_values' => $oldValues === null ? null : self::encode($oldValues),
                'new_values' => $newValues === null ? null : self::encode($newValues),
                'context' => $context === [] ? null : self::encode($context),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);

            Logger::audit($action, $userId, $context, $ip);
        } catch (Throwable $exception) {
            Logger::error('audit_write_failed', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ], 'error');
        }
    }

    public static function recordForCurrentUser(string $action, array $context = []): void
    {
        self::record($action, Auth::instance()->id(), $context);
    }

    private static function safeRequest(): ?Request
    {
        try {
            return App::instance()->request();
        } catch (Throwable) {
            return null;
        }
    }

    private static function ip(?Request $request): string
    {
        if ($request !== null) {
            return $request->ip();
        }
        return 'cli';
    }

    /** Never persist plaintext secrets inside the audit payload. */
    private static function encode(array $values): string
    {
        $filtered = [];
        $sensitive = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'credential',
            'encrypted_payload',
            'card',
        ];

        foreach ($values as $key => $value) {
            $lower = strtolower((string) $key);
            foreach ($sensitive as $needle) {
                if (str_contains($lower, $needle)) {
                    $value = '***';
                    break;
                }
            }
            $filtered[$key] = $value;
        }

        $json = json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? '{}' : substr($json, 0, 60000);
    }

    private static function describe(string $action, array $context): string
    {
        $template = (string) (Config::get('audit.descriptions.' . $action, ''));
        if ($template === '') {
            $template = ucwords(str_replace(['.', '_', '-'], ' ', $action));
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return substr(strtr($template, $replacements), 0, 255);
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 50, ?string $action = null, ?int $userId = null): array
    {
        $sql = 'SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1 = 1';
        $bindings = [];

        if ($action !== null && $action !== '') {
            $sql .= ' AND a.action LIKE :action';
            $bindings['action'] = $action . '%';
        }
        if ($userId !== null) {
            $sql .= ' AND a.user_id = :uid';
            $bindings['uid'] = $userId;
        }

        $sql .= ' ORDER BY a.id DESC LIMIT ' . max(1, min(500, $limit));

        return Database::connection()->select($sql, $bindings);
    }

    public function prune(int $olderThanDays = 365): int
    {
        return Database::connection()->statement(
            'DELETE FROM audit_logs WHERE created_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', time() - ($olderThanDays * 86400))]
        );
    }
}
