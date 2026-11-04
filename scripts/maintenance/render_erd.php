<?php

/**
 * ERD renderer. Reads the schema back from information_schema - not the spec
 * files - so docs/erd.md can only ever describe the database that is actually
 * installed, and a hand-edited migration shows up in the next render.
 *
 * Usage: php scripts/maintenance/render_erd.php [--quiet]
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$quiet = in_array('--quiet', array_slice($argv ?? [], 1), true);

require $root . '/vendor/autoload.php';

if (!is_file($root . '/.env')) {
    fwrite(STDERR, "Missing .env - copy .env.example to .env first.\n");
    exit(1);
}

\App\Core\Env::load($root . '/.env');
\App\Core\Config::load($root . '/config');

/** @var \App\Core\Database $db */
$db = require $root . '/bootstrap/database.php';

$schema = (string) $db->scalar('SELECT DATABASE()');

// Domain grouping follows the spec files that generated the migrations.
$domains = [
    '01' => 'Accounts, roles and sessions',
    '02' => 'Catalogue: categories, brands, products and variations',
    '03' => 'Shopping: cart and wishlist',
    '04' => 'Orders, payments, delivery and subscriptions',
    '05' => 'Promotions: coupons and their restrictions',
    '06' => 'Engagement: reviews, tickets, notifications, newsletter',
    '07' => 'CMS: pages, FAQs, banners, testimonials, settings, menus',
    '08' => 'System: audit, jobs, logs, idempotency',
];

$tableDomain = [];
foreach (glob($root . '/database/schema/*.php') ?: [] as $specFile) {
    foreach ((array) require $specFile as $spec) {
        $tableDomain[(string) $spec['table']] = substr(basename($specFile), 0, 2);
    }
}

$tables = [];
foreach ($db->select(
    'SELECT TABLE_NAME AS name, TABLE_COMMENT AS comment FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> ? ORDER BY TABLE_NAME',
    [$schema, 'phinxlog']
) as $row) {
    $tables[(string) $row['name']] = (string) ($row['comment'] ?? '');
}

if ($tables === []) {
    fwrite(STDERR, "No tables found in {$schema}. Run phinx migrate first.\n");
    exit(1);
}

$columns = [];
$nullableColumns = [];
foreach ($db->select(
    'SELECT TABLE_NAME AS tbl, COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable,
        COLUMN_DEFAULT AS default_value, COLUMN_KEY AS tab_key, EXTRA AS extra
     FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> ?
     ORDER BY TABLE_NAME, ORDINAL_POSITION',
    [$schema, 'phinxlog']
) as $row) {
    $columns[(string) $row['tbl']][] = $row;
    $nullableColumns[(string) $row['tbl']][(string) $row['name']] = (string) $row['nullable'] === 'YES';
}

$indexes = [];
foreach ($db->select(
    'SELECT TABLE_NAME AS tbl, INDEX_NAME AS name, COLUMN_NAME AS column_name, NON_UNIQUE AS non_unique,
        INDEX_TYPE AS index_type
     FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> ?
     ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
    [$schema, 'phinxlog']
) as $row) {
    // Keyed by table and name: PRIMARY is PRIMARY in every table, and two tables
    // can carry the same auto-generated name, so the bare name is not an identity.
    $key = $row['tbl'] . '.' . $row['name'];
    if (!isset($indexes[$key])) {
        $indexes[$key] = [
            'name' => (string) $row['name'],
            'table' => (string) $row['tbl'],
            'unique' => (int) $row['non_unique'] === 0,
            'type' => (string) $row['index_type'],
            'columns' => [],
        ];
    }
    $indexes[$key]['columns'][] = (string) $row['column_name'];
}

$foreignKeys = [];
foreach ($db->select(
    'SELECT k.TABLE_NAME AS tbl, k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS column_name,
        k.REFERENCED_TABLE_NAME AS ref_table, k.REFERENCED_COLUMN_NAME AS ref_column, r.DELETE_RULE AS on_delete
     FROM information_schema.KEY_COLUMN_USAGE k
     INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
        ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     WHERE k.CONSTRAINT_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
     ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
    [$schema]
) as $row) {
    $name = (string) $row['name'];
    if (!isset($foreignKeys[$name])) {
        $foreignKeys[$name] = [
            'table' => (string) $row['tbl'],
            'columns' => [],
            'ref_table' => (string) $row['ref_table'],
            'ref_columns' => [],
            'on_delete' => (string) $row['on_delete'],
        ];
    }
    $foreignKeys[$name]['columns'][] = (string) $row['column_name'];
    $foreignKeys[$name]['ref_columns'][] = (string) $row['ref_column'];
}

// Mermaid data-type tokens may not contain spaces or parentheses. Integer
// display widths are MySQL noise, so they are dropped; decimal precision stays.
$typeToken = static function (string $type): string {
    $flat = trim((string) preg_replace('/[(),\s]+/', '_', $type), '_');
    $flat = (string) preg_replace(
        '/(tinyint|smallint|mediumint|bigint|int)_\d+_(signed|unsigned)/',
        '$1_$2',
        $flat
    );
    return strtolower($flat) !== '' ? strtolower($flat) : 'text';
};

