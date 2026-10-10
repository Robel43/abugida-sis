<?php
/** Use real RosarioSIS user/permission functions in isolated CLI transaction tests. */
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['HTTP_HOST'] = 'localhost:8091';
require '/var/www/html/Warehouse.php'; require '/var/www/html/ProgramFunctions/AbugidaWorkflow.fnc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { throw new RuntimeException('Refusing non-test database'); }
$role = $argv[1] ?? 'registrar';
$db = AbugidaWorkflowDB();
$q = $db->prepare('SELECT staff_id FROM staff WHERE username=?'); $q->execute(['stage2_' . $role]);
$_SESSION['STAFF_ID'] = (string)$q->fetchColumn(); $_SESSION['UserSyear'] = '2026'; $_SESSION['UserSchool'] = '1';
unset($_ROSARIO['User'], $_ROSARIO['AllowEdit'], $_ROSARIO['allow_edit']);
$_REQUEST['modname'] = 'Custom/ApplicationReview.php';
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SESSION['abugida_stage2_csrf'] = 'test-csrf'; $_POST['abugida_csrf'] = 'test-csrf';
$applicant = $db->query("SELECT * FROM abugida_applicants WHERE status='SUBMITTED' AND first_name LIKE 'REGISTRATION_TEST_%' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$applicant) { throw new RuntimeException('Run isolated registration regression first'); }
function verify($ok, $message) { if (!$ok) { throw new LogicException($message); } echo "PASS $message\n"; }
if ($role !== 'registrar') {
    verify(!AbugidaStaffAllowed('Custom/ApplicationReview.php', true), "Actual RosarioSIS $role permission denies edits");
    try { AbugidaReviewDecision($applicant['id'], 'REGISTRAR', 'approve'); throw new LogicException('Unauthorized action accepted'); }
    catch (RuntimeException $e) { verify(str_contains($e->getMessage(), 'permission'), "$role denied before any mutation using a valid CSRF token"); }
    exit;
}
verify(AbugidaStaffAllowed('Custom/ApplicationReview.php', true) && !AbugidaStaffAllowed('Custom/FinanceApplications.php', true), 'Registrar actual program permissions do not grant Finance authority');
$beforeHistory = $db->query('SELECT COUNT(*) FROM abugida_application_history')->fetchColumn();
$beforeOutbox = $db->query('SELECT COUNT(*) FROM abugida_email_outbox')->fetchColumn();
$AbugidaBaseURL = 'not a URL';
try { AbugidaReviewDecision($applicant['id'], 'REGISTRAR', 'approve'); throw new LogicException('Invalid base URL accepted'); }
catch (RuntimeException $e) { verify(str_contains($e->getMessage(), 'base URL'), 'Invalid URL reported without request-derived link'); }
$q = $db->prepare('SELECT * FROM abugida_applicants WHERE id=?'); $q->execute([$applicant['id']]);
verify($q->fetch(PDO::FETCH_ASSOC) === $applicant && $beforeHistory === $db->query('SELECT COUNT(*) FROM abugida_application_history')->fetchColumn() && $beforeOutbox === $db->query('SELECT COUNT(*) FROM abugida_email_outbox')->fetchColumn(), 'Applicant update, audit and outbox roll back together when notification composition fails');
$AbugidaBaseURL = 'http://localhost:8091';
$grade = AbugidaConfiguredGrade($db, 1, (int)$applicant['grade_level']);
$fee = $db->prepare('SELECT amount FROM abugida_registration_fees WHERE school_id=1 AND syear=2026 AND grade_id=?'); $fee->execute([$grade]); $amount = $fee->fetchColumn();
$db->prepare('UPDATE abugida_registration_fees SET amount=-1 WHERE school_id=1 AND syear=2026 AND grade_id=?')->execute([$grade]);
try {
    try { AbugidaReviewDecision($applicant['id'], 'REGISTRAR', 'approve'); throw new LogicException('Invalid fee accepted'); }
    catch (RuntimeException $e) { verify(str_contains($e->getMessage(), 'registration fee'), 'Invalid grade fee blocks approval'); }
} finally { $db->prepare('UPDATE abugida_registration_fees SET amount=? WHERE school_id=1 AND syear=2026 AND grade_id=?')->execute([$amount, $grade]); }
$q->execute([$applicant['id']]); verify($q->fetch(PDO::FETCH_ASSOC) === $applicant, 'Invalid fee leaves applicant untouched');
// Obsolete failures cannot send a correction notice after the applicant has progressed.
$id = $db->query("SELECT id FROM abugida_applicants WHERE first_name='STAGE2_TEST' AND status='PAYMENT_VERIFIED' ORDER BY id DESC LIMIT 1")->fetchColumn();
$obsolete = $db->query("SELECT id FROM abugida_email_outbox WHERE applicant_id=$id AND expected_status='DECLINED' ORDER BY id LIMIT 1")->fetchColumn();
$db->prepare("UPDATE abugida_email_outbox SET delivery_status='FAILED' WHERE id=?")->execute([$obsolete]);
verify(AbugidaDeliverNotification((int)$obsolete, (int)$id, 'REGISTRAR') === 'CANCELLED', 'Obsolete failed notification cancelled without SMTP resend');
