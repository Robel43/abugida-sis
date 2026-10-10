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
$status_class = 'success';

if ( ! empty( $enrollment['START_DATE'] ) && DBDate() < $enrollment['START_DATE'] )
{
	$status = _( 'Upcoming' );
	$status_class = 'warning';
}
elseif ( ! empty( $enrollment['END_DATE'] ) && DBDate() > $enrollment['END_DATE'] )
{
	$status = _( 'Inactive' );
	$status_class = 'neutral';
}

echo '<div class="abg-student-shell">';
echo '<div class="abg-page-hero"><h2>' . _( 'My Profile' ) . '</h2><p>' .
	_( 'Review your official student and enrollment information for the current academic year.' ) . '</p></div>';

echo '<div class="abg-student-section">';
echo '<div class="abg-section-header"><div><h3>' . _( 'Student Information' ) . '</h3><p>' .
	_( 'This information is read-only. Contact the Registrar if an official record needs correction.' ) . '</p></div></div>';

echo '<div class="abg-info-list">';

echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Full Name' ) . '</div><div class="abg-info-value">' .
	AttrEscape( $full_name ) . '</div></div>';

echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Student ID' ) . '</div><div class="abg-info-value">' .
	(int) UserStudentID() . '</div></div>';

echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Username' ) . '</div><div class="abg-info-value">' .
	AttrEscape( issetVal( $student['USERNAME'], '' ) ) . '</div></div>';

echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'School' ) . '</div><div class="abg-info-value">' .
	AttrEscape( issetVal( $enrollment['SCHOOL_TITLE'], '' ) ) . '</div></div>';

echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Grade Level' ) . '</div><div class="abg-info-value">' .
	AttrEscape( issetVal( $enrollment['GRADE_TITLE'], '' ) ) . '</div></div>';

echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Academic Year' ) . '</div><div class="abg-info-value">' .
	(int) UserSyear() . '</div></div>';

echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Enrollment Status' ) . '</div><div class="abg-info-value"><span class="abg-badge ' .
	$status_class . '">' . AttrEscape( $status ) . '</span></div></div>';

echo '</div>';
echo '</div></div>';
