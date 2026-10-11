<?php
/** Existing Abugida PHPMailer transport; configuration never comes from a request. */
foreach (['Exception', 'SMTP', 'PHPMailer'] as $class) {
    require_once __DIR__ . '/../classes/PHPMailer/PHPMailer/' . $class . '.php';
}

class AbugidaTrackedSMTP extends PHPMailer\PHPMailer\SMTP
{
    public $finalAttempted = false;
    public $finalAccepted = false;
    public $finalRejected = false;
    protected function sendCommand($command, $commandstring, $expect)
    {
        if ($command === 'DATA END') { $this->finalAttempted = true; }
        $result = parent::sendCommand($command, $commandstring, $expect);
        if ($command === 'DATA END') {
            $this->finalAccepted = $result;
            $this->finalRejected = (bool)preg_match('/^[45][0-9]{2}[ -]/', $this->getLastReply());
        }
        return $result;
    }
}

function AbugidaMailSetting($env, $legacy, $default = '')
{
    $value = getenv($env);
    return $value !== false ? $value : ($GLOBALS[$legacy] ?? $default);
}

function AbugidaMailConfig(): array
{
    return [
        'host' => trim(AbugidaMailSetting('ABUGIDA_SMTP_HOST', 'AbugidaMailHost')),
        'port' => (int)AbugidaMailSetting('ABUGIDA_SMTP_PORT', 'AbugidaMailPort', 587),
        'username' => AbugidaMailSetting('ABUGIDA_SMTP_USERNAME', 'AbugidaMailUsername'),
        'password' => AbugidaMailSetting('ABUGIDA_SMTP_PASSWORD', 'AbugidaMailPassword'),
        'from' => trim(AbugidaMailSetting('ABUGIDA_SMTP_FROM', 'AbugidaMailFrom')),
        'name' => AbugidaMailSetting('ABUGIDA_SMTP_FROM_NAME', 'AbugidaMailFromName', 'Abugida SIS'),
        'encryption' => strtolower(AbugidaMailSetting('ABUGIDA_SMTP_ENCRYPTION', 'AbugidaMailEncryption', 'tls')),
        'auth' => filter_var(AbugidaMailSetting('ABUGIDA_SMTP_AUTH', 'AbugidaMailAuth', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
    ];
}

function AbugidaMailConfigError(array $config): string
{
    if ($config['auth'] === null || !$config['host'] || !preg_match('/^[a-z0-9._:-]+$/i', $config['host']) ||
        !filter_var($config['from'], FILTER_VALIDATE_EMAIL) || $config['port'] < 1 || $config['port'] > 65535 ||
        !in_array($config['encryption'], ['tls', 'ssl', 'none'], true) ||
        ($config['auth'] && (!$config['username'] || !$config['password']))) {
        return 'SMTP configuration is incomplete or invalid.';
    }
    // Never transmit authenticated credentials in clear text.
    if ($config['auth'] && $config['encryption'] === 'none') { return 'Authenticated SMTP requires TLS or SSL.'; }
    return '';
}

function AbugidaSendEmail($to, $subject, $message, $messageId = '')
{
    global $AbugidaMailLastError, $AbugidaMailLastState;
    $AbugidaMailLastError = '';
    $AbugidaMailLastState = 'FAILED';
    $config = AbugidaMailConfig();
    $problem = AbugidaMailConfigError($config);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $problem = 'Invalid recipient address.'; }
    if ($problem) {
        $AbugidaMailLastError = $problem;
        error_log('[Abugida SMTP] ' . $problem);
        return false;
    }
    $smtp = new AbugidaTrackedSMTP();
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->setSMTPInstance($smtp);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = $config['auth'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'] === 'none' ? '' : $config['encryption'];
        $mail->SMTPAutoTLS = $config['encryption'] !== 'none';
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
        $mail->Timeout = 15;
        $smtp->Timelimit = 15;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from'], $config['name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(false);
        $mail->Body = $message;
        if ($messageId) { $mail->MessageID = $messageId; }
        $sent = $mail->send();
        $AbugidaMailLastState = $sent ? 'SENT' : 'FAILED';
        return $sent;
    } catch (Throwable $exception) {
        if ($smtp->finalAccepted) { $AbugidaMailLastState = 'SENT'; return true; }
        if ($smtp->finalAttempted && !$smtp->finalRejected) {
            $AbugidaMailLastState = 'UNKNOWN';
            $AbugidaMailLastError = 'Delivery outcome is uncertain. Check the SMTP server before any resend.';
        } else {
            // Categorize locally; never expose or log raw server replies, secrets, or message bodies.
            $text = strtolower($exception->getMessage());
            if (str_contains($text, 'authenticate')) { $category = 'SMTP authentication failed. Check the local account/App Password.'; }
            elseif (str_contains($text, 'tls') || str_contains($text, 'certificate') || str_contains($text, 'crypto')) { $category = 'SMTP TLS negotiation failed. Check encryption, port and certificate trust.'; }
            elseif (str_contains($text, 'connect')) { $category = 'SMTP connection failed. Check DNS, Docker egress, host and port.'; }
            elseif (str_contains($text, 'recipient')) { $category = 'SMTP rejected the recipient.'; }
            else { $category = 'SMTP server rejected the message before confirmed acceptance.'; }
            $AbugidaMailLastError = $category;
        }
        error_log('[Abugida SMTP] ' . $AbugidaMailLastState . ': ' . $AbugidaMailLastError);
        return false;
    }
}

function AbugidaPublicURL($path, $query = [])
{
    $base = rtrim(trim(AbugidaMailSetting('ABUGIDA_BASE_URL', 'AbugidaBaseURL', 'http://localhost:8090')), '/');
    $parts = parse_url($base);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) ||
        isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) ||
        preg_match('/[\r\n]/', $base) || !preg_match('/^[a-zA-Z0-9_\/-]+\.php$/', $path) || str_contains($path, '..')) {
        throw new RuntimeException('Configure a valid application base URL locally.');
    }
    return $base . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
}
