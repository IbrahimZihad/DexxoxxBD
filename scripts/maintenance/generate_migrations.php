<?php

declare(strict_types=1);

/**
 * Development-time scaffolder: compiles the declarative table specs in
 * database/schema/*.php into versioned Phinx migration files.
 *
 * The generated files under database/migrations are the source of truth from
 * then on; this script never runs during a request or a deploy. It exists so
 * fifty tables can be written once, validated (naming, ordering, foreign-key
 * targets, identifier limits) and emitted in a consistent style.
 *
 * Usage: php scripts/maintenance/generate_migrations.php [--force]
 */

$root = dirname(__DIR__, 2);
$targetDir = $root . '/database/migrations';
$force = in_array('--force', array_slice($argv, 1), true);

/** @var list<string> $specFiles */
$specFiles = glob($root . '/database/schema/*.php');
sort($specFiles, SORT_STRING);

if ($specFiles === false || $specFiles === []) {
    fwrite(STDERR, "No schema specs found in database/schema.\n");
    exit(1);
}

$tables = [];
foreach ($specFiles as $specFile) {
    $specs = require $specFile;
    if (!is_array($specs)) {
        fwrite(STDERR, sprintf("Spec %s did not return an array.\n", basename($specFile)));
        exit(1);
    }
    foreach ($specs as $spec) {
        $spec['_origin'] = basename($specFile);
        $tables[] = $spec;
    }
}

$errors = [];
$byTable = [];
$previousVersion = 0;

foreach ($tables as $spec) {
    foreach (['version', 'class', 'table', 'comment', 'columns'] as $required) {
        if (!isset($spec[$required])) {
            $errors[] = sprintf('%s: missing "%s.": %s', $spec['_origin'], $required, json_encode($spec['table'] ?? '?'));
        }
    }

    $version = (string) ($spec['version'] ?? '');
    if (preg_match('/^\d{14}$/', $version) !== 1) {
        $errors[] = sprintf('%s: version %s is not 14 digits.', $spec['_origin'], $version);
    } elseif ((int) $version <= $previousVersion) {
        $errors[] = sprintf('%s: version %s is not greater than %d.', $spec['_origin'], $version, $previousVersion);
    } else {
        $previousVersion = (int) $version;
    }

    $class = (string) ($spec['class'] ?? '');
    if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $class) !== 1) {
        $errors[] = sprintf('%s: class %s is not StudlyCase.', $spec['_origin'], $class);
    }

    $table = (string) ($spec['table'] ?? '');
    if ($table !== '' && preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1) {
        $errors[] = sprintf('%s: table name %s must be snake_case.', $spec['_origin'], $table);
    }

    if ($table !== '' && isset($byTable[$table])) {
        $errors[] = sprintf('Table %s declared twice (%s, %s).', $table, $byTable[$table]['_origin'], $spec['_origin']);
    }

    $columnNames = [];
    foreach ((array) ($spec['columns'] ?? []) as $column) {
        if (!is_array($column) || count($column) < 2) {
            $errors[] = sprintf('%s: column definition malformed in %s.', $spec['_origin'], $table);
            continue;
        }
        $name = (string) $column[0];
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            $errors[] = sprintf('%s.%s: column name must be snake_case.', $table, $name);
        }
        if (in_array($name, $columnNames, true)) {
            $errors[] = sprintf('%s: duplicate column %s.', $table, $name);
        }
        $columnNames[] = $name;
    }

    if ($columnNames === [] || $columnNames[0] !== 'id') {
        $errors[] = sprintf('%s: the first column must be id.', $table);
    }

    $byTable[$table] = $spec;
    $byTable[$table]['_columns'] = $columnNames;
}

// Foreign keys and indexes are validated against the whole set so a typo in a
// column or table name fails at generation time, not at migrate time.
foreach ($byTable as $table => $spec) {
    $position = array_search($table, array_keys($byTable), true);

    foreach ((array) ($spec['keys'] ?? []) as $key) {
        $columns = is_array($key['column'] ?? null) ? $key['column'] : [(string) ($key['column'] ?? '')];
        foreach ($columns as $column) {
            if (!in_array($column, $spec['_columns'], true)) {
                $errors[] = sprintf('%s: foreign key %s uses unknown column %s.', $table, $key['name'] ?? '?', $column);
            }
        }

        $referenced = (string) ($key['table'] ?? '');
        if (!isset($byTable[$referenced])) {
            $errors[] = sprintf('%s: foreign key %s points at unknown table %s.', $table, $key['name'] ?? '?', $referenced);
            continue;
        }

        $referencedColumns = is_array($key['referenced_column'] ?? null)
            ? $key['referenced_column']
            : [(string) ($key['referenced_column'] ?? 'id')];

        foreach ($referencedColumns as $column) {
            if (!in_array($column, $byTable[$referenced]['_columns'], true)) {
                $errors[] = sprintf('%s: foreign key %s references unknown column %s.%s.', $table, $key['name'] ?? '?', $referenced, $column);
            }
        }

        if ($referenced !== $table && $position === false) {
            $errors[] = sprintf('%s: foreign key target %s is created later.', $table, $referenced);
        }
    }

    foreach ((array) ($spec['indexes'] ?? []) as $index) {
        $name = (string) ($index[0] ?? '');
        $columns = (array) ($index[1] ?? []);

        if (strlen($name) > 64) {
            $errors[] = sprintf('%s: index name %s exceeds 64 characters.', $table, $name);
        }
        if ($columns === []) {
            $errors[] = sprintf('%s: index %s has no columns.', $table, $name);
        }
        foreach ($columns as $column) {
            if (!in_array((string) $column, $spec['_columns'], true)) {
                $errors[] = sprintf('%s: index %s uses unknown column %s.', $table, $name, (string) $column);
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Schema specs are invalid:\n  - " . implode("\n  - ", $errors) . "\n");
    exit(1);
}

if (is_dir($targetDir) === false && mkdir($targetDir, 0775, true) === false) {
    fwrite(STDERR, "Cannot create {$targetDir}\n");
    exit(1);
}

$written = 0;
$skipped = 0;

foreach ($byTable as $table => $spec) {
    $file = sprintf('%s/%s_%s.php', $targetDir, $spec['version'], snake_case_for_file($spec['class']));

    if (file_exists($file) && $force === false) {
        $skipped++;
        continue;
    }

    if (file_put_contents($file, render_migration($spec), LOCK_EX) === false) {
        fwrite(STDERR, "Cannot write {$file}\n");
        exit(1);
    }

    $written++;
}

printf(
    "Schema: %d tables -> %s (written %d, kept existing %d)\n",
    count($byTable),
    'database/migrations',
    $written,
    $skipped
);

/**
 * Phinx derives the class from the file name: VERSION_create_users_table.php
 * must declare CreateUsersTable, so the file name is the class in snake_case.
 */
function snake_case_for_file(string $class): string
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class) ?? $class);
}

