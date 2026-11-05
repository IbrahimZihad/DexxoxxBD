<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\Plan;
use App\Models\Service;
use Throwable;

/**
 * Machine-readable health probe for the storefront (GET /health).
 *
 * It answers with a status code first (503 the moment MySQL does not reply, 200
 * otherwise) and a small JSON body next. Nothing in the body is usable against
 * the app: no DSN, no host, no username, no file path, and the APP_KEY is
 * reported as a yes/no only.
 */
final class HealthController extends Controller
{
    /**
     * Named "show" because that is the handler routes/web.php binds to the
     * "health" route.
     */
    public function show(Request $request): Response
    {
        $databaseUp = false;

        try {
            $databaseUp = Database::isAvailable();
        } catch (Throwable $exception) {
            // A driver that throws something other than PDOException still has to
            // answer the probe - with a 503 and no internals.
            Logger::error('health_database_check', ['path' => $request->path()]);
        }

        $counts = $databaseUp ? $this->counts() : ['settings' => null, 'services' => null, 'plans' => null];
        $notices = $databaseUp ? App::instance()->bootDiagnostics() : ['Cannot reach MySQL, so no further checks ran.'];

        $healthy = $databaseUp;

        return $this->json([
            'ok' => $healthy,
            'status' => $healthy ? 'operational' : 'degraded',
            'app' => [
                'name' => setting('brand.name', 'ZapSub'),
                'env' => (string) app()->config('app.env', 'production'),
                'currency' => (string) app()->config('app.currency', 'BDT'),
            ],
            'checks' => [
                'database' => $databaseUp,
                // Boolean only: whether notes can be encrypted, never the key.
                'app_key' => Crypto::keyPresent(),
            ],
            'counts' => $counts,
            'notices' => $notices,
            'time' => date(DATE_ATOM),
        ], $healthy ? 200 : 503);
    }

    /**
     * Row counts for the three tables that decide whether the shop has anything
     * to sell. Each one is read through its model, and a broken table reports
     * null instead of taking the probe down with a 500.
     *
     * @return array{settings: int|null, services: int|null, plans: int|null}
     */
    private function counts(): array
    {
        $read = static function (callable $counter): ?int {
            try {
                return $counter();
            } catch (Throwable) {
                return null;
            }
        };

        return [
            'settings' => $read(static fn (): int => Database::table('settings')->count()),
            'services' => $read(static fn (): int => (new Service())->countAll()),
            'plans' => $read(static fn (): int => (new Plan())->countAll()),
        ];
    }
}
