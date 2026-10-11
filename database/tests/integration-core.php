<?php
/** Offline regression of the merged migration boundary and academic calculations.
 * Uses an in-memory SQLite fixture; never connects to the school's database.
 * php -d extension=pdo_sqlite database/tests/integration-core.php
 */
require __DIR__ . '/../migrate-registration.php';
function check($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
    echo "PASS $message\n";
}
require __DIR__ . '/../../rosariosis/ProgramFunctions/AbugidaEmail.fnc.php';
require __DIR__ . '/../../rosariosis/ProgramFunctions/AbugidaWorkflow.fnc.php';
$smtp = ['host'=>'localhost', 'port'=>2525, 'username'=>'fixture', 'password'=>'fixture',
    'from'=>'school@example.invalid', 'name'=>'School', 'encryption'=>'tls', 'auth'=>true];
check(AbugidaMailConfigError($smtp) === '', 'merged SMTP helper accepts valid configuration');
$smtp['encryption'] = 'none';
check(AbugidaMailConfigError($smtp) !== '', 'SMTP refuses plaintext authenticated credentials');
putenv('ABUGIDA_BASE_URL=https://school.example.invalid/sis');
check(AbugidaPublicURL('registration-payment.php', ['token'=>'synthetic']) ===
    'https://school.example.invalid/sis/registration-payment.php?token=synthetic', 'notification links use configured base URL');
try {
    AbugidaPublicURL('../config.inc.php');
    throw new LogicException('Unsafe notification path accepted');
} catch (RuntimeException $error) { check(true, 'unsafe notification path rejected'); }
putenv('ABUGIDA_BASE_URL');
$_SESSION = ['abugida_stage2_csrf'=>'fixture-token'];
$_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = ['abugida_csrf'=>'fixture-token'];
check(!AbugidaValidStaffPost(), 'workflow disallows state changes through GET');
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['abugida_csrf'=>'wrong-token'];
check(!AbugidaValidStaffPost(), 'workflow rejects invalid CSRF');
$_POST = ['abugida_csrf'=>'fixture-token'];
check(AbugidaValidStaffPost(), 'workflow accepts valid POST CSRF');
$names = array_map('basename', registrationMigrations());
check(count($names) === 7 && end($names) === '014_stage2_email_outbox.sql'
    && !in_array('007_excel_grade_import.sql', $names, true), 'registration runner excludes academic migrations');
$migrationFiles = registrationMigrations();
$checksum = hash_file('sha256', end($migrationFiles));
$legacy = registrationMigrationAliases(['007_stage2_email_outbox.sql'=>$checksum], $migrationFiles);
check($legacy['014_stage2_email_outbox.sql'] === $checksum && isset($legacy['007_stage2_email_outbox.sql']), 'legacy outbox tracking adopted without removing history');
$both = registrationMigrationAliases(['007_stage2_email_outbox.sql'=>$checksum, '014_stage2_email_outbox.sql'=>'bad'], $migrationFiles);
check($both['014_stage2_email_outbox.sql'] === 'bad', 'legacy alias cannot hide a corrupt current checksum');
try {
    registrationMigrationAliases(['007_stage2_email_outbox.sql'=>'bad'], $migrationFiles);
    throw new LogicException('Corrupt legacy tracking accepted');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'Checksum changed'), 'corrupt legacy outbox checksum rejected');
}
$numbers = [];
foreach (glob(__DIR__ . '/../migrations/*.sql') as $file) {
    $number = substr(basename($file), 0, 3);
    check(!isset($numbers[$number]), 'unique migration number ' . $number);
    $numbers[$number] = true;
}

