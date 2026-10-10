<?php
require '/var/www/html/config.inc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { exit(1); }
$before = new PDO('mysql:host=abugida-stage2-db;dbname=abugida_stage2_baseline', 'root', 'stage2-test-root-only');
// Main configuration contains only local connection settings; never print it.
require '/test-original-config.inc.php';
$main = new PDO("mysql:host=$DatabaseServer;dbname=$DatabaseName", $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $before->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND table_name NOT LIKE 'abugida%' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $old = $before->query("CHECKSUM TABLE `$table` EXTENDED")->fetch(PDO::FETCH_ASSOC)['Checksum'];
    $now = $main->query("CHECKSUM TABLE `$table` EXTENDED")->fetch(PDO::FETCH_ASSOC)['Checksum'];
    if ($old === null || $old !== $now) { throw new RuntimeException("Main SIS core data changed: $table"); }
}
echo 'PASS all ' . count($tables) . " main SIS core table checksums match the pre-Stage-2 backup\n";
