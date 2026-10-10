<?php
/**
 * Abugida SIS - Existing student re-registration payment verification.
 */

require_once 'ProgramFunctions/AbugidaReRegistration.fnc.php';

DrawHeader( ProgramTitle() );

if ( User( 'PROFILE' ) !== 'admin' )
{
	exit;
}

$error = [];
$note = [];

$request_id = (int) issetVal( $_REQUEST['request_id'], 0 );

if ( $request_id )
{
	$ret = DBGet( "SELECT r.*,s.FIRST_NAME,s.MIDDLE_NAME,s.LAST_NAME,
		tg.TITLE AS TARGET_GRADE_TITLE,mp.TITLE AS TARGET_SEMESTER_TITLE
		FROM abugida_reregistration_requests r
		JOIN students s ON s.STUDENT_ID=r.STUDENT_ID
		LEFT JOIN school_gradelevels tg ON tg.ID=r.TARGET_GRADE_ID
		LEFT JOIN school_marking_periods mp ON mp.MARKING_PERIOD_ID=r.TARGET_SEMESTER_ID
		WHERE r.ID='" . $request_id . "'
		AND r.SCHOOL_ID='" . UserSchool() . "'
		LIMIT 1" );

	$request = ! empty( $ret[1] ) ? $ret[1] : [];

	if ( $request
		&& $_SERVER['REQUEST_METHOD'] === 'POST'
		&& AllowEdit()
		&& $request['STATUS'] === 'PAYMENT_SUBMITTED'
		&& in_array( issetVal( $_POST['finance_action'], '' ), [ 'approve', 'reject' ], true ) )
	{
		$decision = issetVal( $_POST['finance_action'], '' );
		$reason = trim( (string) issetVal( $_POST['reason'], '' ) );
		$from = $request['STATUS'];

		if ( $decision === 'approve' )
		{
			DBUpdate(
				'abugida_reregistration_requests',
				[
					'STATUS' => 'PAYMENT_VERIFIED',
					'PAYMENT_STATUS' => 'VERIFIED',
					'FINANCE_DECISION_REASON' => null,
					'FINANCE_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
					'FINANCE_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
				],
				[ 'ID' => $request_id ]
			);

			AbugidaReRegistrationHistory(
				$request_id,
				$from,
				'PAYMENT_VERIFIED',
				'Re-registration payment verified',
				'FINANCE'
			);

			$note[] = button( 'check' ) . '&nbsp;' . _( 'Payment verified.' );
		}
		elseif ( $decision === 'reject' )
		{
			if ( $reason === '' )
			{
				$error[] = _( 'A rejection reason is required.' );
			}
			else
			{
				DBUpdate(
					'abugida_reregistration_requests',
					[
						'STATUS' => 'PAYMENT_DECLINED',
						'PAYMENT_STATUS' => 'DECLINED',
						'FINANCE_DECISION_REASON' => $reason,
						'FINANCE_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
						'FINANCE_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
					],
					[ 'ID' => $request_id ]
				);

				AbugidaReRegistrationHistory(
					$request_id,
					$from,
					'PAYMENT_DECLINED',
					'Re-registration payment rejected',
					'FINANCE',
					$reason
				);

				$note[] = button( 'check' ) . '&nbsp;' . _( 'Payment rejected and returned to the student.' );
			}
		}

		$ret = DBGet( "SELECT r.*,s.FIRST_NAME,s.MIDDLE_NAME,s.LAST_NAME,
			tg.TITLE AS TARGET_GRADE_TITLE,mp.TITLE AS TARGET_SEMESTER_TITLE
			FROM abugida_reregistration_requests r
			JOIN students s ON s.STUDENT_ID=r.STUDENT_ID
			LEFT JOIN school_gradelevels tg ON tg.ID=r.TARGET_GRADE_ID
			LEFT JOIN school_marking_periods mp ON mp.MARKING_PERIOD_ID=r.TARGET_SEMESTER_ID
			WHERE r.ID='" . $request_id . "'
			AND r.SCHOOL_ID='" . UserSchool() . "'
			LIMIT 1" );
		$request = ! empty( $ret[1] ) ? $ret[1] : [];
	}

	if ( $request )
	{
		echo ErrorMessage( $error );
		echo ErrorMessage( $note, 'note' );

		DrawHeader(
			'<a href="' . URLEscape( 'Modules.php?modname=Custom/ReRegistrationPayments.php' ) . '">' .
			_( 'Re-Registration Payments' ) . '</a> &raquo; ' .
			AttrEscape( $request['REQUEST_REFERENCE'] )
		);

		echo '<style>
			.abg-fin-card{max-width:900px;background:#fff;border:1px solid #d9e1ea;border-radius:12px;padding:18px;margin:16px 0}
			.abg-fin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
			.abg-fin-item{padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fafcff}
			.abg-fin-label{font-size:12px;color:#667085}.abg-fin-value{margin-top:4px;font-weight:700}
			.abg-fin-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
			.abg-fin-btn{border:0;border-radius:8px;padding:10px 14px;font-weight:800;cursor:pointer;text-decoration:none}
			.abg-fin-primary{background:#2563eb;color:#fff}.abg-fin-success{background:#15803d;color:#fff}.abg-fin-danger{background:#b42318;color:#fff}
			.abg-fin-card textarea{width:100%;max-width:700px;padding:9px;border:1px solid #cbd5e1;border-radius:8px}
			@media(max-width:700px){.abg-fin-grid{grid-template-columns:1fr}}
		</style>';

		echo '<div class="abg-fin-card"><h3>' . _( 'Payment Verification' ) . '</h3>';
		echo '<div class="abg-fin-grid">';
		echo '<div class="abg-fin-item"><div class="abg-fin-label">' . _( 'Student' ) . '</div><div class="abg-fin-value">' .
			AttrEscape( trim( $request['FIRST_NAME'] . ' ' . $request['MIDDLE_NAME'] . ' ' . $request['LAST_NAME'] ) ) .
			' (' . (int) $request['STUDENT_ID'] . ')</div></div>';
		echo '<div class="abg-fin-item"><div class="abg-fin-label">' . _( 'Requested For' ) . '</div><div class="abg-fin-value">' .
			AttrEscape( AbugidaReRegistrationTargetLabel( $request ) ) . '</div></div>';
		echo '<div class="abg-fin-item"><div class="abg-fin-label">' . _( 'Amount Due' ) . '</div><div class="abg-fin-value">' .
			Currency( $request['PAYMENT_AMOUNT'] ) . '</div></div>';
		echo '<div class="abg-fin-item"><div class="abg-fin-label">' . _( 'Payment Status' ) . '</div><div class="abg-fin-value">' .
			AttrEscape( $request['PAYMENT_STATUS'] ) . '</div></div>';
		echo '</div>';

		if ( $request['RECEIPT_STORED_NAME'] )
		{
			echo '<div class="abg-fin-actions"><a class="abg-fin-btn abg-fin-primary" href="' .
				URLEscape( 'reregistration-receipt.php?request_id=' . $request_id . '&mode=download' ) .
				'">' . _( 'Download Payment Receipt' ) . '</a></div>';
		}

		if ( $request['STATUS'] === 'PAYMENT_SUBMITTED' && AllowEdit() )
		{
			echo '<hr><h4>' . _( 'Finance Decision' ) . '</h4>';

			echo '<form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/ReRegistrationPayments.php&request_id=' . $request_id ) . '">';
			echo '<input type="hidden" name="finance_action" value="approve">';
			echo '<div class="abg-fin-actions">';
			echo '<button class="abg-fin-btn abg-fin-success" type="submit">' . _( 'Verify Payment' ) . '</button>';
			echo '</div></form>';

			echo '<form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/ReRegistrationPayments.php&request_id=' . $request_id ) . '">';
			echo '<input type="hidden" name="finance_action" value="reject">';
			echo '<p><label><b>' . _( 'Rejection Reason' ) . '</b><br><textarea name="reason" rows="4" required></textarea></label></p>';
			echo '<div class="abg-fin-actions">';
			echo '<button class="abg-fin-btn abg-fin-danger" type="submit">' . _( 'Reject Payment' ) . '</button>';
			echo '</div></form>';
		}
		elseif ( $request['STATUS'] === 'PAYMENT_DECLINED' )
		{
			echo ErrorMessage( [ _( 'Payment rejected: ' ) . $request['FINANCE_DECISION_REASON'] ], 'warning' );
		}
		elseif ( $request['STATUS'] === 'PAYMENT_VERIFIED' )
		{
			echo ErrorMessage( [ _( 'Payment is verified and waiting for Registrar final confirmation.' ) ], 'note' );
		}

		echo '</div>';
		return;
	}
}

$rows = DBGet( "SELECT r.ID,r.REQUEST_REFERENCE,r.STUDENT_ID,
	CONCAT(s.FIRST_NAME,' ',s.LAST_NAME) AS STUDENT_NAME,
	r.REQUEST_TYPE,r.TARGET_SYEAR,tg.TITLE AS TARGET_GRADE,
	r.PAYMENT_AMOUNT,r.PAYMENT_STATUS,r.STATUS,r.RECEIPT_SUBMITTED_AT
	FROM abugida_reregistration_requests r
	JOIN students s ON s.STUDENT_ID=r.STUDENT_ID
	LEFT JOIN school_gradelevels tg ON tg.ID=r.TARGET_GRADE_ID
	WHERE r.SCHOOL_ID='" . UserSchool() . "'
	AND r.STATUS IN ('APPROVED_FOR_PAYMENT','PAYMENT_SUBMITTED','PAYMENT_DECLINED','PAYMENT_VERIFIED')
	ORDER BY r.ID DESC" );

echo '<style>
	.abg-fin-table{width:100%;border-collapse:collapse;background:#fff}
	.abg-fin-table th,.abg-fin-table td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left}
	.abg-fin-table th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}
	.abg-fin-view{display:inline-block;padding:7px 11px;border-radius:7px;background:#2563eb;color:#fff;text-decoration:none;font-weight:800}
</style>';

if ( ! $rows )
{
	echo ErrorMessage( [ _( 'No re-registration payments were found.' ) ], 'note' );
	return;
}

echo '<table class="abg-fin-table"><thead><tr>';
echo '<th>' . _( 'Reference' ) . '</th><th>' . _( 'Student' ) . '</th><th>' . _( 'Target' ) . '</th>';
echo '<th>' . _( 'Amount' ) . '</th><th>' . _( 'Payment Status' ) . '</th><th>' . _( 'Request Status' ) . '</th><th>' . _( 'Action' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( (array) $rows as $row )
{
	echo '<tr>';
	echo '<td>' . AttrEscape( $row['REQUEST_REFERENCE'] ) . '</td>';
	echo '<td>' . AttrEscape( $row['STUDENT_NAME'] ) . ' (' . (int) $row['STUDENT_ID'] . ')</td>';
	echo '<td>' . (int) $row['TARGET_SYEAR'] . ' / ' . AttrEscape( $row['TARGET_GRADE'] ) . '</td>';
	echo '<td>' . Currency( $row['PAYMENT_AMOUNT'] ) . '</td>';
	echo '<td>' . AttrEscape( $row['PAYMENT_STATUS'] ) . '</td>';
	echo '<td>' . AttrEscape( AbugidaReRegistrationStatusLabel( $row['STATUS'] ) ) . '</td>';
	echo '<td><a class="abg-fin-view" href="' .
		URLEscape( 'Modules.php?modname=Custom/ReRegistrationPayments.php&request_id=' . (int) $row['ID'] ) . '">' .
		_( 'Review' ) . '</a></td>';
	echo '</tr>';
}

echo '</tbody></table>';
