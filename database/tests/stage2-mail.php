<?php
/** Direct transport checks against the isolated SMTP fixture only. */
require '/var/www/html/config.inc.php';
require '/var/www/html/ProgramFunctions/AbugidaEmail.fnc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { throw new RuntimeException('Refusing non-test environment'); }
function assertMail($condition, $label) { if (!$condition) { throw new RuntimeException($label); } echo "PASS $label\n"; }
$original = AbugidaMailConfig();
assertMail(AbugidaSendEmail('transport@example.invalid', 'TLS transport check', 'Synthetic TLS test'), 'PHPMailer initialization, authenticated STARTTLS and Docker DNS/TCP');
$AbugidaMailPassword = 'wrong-synthetic-password';
assertMail(!AbugidaSendEmail('transport@example.invalid', 'Wrong authentication', 'Synthetic test') && $AbugidaMailLastState === 'FAILED' && str_contains($AbugidaMailLastError, 'authentication') && !str_contains($AbugidaMailLastError, $AbugidaMailPassword), 'Authentication failure is safe, actionable and credential-free');
$AbugidaMailPassword = $original['password']; $AbugidaMailEncryption = 'none';
assertMail(!AbugidaSendEmail('transport@example.invalid', 'Plaintext blocked', 'Synthetic test') && str_contains($AbugidaMailLastError, 'requires TLS'), 'Authenticated plaintext SMTP blocked');
$AbugidaMailEncryption = 'tls'; $AbugidaMailHost = '';
assertMail(!AbugidaSendEmail('transport@example.invalid', 'Missing config', 'Synthetic test') && $AbugidaMailLastState === 'FAILED', 'Missing SMTP configuration handled');
$AbugidaMailHost = $original['host']; $AbugidaMailPort = 1;
assertMail(!AbugidaSendEmail('transport@example.invalid', 'Connectivity failure', 'Synthetic test') && str_contains($AbugidaMailLastError, 'connection'), 'Docker connection failure handled');
$AbugidaMailPort = 2525;
assertMail(!AbugidaSendEmail('recipient-fail@example.invalid', 'Rejected recipient', 'Synthetic test') && $AbugidaMailLastState === 'FAILED', 'Recipient rejection classified retryable');
$AbugidaMailPort = 2465; $AbugidaMailEncryption = 'ssl';
assertMail(AbugidaSendEmail('implicit-tls@example.invalid', 'Implicit TLS check', 'Synthetic test'), 'Authenticated implicit TLS (SSL/465-style institutional or Gmail configuration)');
$_SERVER['HTTP_HOST'] = 'attacker.example';
assertMail(str_starts_with(AbugidaPublicURL('registration.php'), 'http://localhost:8091/'), 'Configured base URL ignores untrusted Host header');
$AbugidaBaseURL = 'https://school.example/sis/';
assertMail(AbugidaPublicURL('registration-payment.php', ['token' => 'a&b']) === 'https://school.example/sis/registration-payment.php?token=a%26b', 'Institutional base path and query encoding');
$AbugidaBaseURL = 'https://user:password@school.example';
try { AbugidaPublicURL('registration.php'); throw new LogicException('Unsafe URL accepted'); }
catch (RuntimeException $e) { echo "PASS credential-bearing base URL refused\n"; }
