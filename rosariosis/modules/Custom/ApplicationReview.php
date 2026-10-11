<?php
/**
 * Abugida SIS - Registrar online application review.
 */

DrawHeader( ProgramTitle() );

$abugida_decision_feedback = null;

require_once 'ProgramFunctions/AbugidaWorkflow.fnc.php';

if ( ! AbugidaStaffAllowed( 'Custom/ApplicationReview.php' ) )
{
	exit;
}

function AbugidaApplicationHistory( $applicant_id, $from, $to, $action, $reason = '' )
{
	DBInsert(
		'abugida_application_history',
		[
			'APPLICANT_ID' => (int) $applicant_id,
			'FROM_STATUS' => $from,
			'TO_STATUS' => $to,
			'ACTION' => $action,
			'ACTOR_TYPE' => 'REGISTRAR',
			'ACTOR_ID' => (int) User( 'STAFF_ID' ),
			'ACTOR_NAME' => User( 'NAME' ),
			'REASON' => $reason,
		]
	);
}


function AbugidaRegistrarStatusLabel( $status )
{
	$labels = [
		'SUBMITTED' => 'PENDING',
		'UNDER_REVIEW' => 'PENDING',
		'APPROVED_FOR_PAYMENT' => 'APPROVED',
		'DECLINED' => 'REJECTED',
		'PAYMENT_SUBMITTED' => 'PAYMENT SUBMITTED',
		'PAYMENT_DECLINED' => 'PAYMENT REJECTED',
		'PAYMENT_VERIFIED' => 'PAYMENT VERIFIED',
		'ACTIVE' => 'ACTIVE',
	];

	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}


