<?php
/** Finance regression: submit rendered controls without submit-button values. */
require '/var/www/html/config.inc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { throw new RuntimeException('Refusing non-test database'); }
$db = new PDO("mysql:host=$DatabaseServer;dbname=$DatabaseName;charset=utf8mb4", $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function checkFinance($ok, $message) {
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function financeHttp(&$cookies, $path, $fields = null) {
    $headers = ['Cookie: ' . implode('; ', $cookies)];
    $body = '';
    if ($fields !== null) {
        // Match RosarioSIS new FormData(form): successful controls, no submitter.
        $boundary = 'finance' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
        foreach ($fields as $name => $value) { $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n"; }
        $body .= "--$boundary--\r\n";
    }
    $context = stream_context_create(['http' => ['method' => $fields === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers),
        'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]]);
    $html = file_get_contents('http://abugida-stage2-web/' . $path, false, $context);
    if ($html === false) { throw new RuntimeException('HTTP transport failed'); }
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/', $header, $match)) { $cookies[$match[1]] = $match[1] . '=' . $match[2]; }
    }
    checkFinance(!str_contains($http_response_header[0], ' 500 '), 'HTTP server error');
    return $html;
}
function financeLogin($role) {
    $cookies = []; financeHttp($cookies, 'index.php');
    financeHttp($cookies, 'index.php', ['USERNAME' => 'stage2_' . $role, 'PASSWORD' => 'Stage2-synthetic-login-123!']);
    return $cookies;
}
function financeForm($html, $label) {
    $doc = new DOMDocument(); @$doc->loadHTML($html); $xpath = new DOMXPath($doc);
    $form = $xpath->query('//form[.//button[normalize-space(.)="' . $label . '"]]')->item(0);
    checkFinance($form !== null, "Missing $label form");
    $fields = [];
    foreach ($xpath->query('.//input[@name and not(@disabled) and @type="hidden"] | .//textarea[@name and not(@disabled)]', $form) as $control) {
        $fields[$control->getAttribute('name')] = $control->tagName === 'textarea' ? $control->textContent : $control->getAttribute('value');
    }
    return [$form->getAttribute('action'), $fields];
}
function financeApplicant($id) {
    global $db; $q = $db->prepare('SELECT * FROM abugida_applicants WHERE id=?'); $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC);
}
function financeCounts($id) {
    global $db; $counts = [];
    foreach (['abugida_application_history', 'abugida_email_outbox'] as $table) {
        $q = $db->prepare("SELECT COUNT(*) FROM $table WHERE applicant_id=?"); $q->execute([$id]); $counts[] = (int)$q->fetchColumn();
    }
    return $counts;
}
function financeOutbox($id) {
    global $db; $q = $db->prepare('SELECT * FROM abugida_email_outbox WHERE applicant_id=? ORDER BY id DESC LIMIT 1'); $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC);
}
function financeMessages() {
    return is_file('/runtime/messages.jsonl') ? count(file('/runtime/messages.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
}
$finance = financeLogin('finance'); $readonly = financeLogin('readonly'); $denied = financeLogin('denied');
$route = 'Modules.php?modname=Custom/FinanceApplications.php';
$ids = [];
foreach (['approve', 'reject', 'failed-email'] as $scenario) {
    $suffix = bin2hex(random_bytes(6));
    $q = $db->prepare("INSERT INTO abugida_applicants (application_reference,phone,first_name,last_name,email,grade_level,study_approach,status,payment_status,payment_amount,payment_access_token,receipt_stored_name) VALUES (?,?, 'FINANCE_REGRESSION',?,?,7,'ONLINE','PAYMENT_SUBMITTED','SUBMITTED',1234.56,?,'synthetic-receipt.png')");
    $q->execute(['FINANCE-TEST-' . $suffix, 'test-' . $suffix, $scenario, $scenario === 'failed-email' ? 'data-fail@example.invalid' : $scenario . '@example.invalid', bin2hex(random_bytes(32))]);
    $ids[$scenario] = (int)$db->lastInsertId();
}
foreach (['approve' => 'Verify Payment', 'reject' => 'Reject Payment'] as $decision => $label) {
    $id = $ids[$decision]; $detail = $route . '&applicant_id=' . $id;
    [$action, $fields] = financeForm(financeHttp($finance, $detail), $label);
    checkFinance(($fields['decision'] ?? '') === $decision, 'Decision must be serialized independently of the submitter');
    $before = financeApplicant($id); $counts = financeCounts($id); $sent = financeMessages();
    foreach (['missing', 'invalid', 'csrf', 'readonly', 'denied', 'reason'] as $bad) {
        if ($bad === 'reason' && $decision !== 'reject') { continue; }
        $invalid = $fields; $cookies = $finance;
        if ($bad === 'missing') { unset($invalid['decision']); }
        if ($bad === 'invalid') { $invalid['decision'] = 'verify'; }
        if ($bad === 'csrf') { $invalid['abugida_csrf'] = 'invalid'; }
        if ($bad === 'readonly') { $cookies = $readonly; }
        if ($bad === 'denied') { $cookies = $denied; }
        if ($bad === 'reason') { $invalid['reason'] = '   '; }
        $html = financeHttp($cookies, $action, $invalid);
        if (in_array($bad, ['missing', 'invalid'], true)) { checkFinance(str_contains($html, 'Invalid review decision.'), 'Invalid decision validation bypassed'); }
        if ($bad === 'reason') { checkFinance(str_contains($html, 'A rejection reason is required.'), 'Blank reason validation bypassed'); }
        checkFinance(financeApplicant($id) === $before && financeCounts($id) === $counts && financeMessages() === $sent, "$bad request changed applicant, history, outbox or email delivery");
    }
    if ($decision === 'reject') { $fields['reason'] = 'Please upload a readable receipt.'; }
    financeHttp($finance, $action, $fields);
    $applicant = financeApplicant($id); $outbox = financeOutbox($id);
    $expected = $decision === 'approve' ? ['PAYMENT_VERIFIED', 'VERIFIED'] : ['PAYMENT_DECLINED', 'DECLINED'];
    checkFinance([$applicant['status'], $applicant['payment_status']] === $expected, 'Wrong Finance transition');
    checkFinance($decision === 'approve' ? $applicant['finance_decision_reason'] === null : $applicant['finance_decision_reason'] === $fields['reason'], 'Finance reason not saved correctly');
    checkFinance(financeCounts($id) === [1, 1], 'Expected one audit record and one notification');
    checkFinance($outbox['delivery_status'] === 'SENT' && (int)$outbox['attempts'] === 1 && $outbox['expected_status'] === $expected[0], 'SMTP/outbox state incorrect');
    checkFinance(str_contains($outbox['message'], $applicant['application_reference']) && str_contains($outbox['message'], 'FINANCE_REGRESSION ' . $decision) && str_contains($outbox['message'], $applicant['payment_access_token']), 'Personalized notification/link missing');
    checkFinance($decision === 'approve' ? ($outbox['subject'] === 'Abugida SIS payment verified' && str_contains($outbox['message'], 'ETB 1,234.56')) : str_contains($outbox['message'], $fields['reason']), 'Wrong notification content');
    $q = $db->prepare('SELECT * FROM abugida_application_history WHERE applicant_id=?'); $q->execute([$id]); $history = $q->fetch(PDO::FETCH_ASSOC);
    checkFinance($history['actor_type'] === 'FINANCE' && $history['from_status'] === 'PAYMENT_SUBMITTED' && $history['to_status'] === $expected[0], 'Audit transition missing');
    checkFinance($decision === 'approve' ? $history['reason'] === null : $history['reason'] === $fields['reason'], 'Audit reason not saved correctly');
    $sent = financeMessages();
    // A double click / browser POST refresh must not create another decision or email.
    financeHttp($finance, $action, $fields); financeHttp($finance, $action, $fields); financeHttp($finance, $detail);
    checkFinance(financeApplicant($id) === $applicant && financeCounts($id) === [1, 1] && financeOutbox($id) === $outbox && financeMessages() === $sent, 'Duplicate decision or refresh repeated audit/notification');
    financeHttp($finance, $detail . '&modfunc=retry_email', ['token' => $fields['token'], 'abugida_csrf' => $fields['abugida_csrf'], 'notification_id' => $outbox['id']]);
    checkFinance(financeOutbox($id) === $outbox && financeMessages() === $sent, 'Retry resent a delivered email');
    $listing = financeHttp($finance, $route); $doc = new DOMDocument(); @$doc->loadHTML($listing); $xpath = new DOMXPath($doc);
    $row = $xpath->query('//tr[td[contains(.,"' . $applicant['application_reference'] . '")]]')->item(0);
    checkFinance($row !== null && str_contains($row->textContent, $expected[0]), 'Finance listing did not reflect new status');
    $button = $xpath->query('.//a[normalize-space(.)="View Payment"]', $row)->item(0);
    checkFinance($button !== null && str_contains($button->getAttribute('href'), 'applicant_id=' . $id), 'Listing detail navigation lost applicant ID');
}
$id = $ids['failed-email']; $detail = $route . '&applicant_id=' . $id;
[$action, $fields] = financeForm(financeHttp($finance, $detail), 'Verify Payment');
financeHttp($finance, $action, $fields); $outbox = financeOutbox($id);
checkFinance(financeApplicant($id)['status'] === 'PAYMENT_VERIFIED' && $outbox['delivery_status'] === 'FAILED' && financeCounts($id) === [1, 1], 'SMTP failure rolled back payment or lost queued notification');
financeHttp($finance, $action, $fields);
checkFinance(financeCounts($id) === [1, 1] && financeOutbox($id) === $outbox, 'Duplicate action retried failed email');
[$retryAction, $retryFields] = financeForm(financeHttp($finance, $detail), 'Retry notification only');
financeHttp($finance, $retryAction, $retryFields); $delivered = financeOutbox($id); $sent = financeMessages();
checkFinance($delivered['delivery_status'] === 'SENT' && (int)$delivered['attempts'] === 2 && financeCounts($id) === [1, 1], 'Retry did not deliver safely without repeating decision');
financeHttp($finance, $retryAction, $retryFields);
checkFinance(financeOutbox($id) === $delivered && financeMessages() === $sent, 'Repeated email retry duplicated delivery');
foreach (file('/runtime/messages.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    checkFinance($message['tls'] && $message['authenticated'], 'Test SMTP did not use TLS/authentication');
}
echo "PASS Finance form, transitions, permissions, audit, listing, SMTP failure/retry and duplicate protection: $checks checks\n";
