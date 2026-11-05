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

        $catalogue = $databaseUp ? $this->catalogueSeeded() : ['services' => false, 'plans' => false];
        $notices = $databaseUp ? App::instance()->bootDiagnostics() : ['Cannot reach MySQL, so no further checks ran.'];

        $healthy = $databaseUp && $catalogue['services'] && $catalogue['plans'];
        $debug = (bool) app()->config('app.debug', false);

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
                // Seeded or not - never how many rows, which would price the shop.
                'catalogue' => $catalogue,
            ],
            // Fix-it hints name missing config, so they only belong in debug mode.
            'notices' => $debug ? $notices : [],
            'time' => date(DATE_ATOM),
        ], $healthy ? 200 : 503);
    }

    /**
     * The two tables that decide whether the shop can sell anything are read as
     * yes/no. A public probe reporting "52 plans" tells a competitor the size of
     * the catalogue and an operator their stock levels, so the counts stay here:
     *
     * @return array{services: bool, plans: bool}
     */
    private function catalogueSeeded(): array
    {
        $any = static function (callable $counter): bool {
            try {
                return $counter() > 0;
            } catch (Throwable) {
                return false;
            }
        };

        return [
            'services' => $any(static fn (): int => (new Service())->countAll()),
            'plans' => $any(static fn (): int => (new Plan())->countAll()),
        ];
    }

    /**
     * Row counts are kept for the admin dashboard, where they are operator
     * information rather than something the open internet gets to read.
     *
     * @return array{settings: int|null, services: int|null, plans: int|null}
     */
    public function counts(): array
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
