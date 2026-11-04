<?php
declare(strict_types=1);

/**
 * Migration runner.
 *
 *   php database/migrate.php status
 *   php database/migrate.php up
 *   php database/migrate.php fresh --force     (drops every table in the database)
 *
 * Statements that fail only because the object already exists are reported as
 * skips, so 005 can upgrade a legacy database without breaking a fresh one.
 */

['pdo' => $pdo, 'database' => $database] = require __DIR__ . '/cli-boot.php';

$argvCopy = $_SERVER['argv'] ?? [];
$command = $argvCopy[1] ?? 'status';
$forced = in_array('--force', $argvCopy, true);

/** MySQL/MariaDB errnos that mean "you already did this". */
const ALREADY_DONE = [1050, 1060, 1061, 1068, 1091, 121, 1826, 3821];

$ensureBookkeeping = static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(190) NOT NULL PRIMARY KEY,
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            batch INT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};

$ensureBookkeeping($pdo);

/** @return list<string> */
function migrationFiles(): array
{
    $files = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($files, SORT_NATURAL);

    return array_map('basename', $files);
}

/** @return array<string,bool> */
function appliedVersions(PDO $pdo): array
{
    $rows = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

    return array_fill_keys($rows, true);
}

/** @return list<string> */
function statementsIn(string $path): array
{
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }

    $kept = [];

    foreach (explode("\n", $sql) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }

        $kept[] = $line;
    }

    $statements = [];

    foreach (explode(';', implode("\n", $kept)) as $statement) {
        $statement = trim($statement);

        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    return $statements;
}

function runFile(PDO $pdo, string $file, int $batch): void
{
    $path = __DIR__ . '/migrations/' . $file;
    $applied = 0;
    $skipped = 0;

    foreach (statementsIn($path) as $statement) {
        try {
            $pdo->exec($statement);
            $applied++;
        } catch (PDOException $e) {
            $errno = (int) ($e->errorInfo[1] ?? 0);

            if (!in_array($errno, ALREADY_DONE, true)) {
                throw new RuntimeException($file . ": " . $e->getMessage() . "\n  --> " . shorten($statement), 0, $e);
            }

            $skipped++;
        }
    }

    $record = $pdo->prepare('INSERT INTO schema_migrations (version, batch) VALUES (?, ?) ON DUPLICATE KEY UPDATE batch = VALUES(batch)');
    $record->execute([$file, $batch]);

    printf("  %-46s %2d statement(s) applied, %2d already present\n", $file, $applied, $skipped);
}

function shorten(string $statement): string
{
    $oneLine = preg_replace('/\s+/', ' ', $statement) ?? $statement;

    return substr($oneLine, 0, 120) . (strlen($oneLine) > 120 ? '…' : '');
}

function dropEverything(PDO $pdo, string $database): void
{
    $tables = $pdo
        ->query('SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = \'BASE TABLE\'', [$database])
        ->fetchAll(PDO::FETCH_COLUMN);

    if ($tables === []) {
        return;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($tables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    printf("  dropped %d table(s)\n", count($tables));
}

try {
    switch ($command) {
        case 'status':
            $applied = appliedVersions($pdo);
            $pending = migrationFiles();
            echo "Database: {$database}\n\n";

            foreach ($pending as $file) {
                printf("  [%s] %s\n", isset($applied[$file]) ? 'ran ' : 'PENDING', $file);
            }

            printf("\n  %d of %d applied\n", count($applied), count($pending));
            break;

        case 'up':
            $applied = appliedVersions($pdo);
            $files = array_values(array_filter(migrationFiles(), static fn ($f) => !isset($applied[$f])));

            if ($files === []) {
                echo "Nothing to migrate — schema is up to date.\n";
                break;
            }

            $batch = (int) ($pdo->query('SELECT COALESCE(MAX(batch), 0) FROM schema_migrations')->fetchColumn()) + 1;
            echo "Applying " . count($files) . " migration(s) into {$database} (batch {$batch}):\n";

            foreach ($files as $file) {
                runFile($pdo, $file, $batch);
            }

            echo "Done.\n";
            break;

        case 'fresh':
            if (!$forced) {
                fwrite(STDERR, "fresh drops every table in {$database}. Re-run with --force to confirm.\n");
                exit(1);
            }

            echo "Rebuilding {$database} from scratch:\n";
            dropEverything($pdo);
            $ensureBookkeeping($pdo);

            foreach (migrationFiles() as $file) {
                runFile($pdo, $file, 1);
            }

            echo "Done. Run php database/seed.php to load starter data.\n";
            break;

        default:
            fwrite(STDERR, "Usage: php database/migrate.php [status|up|fresh --force]\n");
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