function AbugidaFindConfiguredGradeId( $grade_number )
{
	$grade_number = (int) $grade_number;

	$grade_rows = DBGet( "SELECT ID,TITLE,SHORT_NAME
		FROM school_gradelevels
		WHERE SCHOOL_ID='" . UserSchool() . "'
		ORDER BY SORT_ORDER,ID" );

	foreach ( (array) $grade_rows as $grade_row )
	{
		$candidates = [
			(string) issetVal( $grade_row['TITLE'], '' ),
			(string) issetVal( $grade_row['SHORT_NAME'], '' ),
		];

		foreach ( $candidates as $candidate )
		{
			if ( preg_match( '/(^|[^0-9])' . $grade_number . '([^0-9]|$)/', $candidate ) )
			{
				return (int) $grade_row['ID'];
			}
		}
	}

	return 0;
}

require_once 'ProgramFunctions/AbugidaEmail.fnc.php';

if ( ! empty( $_REQUEST['applicant_id'] ) )
{
	$applicant_id = (int) $_REQUEST['applicant_id'];
	$applicant_RET = DBGet( "SELECT *
		FROM abugida_applicants
		WHERE ID='" . $applicant_id . "'
		LIMIT 1" );
	$applicant = ! empty( $applicant_RET[1] ) ? $applicant_RET[1] : null;


	if ( $applicant && in_array( $_REQUEST['modfunc'] ?? '', [ 'decision', 'retry_email' ], true ) )
	{
		try
		{
			if ( $_REQUEST['modfunc'] === 'decision' )
			{
				$notification_id = AbugidaReviewDecision( $applicant_id, 'REGISTRAR', (string) ( $_POST['decision'] ?? '' ), (string) ( $_POST['reason'] ?? '' ) );
				$note[] = 'Registrar decision saved.';
			}
			else
			{
				$notification_id = (int) ( $_POST['notification_id'] ?? 0 );
			}
			$delivery = AbugidaDeliverNotification( $notification_id, $applicant_id, 'REGISTRAR' );
			$note[] = $delivery === 'SENT' ? 'Notification accepted by the SMTP server.' : 'Notification status: ' . $delivery . '. The saved decision is preserved; see Email notifications below.';
		}
		catch ( RuntimeException $exception )
		{
			$error[] = htmlspecialchars( $exception instanceof PDOException ? 'Unable to save the request. Check that Stage 2 migration 007 is installed.' : $exception->getMessage(), ENT_QUOTES, 'UTF-8' );
		}
	}
	if ( $applicant
		&& $_SERVER['REQUEST_METHOD'] === 'POST'
		&& AbugidaValidStaffPost()
		&& $_REQUEST['modfunc'] === 'final_confirm'
		&& AllowEdit()
		&& $applicant['STATUS'] === 'PAYMENT_VERIFIED' )
	{

		DBQuery( 'START TRANSACTION' );
		$locked = DBGet( "SELECT * FROM abugida_applicants WHERE ID='" . $applicant_id . "' FOR UPDATE" );
		if ( empty( $locked[1] ) || $locked[1]['STATUS'] !== 'PAYMENT_VERIFIED' )
		{
			DBQuery( 'ROLLBACK' );
			$error[] = 'This application was already finalized or is not ready for final confirmation.';
			echo ErrorMessage( $error );
			return;
		}
		$applicant = $locked[1];
		$grade = (int) $applicant['GRADE_LEVEL'];
		$grade_id = AbugidaFindConfiguredGradeId( $grade );

		if ( ! $grade_id )
		{
			$error[] = _( 'The selected grade level is not configured for this school.' );
		}
		else
		{
			do
			{
				$student_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'students' : 'students_student_id_seq' );
			}
			while ( DBGetOne( "SELECT STUDENT_ID FROM students WHERE STUDENT_ID='" . (int) $student_id . "'" ) );

			$username = 'ABG' . $student_id;
			$temp_password = 'Abg!' . substr( bin2hex( random_bytes( 6 ) ), 0, 8 ) . '9a';

			DBInsert(
				'students',
				[
					'STUDENT_ID' => (int) $student_id,
					'FIRST_NAME' => $applicant['FIRST_NAME'],
					'LAST_NAME' => $applicant['LAST_NAME'],
					'USERNAME' => $username,
					'PASSWORD' => encrypt_password( $temp_password ),
				]
			);

			$calendar_id = DBGetOne( "SELECT CALENDAR_ID
				FROM attendance_calendars
				WHERE SYEAR='" . UserSyear() . "'
				AND SCHOOL_ID='" . UserSchool() . "'
				AND DEFAULT_CALENDAR='Y'
				LIMIT 1" );

			$enrollment_code = DBGetOne( "SELECT ID
				FROM student_enrollment_codes
				WHERE SYEAR='" . UserSyear() . "'
				AND TYPE='Add'
				AND DEFAULT_CODE='Y'
				LIMIT 1" );

			DBInsert(
				'student_enrollment',
				[
					'STUDENT_ID' => (int) $student_id,
					'SYEAR' => UserSyear(),
					'SCHOOL_ID' => UserSchool(),
					'GRADE_ID' => (int) $grade_id,
					'START_DATE' => DBDate(),
					'ENROLLMENT_CODE' => $enrollment_code ? (int) $enrollment_code : null,
					'NEXT_SCHOOL' => UserSchool(),
					'CALENDAR_ID' => $calendar_id ? (int) $calendar_id : null,
				]
			);

			$from = $applicant['STATUS'];

			DBUpdate(
				'abugida_applicants',
				[
					'STATUS' => 'ACTIVE',
					'FINAL_CONFIRMED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
					'FINAL_CONFIRMED_BY' => (int) User( 'STAFF_ID' ),
					'STUDENT_ID' => (int) $student_id,
					'GENERATED_USERNAME' => $username,
				],
				[ 'ID' => $applicant_id ]
			);

			AbugidaApplicationHistory( $applicant_id, $from, 'ACTIVE', 'Final registration confirmed' );

			$message = "Your Abugida SIS student account has been created.\n\n" .
				"Username: " . $username . "\n" .
				"Temporary password: " . $temp_password . "\n\n" .
				"Please sign in and change your password.";

			DBQuery( 'COMMIT' );
			AbugidaSendEmail( $applicant['EMAIL'], 'Abugida SIS account created', $message );

			$note[] = button( 'check' ) . '&nbsp;' . _( 'Student account created and registration completed.' );
		}

		DBQuery( 'ROLLBACK' ); // No-op after commit; releases a lock when prerequisites are missing.
		RedirectURL( [ 'modfunc' ] );
	}

	if ( $applicant )
	{
		$applicant_RET = DBGet( "SELECT * FROM abugida_applicants WHERE ID='" . $applicant_id . "' LIMIT 1" );
		$applicant = $applicant_RET[1];

		echo ErrorMessage( $error );
		echo ErrorMessage( $note, 'note' );

		DrawHeader(
			'<a href="' . URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php' ) . '">' . _( 'Online Applications' ) . '</a> &raquo; ' .
			AttrEscape( $applicant['APPLICATION_REFERENCE'] )
		);

		echo '<style>
			.abg-review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}
			.abg-card{background:#fff;border:1px solid #d9e1ea;border-radius:10px;padding:14px}
			.abg-card h3{margin:0 0 12px;font-size:18px}
			.abg-field{margin:0 0 10px}.abg-label{display:block;font-size:12px;color:#667085;margin-bottom:3px}
			.abg-value{font-weight:600;color:#1f2937}
			.abg-actions{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}
			.abg-btn{display:inline-block;padding:9px 13px;border-radius:7px;text-decoration:none;border:0;cursor:pointer;font-weight:700}
			.abg-primary{background:#1677c8;color:#fff}.abg-secondary{background:#eef2f6;color:#253247}
			.abg-danger{background:#b42318;color:#fff}.abg-success{background:#0f7a4d;color:#fff}
			.abg-docs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}
			.abg-doc{border:1px solid #d9e1ea;border-radius:10px;padding:14px;background:#fafbfd}
			.abg-status{display:inline-block;padding:5px 9px;border-radius:999px;background:#eef4ff;color:#174ea6;font-weight:700;font-size:12px}
			.abg-decision{border:1px solid #d9e1ea;border-radius:10px;padding:16px;margin-top:18px;background:#fff}
			.abg-decision textarea,.abg-decision input{max-width:720px;width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:6px}
			.abg-decision details{margin-top:12px;border-top:1px solid #eceff3;padding-top:12px}
			.abg-decision summary{cursor:pointer;font-weight:700;color:#b42318}
			.abg-modal-backdrop{position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.58);display:flex;align-items:center;justify-content:center;padding:20px}
			.abg-modal{width:min(520px,100%);background:#fff;border-radius:14px;padding:24px;box-shadow:0 24px 70px rgba(0,0,0,.3)}
			.abg-modal h3{margin:0 0 10px;font-size:24px}
			.abg-modal p{margin:0 0 18px;line-height:1.5;color:#475467}
			.abg-modal.approved{border-top:6px solid #0f7a4d}
			.abg-modal.rejected{border-top:6px solid #b42318}
			@media(max-width:760px){.abg-review-grid,.abg-docs{grid-template-columns:1fr}}
		</style>';



		if ( $abugida_decision_feedback )
		{
			echo '<div class="abg-modal-backdrop">';
			echo '<div class="abg-modal ' . AttrEscape( $abugida_decision_feedback['type'] ) . '">';
			echo '<h3>' . AttrEscape( $abugida_decision_feedback['title'] ) . '</h3>';
			echo '<p>' . AttrEscape( $abugida_decision_feedback['message'] ) . '</p>';
			echo '<a class="abg-btn abg-primary" href="' .
				URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id ) .
				'">' . _( 'OK' ) . '</a>';
			echo '</div></div>';
		}

		echo '<div class="abg-review-grid">';
		echo '<div class="abg-card"><h3>' . _( 'Applicant Information' ) . '</h3>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Application Reference' ) . '</span><div class="abg-value">' . AttrEscape( $applicant['APPLICATION_REFERENCE'] ) . '</div></div>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Full Name' ) . '</span><div class="abg-value">' . AttrEscape( $applicant['FIRST_NAME'] . ' ' . $applicant['LAST_NAME'] ) . '</div></div>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Phone' ) . '</span><div class="abg-value">' . AttrEscape( $applicant['PHONE'] ) . '</div></div>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Email' ) . '</span><div class="abg-value">' . AttrEscape( $applicant['EMAIL'] ) . '</div></div>';
		echo '</div>';

		echo '<div class="abg-card"><h3>' . _( 'Application Details' ) . '</h3>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Grade' ) . '</span><div class="abg-value">Grade ' . (int) $applicant['GRADE_LEVEL'] . '</div></div>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Learning Approach' ) . '</span><div class="abg-value">' .
			( $applicant['STUDY_APPROACH'] === 'DISTANCE_LEARNING' ? _( 'Distance Learning' ) : _( 'Online' ) ) . '</div></div>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Submitted' ) . '</span><div class="abg-value">' . AttrEscape( $applicant['SUBMITTED_AT'] ) . '</div></div>';
		echo '<div class="abg-field"><span class="abg-label">' . _( 'Status' ) . '</span><span class="abg-status">' . AttrEscape( AbugidaRegistrarStatusLabel( $applicant['STATUS'] ) ) . '</span></div>';
		echo '</div>';
		echo '</div>';

		echo '<h3>' . _( 'Uploaded Documents' ) . '</h3>';
		echo '<div class="abg-docs">';

		echo '<div class="abg-doc"><b>' . _( 'Supporting Document' ) . '</b><br><small>' .
			AttrEscape( $applicant['DOCUMENT_ORIGINAL_NAME'] ? $applicant['DOCUMENT_ORIGINAL_NAME'] : _( 'Not uploaded' ) ) . '</small>';
		if ( $applicant['DOCUMENT_STORED_NAME'] )
		{
			echo '<div class="abg-actions"><a class="abg-btn abg-primary" target="_blank" href="' .
				URLEscape( 'application-file.php?applicant_id=' . $applicant_id . '&type=document&mode=inline' ) . '">' . _( 'View Document' ) . '</a>';
			echo '<a class="abg-btn abg-secondary" href="' .
				URLEscape( 'application-file.php?applicant_id=' . $applicant_id . '&type=document&mode=download' ) . '">' . _( 'Download' ) . '</a></div>';
		}
		echo '</div>';

		echo '<div class="abg-doc"><b>' . _( 'Fayda ID' ) . '</b><br><small>' .
			AttrEscape( $applicant['FAYDA_ORIGINAL_NAME'] ? $applicant['FAYDA_ORIGINAL_NAME'] : _( 'Not uploaded' ) ) . '</small>';
		if ( $applicant['FAYDA_STORED_NAME'] )
		{
			echo '<div class="abg-actions"><a class="abg-btn abg-primary" target="_blank" href="' .
				URLEscape( 'application-file.php?applicant_id=' . $applicant_id . '&type=fayda&mode=inline' ) . '">' . _( 'View Fayda ID' ) . '</a>';
			echo '<a class="abg-btn abg-secondary" href="' .
				URLEscape( 'application-file.php?applicant_id=' . $applicant_id . '&type=fayda&mode=download' ) . '">' . _( 'Download' ) . '</a></div>';
		}
		echo '</div>';
		echo '</div>';

		if ( in_array( $applicant['STATUS'], [ 'SUBMITTED', 'UNDER_REVIEW' ], true ) && AllowEdit() )
		{
			echo '<div class="abg-decision"><h3>' . _( 'Registrar Decision' ) . '</h3>';
			echo '<form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id . '&modfunc=decision' ) . '">';
			echo AbugidaCsrfField();
			echo '<input type="hidden" name="decision" value="approve">';
			echo '<p>' . _( 'The payment amount will be taken automatically from the Registration Fees configured for this grade.' ) . '</p>';
			echo '<button class="abg-btn abg-success" type="submit">' . _( 'Approve Application' ) . '</button>';
			echo '</form>';

			echo '<details>';
			echo '<summary>' . _( 'Reject Application' ) . '</summary>';
			echo '<form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id . '&modfunc=decision' ) . '">';
			echo AbugidaCsrfField();
			echo '<input type="hidden" name="decision" value="reject">';
			echo '<p>' . _( 'Explain why the application is being returned. The applicant will see this reason when they return to the registration page, and it will also be emailed when outgoing email is configured.' ) . '</p>';
			echo '<textarea name="reason" rows="4" required placeholder="' . AttrEscape( _( 'Write the rejection reason here...' ) ) . '"></textarea><br><br>';
			echo '<button class="abg-btn abg-danger" type="submit">' . _( 'Reject and Send Reason' ) . '</button>';
			echo '</form>';
			echo '</details>';
			echo '</div>';
		}
		elseif ( $applicant['STATUS'] === 'DECLINED' )
		{
			echo '<br />' . ErrorMessage( [ _( 'Rejected: ' ) . AttrEscape( $applicant['REGISTRAR_DECISION_REASON'] ) ], 'warning' );
		}
		elseif ( $applicant['STATUS'] === 'PAYMENT_VERIFIED' && AllowEdit() )
		{
			echo '<div class="abg-decision"><h3>' . _( 'Final Registration Confirmation' ) . '</h3>';
			echo '<p>' . _( 'Finance has verified payment. Final confirmation will create the permanent student account.' ) . '</p>';
			echo '<form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id . '&modfunc=final_confirm' ) . '">';
			echo AbugidaCsrfField();
			echo '<button class="abg-btn abg-success" type="submit">' . _( 'Final Confirm & Create Student Account' ) . '</button>';
			echo '</form></div>';
		}
		elseif ( $applicant['STATUS'] === 'ACTIVE' )
		{
			echo '<br />' . ErrorMessage(
				[ _( 'Registration completed. Student ID: ' ) . $applicant['STUDENT_ID'] .
					' | ' . _( 'Username: ' ) . $applicant['GENERATED_USERNAME'] ],
				'note'
			);
		}

		AbugidaNotificationPanel( $applicant_id, 'REGISTRAR' );
		return;
	}
}

$rows = DBGet( "SELECT ID,APPLICATION_REFERENCE,FIRST_NAME,LAST_NAME,PHONE,EMAIL,GRADE_LEVEL,STUDY_APPROACH,STATUS,CREATED_AT
	FROM abugida_applicants
	ORDER BY ID DESC" );

echo '<style>
	.abg-app-table{width:100%;border-collapse:collapse;background:#fff}
	.abg-app-table th,.abg-app-table td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left}
	.abg-app-table th{background:#f7f9fc;color:#344054;font-size:12px;text-transform:uppercase}
	.abg-view{display:inline-block;padding:7px 11px;border-radius:6px;background:#1677c8;color:#fff;text-decoration:none;font-weight:700}
	.abg-empty{padding:18px;background:#fff;border:1px solid #e5e7eb;border-radius:8px}
</style>';

if ( empty( $rows ) )
{
	echo '<div class="abg-empty">' . _( 'No online applications were found.' ) . '</div>';
	return;
}

echo '<table class="abg-app-table">';
echo '<thead><tr>';
echo '<th>' . _( 'Reference' ) . '</th>';
echo '<th>' . _( 'Applicant' ) . '</th>';
echo '<th>' . _( 'Phone' ) . '</th>';
echo '<th>' . _( 'Grade' ) . '</th>';
echo '<th>' . _( 'Approach' ) . '</th>';
echo '<th>' . _( 'Status' ) . '</th>';
echo '<th>' . _( 'Created' ) . '</th>';
echo '<th>' . _( 'Action' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( (array) $rows as $row )
{
	echo '<tr>';
	echo '<td>' . AttrEscape( $row['APPLICATION_REFERENCE'] ) . '</td>';
	echo '<td>' . AttrEscape( trim( $row['FIRST_NAME'] . ' ' . $row['LAST_NAME'] ) ) . '</td>';
	echo '<td>' . AttrEscape( $row['PHONE'] ) . '</td>';
	echo '<td>Grade ' . (int) $row['GRADE_LEVEL'] . '</td>';
	echo '<td>' . ( $row['STUDY_APPROACH'] === 'DISTANCE_LEARNING' ? _( 'Distance Learning' ) : _( 'Online' ) ) . '</td>';
	echo '<td>' . AttrEscape( AbugidaRegistrarStatusLabel( $row['STATUS'] ) ) . '</td>';
	echo '<td>' . AttrEscape( $row['CREATED_AT'] ) . '</td>';
	echo '<td><a class="abg-view" href="' .
		URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . (int) $row['ID'] ) .
		'">' . _( 'View Application' ) . '</a></td>';
	echo '</tr>';
}

echo '</tbody></table>';
