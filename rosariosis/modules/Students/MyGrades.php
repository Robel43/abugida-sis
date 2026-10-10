<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'My Grades' ) );
AbugidaStudentPortalStyles();

$semesters = AbugidaStudentPortalSemesters();
$grades = AbugidaStudentPortalGrades();
$rank = AbugidaStudentPortalRankSummary();

echo '<div class="abg-student-shell">';
echo '<div class="abg-page-hero"><h2>' . _( 'My Grades' ) . '</h2><p>' .
	_( 'Review your official Semester results, academic averages, and class rank in one place.' ) . '</p></div>';

echo '<div class="abg-student-section">';
echo '<div class="abg-section-header"><div><h3>' . _( 'Official Grades' ) . '</h3><p>' .
	_( 'Subject grades and averages are percentages. Pending means an official Semester grade has not yet been published.' ) . '</p></div></div>';

echo '<div class="abg-student-table-wrap"><table class="abg-student-table"><thead><tr><th>' . _( 'Subject' ) . '</th>';

foreach ( (array) $semesters as $semester )
{
	echo '<th>' . AttrEscape( $semester['TITLE'] ) . '</th>';
}

echo '</tr></thead><tbody>';

foreach ( (array) $grades as $course )
{
	echo '<tr><td><span class="abg-grade-value">' . AttrEscape( $course['COURSE_TITLE'] ) . '</span></td>';

	foreach ( (array) $semesters as $semester )
	{
		$semester_id = (int) $semester['MARKING_PERIOD_ID'];

		if ( isset( $course['SEMESTERS'][$semester_id] ) )
		{
			echo '<td><span class="abg-grade-value">' .
				number_format( (float) $course['SEMESTERS'][$semester_id], 2 ) . '%</span></td>';
		}
		else
		{
			echo '<td><span class="abg-badge neutral">' . _( 'Pending' ) . '</span></td>';
		}
	}

	echo '</tr>';
}

if ( ! $grades )
{
	echo '<tr><td colspan="' . ( count( (array) $semesters ) + 1 ) . '"><div class="abg-empty">' .
		_( 'No official Semester grades are available yet.' ) . '</div></td></tr>';
}

echo '</tbody></table></div></div>';

echo '<div class="abg-student-grid">';

foreach ( (array) $semesters as $semester )
{
	$semester_id = (int) $semester['MARKING_PERIOD_ID'];
	$summary = issetVal( $rank['SEMESTERS'][$semester_id], [] );

	echo '<div class="abg-student-card">';
	echo '<div class="abg-student-label">' . AttrEscape( $semester['TITLE'] ) . ' ' . _( 'Average' ) . '</div>';
	echo '<div class="abg-student-value">' .
		( $summary ? number_format( (float) $summary['AVERAGE_PERCENT'], 2 ) . '%' : '—' ) . '</div>';
	echo '<div class="abg-student-subvalue">' . _( 'Class Rank' ) . ': ' .
		( $summary ? (int) $summary['RANK_POSITION'] . ' / ' . (int) $summary['COHORT_SIZE'] : '—' ) . '</div>';
	echo '</div>';
}

$fy = $rank['FULL_YEAR'];

echo '<div class="abg-student-card">';
echo '<div class="abg-student-label">' . _( 'Full Year Cumulative Average' ) . '</div>';
echo '<div class="abg-student-value">' .
	( $fy ? number_format( (float) $fy['AVERAGE_PERCENT'], 2 ) . '%' : '—' ) . '</div>';
echo '<div class="abg-student-subvalue">' . _( 'Full Year Class Rank' ) . ': ' .
	( $fy ? (int) $fy['RANK_POSITION'] . ' / ' . (int) $fy['COHORT_SIZE'] : '—' ) . '</div>';
echo '</div>';

echo '</div></div>';
