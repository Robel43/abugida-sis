<?php
// Isolated test fixture only: never installed into the main web container.
require '/test-original-config.inc.php';
$DatabaseServer = 'abugida-stage2-db';
$DatabaseUsername = 'abugida_user';
$DatabasePassword = 'stage2-test-database-only';
$DatabaseName = 'abugida_sis';
$DatabasePort = 3306;
$AbugidaMailHost = 'abugida-stage2-smtp';
$AbugidaMailPort = 2525;
$AbugidaMailUsername = 'smtp-test@example.invalid';
$AbugidaMailPassword = 'stage2-synthetic-password';
$AbugidaMailFrom = 'school@example.invalid';
$AbugidaMailEncryption = 'tls';
$AbugidaMailAuth = true;
$AbugidaBaseURL = 'http://localhost:8091';
$AbugidaPaymentInstructions = 'Synthetic demonstration: transfer ETB to TEST BANK, reference the application, then upload your receipt.';
