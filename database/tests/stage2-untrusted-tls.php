<?php
require '/var/www/html/config.inc.php';
require '/var/www/html/ProgramFunctions/AbugidaEmail.fnc.php';
if ($DatabaseServer !== 'abugida-stage2-db') { exit(1); }
if (AbugidaSendEmail('untrusted-ca@example.invalid', 'Certificate trust check', 'Synthetic test')) { throw new RuntimeException('Untrusted certificate accepted'); }
echo "PASS untrusted TLS certificate refused\n";
