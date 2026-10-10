<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'My Grades' ) );
AbugidaStudentPortalStyles();

$semesters = AbugidaStudentPortalSemesters();
$grades = AbugidaStudentPortalGrades();
$rank = AbugidaStudentPortalRankSummary();

echo '<div class="abg-student-shell"><div class="abg-student-section">';
echo '<h3>' . _( 'Official Grades' ) . '</h3>';
echo '<p class="abg-student-muted">' . _( 'Only official SIS Semester grades are shown. Subject grades and averages are percentages; no GPA is used.' ) . '</p>';

echo '<div style="overflow:auto"><table class="abg-student-table"><thead><tr><th>' . _( 'Subject' ) . '</th>';

foreach ( (array) $semesters as $semester )
{
	echo '<th>' . AttrEscape( $semester['TITLE'] ) . '</th>';
}

echo '</tr></thead><tbody>';

foreach ( (array) $grades as $course )
{
	echo '<tr><td>' . AttrEscape( $course['COURSE_TITLE'] ) . '</td>';

	foreach ( (array) $semesters as $semester )
	{
		$semester_id = (int) $semester['MARKING_PERIOD_ID'];
		echo '<td>' . ( isset( $course['SEMESTERS'][$semester_id] ) ?
			number_format( (float) $course['SEMESTERS'][$semester_id], 2 ) . '%' :
			'<span class="abg-student-muted">' . _( 'Pending' ) . '</span>' ) . '</td>';
	}

	echo '</tr>';
}

if ( ! $grades )
{
	echo '<tr><td colspan="' . ( count( (array) $semesters ) + 1 ) . '">' .
		_( 'No official Semester grades are available yet.' ) . '</td></tr>';
}

echo '</tbody></table></div></div>';

echo '<div class="abg-student-grid">';

foreach ( (array) $semesters as $semester )
{
	$semester_id = (int) $semester['MARKING_PERIOD_ID'];
	$summary = issetVal( $rank['SEMESTERS'][$semester_id], [] );

	echo '<div class="abg-student-card"><div class="abg-student-label">' . AttrEscape( $semester['TITLE'] ) . ' ' . _( 'Average' ) . '</div>';
	echo '<div class="abg-student-value">' . ( $summary ? number_format( (float) $summary['AVERAGE_PERCENT'], 2 ) . '%' : '—' ) . '</div>';
	echo '<div class="abg-student-muted">' . _( 'Class Rank' ) . ': ' .
		( $summary ? (int) $summary['RANK_POSITION'] . ' / ' . (int) $summary['COHORT_SIZE'] : '—' ) . '</div></div>';
}

$fy = $rank['FULL_YEAR'];
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Full Year Cumulative Average' ) . '</div>';
echo '<div class="abg-student-value">' . ( $fy ? number_format( (float) $fy['AVERAGE_PERCENT'], 2 ) . '%' : '—' ) . '</div>';
echo '<div class="abg-student-muted">' . _( 'Full Year Class Rank' ) . ': ' .
	( $fy ? (int) $fy['RANK_POSITION'] . ' / ' . (int) $fy['COHORT_SIZE'] : '—' ) . '</div></div>';

echo '</div></div>';
