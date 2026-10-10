<?php
/** Administrator SMTP test and credential-free diagnostics. */
require_once 'ProgramFunctions/AbugidaWorkflow.fnc.php';
if (!AbugidaStaffAllowed('Custom/EmailTest.php')) { http_response_code(403); exit('Access denied.'); }
DrawHeader(ProgramTitle());
$test_note = []; $test_error = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        AbugidaRequireStaffPost('Custom/EmailTest.php');
        $recipient = trim((string)($_POST['test_email'] ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid recipient email address.'); }
        $sent = AbugidaSendEmail($recipient, 'Abugida SIS SMTP test', "This is an Abugida SIS SMTP test.\n\nApplication URL: " . AbugidaPublicURL('registration.php'));
        if ($sent) { $test_note[] = 'The SMTP server accepted the test message. Confirm receipt in the destination mailbox.'; }
        else { $test_error[] = AttrEscape($GLOBALS['AbugidaMailLastError']); }
    } catch (RuntimeException $exception) { $test_error[] = AttrEscape($exception->getMessage()); }
}
echo ErrorMessage($test_error);
echo ErrorMessage($test_note, 'note');
$config = AbugidaMailConfig();
echo '<div style="max-width:760px"><h3>SMTP diagnostics</h3><dl>';
$diagnostics = [
    'PHPMailer' => class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? 'Loaded' : 'Unavailable',
    'OpenSSL / TLS' => extension_loaded('openssl') ? 'Available; certificate verification enabled' : 'Unavailable',
    'SMTP host' => $config['host'] ?: 'Not configured',
    'SMTP port' => $config['port'],
    'Encryption' => $config['encryption'],
    'Authentication' => $config['auth'] ? 'Enabled' : 'Disabled (relay only)',
    'Username configured' => $config['username'] ? 'Yes' : 'No',
    'Password configured' => $config['password'] ? 'Yes' : 'No',
    'Configuration' => AbugidaMailConfigError($config) ?: 'Complete',
];
try { $diagnostics['Application URL'] = AbugidaPublicURL('registration.php'); }
catch (RuntimeException $exception) { $diagnostics['Application URL'] = 'Invalid; configure locally'; }
foreach ($diagnostics as $label => $value) { echo '<dt><b>' . AttrEscape($label) . '</b></dt><dd>' . AttrEscape($value) . '</dd>'; }
echo '</dl><p>Connection, TLS and authentication are verified when sending the test. Credentials and SMTP message content are never shown here.</p>';
if (AllowEdit()) {
    echo '<form method="POST">' . AbugidaCsrfField() . '<label>Recipient Email <input type="email" name="test_email" required></label> <button type="submit">Send Test Email</button></form>';
}
echo '</div>';
