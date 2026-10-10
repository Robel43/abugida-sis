<?php
/** Full Stage 2 HTTP tests using actual RosarioSIS logins and program permissions. */
require '/var/www/html/config.inc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { throw new RuntimeException('Refusing non-test database'); }
$db = new PDO("mysql:host=$DatabaseServer;dbname=$DatabaseName;charset=utf8mb4", $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$origin = 'http://abugida-stage2-web';
$checks = 0;
function ok($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function http(&$jar, $path, $fields = [], $files = []) {
    global $origin;
    $headers = ['Cookie: ' . implode('; ', array_filter( $jar, static fn( $key ) => !str_starts_with( $key, '_' ), ARRAY_FILTER_USE_KEY ))]; $body = '';
    if ($fields) {
        $boundary = 'stage2' . bin2hex(random_bytes(8)); $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
        foreach ($fields as $name => $value) { $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n"; }
        foreach ($files as $name => [$filename, $data]) { $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"; filename=\"$filename\"\r\nContent-Type: application/octet-stream\r\n\r\n$data\r\n"; }
        $body .= "--$boundary--\r\n";
    }
    $ctx = stream_context_create(['http' => ['method' => $fields ? 'POST' : 'GET', 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 45]]);
    $response = file_get_contents($origin . '/' . $path, false, $ctx);
    if ($response === false) { throw new RuntimeException('HTTP transport failed'); }
    foreach ($http_response_header as $header) { if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/', $header, $m)) { $jar[$m[1]] = $m[1] . '=' . $m[2]; } }
    foreach (['token', 'abugida_csrf'] as $name) { if (preg_match('/name="' . $name . '" value="([^"]*)"/', $response, $found)) { $jar['_' . $name] = html_entity_decode($found[1], ENT_QUOTES); } }
    $GLOBALS['activeTokens'] = $jar;
    preg_match('/ (\d{3}) /', $http_response_header[0], $m);
    return [(int)$m[1], $response];
}
function field($html, $name) {
    if (preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $html, $m)) { return html_entity_decode($m[1], ENT_QUOTES); }
    if (isset($GLOBALS['activeTokens']['_' . $name])) { return $GLOBALS['activeTokens']['_' . $name]; }
    throw new RuntimeException("Missing $name field");
}
function login($role) {
    $jar = []; http($jar, 'index.php');
    http($jar, 'index.php', ['USERNAME' => 'stage2_' . $role, 'PASSWORD' => 'Stage2-synthetic-login-123!']);
    return $jar;
}
function applicant($id) { global $db; $q = $db->prepare('SELECT * FROM abugida_applicants WHERE id=?'); $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC); }
function countHistory($id) { global $db; $q = $db->prepare('SELECT COUNT(*) FROM abugida_application_history WHERE applicant_id=?'); $q->execute([$id]); return (int)$q->fetchColumn(); }
function outbox($id) { global $db; $q = $db->prepare('SELECT * FROM abugida_email_outbox WHERE applicant_id=? ORDER BY id DESC LIMIT 1'); $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC); }
function decision(&$jar, $id, $module, $decision, $reason = '', $valid = true) {
    [, $html] = http($jar, "Modules.php?modname=$module&applicant_id=$id");
    return http($jar, "Modules.php?modname=$module&applicant_id=$id&modfunc=decision", ['token' => field($html, 'token'), 'abugida_csrf' => $valid ? field($html, 'abugida_csrf') : 'bad', 'decision' => $decision, 'reason' => $reason]);
}
function retryMail(&$jar, $id, $module, $notification) {
    [, $html] = http($jar, "Modules.php?modname=$module&applicant_id=$id");
    return http($jar, "Modules.php?modname=$module&applicant_id=$id&modfunc=retry_email", ['token' => field($html, 'token'), 'abugida_csrf' => field($html, 'abugida_csrf'), 'notification_id' => $notification]);
}
function createApplication($suffix, $email) {
    global $db;
    $jar = []; [, $html] = http($jar, 'registration.php');
    $phone = '2518' . substr( (string) time(), -8 ) . $suffix;
    http($jar, 'registration.php', ['csrf' => field($html, 'csrf'), 'action' => 'access', 'phone' => $phone]);
    [, $html] = http($jar, 'registration.php');
    $fields = ['csrf' => field($html, 'csrf'), 'action' => 'submit', 'first_name' => 'STAGE2_TEST', 'last_name' => 'Applicant_' . $suffix, 'email' => $email, 'grade_level' => '7', 'study_approach' => 'ONLINE'];
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII=');
    [$status, $response] = http($jar, 'registration.php', $fields, ['document' => ['support.png', $png], 'fayda_id' => ['fayda.png', $png]]);
    ok($status === 302, 'Applicant creation/submission failed: ' . strip_tags($response));
    $q = $db->prepare('SELECT id FROM abugida_applicants WHERE phone=?'); $q->execute([$phone]);
    return [(int)$q->fetchColumn(), $jar, $fields, $png];
}
$registrar = login('registrar'); $finance = login('finance'); $readonly = login('readonly'); $denied = login('denied'); $emailAdmin = login('email');
$review = 'Custom/ApplicationReview.php'; $payments = 'Custom/FinanceApplications.php';
$core = []; foreach (['students', 'student_enrollment'] as $table) { $core[$table] = $db->query("CHECKSUM TABLE $table EXTENDED")->fetch(PDO::FETCH_ASSOC)['Checksum']; }
[$id, $public, $fields, $png] = createApplication('01', 'success@example.invalid');
[, $queue] = http($registrar, "Modules.php?modname=$review");
ok(str_contains($queue, applicant($id)['application_reference']), 'Submitted applicant missing from Registrar queue');
$history = countHistory($id);
decision($registrar, $id, $review, 'approve', '', false);
ok(applicant($id)['status'] === 'SUBMITTED' && countHistory($id) === $history, 'Invalid CSRF changed application');
decision($registrar, $id, $review, 'reject'); ok(applicant($id)['status'] === 'SUBMITTED', 'Blank Registrar reason accepted');
decision($registrar, $id, $review, 'reject', 'Please correct your name <script>alert(1)</script>');
ok(applicant($id)['status'] === 'DECLINED' && outbox($id)['delivery_status'] === 'SENT', 'Registrar rejection or notification failed');
[, $html] = http($public, 'registration.php'); ok(str_contains($html, '&lt;script&gt;'), 'Applicant rejection reason is not escaped');
$fields['csrf'] = field($html, 'csrf'); $fields['last_name'] = 'Corrected applicant';
http($public, 'registration.php', $fields);
ok(applicant($id)['status'] === 'SUBMITTED' && applicant($id)['last_name'] === 'Corrected applicant', 'Correction/resubmission failed');
$q = $db->prepare('SELECT COUNT(*) FROM abugida_applicants WHERE phone=?'); $q->execute([applicant($id)['phone']]); ok((int)$q->fetchColumn() === 1, 'Resubmission duplicated applicant');
ok(countHistory($id) === $history + 2, 'Rejection/resubmission audit missing');
decision($registrar, $id, $review, 'approve');
$app = applicant($id); $mail = outbox($id);
ok($app['status'] === 'APPROVED_FOR_PAYMENT' && $app['payment_amount'] === '1234.56' && $mail['delivery_status'] === 'SENT', 'Approval fee or delivery failed');
ok(str_contains($mail['message'], 'STAGE2_TEST Corrected applicant') && str_contains($mail['message'], $app['application_reference']) && str_contains($mail['message'], '1,234.56') && str_contains($mail['message'], 'TEST BANK') && str_contains($mail['message'], 'http://localhost:8091/registration-payment.php?token=' . $app['payment_access_token']), 'Approval email content/link incorrect');
$token = $app['payment_access_token']; $history = countHistory($id);
// Repeat an old decision using the still-valid session token from the notification panel.
retryMail($registrar, $id, $review, $mail['id']);
ok(outbox($id)['attempts'] === $mail['attempts'], 'Already-sent notification retried');
[, $html] = http($registrar, "Modules.php?modname=$review&applicant_id=$id");
http($registrar, "Modules.php?modname=$review&applicant_id=$id&modfunc=decision", ['token' => field($html, 'token'), 'abugida_csrf' => field($html, 'abugida_csrf'), 'decision' => 'approve']);
ok(applicant($id)['payment_access_token'] === $token && countHistory($id) === $history, 'Duplicate approval changed token/history');
$paymentJar = []; [, $html] = http($paymentJar, 'registration-payment.php?token=' . $token);
ok(str_contains($html, '1,234.56') && str_contains($html, 'TEST BANK'), 'Payment amount/instructions incorrect');
$receiptCsrf = field($html, 'csrf');
http($paymentJar, 'registration-payment.php', ['csrf' => 'bad'], ['receipt' => ['receipt.png', $png]]);
ok(applicant($id)['status'] === 'APPROVED_FOR_PAYMENT', 'Receipt accepted invalid CSRF');
http($paymentJar, 'registration-payment.php', ['csrf' => $receiptCsrf], ['receipt' => ['receipt.png', 'not an image']]);
ok(applicant($id)['status'] === 'APPROVED_FOR_PAYMENT', 'Receipt accepted invalid MIME');
[$status] = http($paymentJar, 'registration-payment.php', ['csrf' => $receiptCsrf], ['receipt' => ['receipt.png', $png]]);
ok($status === 302 && applicant($id)['status'] === 'PAYMENT_SUBMITTED', 'Receipt submit failed');
$oldReceipt = applicant($id)['receipt_stored_name']; $history = countHistory($id);
http($paymentJar, 'registration-payment.php', ['csrf' => $receiptCsrf], ['receipt' => ['duplicate.png', $png]]);
ok(countHistory($id) === $history, 'Duplicate receipt created audit');
[, $queue] = http($finance, "Modules.php?modname=$payments"); ok(str_contains($queue, $app['application_reference']), 'Payment missing from Finance queue');
$anonymous = []; [$status] = http($anonymous, "application-file.php?applicant_id=$id&type=receipt"); ok($status === 403, 'Anonymous receipt access allowed');
[$status] = http($registrar, "application-file.php?applicant_id=$id&type=receipt"); ok($status === 403, 'Registrar accessed Finance-only receipt');
[$status] = http($finance, "application-file.php?applicant_id=$id&type=document"); ok($status === 403, 'Finance accessed Registrar-only document');
[$status, $body] = http($registrar, "application-file.php?applicant_id=$id&type=document"); ok($status === 200 && $body === $png, 'Secure supporting document read failed');
[$status, $body] = http($finance, "application-file.php?applicant_id=$id&type=receipt"); ok($status === 200 && $body === $png, 'Secure receipt read failed');
[$status] = http($anonymous, 'assets/FileUploads/ApplicantDocuments/' . $oldReceipt); ok($status === 403, 'Direct upload access allowed');
decision($finance, $id, $payments, 'reject'); ok(applicant($id)['status'] === 'PAYMENT_SUBMITTED', 'Blank Finance reason accepted');
decision($finance, $id, $payments, 'reject', 'Upload a legible receipt <script>bad</script>');
ok(applicant($id)['status'] === 'PAYMENT_DECLINED' && outbox($id)['delivery_status'] === 'SENT', 'Finance rejection/email failed');
[, $html] = http($paymentJar, 'registration-payment.php'); ok(str_contains($html, '&lt;script&gt;'), 'Finance rejection reason not escaped');
http($paymentJar, 'registration-payment.php', ['csrf' => field($html, 'csrf')], ['receipt' => ['corrected.png', $png]]);
ok(applicant($id)['status'] === 'PAYMENT_SUBMITTED' && is_file('/var/www/html/assets/FileUploads/ApplicantDocuments/' . $oldReceipt), 'Corrected receipt failed or deleted historical receipt');
decision($finance, $id, $payments, 'approve'); ok(applicant($id)['status'] === 'PAYMENT_VERIFIED' && outbox($id)['delivery_status'] === 'SENT', 'Finance verification/email failed');
$history = countHistory($id);
[, $html] = http($finance, "Modules.php?modname=$payments&applicant_id=$id");
http($finance, "Modules.php?modname=$payments&applicant_id=$id&modfunc=decision", ['token' => field($html, 'token'), 'abugida_csrf' => field($html, 'abugida_csrf'), 'decision' => 'approve']);
ok(countHistory($id) === $history, 'Duplicate Finance approval created history');
[, $html] = http($readonly, "Modules.php?modname=$review&applicant_id=$id"); ok(!str_contains($html, 'Final Confirm &amp;') && !str_contains($html, 'name="decision"'), 'Read-only user got state-changing controls');
[$other, $otherPublic] = createApplication('02', 'data-fail@example.invalid');
[, $html] = http($registrar, "Modules.php?modname=$review&applicant_id=$other");
http($readonly, "Modules.php?modname=$review&applicant_id=$other&modfunc=decision", ['token' => field($html, 'token'), 'abugida_csrf' => field($html, 'abugida_csrf'), 'decision' => 'approve']);
ok(applicant($other)['status'] === 'SUBMITTED', 'Read-only actor changed application');
http($denied, "Modules.php?modname=$review&applicant_id=$other"); ok(applicant($other)['status'] === 'SUBMITTED', 'Denied actor changed application');
decision($registrar, $other, $review, 'approve');
$mail = outbox($other); $history = countHistory($other); $savedToken = applicant($other)['payment_access_token'];
ok(applicant($other)['status'] === 'APPROVED_FOR_PAYMENT' && $mail['delivery_status'] === 'FAILED', 'SMTP rejection rolled back decision or was misclassified');
// Change ONLY the isolated fixture's recipient snapshot to demonstrate a recovered SMTP failure.
// The fixture rejects the first DATA attempt and accepts the same recipient on retry.
retryMail($registrar, $other, $review, $mail['id']);
ok(outbox($other)['delivery_status'] === 'SENT' && (int)outbox($other)['attempts'] === 2 && countHistory($other) === $history && applicant($other)['payment_access_token'] === $savedToken, 'Safe retry repeated decision or failed');
$attempts = outbox($other)['attempts']; retryMail($registrar, $other, $review, $mail['id']); ok(outbox($other)['attempts'] === $attempts, 'Duplicate retry sent again');
[$uncertain] = createApplication('03', 'uncertain@example.invalid'); decision($registrar, $uncertain, $review, 'approve');
$mail = outbox($uncertain); ok($mail['delivery_status'] === 'UNKNOWN' && applicant($uncertain)['status'] === 'APPROVED_FOR_PAYMENT', 'Ambiguous delivery not protected');
retryMail($registrar, $uncertain, $review, $mail['id']); ok(outbox($uncertain)['attempts'] === $mail['attempts'], 'Ambiguous delivery retried automatically');
// Administrator test works with verified TLS/authentication and without disclosing credentials.
[, $html] = http($emailAdmin, 'Modules.php?modname=Custom/EmailTest.php');
ok(str_contains($html, 'SMTP diagnostics') && !str_contains($html, 'stage2-synthetic-password') && !str_contains($html, 'smtp-test@example.invalid'), 'Diagnostics leak credentials or do not render');
http($emailAdmin, 'Modules.php?modname=Custom/EmailTest.php', ['token' => field($html, 'token'), 'abugida_csrf' => field($html, 'abugida_csrf'), 'test_email' => 'admin-test@example.invalid']);
// Final enrollment remains a separate explicit POST action; GET cannot create students.
[, $html] = http($registrar, "Modules.php?modname=$review&applicant_id=$id");
http($registrar, "Modules.php?modname=$review&applicant_id=$id&modfunc=final_confirm&token=" . urlencode(field($html, 'token')));
ok(applicant($id)['status'] === 'PAYMENT_VERIFIED', 'GET finalized application');
foreach ($core as $table => $checksum) { ok($checksum === $db->query("CHECKSUM TABLE $table EXTENDED")->fetch(PDO::FETCH_ASSOC)['Checksum'], "Permanent $table data changed"); }
$messages = array_map(fn($line) => json_decode($line, true), file('/runtime/messages.jsonl', FILE_IGNORE_NEW_LINES));
ok(count($messages) > 0 && count(array_filter($messages, fn($message) => !$message['tls'] || !$message['authenticated'])) === 0, 'SMTP fixture accepted message without TLS/auth');
echo "PASS $checks Stage 2 HTTP/security/content checks; " . count($messages) . " messages captured by isolated TLS/authenticated SMTP only.\n";
