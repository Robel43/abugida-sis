<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'My Courses' ) );
AbugidaStudentPortalStyles();

$courses = AbugidaStudentPortalCourses();

echo '<div class="abg-student-shell">';
echo '<div class="abg-page-hero"><h2>' . _( 'My Courses' ) . '</h2><p>' .
	_( 'View your enrolled subjects, sections, assigned teachers, Semester, and course status.' ) . '</p></div>';

echo '<div class="abg-student-section">';
echo '<div class="abg-section-header"><div><h3>' . _( 'Enrolled Courses' ) . '</h3><p>' .
	_( 'Courses are read directly from your official SIS schedule for the current academic year.' ) . '</p></div></div>';

echo '<div class="abg-student-table-wrap"><table class="abg-student-table"><thead><tr>';
echo '<th>' . _( 'Subject' ) . '</th><th>' . _( 'Section' ) . '</th><th>' . _( 'Teacher' ) . '</th>';
echo '<th>' . _( 'Semester' ) . '</th><th>' . _( 'Status' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( (array) $courses as $course )
{
	$status_class = 'neutral';

	if ( $course['STATUS'] === _( 'Active' ) )
	{
		$status_class = 'success';
	}
	elseif ( $course['STATUS'] === _( 'Upcoming' ) )
	{
		$status_class = 'warning';
	}
	elseif ( $course['STATUS'] === _( 'Completed' ) )
	{
		$status_class = 'info';
	}

	echo '<tr>';
	echo '<td><span class="abg-grade-value">' . AttrEscape( $course['COURSE_TITLE'] ) . '</span></td>';
	echo '<td>' . AttrEscape( $course['COURSE_PERIOD_TITLE'] ) . '</td>';
	echo '<td>' . AttrEscape( $course['TEACHER'] ) . '</td>';
	echo '<td>' . AttrEscape( $course['SEMESTER_TITLE'] ) . '</td>';
	echo '<td><span class="abg-badge ' . $status_class . '">' . AttrEscape( $course['STATUS'] ) . '</span></td>';
	echo '</tr>';
}

if ( ! $courses )
{
	echo '<tr><td colspan="5"><div class="abg-empty">' .
		_( 'No courses are scheduled for the current academic year.' ) . '</div></td></tr>';
}

echo '</tbody></table></div></div></div>';
