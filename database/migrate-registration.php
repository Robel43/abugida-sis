<?php
/** Explicit CLI-only MariaDB migrations; never loaded by RosarioSIS requests. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function registrationMigrations(): array
{
    $files = glob(__DIR__ . '/migrations/00[1-7]_*.sql');
    sort($files);
    if (count($files) !== 7) { throw new RuntimeException('Expected migrations 001–007.'); }
    return $files;
}

function migrationStatements(string $file): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}

function migrationColumns(string $statement): array
{
    if (!preg_match('/^(?:CREATE TABLE IF NOT EXISTS|ALTER TABLE) (\w+)/i', $statement, $table)) {
        throw new RuntimeException('Unsupported migration statement.');
    }
    preg_match_all('/(?:^\s*|ADD COLUMN\s+)(\w+)\s+((?:BIGINT|TINYINT|INT|VARCHAR\(\d+\)|DECIMAL\(\d+,\d+\)|TEXT|DATETIME)(?: UNSIGNED)?)\s+(NOT NULL|NULL)/im', $statement, $columns, PREG_SET_ORDER);
    return [$table[1], $columns];
}

function verifyMigration(PDO $db, string $file, bool $allowMissing): void
{
    foreach (migrationStatements($file) as $statement) {
        [$table, $columns] = migrationColumns($statement);
        $query = $db->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
        $query->execute([$table]);
        $actual = [];
        foreach ($query as $row) { $actual[strtolower($row['COLUMN_NAME'])] = $row; }
        foreach ($columns as $column) {
            $found = $actual[strtolower($column[1])] ?? null;
            if (!$found) {
                if ($allowMissing) { continue; }
                throw new RuntimeException("Missing $table.$column[1]");
            }
            $normalize = static fn($type) => preg_replace('/\b(bigint|tinyint|int)\(\d+\)/', '$1', strtolower($type));
            if ($normalize($found['COLUMN_TYPE']) !== $normalize($column[2]) ||
                $found['IS_NULLABLE'] !== ($column[3] === 'NULL' ? 'YES' : 'NO')) {
                throw new RuntimeException("Incompatible existing column $table.$column[1]; no data will be converted automatically.");
            }
        }
        preg_match_all('/(?:UNIQUE KEY|(?<!UNIQUE )KEY)\s+(\w+)\s*\(([^)]+)\)/i', $statement, $indexes, PREG_SET_ORDER);
        foreach ($indexes as $index) {
            $query = $db->prepare('SELECT COLUMN_NAME, NON_UNIQUE FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? ORDER BY SEQ_IN_INDEX');
            $query->execute([$table, $index[1]]);
            $rows = $query->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows && $allowMissing) { continue; }
            $unique = preg_match('/UNIQUE KEY\s+' . preg_quote($index[1], '/') . '\b/i', $statement);
            if (array_column($rows, 'COLUMN_NAME') !== array_map('trim', explode(',', $index[2])) ||
                (int)($rows[0]['NON_UNIQUE'] ?? -1) !== ($unique ? 0 : 1)) {
                throw new RuntimeException("Missing or incompatible index $table.$index[1]");
            }
        }
    }
}

function runRegistrationMigrations(array $argv): void
{
    $apply = in_array('--apply', $argv, true);
    $backup = '';
    foreach ($argv as $arg) { if (str_starts_with($arg, '--backup=')) { $backup = substr($arg, 9); } }
    require getenv('ABUGIDA_MIGRATION_CONFIG') ?: __DIR__ . '/../rosariosis/config.inc.php';
    if (($DatabaseType ?? '') !== 'mysql') { throw new RuntimeException('This runner requires MariaDB.'); }
    $db = new PDO('mysql:host=' . $DatabaseServer . ';port=' . ($DatabasePort ?? 3306) . ';dbname=' . $DatabaseName . ';charset=utf8mb4', $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (!str_contains($db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB')) { throw new RuntimeException('This runner requires MariaDB.'); }
    $files = registrationMigrations();
    // Preflight ALL existing objects before the first DDL statement.
    foreach ($files as $file) { verifyMigration($db, $file, true); }
    $tracked = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='abugida_schema_migrations'")->fetchColumn();
    $applied = $tracked ? $db->query('SELECT migration, checksum FROM abugida_schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    foreach ($files as $file) {
        $name = basename($file);
        if (isset($applied[$name])) {
            if (!hash_equals($applied[$name], hash_file('sha256', $file))) { throw new RuntimeException("Checksum changed: $name"); }
            verifyMigration($db, $file, false);
        }
        echo $name . (isset($applied[$name]) ? " applied\n" : " pending (existing compatible objects retained)\n");
    }
    if (!$apply) { echo "Read-only status. Use --apply --approved --backup=/path/to/full.sql after approval.\n"; return; }
    if (!in_array('--approved', $argv, true) || !is_file($backup) || filesize($backup) < 100) {
        throw new RuntimeException('Apply requires explicit approval and an existing full database backup.');
    }
    $dump = file_get_contents($backup);
    if (!str_contains($dump, '-- Dump completed on') || !str_contains($dump, 'CREATE DATABASE') || !str_contains($dump, '`' . $DatabaseName . '`')) {
        throw new RuntimeException('Backup must be a completed --databases dump of this database.');
    }
    if ((int)$db->query("SELECT GET_LOCK('abugida_registration_migrations', 10)")->fetchColumn() !== 1) { throw new RuntimeException('Another migration runner is active.'); }
    try {
        $db->exec('CREATE TABLE IF NOT EXISTS abugida_schema_migrations (migration VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        // Reload tracking under the lock to support simultaneous operators.
        $applied = $db->query('SELECT migration, checksum FROM abugida_schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                if (!hash_equals($applied[$name], hash_file('sha256', $file))) { throw new RuntimeException("Checksum changed: $name"); }
                verifyMigration($db, $file, false);
                continue;
            }
            foreach (migrationStatements($file) as $statement) {
                // MariaDB guards allow adoption of untracked and partially applied schemas.
                $statement = preg_replace('/ADD COLUMN\s+/i', 'ADD COLUMN IF NOT EXISTS ', $statement);
                $statement = preg_replace('/ADD UNIQUE KEY\s+/i', 'ADD UNIQUE KEY IF NOT EXISTS ', $statement);
                $db->exec($statement);
            }
            verifyMigration($db, $file, false);
            $record = $db->prepare('INSERT INTO abugida_schema_migrations (migration, checksum) VALUES (?, ?)');
            $record->execute([$name, hash_file('sha256', $file)]);
            echo "Verified and tracked $name\n";
        }
    } finally { $db->query("SELECT RELEASE_LOCK('abugida_registration_migrations')"); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { runRegistrationMigrations($argv); }
    catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
}