$foreignKeyColumns = [];
foreach ($foreignKeys as $fk) {
    foreach ($fk['columns'] as $position => $column) {
        $foreignKeyColumns[$fk['table']][$column] = $fk['ref_table'] . '.' . $fk['ref_columns'][$position];
    }
}

$uniqueColumns = [];
foreach ($indexes as $index) {
    if (!$index['unique'] || $index['name'] === 'PRIMARY') {
        continue;
    }
    foreach ($index['columns'] as $column) {
        $uniqueColumns[$index['table']][$column] = true;
    }
}

// A NULL-able foreign key is a deliberate snapshot link: the child row survives and
// its reference is blanked when the parent goes. Every other foreign key column is
// mandatory, so a parent cannot be deleted out from under a row that needs it.
$fkColumnCount = 0;
$fkOptionalCount = 0;
foreach ($foreignKeys as $fk) {
    foreach ($fk['columns'] as $column) {
        $fkColumnCount++;
        if ($nullableColumns[$fk['table']][$column] ?? false) {
            $fkOptionalCount++;
        }
    }
}

// Domain -> tables, in the order information_schema returned them.
$tablesByDomain = [];
foreach ($tables as $name => $unused) {
    $tablesByDomain[$tableDomain[$name] ?? '??'][] = $name;
}

/**
 * One mermaid entity block, with PK/FK/UK markers from the live schema.
 *
 * @param list<array<string, mixed>> $rows
 */
$entityBlock = static function (string $table, array $rows, int $indent) use (
    $typeToken,
    $foreignKeyColumns,
    $uniqueColumns
): array {
    $pad = str_repeat(' ', $indent);
    $lines = [$pad . strtoupper($table) . ' {'];
    foreach ($rows as $column) {
        $name = (string) $column['name'];
        $marker = '';
        if ((string) $column['tab_key'] === 'PRI') {
            $marker = ' PK';
        } elseif (isset($foreignKeyColumns[$table][$name])) {
            $marker = ' FK';
        } elseif (isset($uniqueColumns[$table][$name])) {
            $marker = ' UK';
        }
        $lines[] = $pad . '    ' . $typeToken((string) $column['type']) . ' ' . $name . $marker;
    }
    $lines[] = $pad . '}';
    return $lines;
};

$out = [];
$out[] = '# Database design and entity-relationship diagram';
$out[] = '';
$out[] = '<!-- Generated by scripts/maintenance/render_erd.php - do not edit by hand. -->';
$out[] = '';
$out[] = 'Source of truth: the schema named `' . $schema . '` as read back from `information_schema` on '
    . date('Y-m-d') . '. It describes what is installed, not what was intended.';
$out[] = '';
$out[] = 'Conventions that hold for all ' . count($tables) . ' tables:';
$out[] = '';
$out[] = '- InnoDB, `utf8mb4`, `utf8mb4_unicode_ci`, `BIGINT UNSIGNED` surrogate keys with `AUTO_INCREMENT`.';
$out[] = '- Money in `DECIMAL(12,2)`; never floats, never a currency column per line - one BDT shop, currency stored on the order.';
$out[] = '- `created_at` / `updated_at` everywhere; `deleted_at` on catalogue, CMS and account records (soft delete).';
$out[] = '- Every foreign key is declared with an index on the child column, so joins and cascades stay fast.';
$out[] = '- A foreign key column is `NOT NULL` unless the row may genuinely be unassigned: guest carts, webhook '
    . 'events that land before their payment row, and `SET NULL` snapshot links that outlive the parent.';
$out[] = '- Historical snapshots: `order_items` and `cart_items` copy the name, price and plan they were bought as.';
$out[] = '- `stock = -1` means "not stock limited"; digital fulfilment is capped by the rows in `digital_inventory`.';
$out[] = '';
$out[] = '| Metric | Count |';
$out[] = '|---|---:|';
$out[] = '| Tables | ' . count($tables) . ' |';
$out[] = '| Columns | ' . array_sum(array_map('count', $columns)) . ' |';
$out[] = '| Indexes | ' . count($indexes) . ' |';
$out[] = '| Foreign keys | ' . count($foreignKeys) . ' |';
$out[] = '| Foreign key columns | ' . $fkColumnCount . ' (' . ($fkColumnCount - $fkOptionalCount)
    . ' mandatory, ' . $fkOptionalCount . ' optional) |';
$out[] = '| Full-text indexes | ' . count(array_filter($indexes, static fn (array $i): bool => $i['type'] === 'FULLTEXT')) . ' |';
$out[] = '';
$out[] = '## Domains';
$out[] = '';
$out[] = '| Domain | Tables |';
$out[] = '|---|---|';
foreach ($domains as $prefix => $label) {
    $names = $tablesByDomain[$prefix] ?? [];
    $out[] = '| ' . $label . ' | ' . implode(', ', array_map(
        static fn (string $name): string => '`' . $name . '`',
        $names
    )) . ' |';
}
$out[] = '';

