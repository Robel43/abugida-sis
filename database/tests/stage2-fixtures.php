<?php
/** Create synthetic staff/fees only in the isolated Stage 2 database. */
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['HTTP_HOST'] = 'localhost:8091';
require '/var/www/html/Warehouse.php';
require '/var/www/html/ProgramFunctions/AbugidaWorkflow.fnc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { throw new RuntimeException('Refusing non-test database'); }
$db = AbugidaWorkflowDB();
$modules = ['registrar' => ['Custom/ApplicationReview.php'], 'finance' => ['Custom/FinanceApplications.php'],
    'readonly' => ['Custom/ApplicationReview.php', 'Custom/FinanceApplications.php'],
    'email' => ['Custom/EmailTest.php', 'Custom/RegistrationFees.php'], 'denied' => []];
foreach ($modules as $role => $allowed) {
    $insert = $db->prepare("INSERT INTO staff (syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES (2026,'STAGE2_TEST',?,?,?,'admin',NULL,',1,',1,NOW())");
    $insert->execute([$role, 'stage2_' . $role, encrypt_password('Stage2-synthetic-login-123!')]);
    $id = $db->lastInsertId();
    foreach ($allowed as $module) { $db->prepare("INSERT INTO staff_exceptions (user_id,modname,can_use,can_edit) VALUES (?,?,'Y',?)")->execute([$id, $module, $role === 'readonly' ? 'N' : 'Y']); }
}
$db->exec("INSERT INTO abugida_registration_fees (school_id,syear,grade_id,amount) SELECT school_id,2026,id,1234.56 FROM school_gradelevels WHERE school_id=1 ON DUPLICATE KEY UPDATE amount=1234.56");
echo "Synthetic test users and grade fees created in isolated database only.\n";
