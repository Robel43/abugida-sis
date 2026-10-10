<?php
/**
 * Abugida SIS - Existing student re-registration review.
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
	$ret = DBGet( "SELECT r.*,s.FIRST_NAME,s.MIDDLE_NAME,s.LAST_NAME,s.USERNAME,
		fg.TITLE AS FROM_GRADE_TITLE,tg.TITLE AS TARGET_GRADE_TITLE,
		mp.TITLE AS TARGET_SEMESTER_TITLE
		FROM abugida_reregistration_requests r
		JOIN students s ON s.STUDENT_ID=r.STUDENT_ID
		LEFT JOIN school_gradelevels fg ON fg.ID=r.FROM_GRADE_ID
		LEFT JOIN school_gradelevels tg ON tg.ID=r.TARGET_GRADE_ID
		LEFT JOIN school_marking_periods mp ON mp.MARKING_PERIOD_ID=r.TARGET_SEMESTER_ID
		WHERE r.ID='" . $request_id . "'
		AND r.SCHOOL_ID='" . UserSchool() . "'
		LIMIT 1" );

	$request = ! empty( $ret[1] ) ? $ret[1] : [];

	if ( $request
		&& $_SERVER['REQUEST_METHOD'] === 'POST'
		&& AllowEdit() )
	{
		$action = issetVal( $_POST['action'], '' );
		$from = $request['STATUS'];

		if ( $action === 'reject'
			&& in_array( $request['STATUS'], [ 'SUBMITTED', 'READY_FOR_FINAL' ], true ) )
		{
			$reason = trim( (string) issetVal( $_POST['reason'], '' ) );

			if ( $reason === '' )
			{
				$error[] = _( 'A reason is required when returning a request.' );
			}
			else
			{
				DBUpdate(
					'abugida_reregistration_requests',
					[
						'STATUS' => 'DECLINED',
						'REGISTRAR_DECISION_REASON' => $reason,
						'REGISTRAR_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
						'REGISTRAR_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
					],
					[ 'ID' => $request_id ]
				);

				AbugidaReRegistrationHistory(
					$request_id,
					$from,
					'DECLINED',
					'Re-registration request returned',
					'REGISTRAR',
					$reason
				);

				$note[] = button( 'check' ) . '&nbsp;' . _( 'The request was returned to the student.' );
			}
		}
		elseif ( $action === 'approve'
			&& $request['STATUS'] === 'SUBMITTED' )
		{
			$amount = AbugidaReRegistrationFee(
				$request['TARGET_SYEAR'],
				$request['TARGET_GRADE_ID']
			);

			if ( $amount === null || $amount === false || $amount === '' )
			{
				$error[] = _( 'No registration fee is configured for the requested academic year and grade. Configure the fee under Student Billing > Registration Fees before approval.' );
			}
			else
			{
				$amount = (float) $amount;
				$next_status = $amount > 0 ? 'APPROVED_FOR_PAYMENT' : 'READY_FOR_FINAL';
				$payment_status = $amount > 0 ? 'PENDING' : 'NOT_REQUIRED';
				$instructions = $amount > 0 ?
					_( 'Your re-registration request has been approved. Complete the required payment and upload your receipt from the Student Portal.' ) :
					_( 'No payment is required for this re-registration request.' );

				DBUpdate(
					'abugida_reregistration_requests',
					[
						'STATUS' => $next_status,
						'REGISTRAR_DECISION_REASON' => null,
						'REGISTRAR_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
						'REGISTRAR_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
						'PAYMENT_AMOUNT' => $amount,
						'PAYMENT_INSTRUCTIONS' => $instructions,
						'PAYMENT_STATUS' => $payment_status,
					],
					[ 'ID' => $request_id ]
				);

				AbugidaReRegistrationHistory(
					$request_id,
					$from,
					$next_status,
					$amount > 0 ? 'Re-registration approved for payment' : 'Re-registration approved without payment',
					'REGISTRAR'
				);

				$note[] = button( 'check' ) . '&nbsp;' .
					( $amount > 0 ? _( 'Request approved for payment.' ) : _( 'Request approved and ready for final confirmation.' ) );
			}
		}
		elseif ( $action === 'final_confirm'
			&& AbugidaReRegistrationCanFinalize( $request ) )
		{
			$final_ok = true;

			if ( $request['REQUEST_TYPE'] === 'YEAR' )
			{
				$target_syear = (int) $request['TARGET_SYEAR'];
				$target_grade_id = (int) $request['TARGET_GRADE_ID'];

				$grade_exists = DBGetOne( "SELECT ID
					FROM school_gradelevels
					WHERE ID='" . $target_grade_id . "'
					AND SCHOOL_ID='" . UserSchool() . "'
					LIMIT 1" );

				$fy_start = DBGetOne( "SELECT START_DATE
					FROM school_marking_periods
					WHERE SCHOOL_ID='" . UserSchool() . "'
					AND SYEAR='" . $target_syear . "'
					AND MP='FY'
					ORDER BY MARKING_PERIOD_ID
					LIMIT 1" );

				if ( ! $grade_exists || ! $fy_start )
				{
					$final_ok = false;
					$error[] = _( 'The target academic year or grade is not fully configured.' );
				}
				else
				{
					$calendar_id = DBGetOne( "SELECT CALENDAR_ID
						FROM attendance_calendars
						WHERE SYEAR='" . $target_syear . "'
						AND SCHOOL_ID='" . UserSchool() . "'
						AND DEFAULT_CALENDAR='Y'
						LIMIT 1" );

					$enrollment_code = DBGetOne( "SELECT ID
						FROM student_enrollment_codes
						WHERE SYEAR='" . $target_syear . "'
						AND TYPE='Add'
						AND DEFAULT_CODE='Y'
						LIMIT 1" );

					$existing_enrollment = DBGetOne( "SELECT ID
						FROM student_enrollment
						WHERE STUDENT_ID='" . (int) $request['STUDENT_ID'] . "'
						AND SCHOOL_ID='" . UserSchool() . "'
						AND SYEAR='" . $target_syear . "'
						ORDER BY ID DESC
						LIMIT 1" );

					$enrollment_values = [
						'GRADE_ID' => $target_grade_id,
						'START_DATE' => $fy_start,
						'NEXT_SCHOOL' => UserSchool(),
						'CALENDAR_ID' => $calendar_id ? (int) $calendar_id : null,
					];

					if ( $enrollment_code )
					{
						$enrollment_values['ENROLLMENT_CODE'] = (int) $enrollment_code;
					}

					if ( $existing_enrollment )
					{
						// Preserve dates and other enrollment details if a future-year
						// enrollment row was already prepared by the Registrar.
						DBUpdate(
							'student_enrollment',
							[ 'GRADE_ID' => $target_grade_id ],
							[ 'ID' => (int) $existing_enrollment ]
						);
					}
					else
					{
						DBInsert(
							'student_enrollment',
							[
								'STUDENT_ID' => (int) $request['STUDENT_ID'],
								'SYEAR' => $target_syear,
								'SCHOOL_ID' => UserSchool(),
							] + $enrollment_values
						);
					}
				}
			}

			if ( $final_ok )
			{
				DBUpdate(
					'abugida_reregistration_requests',
					[
						'STATUS' => 'COMPLETED',
						'FINAL_CONFIRMED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
						'FINAL_CONFIRMED_BY' => (int) User( 'STAFF_ID' ),
					],
					[ 'ID' => $request_id ]
				);

				AbugidaReRegistrationHistory(
					$request_id,
					$from,
					'COMPLETED',
					$request['REQUEST_TYPE'] === 'YEAR' ?
						'New academic year enrollment confirmed' :
						'Semester re-registration confirmed',
					'REGISTRAR'
				);

				$note[] = button( 'check' ) . '&nbsp;' . _( 'Re-registration completed successfully.' );
			}
		}

		$ret = DBGet( "SELECT r.*,s.FIRST_NAME,s.MIDDLE_NAME,s.LAST_NAME,s.USERNAME,
			fg.TITLE AS FROM_GRADE_TITLE,tg.TITLE AS TARGET_GRADE_TITLE,
			mp.TITLE AS TARGET_SEMESTER_TITLE
			FROM abugida_reregistration_requests r
			JOIN students s ON s.STUDENT_ID=r.STUDENT_ID
			LEFT JOIN school_gradelevels fg ON fg.ID=r.FROM_GRADE_ID
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
			'<a href="' . URLEscape( 'Modules.php?modname=Custom/ReRegistrationReview.php' ) . '">' .
			_( 'Re-Registration Requests' ) . '</a> &raquo; ' .
			AttrEscape( $request['REQUEST_REFERENCE'] )
		);

		echo '<style>
			.abg-rereg-review{max-width:1050px}
			.abg-rereg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}
			.abg-rereg-card{background:#fff;border:1px solid #d9e1ea;border-radius:12px;padding:17px}
			.abg-rereg-card h3{margin:0 0 12px}
			.abg-rereg-row{margin:10px 0}.abg-rereg-label{display:block;color:#667085;font-size:12px;margin-bottom:3px}.abg-rereg-value{font-weight:700}
			.abg-rereg-status{display:inline-flex;padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:800}
			.abg-rereg-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:15px}
			.abg-rereg-btn{border:0;border-radius:8px;padding:10px 14px;font-weight:800;cursor:pointer}
			.abg-rereg-primary{background:#2563eb;color:#fff}.abg-rereg-success{background:#15803d;color:#fff}.abg-rereg-danger{background:#b42318;color:#fff}
			.abg-rereg-decision{background:#fff;border:1px solid #d9e1ea;border-radius:12px;padding:17px;margin-top:16px}
			.abg-rereg-decision textarea{width:100%;max-width:720px;border:1px solid #cbd5e1;border-radius:8px;padding:9px}
			@media(max-width:760px){.abg-rereg-grid{grid-template-columns:1fr}}
		</style>';

		echo '<div class="abg-rereg-review">';
		echo '<div class="abg-rereg-grid">';
		echo '<div class="abg-rereg-card"><h3>' . _( 'Student' ) . '</h3>';
		echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Name' ) . '</span><div class="abg-rereg-value">' .
			AttrEscape( trim( $request['FIRST_NAME'] . ' ' . $request['MIDDLE_NAME'] . ' ' . $request['LAST_NAME'] ) ) . '</div></div>';
		echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Student ID' ) . '</span><div class="abg-rereg-value">' .
			(int) $request['STUDENT_ID'] . '</div></div>';
		echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Current Grade' ) . '</span><div class="abg-rereg-value">' .
			AttrEscape( $request['FROM_GRADE_TITLE'] ) . '</div></div>';
		echo '</div>';

		echo '<div class="abg-rereg-card"><h3>' . _( 'Requested Enrollment' ) . '</h3>';
		echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Type' ) . '</span><div class="abg-rereg-value">' .
			( $request['REQUEST_TYPE'] === 'SEMESTER' ? _( 'Semester' ) : _( 'Academic Year' ) ) . '</div></div>';
		echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Target' ) . '</span><div class="abg-rereg-value">' .
			AttrEscape( AbugidaReRegistrationTargetLabel( $request ) ) . '</div></div>';
		echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Status' ) . '</span><span class="abg-rereg-status">' .
			AttrEscape( AbugidaReRegistrationStatusLabel( $request['STATUS'] ) ) . '</span></div>';
		echo '</div></div>';

		if ( $request['PAYMENT_AMOUNT'] !== null )
		{
			echo '<div class="abg-rereg-card"><h3>' . _( 'Payment' ) . '</h3>';
			echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Amount' ) . '</span><div class="abg-rereg-value">' .
				Currency( $request['PAYMENT_AMOUNT'] ) . '</div></div>';
			echo '<div class="abg-rereg-row"><span class="abg-rereg-label">' . _( 'Payment Status' ) . '</span><div class="abg-rereg-value">' .
				AttrEscape( $request['PAYMENT_STATUS'] ) . '</div></div>';
			echo '</div>';
		}

		if ( $request['STATUS'] === 'SUBMITTED' && AllowEdit() )
		{
			echo '<div class="abg-rereg-decision"><h3>' . _( 'Registrar Decision' ) . '</h3>';
			echo '<form method="POST"><input type="hidden" name="action" value="approve">';
			echo '<p>' . _( 'Approval uses the registration fee configured for the requested academic year and target grade. A zero fee skips Finance verification.' ) . '</p>';
			echo '<button class="abg-rereg-btn abg-rereg-success" type="submit">' . _( 'Approve Request' ) . '</button></form>';
			echo '<hr>';
			echo '<form method="POST"><input type="hidden" name="action" value="reject">';
			echo '<p><label><b>' . _( 'Return Reason' ) . '</b><br><textarea name="reason" rows="4" required></textarea></label></p>';
			echo '<button class="abg-rereg-btn abg-rereg-danger" type="submit">' . _( 'Return to Student' ) . '</button></form>';
			echo '</div>';
		}
		elseif ( AbugidaReRegistrationCanFinalize( $request ) && AllowEdit() )
		{
			echo '<div class="abg-rereg-decision"><h3>' . _( 'Final Confirmation' ) . '</h3>';
			echo '<p>' . ( $request['REQUEST_TYPE'] === 'YEAR' ?
				_( 'Final confirmation creates or updates the student enrollment for the requested academic year and grade. The existing student account is retained.' ) :
				_( 'Final confirmation completes Semester re-registration. The existing annual enrollment and student account are retained.' ) ) . '</p>';
			echo '<form method="POST"><input type="hidden" name="action" value="final_confirm">';
			echo '<button class="abg-rereg-btn abg-rereg-success" type="submit">' . _( 'Final Confirm Re-Registration' ) . '</button></form>';
			echo '</div>';
		}
		elseif ( $request['STATUS'] === 'DECLINED' )
		{
			echo ErrorMessage( [ _( 'Returned: ' ) . $request['REGISTRAR_DECISION_REASON'] ], 'warning' );
		}
		elseif ( $request['STATUS'] === 'COMPLETED' )
		{
			echo ErrorMessage( [ _( 'Re-registration is complete.' ) ], 'note' );
		}

		echo '</div>';
		return;
	}
}

$rows = DBGet( "SELECT r.ID,r.REQUEST_REFERENCE,r.STUDENT_ID,
	CONCAT(s.FIRST_NAME,' ',s.LAST_NAME) AS STUDENT_NAME,
	r.REQUEST_TYPE,r.TARGET_SYEAR,tg.TITLE AS TARGET_GRADE,r.STATUS,r.CREATED_AT
	FROM abugida_reregistration_requests r
	JOIN students s ON s.STUDENT_ID=r.STUDENT_ID
	LEFT JOIN school_gradelevels tg ON tg.ID=r.TARGET_GRADE_ID
	WHERE r.SCHOOL_ID='" . UserSchool() . "'
	ORDER BY r.ID DESC" );

echo '<style>
	.abg-rereg-table{width:100%;border-collapse:collapse;background:#fff}
	.abg-rereg-table th,.abg-rereg-table td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left}
	.abg-rereg-table th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}
	.abg-rereg-view{display:inline-block;padding:7px 11px;border-radius:7px;background:#2563eb;color:#fff;text-decoration:none;font-weight:800}
</style>';

if ( ! $rows )
{
	echo ErrorMessage( [ _( 'No re-registration requests were found.' ) ], 'note' );
	return;
}

echo '<table class="abg-rereg-table"><thead><tr>';
echo '<th>' . _( 'Reference' ) . '</th><th>' . _( 'Student' ) . '</th><th>' . _( 'Type' ) . '</th>';
echo '<th>' . _( 'Target Year' ) . '</th><th>' . _( 'Target Grade' ) . '</th><th>' . _( 'Status' ) . '</th><th>' . _( 'Submitted' ) . '</th><th>' . _( 'Action' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( (array) $rows as $row )
{
	echo '<tr>';
	echo '<td>' . AttrEscape( $row['REQUEST_REFERENCE'] ) . '</td>';
	echo '<td>' . AttrEscape( $row['STUDENT_NAME'] ) . ' (' . (int) $row['STUDENT_ID'] . ')</td>';
	echo '<td>' . AttrEscape( $row['REQUEST_TYPE'] === 'SEMESTER' ? _( 'Semester' ) : _( 'Academic Year' ) ) . '</td>';
	echo '<td>' . (int) $row['TARGET_SYEAR'] . '</td>';
	echo '<td>' . AttrEscape( $row['TARGET_GRADE'] ) . '</td>';
	echo '<td>' . AttrEscape( AbugidaReRegistrationStatusLabel( $row['STATUS'] ) ) . '</td>';
	echo '<td>' . AttrEscape( $row['CREATED_AT'] ) . '</td>';
	echo '<td><a class="abg-rereg-view" href="' .
		URLEscape( 'Modules.php?modname=Custom/ReRegistrationReview.php&request_id=' . (int) $row['ID'] ) . '">' .
		_( 'Review' ) . '</a></td>';
	echo '</tr>';
}

echo '</tbody></table>';
