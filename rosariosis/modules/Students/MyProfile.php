<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'My Profile' ) );
AbugidaStudentPortalStyles();

$data = AbugidaStudentPortalProfile();
$student = $data['STUDENT'];
$enrollment = $data['ENROLLMENT'];

$full_name = trim(
	issetVal( $student['FIRST_NAME'], '' ) . ' ' .
	issetVal( $student['MIDDLE_NAME'], '' ) . ' ' .
	issetVal( $student['LAST_NAME'], '' )
);

$status = _( 'Active' );
if ( ! empty( $enrollment['START_DATE'] ) && DBDate() < $enrollment['START_DATE'] )
{
	$status = _( 'Upcoming' );
}
elseif ( ! empty( $enrollment['END_DATE'] ) && DBDate() > $enrollment['END_DATE'] )
{
	$status = _( 'Inactive' );
}

echo '<div class="abg-student-shell"><div class="abg-student-section">';
echo '<h3>' . _( 'Student Information' ) . '</h3>';
echo '<table class="abg-student-table">';
echo '<tr><th>' . _( 'Full Name' ) . '</th><td>' . AttrEscape( $full_name ) . '</td></tr>';
echo '<tr><th>' . _( 'Student ID' ) . '</th><td>' . (int) UserStudentID() . '</td></tr>';
echo '<tr><th>' . _( 'Username' ) . '</th><td>' . AttrEscape( issetVal( $student['USERNAME'], '' ) ) . '</td></tr>';
echo '<tr><th>' . _( 'School' ) . '</th><td>' . AttrEscape( issetVal( $enrollment['SCHOOL_TITLE'], '' ) ) . '</td></tr>';
echo '<tr><th>' . _( 'Grade Level' ) . '</th><td>' . AttrEscape( issetVal( $enrollment['GRADE_TITLE'], '' ) ) . '</td></tr>';
echo '<tr><th>' . _( 'Academic Year' ) . '</th><td>' . (int) UserSyear() . '</td></tr>';
echo '<tr><th>' . _( 'Enrollment Status' ) . '</th><td>' . AttrEscape( $status ) . '</td></tr>';
echo '</table>';
echo '<p class="abg-student-muted">' . _( 'This page is read-only. Contact the Registrar if any official information needs correction.' ) . '</p>';
echo '</div></div>';
