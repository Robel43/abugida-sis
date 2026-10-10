<?php
$DatabaseType = 'mysql';
$DatabaseServer = 'abugida-registration-test';
$DatabaseUsername = 'root';
$DatabasePassword = 'registration-test-only';
$DatabaseName = getenv('ABUGIDA_TEST_DATABASE');
if (!in_array($DatabaseName, ['reg_fresh', 'reg_partial', 'reg_complete', 'reg_incompatible'], true)) {
    throw new RuntimeException('Refusing a non-test database.');
}
