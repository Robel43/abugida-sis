<?php
/** Stage 2 transactions, audit and notification delivery using RosarioSIS permissions. */
require_once __DIR__ . '/AbugidaEmail.fnc.php';

function AbugidaWorkflowDB(): PDO
{
    global $DatabaseServer, $DatabasePort, $DatabaseName, $DatabaseUsername, $DatabasePassword;
    static $db;
    if (!$db) {
        $db = new PDO('mysql:host=' . $DatabaseServer . ';port=' . ($DatabasePort ?? 3306) . ';dbname=' . $DatabaseName . ';charset=utf8mb4', $DatabaseUsername, $DatabasePassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $db;
}

function AbugidaStaffAllowed(string $module, bool $edit = false): bool
{
    return User('PROFILE') === 'admin' && (int)User('STAFF_ID') > 0 && AllowUse($module) && (!$edit || AllowEdit($module));
}

function AbugidaCsrfField(): string
{
    if (empty($_SESSION['abugida_stage2_csrf'])) { $_SESSION['abugida_stage2_csrf'] = bin2hex(random_bytes(32)); }
    return '<input type="hidden" name="abugida_csrf" value="' . htmlspecialchars($_SESSION['abugida_stage2_csrf'], ENT_QUOTES, 'UTF-8') . '">' .
        '<input type="hidden" name="token" value="' . htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
}

function AbugidaValidStaffPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_SESSION['abugida_stage2_csrf']) &&
        isset($_POST['abugida_csrf']) && is_string($_POST['abugida_csrf']) &&
        hash_equals($_SESSION['abugida_stage2_csrf'], $_POST['abugida_csrf']);
}

function AbugidaRequireStaffPost(string $module): void
{
    if (!AbugidaStaffAllowed($module, true)) { throw new RuntimeException('You do not have permission to change this application.'); }
    if (!AbugidaValidStaffPost()) { throw new RuntimeException('Your session expired. Refresh the page and try again.'); }
}

function AbugidaConfiguredGrade(PDO $db, int $school, int $number): int
{
    $query = $db->prepare('SELECT id, title, short_name FROM school_gradelevels WHERE school_id=? ORDER BY sort_order,id');
    $query->execute([$school]);
    $matches = [];
    foreach ($query as $grade) {
        foreach ([$grade['title'], $grade['short_name']] as $candidate) {
            if (preg_match('/(^|[^0-9])' . $number . '([^0-9]|$)/', (string)$candidate)) { $matches[(int)$grade['id']] = true; }
        }
    }
    if (count($matches) > 1) { throw new RuntimeException('More than one configured grade matches this applicant; review the grade configuration.'); }
    return (int)(array_key_first($matches) ?? 0);
}

function AbugidaAudit(PDO $db, array $applicant, string $to, string $action, string $actor, string $reason = ''): int
{
    $query = $db->prepare('INSERT INTO abugida_application_history (applicant_id,from_status,to_status,action,actor_type,actor_id,actor_name,reason) VALUES (?,?,?,?,?,?,?,?)');
    $query->execute([$applicant['id'], $applicant['status'], $to, $action, $actor,
        $actor === 'APPLICANT' ? null : (int)User('STAFF_ID'),
        $actor === 'APPLICANT' ? trim($applicant['first_name'] . ' ' . $applicant['last_name']) : User('NAME'), $reason ?: null]);
    return (int)$db->lastInsertId();
}