foreach ($domains as $prefix => $label) {
    $domainTables = $tablesByDomain[$prefix] ?? [];
    if ($domainTables === []) {
        continue;
    }

    $edges = array_values(array_filter(
        $foreignKeys,
        static fn (array $fk): bool => in_array($fk['table'], $domainTables, true)
    ));

    // A parent table from another domain appears here so the diagram reads
    // without a second sheet of paper.
    $shown = $domainTables;
    foreach ($edges as $fk) {
        if (!in_array($fk['ref_table'], $shown, true)) {
            $shown[] = $fk['ref_table'];
        }
    }

    $out[] = '## ' . $label;
    $out[] = '';
    $out[] = '| Table | Purpose |';
    $out[] = '|---|---|';
    foreach ($domainTables as $name) {
        $out[] = '| `' . $name . '` | ' . ($tables[$name] !== '' ? $tables[$name] : '-') . ' |';
    }
    $out[] = '';

    if ($edges !== []) {
        $out[] = '```mermaid';
        $out[] = 'erDiagram';
        foreach ($shown as $name) {
            foreach ($entityBlock($name, $columns[$name] ?? [], 4) as $line) {
                $out[] = $line;
            }
        }
        foreach ($edges as $fk) {
            $nullable = false;
            foreach ($fk['columns'] as $column) {
                foreach ($columns[$fk['table']] ?? [] as $spec) {
                    if ((string) $spec['name'] === $column && (string) $spec['nullable'] === 'YES') {
                        $nullable = true;
                    }
                }
            }
            $left = $nullable ? '|o--o{' : '||--o{';
            $out[] = '    ' . strtoupper($fk['ref_table']) . ' ' . $left . ' ' . strtoupper($fk['table'])
                . ' : "' . implode('+', $fk['columns']) . ' (' . strtolower($fk['on_delete']) . ')"';
        }
        $out[] = '```';
    } else {
        $out[] = 'No foreign keys leave this domain: these tables are roots (lookups or audit streams).';
    }
    $out[] = '';
}

$out[] = '## Column reference';
$out[] = '';
foreach ($domains as $prefix => $label) {
    $domainTables = $tablesByDomain[$prefix] ?? [];
    if ($domainTables === []) {
        continue;
    }

    $out[] = '### ' . $label;
    $out[] = '';
    foreach ($domainTables as $name) {
        $out[] = '#### `' . $name . '`';
        $out[] = '';
        if ($tables[$name] !== '') {
            $out[] = $tables[$name];
            $out[] = '';
        }
        $out[] = '| Column | Type | Null | Key | Default |';
        $out[] = '|---|---|---|---|---|';
        foreach ($columns[$name] ?? [] as $column) {
            $key = (string) $column['tab_key'];
            if ($key === '' && isset($foreignKeyColumns[$name][(string) $column['name']])) {
                $key = 'MUL';
            }
            $default = $column['default_value'] === null
                ? ((string) $column['nullable'] === 'YES' ? 'NULL' : '-')
                : (string) $column['default_value'];
            $extra = trim((string) $column['extra']);
            $out[] = '| `' . (string) $column['name'] . '` | ' . (string) $column['type'] . ' | '
                . ((string) $column['nullable'] === 'YES' ? 'yes' : 'no') . ' | ' . $key . ' | '
                . $default . ($extra !== '' ? ' _( ' . $extra . ' )_' : '') . ' |';
        }
        $out[] = '';

        $tableIndexes = array_values(array_filter(
            $indexes,
            static fn (array $i): bool => $i['table'] === $name && $i['name'] !== 'PRIMARY'
        ));
        if ($tableIndexes !== []) {
            $out[] = 'Indexes: ' . implode('; ', array_map(static fn (array $i): string => ($i['unique'] ? 'UNIQUE ' : '')
                . $i['name'] . ' (' . implode(', ', $i['columns']) . ')'
                . ($i['type'] === 'FULLTEXT' ? ' FULLTEXT' : ''), $tableIndexes)) . '.';
            $out[] = '';
        }

        $tableForeignKeys = array_values(array_filter(
            $foreignKeys,
            static fn (array $fk): bool => $fk['table'] === $name
        ));
        if ($tableForeignKeys !== []) {
            $out[] = 'Foreign keys: ' . implode('; ', array_map(static fn (array $fk): string => '('
                . implode(', ', $fk['columns']) . ') -> ' . $fk['ref_table'] . '('
                . implode(', ', $fk['ref_columns']) . ') ON DELETE ' . strtolower($fk['on_delete']), $tableForeignKeys)) . '.';
            $out[] = '';
        }
    }
}

$target = $root . '/docs/erd.md';
if (file_put_contents($target, implode("\n", $out) . "\n") === false) {
    fwrite(STDERR, "Could not write {$target}.\n");
    exit(1);
}

if (!$quiet) {
    echo 'Wrote ' . $target . ' (' . count($out) . " lines) from {$schema}.\n";
}
