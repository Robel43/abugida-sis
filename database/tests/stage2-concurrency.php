<?php
require '/var/www/html/config.inc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { exit(1); }
$db = new PDO("mysql:host=$DatabaseServer;dbname=$DatabaseName", $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$id = $db->query("SELECT id FROM abugida_applicants WHERE status='SUBMITTED' AND first_name LIKE 'REGISTRATION_TEST_%' ORDER BY id DESC LIMIT 1")->fetchColumn();
if (!$id) { throw new RuntimeException('No synthetic pending applicant'); }
$history = (int)$db->query("SELECT COUNT(*) FROM abugida_application_history WHERE applicant_id=$id")->fetchColumn();
function twoProcesses(array $args): array {
    $running = [];
    for ($i = 0; $i < 2; $i++) { $process = proc_open(array_merge(['php', '/test/stage2-concurrency-action.php'], $args), [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes); fclose($pipes[0]); $running[] = [$process, $pipes]; }
    $output = [];
    foreach ($running as [$process, $pipes]) { $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); if (proc_close($process) !== 0) { throw new RuntimeException('Concurrent child failed: ' . $err); } $output[] = $out; }
    return $output;
}
$result = twoProcesses(['decision', (string)$id]); sort($result);
if ($result !== ['accepted', 'rejected']) { throw new RuntimeException('Concurrent duplicate decision accepted'); }
if ((int)$db->query("SELECT COUNT(*) FROM abugida_application_history WHERE applicant_id=$id")->fetchColumn() !== $history + 1) { throw new RuntimeException('Concurrent decision duplicated history'); }
$rows = $db->query("SELECT * FROM abugida_email_outbox WHERE applicant_id=$id")->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) !== 1) { throw new RuntimeException('Concurrent decision duplicated outbox'); }
echo "PASS simultaneous Registrar approvals produce one action, audit and notification\n";
$result = twoProcesses(['deliver', (string)$rows[0]['id'], (string)$id]);
$after = $db->query("SELECT * FROM abugida_email_outbox WHERE applicant_id=$id")->fetch(PDO::FETCH_ASSOC);
if ($after['delivery_status'] !== 'SENT' || (int)$after['attempts'] !== 1) { throw new RuntimeException('Concurrent delivery attempted more than once'); }
$messages = file('/runtime/messages.jsonl', FILE_IGNORE_NEW_LINES);
$matching = array_filter($messages, fn($message) => str_contains(json_decode($message, true)['message'], '<abugida-notification-' . $after['id'] . '@localhost>'));
if (count($matching) !== 1) { throw new RuntimeException('SMTP accepted a duplicate notification'); }
echo "PASS simultaneous notification retries result in one SMTP acceptance\n";
