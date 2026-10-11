<?php
/** HTTP integration test. Retains its clearly identified applications for inspection. */
if (PHP_SAPI !== 'cli' || !in_array('--approved', $argv, true)) { exit("Requires --approved against the local test environment.\n"); }
$base = rtrim( getenv('ABUGIDA_TEST_BASE_URL') ?: 'http://web', '/' ) . '/registration.php';
$test_web_root = getenv('ABUGIDA_TEST_WEB_ROOT') ?: __DIR__ . '/../../rosariosis';
$cookie = '';
function request(string $url, array $fields = [], array $files = []): array
{
    global $cookie;
    $headers = ['Cookie: ' . $cookie];
    $content = '';
    if ($fields) {
        $boundary = 'test' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
        foreach ($fields as $name => $value) {
            $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        foreach ($files as $name => [$filename, $data]) {
            $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"; filename=\"$filename\"\r\nContent-Type: application/octet-stream\r\n\r\n$data\r\n";
        }
        $content .= "--$boundary--\r\n";
    }
    $context = stream_context_create(['http' => ['method' => $fields ? 'POST' : 'GET', 'header' => implode("\r\n", $headers), 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]]);
    $body = file_get_contents($url, false, $context);
    if ($body === false) { throw new RuntimeException('HTTP request failed'); }
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) { $cookie = $match[1]; }
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    return [(int)$status[1], $body];
}
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function csrf(string $body): string {
    check((bool)preg_match('/name="csrf" value="([^"]+)"/', $body, $match), 'Missing CSRF token');
    return $match[1];
}
require $test_web_root . '/config.inc.php';
$db = new PDO("mysql:host=$DatabaseServer;dbname=$DatabaseName", $DatabaseUsername, $DatabasePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII=');
$pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
$run = date('YmdHis');
foreach (range(7, 12) as $grade) {
    $cookie = '';
    $phone = '2518' . substr((string)time(), -7) . $grade;
    $fetch = $db->prepare('SELECT * FROM abugida_applicants WHERE phone=?');
    $fetch->execute([$phone]);
    check(!$fetch->fetch(), 'Test phone collision; stop without touching an existing applicant');
    [$status, $body] = request($base);
    check($status === 200, 'Registration GET unavailable');
    $token = csrf($body);
    [$status] = request($base, ['csrf' => 'invalid', 'action' => 'access', 'phone' => $phone]);
    $fetch->execute([$phone]);
    check(!$fetch->fetch(), 'Invalid CSRF created an applicant');
    [$status] = request($base, ['csrf' => $token, 'action' => 'access', 'phone' => $phone]);
    check($status === 302, 'Create application failed');
    [, $body] = request($base);
    foreach (range(7, 12) as $option) { check(str_contains($body, '>Grade ' . $option . '</option>'), 'Missing grade option'); }
    $fields = ['csrf' => csrf($body), 'action' => 'save', 'first_name' => "REGISTRATION_TEST_$run", 'last_name' => "Grade_$grade", 'email' => 'registration-test@example.invalid', 'grade_level' => $grade, 'study_approach' => $grade % 2 ? 'ONLINE' : 'DISTANCE_LEARNING'];
    [$status] = request($base, $fields);
    check($status === 302, 'Save draft failed');
    $cookie = ''; // Resume in a genuinely new session.
    [, $body] = request($base);
    request($base, ['csrf' => csrf($body), 'action' => 'access', 'phone' => $phone]);
    [, $body] = request($base);
    check(str_contains($body, "REGISTRATION_TEST_$run") && str_contains($body, 'value="' . $grade . '" selected') && str_contains($body, 'value="' . $fields['study_approach'] . '" selected'), 'Resume did not restore saved values');
    $fields['csrf'] = csrf($body);
    $fields['action'] = 'submit';
    [$status, $body] = request($base, $fields);
    check($status === 200 && str_contains($body, 'Upload the required supporting document.') && str_contains($body, 'Upload the Fayda ID file.'), 'Missing uploads were accepted');
    $fields['action'] = 'save';
    [$status] = request($base, $fields, ['document' => ['support.pdf', $pdf], 'fayda_id' => ['fayda.png', $png]]);
    check($status === 302, 'Saving PDF and PNG failed');
    $fetch->execute([$phone]);
    $before = $fetch->fetch(PDO::FETCH_ASSOC);
    check($before['status'] === 'DRAFT' && $before['document_mime_type'] === 'application/pdf' && $before['fayda_mime_type'] === 'image/png', 'Upload metadata mismatch');
    $fields['action'] = 'submit';
    [$status, $body] = request($base, $fields, ['document' => ['replacement.pdf', $pdf], 'fayda_id' => ['invalid.png', 'not a PNG']]);
    check($status === 200 && str_contains($body, 'Only PDF and PNG files are allowed.'), 'Invalid MIME accepted');
    check(is_file($test_web_root . '/assets/FileUploads/ApplicantDocuments/' . $before['document_stored_name']), 'Failed replacement deleted saved upload');
    $fetch->execute([$phone]);
    check($fetch->fetch(PDO::FETCH_ASSOC)['document_stored_name'] === $before['document_stored_name'], 'Failed replacement changed saved metadata');
    $invalid = $fields; $invalid['grade_level'] = 6;
    [$status, $body] = request($base, $invalid);
    check($status === 200 && str_contains($body, 'Select a grade from Grade 7 to Grade 12.'), 'Invalid grade accepted');
    $invalid = $fields; $invalid['study_approach'] = 'OTHER';
    [$status, $body] = request($base, $invalid);
    check($status === 200 && str_contains($body, 'Select a valid learning approach.'), 'Invalid learning approach accepted');
    [$status] = request($base, $fields);
    check($status === 302, 'Submit with previously saved uploads failed');
    [, $body] = request($base);
    check(str_contains($body, 'Application received.') && !str_contains($body, 'name="first_name"'), 'Submitted application still editable');
    request($base, array_replace($fields, ['action' => 'save', 'first_name' => 'SHOULD_NOT_SAVE']));
    $fetch->execute([$phone]); $after = $fetch->fetch(PDO::FETCH_ASSOC);
    check($after['status'] === 'SUBMITTED' && $after['first_name'] === "REGISTRATION_TEST_$run" && $after['submitted_at'] !== null, 'Submitted application changed');
    echo "PASS Grade $grade {$fields['study_approach']}: create, save, resume, CSRF, uploads, validation, submit, read-only ({$after['application_reference']})\n";
}