function AbugidaNotification(array $applicant, string $status, string $reason): array
{
    $name = trim($applicant['first_name'] . ' ' . $applicant['last_name']);
    $intro = "Dear $name,\n\nApplication reference: {$applicant['application_reference']}\n\n";
    if ($status === 'APPROVED_FOR_PAYMENT') {
        return ['Abugida SIS application approved', $intro . 'Your application has been approved.' . "\n\nAmount payable: ETB " . number_format((float)$applicant['payment_amount'], 2) .
            "\n\nPayment instructions:\n{$applicant['payment_instructions']}\n\nPayment link:\n" . AbugidaPublicURL('registration-payment.php', ['token' => $applicant['payment_access_token']])];
    }
    if ($status === 'DECLINED') {
        return ['Abugida SIS application update', $intro . "Your application requires correction.\n\nReason: $reason\n\nUse the same phone number to correct and resubmit your existing application:\n" . AbugidaPublicURL('registration.php')];
    }
    if ($status === 'PAYMENT_VERIFIED') {
        return ['Abugida SIS payment verified', $intro . "Your payment of ETB " . number_format((float)$applicant['payment_amount'], 2) . " has been verified. The Registrar will complete your enrollment.\n\nStatus:\n" . AbugidaPublicURL('registration-payment.php', ['token' => $applicant['payment_access_token']])];
    }
    return ['Abugida SIS payment update', $intro . "Your payment proof requires correction.\n\nReason: $reason\n\nUpload a corrected receipt here:\n" . AbugidaPublicURL('registration-payment.php', ['token' => $applicant['payment_access_token']])];
}

function AbugidaReviewDecision(int $id, string $actor, string $decision, string $reason = ''): int
{
    $module = $actor === 'REGISTRAR' ? 'Custom/ApplicationReview.php' : 'Custom/FinanceApplications.php';
    AbugidaRequireStaffPost($module);
    if (!in_array($actor, ['REGISTRAR', 'FINANCE'], true) || !in_array($decision, ['approve', 'reject'], true)) { throw new RuntimeException('Invalid review decision.'); }
    $reason = trim($reason);
    if ($decision === 'reject' && $reason === '') { throw new RuntimeException('A rejection reason is required.'); }
    if (strlen($reason) > 10000) { throw new RuntimeException('The rejection reason is too long.'); }
    $db = AbugidaWorkflowDB();
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT * FROM abugida_applicants WHERE id=? FOR UPDATE');
        $query->execute([$id]); $applicant = $query->fetch(PDO::FETCH_ASSOC);
        $allowed = $actor === 'REGISTRAR' ? ['SUBMITTED', 'UNDER_REVIEW'] : ['PAYMENT_SUBMITTED'];
        if (!$applicant || !in_array($applicant['status'], $allowed, true)) { throw new RuntimeException('This application was already reviewed or is not awaiting this decision.'); }
        if ($actor === 'REGISTRAR') {
            $to = $decision === 'approve' ? 'APPROVED_FOR_PAYMENT' : 'DECLINED';
            if ($decision === 'approve') {
                $grade = AbugidaConfiguredGrade($db, (int)UserSchool(), (int)$applicant['grade_level']);
                $fee = $db->prepare('SELECT amount FROM abugida_registration_fees WHERE school_id=? AND syear=? AND grade_id=?');
                $fee->execute([(int)UserSchool(), (int)UserSyear(), $grade]); $amount = $fee->fetchColumn();
                if (!$grade || $amount === false || (float)$amount < 0) { throw new RuntimeException('No valid registration fee is configured for this grade and school year. Set it under Student Billing > Registration Fees.'); }
                $applicant['payment_amount'] = $amount;
                $applicant['payment_instructions'] = trim(AbugidaMailSetting('ABUGIDA_PAYMENT_INSTRUCTIONS', 'AbugidaPaymentInstructions', 'Pay the registration fee using the school payment instructions, then upload your payment receipt here. Contact the school for the bank/account details.'));
                if (!$applicant['payment_instructions']) { throw new RuntimeException('Configure the school payment instructions locally.'); }
                $applicant['payment_access_token'] = bin2hex(random_bytes(32));
                $update = $db->prepare('UPDATE abugida_applicants SET status=?,registrar_decision_reason=NULL,registrar_reviewed_at=NOW(),registrar_reviewed_by=?,payment_amount=?,payment_instructions=?,payment_status=\'PENDING\',payment_access_token=? WHERE id=?');
                $update->execute([$to, (int)User('STAFF_ID'), $amount, $applicant['payment_instructions'], $applicant['payment_access_token'], $id]);
            } else {
                $update = $db->prepare('UPDATE abugida_applicants SET status=?,registrar_decision_reason=?,registrar_reviewed_at=NOW(),registrar_reviewed_by=? WHERE id=?');
                $update->execute([$to, $reason, (int)User('STAFF_ID'), $id]);
            }
        } else {
            if (empty($applicant['receipt_stored_name'])) { throw new RuntimeException('This application has no submitted receipt.'); }
            $to = $decision === 'approve' ? 'PAYMENT_VERIFIED' : 'PAYMENT_DECLINED';
            $update = $db->prepare('UPDATE abugida_applicants SET status=?,payment_status=?,finance_decision_reason=?,finance_reviewed_at=NOW(),finance_reviewed_by=? WHERE id=?');
            $update->execute([$to, $decision === 'approve' ? 'VERIFIED' : 'DECLINED', $decision === 'reject' ? $reason : null, (int)User('STAFF_ID'), $id]);
        }
        $history = AbugidaAudit($db, $applicant, $to, $actor === 'REGISTRAR' ? ($decision === 'approve' ? 'Application approved for payment' : 'Application rejected') : ($decision === 'approve' ? 'Payment verified' : 'Payment rejected'), $actor, $reason);
        [$subject, $message] = AbugidaNotification($applicant, $to, $reason);
        $queue = $db->prepare('INSERT INTO abugida_email_outbox (history_id,applicant_id,actor_type,expected_status,recipient,subject,message) VALUES (?,?,?,?,?,?,?)');
        $queue->execute([$history, $id, $actor, $to, $applicant['email'], $subject, $message]);
        $notification = (int)$db->lastInsertId();
        $db->commit();
        return $notification;
    } catch (Throwable $exception) { if ($db->inTransaction()) { $db->rollBack(); } throw $exception; }
}

