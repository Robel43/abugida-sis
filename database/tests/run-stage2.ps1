param([Parameter(Mandatory=$true)][string]$BackupPath, [switch]$Approved)
$ErrorActionPreference = 'Stop'
if (!$Approved) { throw 'Use -Approved only after approval for the isolated database restore/migration/tests.' }
$taskRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$taskBackup = (Resolve-Path -LiteralPath $BackupPath).Path
if (!(Test-Path -LiteralPath (Join-Path $taskRoot 'rosariosis/config.inc.php'))) { throw 'Local SIS configuration is required.' }
function DockerChecked { & docker @args; if ($LASTEXITCODE -ne 0) { throw "Docker step failed: $($args[0])" } }
Push-Location $taskRoot
try {
    foreach ($name in @('abugida-stage2-db','abugida-stage2-smtp','abugida-stage2-web')) {
        $existing = docker ps -a --filter "name=^/$name$" --format '{{.Names}}'
        if ($existing) { throw "Existing container $name; stop here and inspect it rather than resetting test data." }
    }
    $runtime = Join-Path $taskRoot 'tmp/stage2'
    New-Item -ItemType Directory -Force (Join-Path $runtime 'uploads') | Out-Null
    # Keep previous test logs by choosing a fresh runtime directory per suite.
    if (Test-Path -LiteralPath (Join-Path $runtime 'messages.jsonl')) { throw 'Existing test log; preserve it and choose a fresh test workspace before rerunning.' }
    DockerChecked run --rm --volume "${runtime}:/runtime" --entrypoint openssl abugida-sis-web req -x509 -newkey rsa:2048 -nodes -keyout /runtime/key.pem -out /runtime/ca.pem -days 2 -subj /CN=abugida-stage2-smtp -addext subjectAltName=DNS:abugida-stage2-smtp 2>$null
    $certificate = [IO.File]::ReadAllText((Join-Path $runtime 'ca.pem')) + [IO.File]::ReadAllText((Join-Path $runtime 'key.pem'))
    [IO.File]::WriteAllText((Join-Path $runtime 'server.pem'), $certificate)
    DockerChecked compose -f database/tests/docker-compose.stage2.yaml -p abugida-stage2 up -d
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        docker exec abugida-stage2-db sh -c 'MYSQL_PWD=$MARIADB_ROOT_PASSWORD mariadb -uroot -e "SELECT 1"' 2>$null
        if ($LASTEXITCODE -eq 0) { break }; Start-Sleep -Seconds 1
    }
    if ($LASTEXITCODE -ne 0) { throw 'Test DB did not become ready' }
    DockerChecked cp $taskBackup abugida-stage2-db:/tmp/before.sql
    DockerChecked exec abugida-stage2-db sh -c 'MYSQL_PWD=$MARIADB_ROOT_PASSWORD mariadb -uroot < /tmp/before.sql'
    # Mount provides database/backups; the supplied backup must be in that directory.
    $backupDir = [IO.Path]::GetFullPath((Join-Path $taskRoot 'database/backups')) + [IO.Path]::DirectorySeparatorChar
    if (!$taskBackup.StartsWith($backupDir, [StringComparison]::OrdinalIgnoreCase)) { throw 'Backup must be under database/backups for the read-only runner mount.' }
    $backupName = [IO.Path]::GetFileName($taskBackup)
    DockerChecked exec abugida-stage2-web php /opt/abugida/database/migrate-registration.php --apply --approved "--backup=/opt/abugida/database/backups/$backupName"
    DockerChecked exec abugida-stage2-db sh -c 'MYSQL_PWD=$MARIADB_ROOT_PASSWORD mariadb-dump -uroot --single-transaction --routines --events --triggers --databases abugida_sis > /tmp/fixture-before.sql'
    DockerChecked cp abugida-stage2-db:/tmp/fixture-before.sql database/backups/stage2-isolated-before-tests.sql
    DockerChecked exec abugida-stage2-web php /test/stage2-fixtures.php
    DockerChecked exec abugida-stage2-web php /test/stage2-http.php
    DockerChecked exec -d -e ABUGIDA_TEST_IMPLICIT_TLS=1 abugida-stage2-smtp php /test/stage2-smtp.php
    DockerChecked exec abugida-stage2-web php /test/stage2-mail.php
    DockerChecked exec -e ABUGIDA_TEST_BASE_URL=http://abugida-stage2-web -e ABUGIDA_TEST_WEB_ROOT=/var/www/html abugida-stage2-web php /opt/abugida/database/tests/registration-http.php --approved
    foreach ($role in @('registrar','readonly','denied','finance')) { DockerChecked exec abugida-stage2-web php /test/stage2-transactions.php $role }
    DockerChecked exec abugida-stage2-web php -d openssl.cafile=/etc/ssl/certs/ca-certificates.crt /test/stage2-untrusted-tls.php
    DockerChecked exec abugida-stage2-web php /test/stage2-concurrency.php
    $baseline = [IO.File]::ReadAllText($taskBackup).Replace('`abugida_sis`','`abugida_stage2_baseline`')
    [IO.File]::WriteAllText((Join-Path $taskRoot 'database/backups/stage2-baseline.sql'), $baseline)
    "GRANT SELECT ON abugida_stage2_baseline.* TO 'abugida_user'@'%';" | docker exec -i abugida-stage2-db sh -c 'MYSQL_PWD=$MARIADB_ROOT_PASSWORD mariadb -uroot'
    if ($LASTEXITCODE -ne 0) { throw 'Baseline view-definer grant failed' }
    DockerChecked cp database/backups/stage2-baseline.sql abugida-stage2-db:/tmp/baseline.sql
    DockerChecked exec abugida-stage2-db sh -c 'MYSQL_PWD=$MARIADB_ROOT_PASSWORD mariadb -uroot < /tmp/baseline.sql'
    DockerChecked exec abugida-stage2-web php /test/stage2-core-preservation.php
} finally { Pop-Location }
# Deliberately retain disposable containers for inspection; cleanup is an explicit subsequent command.
