<?php
/** Run a single authorized action in its own process, to exercise real row locks. */
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['HTTP_HOST'] = 'localhost:8091';
require '/var/www/html/Warehouse.php'; require '/var/www/html/ProgramFunctions/AbugidaWorkflow.fnc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { exit(1); }
$db = AbugidaWorkflowDB();
$_SESSION['STAFF_ID'] = (string)$db->query("SELECT staff_id FROM staff WHERE username='stage2_registrar'")->fetchColumn();
$_SESSION['UserSyear'] = '2026'; $_SESSION['UserSchool'] = '1';
unset($_ROSARIO['User'], $_ROSARIO['AllowEdit'], $_ROSARIO['allow_edit']);
$_REQUEST['modname'] = 'Custom/ApplicationReview.php'; $_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['abugida_stage2_csrf'] = 'concurrency-csrf'; $_POST['abugida_csrf'] = 'concurrency-csrf';
if (($argv[1] ?? '') === 'decision') {
    try { AbugidaReviewDecision((int)$argv[2], 'REGISTRAR', 'approve'); echo 'accepted'; }
    catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'already reviewed')) { throw $e; } echo 'rejected'; }
} else { echo AbugidaDeliverNotification((int)$argv[2], (int)$argv[3], 'REGISTRAR'); }
