<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'Student Dashboard' ) );
AbugidaStudentPortalStyles();

$profile = AbugidaStudentPortalProfile();
$enrollment = $profile['ENROLLMENT'];
$courses = AbugidaStudentPortalCourses();
$rank = AbugidaStudentPortalRankSummary();
$billing = AbugidaStudentPortalBilling();

$current_semester = GetMP( UserMP(), 'MP' ) === 'SEM' ? GetMP( UserMP() ) : _( 'Semester' );

$semester_summary = ! empty( $rank['SEMESTERS'][ (int) UserMP() ] ) ?
	$rank['SEMESTERS'][ (int) UserMP() ] : [];

$active_courses = 0;
foreach ( (array) $courses as $course )
{
	if ( $course['STATUS'] === _( 'Active' ) )
	{
		$active_courses++;
	}
}

$student_name = trim(
	issetVal( $profile['STUDENT']['FIRST_NAME'], '' ) . ' ' .
	issetVal( $profile['STUDENT']['LAST_NAME'], '' )
);

$payment_badge = 'neutral';
if ( $billing['STATUS'] === _( 'Paid' ) )
{
	$payment_badge = 'success';
}
elseif ( $billing['STATUS'] === _( 'Partially Paid' ) )
{
	$payment_badge = 'warning';
}
elseif ( $billing['STATUS'] === _( 'Unpaid' ) )
{
	$payment_badge = 'danger';
}
elseif ( $billing['STATUS'] === _( 'No Charges' ) )
{
	$payment_badge = 'info';
}

echo '<div class="abg-student-shell">';

echo '<div class="abg-page-hero">';
echo '<h2>' . sprintf( _( 'Welcome, %s' ), AttrEscape( $student_name ) ) . '</h2>';
echo '<p>' . _( 'A clear view of your courses, official grades, academic standing, payments, and student profile.' ) . '</p>';
echo '</div>';

echo '<div class="abg-student-grid">';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Grade Level' ) . '</div><div class="abg-student-value">' .
	AttrEscape( issetVal( $enrollment['GRADE_TITLE'], '—' ) ) . '</div><div class="abg-student-subvalue">' . _( 'Current enrollment' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Current Semester' ) . '</div><div class="abg-student-value">' .
	AttrEscape( $current_semester ) . '</div><div class="abg-student-subvalue">' . _( 'Selected academic period' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Active Courses' ) . '</div><div class="abg-student-value">' .
	(int) $active_courses . '</div><div class="abg-student-subvalue">' . _( 'Currently scheduled courses' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Payment Status' ) . '</div><div class="abg-student-value"><span class="abg-badge ' .
	$payment_badge . '">' . AttrEscape( $billing['STATUS'] ) . '</span></div><div class="abg-student-subvalue">' . _( 'Current academic year' ) . '</div></div>';

echo '</div>';

echo '<div class="abg-student-grid">';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Semester Average' ) . '</div><div class="abg-student-value">' .
	( $semester_summary ? number_format( (float) $semester_summary['AVERAGE_PERCENT'], 2 ) . '%' : '—' ) .
	'</div><div class="abg-student-subvalue">' . _( 'Average of official subject percentages' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Semester Class Rank' ) . '</div><div class="abg-student-value">' .
	( $semester_summary ? (int) $semester_summary['RANK_POSITION'] . ' / ' . (int) $semester_summary['COHORT_SIZE'] : '—' ) .
	'</div><div class="abg-student-subvalue">' . _( 'Compared with your grade-level cohort' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Outstanding Balance' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['BALANCE'] ) . '</div><div class="abg-student-subvalue">' . _( 'Charges less recorded payments' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Academic Year' ) . '</div><div class="abg-student-value">' .
	(int) UserSyear() . '</div><div class="abg-student-subvalue">' . _( 'Current school year' ) . '</div></div>';

echo '</div>';

echo '<div class="abg-student-section">';
echo '<div class="abg-section-header"><div><h3>' . _( 'Quick Access' ) . '</h3><p>' .
	_( 'Open the student services you use most often.' ) . '</p></div></div>';
echo '<div class="abg-student-actions">';
echo '<a class="abg-student-link" href="Modules.php?modname=Students/MyCourses.php">' . _( 'My Courses' ) . '</a>';
echo '<a class="abg-student-link" href="Modules.php?modname=Students/MyGrades.php">' . _( 'My Grades' ) . '</a>';
echo '<a class="abg-student-link" href="Modules.php?modname=Students/MyPayments.php">' . _( 'Payments' ) . '</a>';
echo '<a class="abg-student-link secondary" href="Modules.php?modname=Students/MyProfile.php">' . _( 'My Profile' ) . '</a>';
echo '<a class="abg-student-link secondary" href="Modules.php?modname=Students/ReRegistration.php">' . _( 'Re-Registration' ) . '</a>';
echo '</div></div>';

echo '</div>';
