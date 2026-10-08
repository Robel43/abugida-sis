<?php
/**
 * Custom module Menu entries
 *
 * @uses $menu global var
 *
 * @see  Menu.php in root folder
 *
 * @package RosarioSIS
 * @subpackage modules
 */

// Custom Students programs
if ( $RosarioModules['Students'] )
{
	$menu['Students']['admin'][] = _( 'Utilities' );
	$menu['Students']['admin'] += [
		'Custom/MyReport.php' => _( 'My Report' ),
		'Custom/CreateParents.php' => _( 'Create Parent Users' ),
		// @since 6.6 Add Registration program for Administrators.
		'Custom/Registration.php' => _( 'Registration' ),
		'Custom/ApplicationReview.php' => _( 'Online Applications' ),
		'Custom/RemoveAccess.php' => _( 'Remove Access' ),
	];

	$exceptions['Students']['Custom/CreateParents.php'] = true;

	$menu['Students']['parent']['Custom/Registration.php'] = _( 'Registration' );
}

// Abugida Student Billing workflow.
if ( $RosarioModules['Student_Billing'] )
{
	$menu['Student_Billing']['admin'] = issetVal( $menu['Student_Billing']['admin'], [] );

	$menu['Student_Billing']['admin'] += [
		'Custom/FinanceApplications.php' => _( 'Application Payments' ),
	];
}

// Custom Users programs
if ( $RosarioModules['Users'] )
{
	$menu['Users']['admin'][] = _( 'Utilities' );
	$menu['Users']['admin']['Custom/NotifyParents.php'] = _( 'Notify Parents' );

	$exceptions['Users']['Custom/NotifyParents.php'] = true;
}

// Custom Attendance programs
if ( $RosarioModules['Attendance'] )
{
	$menu['Attendance']['admin'] = issetVal( $menu['Attendance']['admin'], [] );

	// Place Attendance Summary program before Utilities separator.
	$utilities_pos = array_search( 2, array_keys( $menu['Attendance']['admin'] ) );

	$menu['Attendance']['admin'] = array_merge(
	    array_slice( $menu['Attendance']['admin'], 0, $utilities_pos ),
	    [ 'Custom/AttendanceSummary.php' => _( 'Attendance Summary' ) ],
	    array_slice( $menu['Attendance']['admin'], $utilities_pos )
	);
}