function AbugidaDeliverNotification(int $id, int $applicantId, string $actor): string
{
    $module = $actor === 'REGISTRAR' ? 'Custom/ApplicationReview.php' : 'Custom/FinanceApplications.php';
    AbugidaRequireStaffPost($module);
    $domain = parse_url(AbugidaPublicURL('registration.php'), PHP_URL_HOST);
    $db = AbugidaWorkflowDB();
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT * FROM abugida_email_outbox WHERE id=? AND applicant_id=? AND actor_type=? FOR UPDATE');
        $query->execute([$id, $applicantId, $actor]); $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Notification not found or outside this review queue.'); }
        if (!in_array($row['delivery_status'], ['PENDING', 'FAILED'], true)) { $db->commit(); return $row['delivery_status']; }
        $current = $db->prepare('SELECT status FROM abugida_applicants WHERE id=?');
        $current->execute([$applicantId]);
        if ($current->fetchColumn() !== $row['expected_status']) {
            $db->prepare("UPDATE abugida_email_outbox SET delivery_status='CANCELLED',last_error='Application has moved on; obsolete notification suppressed.' WHERE id=?")->execute([$id]);
            $db->commit(); return 'CANCELLED';
        }
        $db->prepare("UPDATE abugida_email_outbox SET delivery_status='SENDING',attempts=attempts+1,attempted_at=NOW(),last_error=NULL WHERE id=?")->execute([$id]);
        $db->commit();
    } catch (Throwable $exception) { if ($db->inTransaction()) { $db->rollBack(); } throw $exception; }
    // A committed action never rolls back because SMTP is unavailable.
    $sent = AbugidaSendEmail($row['recipient'], $row['subject'], $row['message'], '<abugida-notification-' . $id . '@' . $domain . '>');
    $state = $sent ? 'SENT' : ($GLOBALS['AbugidaMailLastState'] ?? 'FAILED');
    $db->prepare("UPDATE abugida_email_outbox SET delivery_status=?,last_error=?,sent_at=IF(?='SENT',NOW(),NULL) WHERE id=? AND delivery_status='SENDING'")->execute([$state, $GLOBALS['AbugidaMailLastError'] ?: null, $state, $id]);
    return $state;
}

