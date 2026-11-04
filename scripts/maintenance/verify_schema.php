<?php

declare(strict_types=1);

/**
 * Schema verifier. Reads the same specs that produced the migrations and checks
 * them against information_schema, so a hand-edited migration, a partial
 * migrate or a wrong engine/collation fails loudly instead of surprising the
 * app later.
 *
 * Usage: php scripts/maintenance/verify_schema.php [--quiet]
 */

$quiet = in_array('--quiet', array_slice($argv, 1), true);
$root = dirname(__DIR__, 2);

/** @var \App\Core\Database $db */
$db = require $root . '/bootstrap/database.php';

$specs = [];
foreach ((array) glob($root . '/database/schema/*.php') as $file) {
    foreach ((array) require $file as $spec) {
        $specs[$spec['table']] = $spec;
    }
}

ksort($specs);

$schemaName = (string) $db->scalar('SELECT DATABASE()');
$failures = [];
$engineCounts = [];
$collationCounts = [];
$counts = ['tables' => 0, 'columns' => 0, 'indexes' => 0, 'foreign_keys' => 0, 'decimal_columns' => 0, 'fulltext' => 0];

$tableRows = $db->select(
    'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = :schema',
    ['schema' => $schemaName]
);

$existingTables = [];
foreach ($tableRows as $row) {
    $existingTables[(string) $row['TABLE_NAME']] = $row;
}

$columnRows = $db->select(
    'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
     FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME, ORDINAL_POSITION',
    ['schema' => $schemaName]
);

$columnsByTable = [];
foreach ($columnRows as $row) {
    $columnsByTable[(string) $row['TABLE_NAME']][] = $row;
}

foreach ($specs as $table => $spec) {
    if (!isset($existingTables[$table])) {
        $failures[] = sprintf('%s: table is missing (has it been migrated?)', $table);
        continue;
    }

    $counts['tables']++;
    $engine = (string) ($existingTables[$table]['ENGINE'] ?? '');
    $collation = (string) ($existingTables[$table]['TABLE_COLLATION'] ?? '');
    $engineCounts[$engine] = ($engineCounts[$engine] ?? 0) + 1;
    $collationCounts[$collation] = ($collationCounts[$collation] ?? 0) + 1;

    if ($engine !== 'InnoDB') {
        $failures[] = sprintf('%s: engine is %s, InnoDB is required', $table, $engine === '' ? 'unknown' : $engine);
    }
    if ($collation !== 'utf8mb4_unicode_ci') {
        $failures[] = sprintf('%s: collation is %s, utf8mb4_unicode_ci is required', $table, $collation);
    }

    $actual = [];
    foreach ($columnsByTable[$table] ?? [] as $row) {
        $actual[(string) $row['COLUMN_NAME']] = $row;
    }

    foreach ($spec['columns'] as $column) {
        $name = (string) $column[0];
        $type = (string) $column[1];

        if (!isset($actual[$name])) {
            $failures[] = sprintf('%s.%s: column is missing', $table, $name);
            continue;
        }

        $counts['columns']++;
        $dataType = (string) $actual[$name]['DATA_TYPE'];

        if ($type === 'decimal' && $dataType !== 'decimal') {
            $failures[] = sprintf('%s.%s: money must be DECIMAL, found %s', $table, $name, $dataType);
        }
        if ($type === 'decimal') {
            $counts['decimal_columns']++;
        }
    }

    $id = $actual['id'] ?? null;
    if ($id === null) {
        $failures[] = sprintf('%s: has no id column', $table);
    } else {
        $columnType = (string) $id['COLUMN_TYPE'];
        // MariaDB still prints the display width; MySQL 8 does not.
        $columnType = (string) preg_replace('/\(\d+\)/', '', $columnType);
        $columnType = strtolower(trim($columnType));
        if ($columnType !== 'bigint unsigned') {
            $failures[] = sprintf('%s.id: expected BIGINT UNSIGNED, found %s', $table, $columnType);
        }
        if (!str_contains((string) $id['EXTRA'], 'auto_increment')) {
            $failures[] = sprintf('%s.id: is not AUTO_INCREMENT', $table);
        }
    }

    $indexes = $db->select(
        'SELECT DISTINCT INDEX_NAME, NON_UNIQUE, INDEX_TYPE FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
        ['schema' => $schemaName, 'table' => $table]
    );

    $indexNames = [];
    foreach ($indexes as $index) {
        $indexNames[(string) $index['INDEX_NAME']] = $index;
        $counts['indexes']++;
        if ((string) $index['INDEX_TYPE'] === 'FULLTEXT') {
            $counts['fulltext']++;
        }
    }

    if (!isset($indexNames['PRIMARY'])) {
        $failures[] = sprintf('%s: has no PRIMARY KEY', $table);
    }

    foreach ((array) ($spec['indexes'] ?? []) as $index) {
        $name = (string) $index[0];
        if (!isset($indexNames[$name])) {
            $failures[] = sprintf('%s: index %s is missing', $table, $name);
            continue;
        }
        $isUnique = (bool) ($index[2]['unique'] ?? false);
        if ($isUnique && (int) $indexNames[$name]['NON_UNIQUE'] === 1) {
            $failures[] = sprintf('%s: index %s is not unique', $table, $name);
        }
    }

    $foreignKeys = $db->select(
        'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND REFERENCED_TABLE_NAME IS NOT NULL',
        ['schema' => $schemaName, 'table' => $table]
    );

    $fkByName = [];
    foreach ($foreignKeys as $fk) {
        $fkByName[(string) $fk['CONSTRAINT_NAME']] = $fk;
        $counts['foreign_keys']++;
    }

    foreach ((array) ($spec['keys'] ?? []) as $key) {
        $name = (string) $key['name'];
        if (!isset($fkByName[$name])) {
            $failures[] = sprintf('%s: foreign key %s is missing', $table, $name);
            continue;
        }
        $referenced = (string) $fkByName[$name]['REFERENCED_TABLE_NAME'];
        if ($referenced !== (string) $key['table']) {
            $failures[] = sprintf('%s: foreign key %s points at %s, expected %s', $table, $name, $referenced, (string) $key['table']);
        }
    }
}

$orphans = array_diff(array_keys($existingTables), array_keys($specs), ['phinxlog']);
if ($orphans !== []) {
    $failures[] = 'Unexpected tables in the database: ' . implode(', ', $orphans);
}

$missingMigrations = count($specs) - $counts['tables'];

if ($missingMigrations !== 0) {
    $failures[] = sprintf('%d spec tables were not checked/migrated', $missingMigrations);
}

if (!$quiet) {
    printf("Database %s\n", $schemaName);
    printf(
        "Tables %d | columns %d | indexes %d (fulltext %d) | foreign keys %d | decimal money columns %d\n",
        $counts['tables'],
        $counts['columns'],
        $counts['indexes'],
        $counts['fulltext'],
        $counts['foreign_keys'],
        $counts['decimal_columns']
    );
    printf('Engines: %s', json_encode($engineCounts));
    printf("\nCollations: %s", json_encode($collationCounts));
    printf("\n");
}

if ($failures !== []) {
    fwrite(STDERR, "Schema problems:\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}

if (!$quiet) {
    echo "Schema OK: every spec table is InnoDB/utf8mb4 with the declared columns, indexes and foreign keys.\n";
}

exit(0);
