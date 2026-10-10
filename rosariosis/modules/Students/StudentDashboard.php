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

echo '<div class="abg-student-shell">';

echo '<div class="abg-student-grid">';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Grade Level' ) . '</div><div class="abg-student-value">' .
	AttrEscape( issetVal( $enrollment['GRADE_TITLE'], '—' ) ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Current Semester' ) . '</div><div class="abg-student-value">' .
	AttrEscape( $current_semester ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Active Courses' ) . '</div><div class="abg-student-value">' .
	(int) $active_courses . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Payment Status' ) . '</div><div class="abg-student-value">' .
	AttrEscape( $billing['STATUS'] ) . '</div></div>';
echo '</div>';

echo '<div class="abg-student-grid">';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Semester Average' ) . '</div><div class="abg-student-value">' .
	( $semester_summary ? number_format( (float) $semester_summary['AVERAGE_PERCENT'], 2 ) . '%' : '—' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Semester Class Rank' ) . '</div><div class="abg-student-value">' .
	( $semester_summary ? (int) $semester_summary['RANK_POSITION'] . ' / ' . (int) $semester_summary['COHORT_SIZE'] : '—' ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Outstanding Balance' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['BALANCE'] ) . '</div></div>';

echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Academic Year' ) . '</div><div class="abg-student-value">' .
	(int) UserSyear() . '</div></div>';

echo '</div>';

echo '<div class="abg-student-section"><h3>' . _( 'Quick Access' ) . '</h3>';
echo '<p class="abg-student-muted">' . _( 'View your current academic and account information.' ) . '</p>';
echo '<div class="abg-student-actions">';
echo '<a class="abg-student-link" href="Modules.php?modname=Students/MyCourses.php">' . _( 'My Courses' ) . '</a>';
echo '<a class="abg-student-link" href="Modules.php?modname=Students/MyGrades.php">' . _( 'My Grades' ) . '</a>';
echo '<a class="abg-student-link" href="Modules.php?modname=Students/MyPayments.php">' . _( 'Payments' ) . '</a>';
echo '<a class="abg-student-link" href="Modules.php?modname=Students/MyProfile.php">' . _( 'My Profile' ) . '</a>';
echo '</div></div>';

echo '</div>';
