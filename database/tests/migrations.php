<?php
if (PHP_SAPI !== 'cli' || !in_array('--approved', $argv, true)) { exit("Requires --approved.\n"); }
require __DIR__ . '/../migrate-registration.php';
putenv('ABUGIDA_MIGRATION_CONFIG=' . __DIR__ . '/migration-config.php');
function expect(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function testDb(string $name): PDO {
    return new PDO("mysql:host=abugida-registration-test;dbname=$name;charset=utf8mb4", 'root', 'registration-test-only', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function applyTest(string $name): void {
    putenv('ABUGIDA_TEST_DATABASE=' . $name);
    runRegistrationMigrations(['test', '--apply', '--approved', '--backup=' . __DIR__ . '/../backups/registration-isolated-empty.sql']);
}
function seedBase(PDO $db): void { foreach (migrationStatements(registrationMigrations()[0]) as $sql) { $db->exec($sql); } }
function sentinel(PDO $db): array {
    $db->exec("INSERT INTO abugida_applicants (phone, first_name, application_reference, document_stored_name) VALUES ('251900000001', 'Existing applicant አቡጊዳ', 'PRESERVE-EXISTING', 'keep-existing.pdf')");
    return $db->query('SELECT * FROM abugida_applicants')->fetchAll(PDO::FETCH_ASSOC);
}
function preserved(PDO $db, array $before): void {
    $after = $db->query('SELECT * FROM abugida_applicants')->fetchAll(PDO::FETCH_ASSOC);
    expect(count($after) === count($before), 'Existing row count changed');
    foreach ($before as $i => $row) { foreach ($row as $key => $value) { expect($after[$i][$key] === $value, "Existing value changed: $key"); } }
    expect((int)$db->query('SELECT COUNT(*) FROM abugida_schema_migrations')->fetchColumn() === count(registrationMigrations()), 'Tracking incomplete');
}
putenv('ABUGIDA_TEST_DATABASE=reg_fresh');
try { runRegistrationMigrations(['test', '--apply', '--approved']); throw new LogicException('Missing backup accepted'); }
catch (RuntimeException $e) { expect(str_contains($e->getMessage(), 'backup'), 'Unexpected missing-backup error'); }
$db = testDb('reg_fresh');
expect(!(int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn(), 'Missing backup wrote schema');
applyTest('reg_fresh'); $before = sentinel($db);
$tracking = $db->query('SELECT * FROM abugida_schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC);
applyTest('reg_fresh'); preserved($db, $before);
expect($tracking === $db->query('SELECT * FROM abugida_schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC), 'Rerun rewrote tracking');
echo "PASS fresh setup, backup gate, rerun and tracking timestamps\n";
$db = testDb('reg_partial'); seedBase($db); $before = sentinel($db);
$db->exec('ALTER TABLE abugida_applicants ADD COLUMN fayda_stored_name VARCHAR(255) NULL AFTER document_size, ADD COLUMN registrar_decision_reason TEXT NULL AFTER status');
applyTest('reg_partial'); preserved($db, $before);
echo "PASS partially applied untracked schema preserves existing data\n";
$db = testDb('reg_complete');
foreach (registrationMigrations() as $file) { foreach (migrationStatements($file) as $sql) { $db->exec($sql); } }
$before = sentinel($db); applyTest('reg_complete'); preserved($db, $before);
echo "PASS complete untracked schema adopted without data changes\n";
$db->exec("UPDATE abugida_schema_migrations SET checksum=REPEAT('0',64) WHERE migration='001_online_registration.sql'");
try { applyTest('reg_complete'); throw new LogicException('Changed checksum accepted'); }
catch (RuntimeException $e) { expect(str_contains($e->getMessage(), 'Checksum changed'), 'Unexpected checksum error'); }
preserved($db, $before); echo "PASS checksum mismatch refused\n";
$db = testDb('reg_incompatible'); seedBase($db);
$db->exec('ALTER TABLE abugida_applicants MODIFY phone VARCHAR(10) NOT NULL');
$before = $db->query('SHOW CREATE TABLE abugida_applicants')->fetch(PDO::FETCH_NUM)[1];
try { applyTest('reg_incompatible'); throw new LogicException('Incompatible column accepted'); }
catch (RuntimeException $e) { expect(str_contains($e->getMessage(), 'Incompatible existing column'), 'Unexpected schema error'); }
expect($before === $db->query('SHOW CREATE TABLE abugida_applicants')->fetch(PDO::FETCH_NUM)[1], 'Failed preflight changed schema');
expect((int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn() === 1, 'Failed preflight created tracking');
echo "PASS incompatible schema refused before DDL\n";
require __DIR__ . '/../../rosariosis/config.inc.php';
$live = new PDO("mysql:host=$DatabaseServer;dbname=$DatabaseName", $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$restored = testDb('abugida_verify');
$tables = $restored->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $table = str_replace('`', '``', $table);
    $old = $restored->query("CHECKSUM TABLE `$table` EXTENDED")->fetch(PDO::FETCH_ASSOC)['Checksum'];
    $new = $live->query("CHECKSUM TABLE `$table` EXTENDED")->fetch(PDO::FETCH_ASSOC)['Checksum'];
    expect($old !== null && $old === $new, "Core data checksum changed: $table");
}
echo 'PASS all ' . count($tables) . " core table data checksums match the pre-migration backup\n";
