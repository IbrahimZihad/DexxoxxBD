<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\Database;
use App\Exceptions\ThrottleException;

/**
 * Fixed-window throttle backed by MySQL, so it survives across PHP workers and
 * is not fooled by an attacker rotating sessions.
 */
final class RateLimiter
{
    public function __construct(private readonly string $ip)
    {
    }

    /** @throws ThrottleException */
    public function hit(string $bucket): void
    {
        [$max, $window] = $this->limits($bucket);
        $key = $bucket . '|' . hash('sha256', $this->ip);

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM rate_limits WHERE rl_key = ? AND expires_at <= NOW()')->execute([$key]);

        $pdo->prepare(
            'INSERT INTO rate_limits (rl_key, hits, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE hits = hits + 1'
        )->execute([$key, $window]);

        $row = $pdo->prepare('SELECT hits, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS retry FROM rate_limits WHERE rl_key = ?');
        $row->execute([$key]);
        $current = $row->fetch();

        if ($current && (int) $current['hits'] > $max) {
            throw new ThrottleException(max(1, (int) $current['retry']));
        }
    }

    /** @return array{0:int,1:int} */
    private function limits(string $bucket): array
    {
        $sets = Config::get('rate_limits', []);
        $rule = $sets[$bucket] ?? $sets['default'] ?? ['max' => 600, 'window' => 60];

        return [(int) $rule['max'], max(5, (int) $rule['window'])];
    }
}