function AbugidaNotificationPanel(int $applicantId, string $actor): void
{
    $module = $actor === 'REGISTRAR' ? 'Custom/ApplicationReview.php' : 'Custom/FinanceApplications.php';
    $query = AbugidaWorkflowDB()->prepare('SELECT id,subject,delivery_status,attempts,last_error FROM abugida_email_outbox WHERE applicant_id=? AND actor_type=? ORDER BY id DESC');
    $query->execute([$applicantId, $actor]);
    echo '<h3>Email notifications</h3>';
    foreach ($query as $row) {
        echo '<p>' . AttrEscape($row['subject']) . ': <b>' . AttrEscape($row['delivery_status']) . '</b> (' . (int)$row['attempts'] . ' attempts)';
        if ($row['last_error']) { echo '<br>' . AttrEscape($row['last_error']); }
        echo '</p>';
        if (in_array($row['delivery_status'], ['PENDING', 'FAILED'], true) && AbugidaStaffAllowed($module, true)) {
            echo '<form method="post" action="' . URLEscape('Modules.php?modname=' . $module . '&applicant_id=' . $applicantId . '&modfunc=retry_email') . '">' . AbugidaCsrfField() .
                '<input type="hidden" name="notification_id" value="' . (int)$row['id'] . '"><button type="submit">Retry notification only</button></form>';
        }
        if (in_array($row['delivery_status'], ['UNKNOWN', 'SENDING'], true)) { echo '<p>Confirm delivery with the mail administrator before considering a resend. Automatic retry is blocked to avoid duplicates.</p>'; }
    }
}

function AbugidaSubmitReceipt(int $id, string $phone, array $upload): void
{
    $db = AbugidaWorkflowDB(); $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT * FROM abugida_applicants WHERE id=? AND phone=? FOR UPDATE');
        $query->execute([$id, $phone]); $applicant = $query->fetch(PDO::FETCH_ASSOC);
        if (!$applicant || !in_array($applicant['status'], ['APPROVED_FOR_PAYMENT', 'PAYMENT_DECLINED'], true)) { throw new RuntimeException('This application is not accepting a receipt.'); }
        $db->prepare("UPDATE abugida_applicants SET status='PAYMENT_SUBMITTED',payment_status='SUBMITTED',receipt_stored_name=?,receipt_original_name=?,receipt_mime_type=?,receipt_size=?,receipt_submitted_at=NOW(),finance_decision_reason=NULL WHERE id=?")
            ->execute([$upload['stored'], $upload['original'], $upload['mime'], $upload['size'], $id]);
        AbugidaAudit($db, $applicant, 'PAYMENT_SUBMITTED', 'Payment receipt submitted', 'APPLICANT');
        $db->commit();
    } catch (Throwable $exception) { if ($db->inTransaction()) { $db->rollBack(); } throw $exception; }
}

function AbugidaSaveRegistration(int $id, string $phone, array $fields, bool $submit): void
{
    $db = AbugidaWorkflowDB(); $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT * FROM abugida_applicants WHERE id=? AND phone=? FOR UPDATE');
        $query->execute([$id, $phone]); $applicant = $query->fetch(PDO::FETCH_ASSOC);
        if (!$applicant || !in_array($applicant['status'], ['DRAFT', 'DECLINED'], true)) { throw new RuntimeException('This application is no longer editable.'); }
        $allowed = ['first_name', 'last_name', 'email', 'grade_level', 'study_approach', 'current_step',
            'document_stored_name', 'document_original_name', 'document_mime_type', 'document_size',
            'fayda_stored_name', 'fayda_original_name', 'fayda_mime_type', 'fayda_size'];
        $set = []; $values = [];
        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) { throw new RuntimeException('Invalid registration field.'); }
            $set[] = $column . '=?'; $values[] = $value;
        }
        $set[] = 'updated_at=NOW()';
        if ($submit) { $set[] = "status='SUBMITTED'"; $set[] = 'submitted_at=NOW()'; $set[] = 'registrar_decision_reason=NULL'; }
        $values[] = $id;
        $db->prepare('UPDATE abugida_applicants SET ' . implode(',', $set) . ' WHERE id=?')->execute($values);
        if ($submit) { AbugidaAudit($db, $applicant, 'SUBMITTED', $applicant['status'] === 'DECLINED' ? 'Application corrected and resubmitted' : 'Application submitted', 'APPLICANT'); }
        $db->commit();
    } catch (Throwable $exception) { if ($db->inTransaction()) { $db->rollBack(); } throw $exception; }
}