/** @param array<string, mixed> $spec */
function render_migration(array $spec): string
{
    $table = (string) $spec['table'];
    $class = (string) $spec['class'];
    $lines = [];

    $lines[] = '<?php';
    $lines[] = '';
    $lines[] = 'declare(strict_types=1);';
    $lines[] = '';
    $lines[] = 'use Phinx\Migration\AbstractMigration;';
    $lines[] = '';
    $lines[] = '/**';
    $lines[] = ' * ' . wordwrap((string) $spec['comment'], 68, "\n * ", true);
    $lines[] = ' */';
    $lines[] = 'final class ' . $class . ' extends AbstractMigration';
    $lines[] = '{';
    $lines[] = '    public function up(): void';
    $lines[] = '    {';
    $lines[] = '        $table = $this->table(' . php_scalar($table) . ', [';
    $lines[] = '            \'id\' => false,';
    $lines[] = '            \'primary_key\' => [\'id\'],';
    $lines[] = '            \'engine\' => \'InnoDB\',';
    $lines[] = '            \'collation\' => \'utf8mb4_unicode_ci\',';
    $lines[] = '            \'comment\' => ' . php_scalar((string) $spec['comment']) . ',';
    $lines[] = '        ]);';
    $lines[] = '';
    $lines[] = '        $table';

    $columns = (array) $spec['columns'];
    $last = count($columns) - 1;
    foreach ($columns as $index => $column) {
        $name = (string) $column[0];
        $type = (string) $column[1];
        $options = (array) ($column[2] ?? []);
        $call = '->addColumn(' . php_scalar($name) . ', ' . php_scalar($type) . ', ' . php_array($options) . ')';
        $prefix = $index === 0 ? '        ' : '            ';
        $lines[] = $prefix . $call . ($index === $last ? ';' : '');
    }

    $lines[] = '        ;';
    $lines[] = '';

    foreach ((array) ($spec['indexes'] ?? []) as $index) {
        $name = (string) $index[0];
        $columnsForIndex = (array) $index[1];
        $options = (array) ($index[2] ?? []);
        $lines[] = '        $table->addIndex(' . php_array($columnsForIndex) . ', '
            . php_array($options + ['name' => $name]) . ');';
    }

    if (($spec['indexes'] ?? []) !== []) {
        $lines[] = '';
    }

    foreach ((array) ($spec['keys'] ?? []) as $key) {
        $lines[] = '        $table->addForeignKeyWithName(' . php_scalar((string) $key['name']) . ', '
            . (is_array($key['column']) ? php_array($key['column']) : php_scalar($key['column'])) . ', '
            . php_scalar((string) $key['table']) . ', '
            . (isset($key['referenced_column']) && is_array($key['referenced_column'])
                ? php_array($key['referenced_column'])
                : php_scalar($key['referenced_column'] ?? 'id')) . ', '
            . php_array([
                'delete' => (string) ($key['delete'] ?? 'RESTRICT'),
                'update' => (string) ($key['update'] ?? 'RESTRICT'),
            ]) . ');';
    }

    if (($spec['keys'] ?? []) !== []) {
        $lines[] = '';
    }

    $lines[] = '        $table->create();';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    public function down(): void';
    $lines[] = '    {';
    $lines[] = '        $this->table(' . php_scalar($table) . ')->drop()->save();';
    $lines[] = '    }';
    $lines[] = '}';
    $lines[] = '';

    return implode("\n", $lines);
}

function php_scalar(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value) . "'";
}

/** @param array<string|int, mixed> $values */
function php_array(array $values): string
{
    if ($values === []) {
        return '[]';
    }

    $parts = [];
    foreach ($values as $key => $value) {
        $entry = is_int($key) ? php_scalar($value) : php_scalar((string) $key) . ' => ' . php_value($value);
        $parts[] = $entry;
    }

    $inline = '[' . implode(', ', $parts) . ']';
    if (strlen($inline) <= 108 && !str_contains($inline, "\n")) {
        return $inline;
    }

    $body = '';
    foreach ($parts as $part) {
        $body .= '            ' . str_replace("\n", "\n            ", $part) . ",\n";
    }

    return "[\n" . $body . '        ]';
}

function php_value(mixed $value): string
{
    return is_array($value) ? php_array($value) : php_scalar($value);
}
