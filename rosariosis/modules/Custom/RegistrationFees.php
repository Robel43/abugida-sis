<?php
/**
 * Abugida SIS - Grade-based online registration fees.
 */

DrawHeader( ProgramTitle() );

require_once 'ProgramFunctions/AbugidaWorkflow.fnc.php';

if ( ! AbugidaStaffAllowed( 'Custom/RegistrationFees.php' ) )
{
	exit;
}

$error = [];
$note = [];

$grades = DBGet( "SELECT ID,TITLE,SHORT_NAME,SORT_ORDER
	FROM school_gradelevels
	WHERE SCHOOL_ID='" . UserSchool() . "'
	ORDER BY SORT_ORDER,ID" );

if ( $_SERVER['REQUEST_METHOD'] === 'POST'
	&& AllowEdit()
	&& AbugidaValidStaffPost() )
{
	foreach ( (array) $grades as $grade )
	{
		$grade_id = (int) $grade['ID'];
		$key = 'fee_' . $grade_id;
		$value = trim( (string) issetVal( $_POST[ $key ], '' ) );

		if ( $value === '' )
		{
			continue;
		}

		if ( ! is_numeric( $value ) || (float) $value < 0 )
		{
			$error[] = sprintf( _( 'Enter a valid amount for %s.' ), $grade['TITLE'] );
			continue;
		}

		$amount = number_format( (float) $value, 2, '.', '' );

		$existing_id = DBGetOne( "SELECT ID
			FROM abugida_registration_fees
			WHERE SCHOOL_ID='" . UserSchool() . "'
			AND SYEAR='" . UserSyear() . "'
			AND GRADE_ID='" . $grade_id . "'
			LIMIT 1" );

		if ( $existing_id )
		{
			DBUpdate(
				'abugida_registration_fees',
				[
					'AMOUNT' => $amount,
					'UPDATED_BY' => (int) User( 'STAFF_ID' ),
				],
				[ 'ID' => (int) $existing_id ]
			);
		}
		else
		{
			DBInsert(
				'abugida_registration_fees',
				[
					'SCHOOL_ID' => UserSchool(),
					'SYEAR' => UserSyear(),
					'GRADE_ID' => $grade_id,
					'AMOUNT' => $amount,
					'UPDATED_BY' => (int) User( 'STAFF_ID' ),
				]
			);
		}
	}

	if ( empty( $error ) )
	{
		$note[] = button( 'check' ) . '&nbsp;' . _( 'Registration fees saved.' );
	}
}

echo ErrorMessage( $error );
echo ErrorMessage( $note, 'note' );

$fee_rows = DBGet( "SELECT GRADE_ID,AMOUNT
	FROM abugida_registration_fees
	WHERE SCHOOL_ID='" . UserSchool() . "'
	AND SYEAR='" . UserSyear() . "'" );

$fee_map = [];

foreach ( (array) $fee_rows as $row )
{
	$fee_map[ (int) $row['GRADE_ID'] ] = $row['AMOUNT'];
}

echo '<style>
	.abg-fees-wrap{max-width:820px}
	.abg-fees-card{background:#fff;border:1px solid #d9e1ea;border-radius:10px;padding:18px}
	.abg-fees-table{width:100%;border-collapse:collapse}
	.abg-fees-table th,.abg-fees-table td{padding:11px 12px;border-bottom:1px solid #e7ebf0;text-align:left}
	.abg-fees-table th{background:#f7f9fc;color:#344054}
	.abg-fees-table input{width:180px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px}
	.abg-fees-help{color:#667085;margin:0 0 16px;line-height:1.5}
	.abg-fees-btn{margin-top:16px;padding:10px 16px;border:0;border-radius:7px;background:#1677c8;color:#fff;font-weight:700;cursor:pointer}
</style>';

echo '<div class="abg-fees-wrap">';
echo '<div class="abg-fees-card">';
echo '<p class="abg-fees-help">' .
	_( 'Set the online registration payment amount once for each grade. When the Registrar approves an application, the system automatically uses the amount configured here.' ) .
	'</p>';

echo '<form method="POST">' . AbugidaCsrfField();
echo '<table class="abg-fees-table">';
echo '<thead><tr><th>' . _( 'Grade' ) . '</th><th>' . _( 'Registration Fee (ETB)' ) . '</th></tr></thead><tbody>';

foreach ( (array) $grades as $grade )
{
	$grade_id = (int) $grade['ID'];
	$current = isset( $fee_map[ $grade_id ] ) ? $fee_map[ $grade_id ] : '';

	echo '<tr>';
	echo '<td><b>' . AttrEscape( $grade['TITLE'] ) . '</b></td>';
	echo '<td><input type="number" min="0" step="0.01" name="fee_' . $grade_id . '" value="' . AttrEscape( $current ) . '" placeholder="0.00"' . ( AllowEdit() ? '' : ' readonly' ) . '></td>';
	echo '</tr>';
}

echo '</tbody></table>';

if ( AllowEdit() )
{
	echo '<button class="abg-fees-btn" type="submit">' . _( 'Save Registration Fees' ) . '</button>';
}

echo '</form></div></div>';