$fixture = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function UserSchool() { return 1; }
function UserSyear() { return 2026; }
function UserStudentID() { return 101; }
function DBDate() { return '2026-10-11'; }
function DBEscapeString($s) { return str_replace("'", "''", $s); }
function DBQuery($sql) { global $fixture; return $fixture->exec($sql); }
function DBGet($sql) {
    global $fixture;
    $result = []; foreach ($fixture->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[count($result) + 1] = array_change_key_case($row, CASE_UPPER);
    }
    return $result;
}
function DBGetOne($sql) { global $fixture; return $fixture->query($sql)->fetchColumn(); }
function DBInsert($table, $values) {
    global $fixture;
    $fixture->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' .
        implode(',', array_fill(0, count($values), '?')) . ')')->execute(array_values($values));
}
function DBUpdate($table, $values, $where) {
    global $fixture;
    $set = implode(',', array_map(fn($k) => "$k=?", array_keys($values)));
    $condition = implode(' AND ', array_map(fn($k) => "$k=?", array_keys($where)));
    $fixture->prepare("UPDATE $table SET $set WHERE $condition")->execute(array_merge(array_values($values), array_values($where)));
}
function GetMP($id, $field) { return DBGetOne("SELECT $field FROM school_marking_periods WHERE MARKING_PERIOD_ID=" . (int)$id); }
function GetParentMP($type, $id) { return GetMP($id, 'PARENT_ID'); }
require __DIR__ . '/../../rosariosis/ProgramFunctions/AbugidaClassRank.fnc.php';
require __DIR__ . '/../../rosariosis/ProgramFunctions/AbugidaReRegistration.fnc.php';
DBQuery('CREATE TABLE school_marking_periods (MARKING_PERIOD_ID INTEGER, SCHOOL_ID INTEGER, SYEAR INTEGER, MP TEXT, PARENT_ID INTEGER, DOES_GRADES TEXT, SORT_ORDER INTEGER, START_DATE TEXT, END_DATE TEXT, TITLE TEXT, SHORT_NAME TEXT)');
DBQuery("INSERT INTO school_marking_periods VALUES
    (1,1,2026,'FY',0,'Y',0,'2026-01-01','2026-12-31','Full Year','FY'),
    (2,1,2026,'SEM',1,'Y',1,'2026-01-01','2026-05-31','Semester 1','S1'),
    (3,1,2026,'SEM',1,'Y',2,'2026-06-01','2026-12-01','Semester 2','S2'),
    (9,1,2026,'SEM',1,'N',3,'2026-12-02','2026-12-31','Future Test','TEST'),
    (10,2,2026,'SEM',1,'Y',1,'2026-12-02','2026-12-31','Other school','OTHER')");
DBQuery('CREATE TABLE student_enrollment (ID INTEGER PRIMARY KEY, STUDENT_ID INTEGER, SCHOOL_ID INTEGER, SYEAR INTEGER, GRADE_ID INTEGER)');
DBQuery('CREATE TABLE student_report_card_grades (STUDENT_ID INTEGER, SCHOOL_ID INTEGER, SYEAR INTEGER, MARKING_PERIOD_ID INTEGER, COURSE_PERIOD_ID INTEGER, GRADE_PERCENT REAL)');
DBQuery('CREATE TABLE abugida_student_academic_rank (ID INTEGER PRIMARY KEY, SCHOOL_ID INTEGER, SYEAR INTEGER, STUDENT_ID INTEGER, PERIOD_TYPE TEXT, MARKING_PERIOD_ID INTEGER, GRADE_ID INTEGER, AVERAGE_PERCENT REAL, SUBJECT_COUNT INTEGER, RANK_POSITION INTEGER, COHORT_SIZE INTEGER, CALCULATED_AT TEXT)');
foreach ([101,102,103,104] as $student) {
    DBInsert('student_enrollment', ['STUDENT_ID'=>$student,'SCHOOL_ID'=>1,'SYEAR'=>2026,'GRADE_ID'=>8]);
    foreach (($student === 101 ? [90,80,85,95] : ($student === 104 ? [70,70,70,70] : [80,80,80,80])) as $course=>$score) {
        DBInsert('student_report_card_grades', ['STUDENT_ID'=>$student,'SCHOOL_ID'=>1,'SYEAR'=>2026,'MARKING_PERIOD_ID'=>2,'COURSE_PERIOD_ID'=>$course+1,'GRADE_PERCENT'=>$score]);
    }
}
AbugidaRankRecalculateSemester(2);
$r = AbugidaRankGetStudent(101, 'SEM', 2)[1];
check((float)$r['AVERAGE_PERCENT'] === 87.5 && (int)$r['RANK_POSITION'] === 1, 'four-subject semester average 87.50 and rank 1');
check((int)AbugidaRankGetStudent(102,'SEM',2)[1]['RANK_POSITION'] === 2
    && (int)AbugidaRankGetStudent(103,'SEM',2)[1]['RANK_POSITION'] === 2
    && (int)AbugidaRankGetStudent(104,'SEM',2)[1]['RANK_POSITION'] === 4, 'competition ties rank 1,2,2,4');
check(!AbugidaRankGetStudent(101,'FY',1), 'full-year result waits for both graded semesters');
foreach ([85,75,80,90] as $course=>$score) {
    DBInsert('student_report_card_grades', ['STUDENT_ID'=>101,'SCHOOL_ID'=>1,'SYEAR'=>2026,'MARKING_PERIOD_ID'=>3,'COURSE_PERIOD_ID'=>$course+1,'GRADE_PERCENT'=>$score]);
}
AbugidaRankRecalculateSemester(3);
check((float)AbugidaRankGetStudent(101,'FY',1)[1]['AVERAGE_PERCENT'] === 85.0, 'full-year average 85.00 excludes ungraded test semester');
DBQuery('UPDATE student_report_card_grades SET GRADE_PERCENT=94 WHERE STUDENT_ID=101 AND MARKING_PERIOD_ID=2 AND COURSE_PERIOD_ID=1');
AbugidaRankRecalculateSemester(2);
check((float)AbugidaRankGetStudent(101,'SEM',2)[1]['AVERAGE_PERCENT'] === 88.5
    && (float)AbugidaRankGetStudent(101,'FY',1)[1]['AVERAGE_PERCENT'] === 85.5, 'grade replacement recalculates semester and year');
check(AbugidaReRegistrationCurrentSemesterId() === 3, 'current semester determined by dates');
$future = AbugidaReRegistrationFutureSemesters();
check(count($future) === 1 && (int)$future[1]['MARKING_PERIOD_ID'] === 9, 'past/current semesters and other schools excluded');
echo "All offline integration checks passed. MariaDB/HTTP/SMTP suites remain separate.\n";
