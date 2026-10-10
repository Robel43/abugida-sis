<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'My Courses' ) );
AbugidaStudentPortalStyles();

$courses = AbugidaStudentPortalCourses();

echo '<div class="abg-student-shell"><div class="abg-student-section">';
echo '<h3>' . _( 'Enrolled Courses' ) . '</h3>';
echo '<p class="abg-student-muted">' . _( 'Courses are read from your official SIS schedule for the current academic year.' ) . '</p>';

echo '<div style="overflow:auto"><table class="abg-student-table"><thead><tr>';
echo '<th>' . _( 'Subject' ) . '</th><th>' . _( 'Section' ) . '</th><th>' . _( 'Teacher' ) . '</th>';
echo '<th>' . _( 'Semester' ) . '</th><th>' . _( 'Status' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( (array) $courses as $course )
{
	echo '<tr>';
	echo '<td>' . AttrEscape( $course['COURSE_TITLE'] ) . '</td>';
	echo '<td>' . AttrEscape( $course['COURSE_PERIOD_TITLE'] ) . '</td>';
	echo '<td>' . AttrEscape( $course['TEACHER'] ) . '</td>';
	echo '<td>' . AttrEscape( $course['SEMESTER_TITLE'] ) . '</td>';
	echo '<td><span class="abg-status">' . AttrEscape( $course['STATUS'] ) . '</span></td>';
	echo '</tr>';
}

if ( ! $courses )
{
	echo '<tr><td colspan="5">' . _( 'No courses are scheduled for the current academic year.' ) . '</td></tr>';
}

echo '</tbody></table></div></div></div>';
