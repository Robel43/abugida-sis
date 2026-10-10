<?php
/** Isolated SMTP fixture: TLS/auth, explicit rejection and uncertain DATA outcome. */
$ctx = stream_context_create(['ssl' => ['local_cert' => '/runtime/server.pem', 'verify_peer' => false]]);
$implicit = (bool) getenv('ABUGIDA_TEST_IMPLICIT_TLS');
$server = stream_socket_server($implicit ? 'tls://0.0.0.0:2465' : 'tcp://0.0.0.0:2525', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
if (!$server) { throw new RuntimeException('Unable to start test SMTP'); }
$rejectedRecipients = [];
while ($client = stream_socket_accept($server, -1)) {
    stream_set_timeout($client, 20);
    fwrite($client, "220 isolated-test ESMTP\r\n");
    $tls = $implicit; $authenticated = false; $recipient = ''; $authStep = 0;
    while (($line = fgets($client)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($authStep === 1) { $authUser = base64_decode($line); $authStep = 2; fwrite($client, "334 UGFzc3dvcmQ6\r\n"); continue; }
        if ($authStep === 2) {
            $authenticated = $tls && $authUser === 'smtp-test@example.invalid' && base64_decode($line) === 'stage2-synthetic-password';
            $authStep = 0; fwrite($client, $authenticated ? "235 2.7.0 authenticated\r\n" : "535 5.7.8 credentials rejected\r\n"); continue;
        }
        if (str_starts_with($line, 'EHLO')) { fwrite($client, "250-isolated-test\r\n" . ($tls ? "250-AUTH LOGIN\r\n" : "250-STARTTLS\r\n") . "250 SIZE 1000000\r\n"); }
        elseif ($line === 'STARTTLS') { fwrite($client, "220 ready for TLS\r\n"); $tls = stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) === true; if (!$tls) { break; } }
        elseif ($line === 'AUTH LOGIN') { $authStep = 1; fwrite($client, "334 VXNlcm5hbWU6\r\n"); }
        elseif (str_starts_with($line, 'MAIL FROM:')) { fwrite($client, $authenticated ? "250 sender OK\r\n" : "530 authentication required\r\n"); }
        elseif (str_starts_with($line, 'RCPT TO:')) {
            preg_match('/<([^>]+)>/', $line, $match); $recipient = $match[1] ?? '';
            fwrite($client, $recipient === 'recipient-fail@example.invalid' ? "550 recipient rejected\r\n" : "250 recipient OK\r\n");
        }
        elseif ($line === 'DATA') {
            fwrite($client, "354 send message\r\n"); $body = '';
            while (($data = fgets($client)) !== false && rtrim($data, "\r\n") !== '.') { $body .= $data; }
            if ($recipient === 'data-fail@example.invalid' && empty( $rejectedRecipients[$recipient] )) { $rejectedRecipients[$recipient] = true; fwrite($client, "550 message rejected\r\n"); continue; }
            file_put_contents('/runtime/messages.jsonl', json_encode(['recipient' => $recipient, 'tls' => $tls, 'authenticated' => $authenticated, 'message' => $body]) . "\n", FILE_APPEND | LOCK_EX);
            if ($recipient === 'uncertain@example.invalid') { break; }
            fwrite($client, "250 message accepted\r\n");
        }
        elseif ($line === 'QUIT') { fwrite($client, "221 bye\r\n"); break; }
        elseif ($line === 'RSET' || $line === 'NOOP') { fwrite($client, "250 OK\r\n"); }
        else { fwrite($client, "500 unsupported\r\n"); }
    }
    fclose($client);
}
