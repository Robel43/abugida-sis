<?php
/**
 * Abugida SIS - Registrar online application review.
 */

DrawHeader( ProgramTitle() );

if ( User( 'PROFILE' ) !== 'admin' )
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

function AbugidaApplicationDownload( $row, $type )
{
	$map = [
		'document' => [ 'DOCUMENT_STORED_NAME', 'DOCUMENT_ORIGINAL_NAME' ],
		'fayda' => [ 'FAYDA_STORED_NAME', 'FAYDA_ORIGINAL_NAME' ],
	];

	if ( empty( $map[ $type ] ) )
	{
		return;
	}

	$stored = $row[ $map[ $type ][0] ];
	$original = $row[ $map[ $type ][1] ];

	if ( ! $stored )
	{
		return;
	}

	$file = 'assets/FileUploads/ApplicantDocuments/' . basename( $stored );

	if ( ! is_file( $file ) )
	{
		return;
	}

	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', basename( $original ) ) . '"' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file );
	exit;
}

if ( ! empty( $_REQUEST['applicant_id'] ) )
{
	$applicant_id = (int) $_REQUEST['applicant_id'];
	$applicant_RET = DBGet( "SELECT *
		FROM abugida_applicants
		WHERE ID='" . $applicant_id . "'
		LIMIT 1" );
	$applicant = ! empty( $applicant_RET[1] ) ? $applicant_RET[1] : null;

	if ( $applicant && ! empty( $_REQUEST['download'] ) )
	{
		AbugidaApplicationDownload( $applicant, $_REQUEST['download'] );
	}

	if ( $applicant
		&& $_REQUEST['modfunc'] === 'decision'
		&& AllowEdit() )
	{
		$decision = issetVal( $_POST['decision'] );
		$reason = trim( issetVal( $_POST['reason'] ) );
		$from = $applicant['STATUS'];

		if ( $decision === 'reject' )
		{
			if ( $reason === '' )
			{
				$error[] = _( 'A rejection reason is required.' );
			}
			else
			{
				DBUpdate(
					'abugida_applicants',
					[
						'STATUS' => 'DECLINED',
						'REGISTRAR_DECISION_REASON' => $reason,
						'REGISTRAR_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
						'REGISTRAR_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
					],
					[ 'ID' => $applicant_id ]
				);

				AbugidaApplicationHistory( $applicant_id, $from, 'DECLINED', 'Application rejected', $reason );
				$note[] = button( 'check' ) . '&nbsp;' . _( 'Application rejected.' );
			}
		}
		elseif ( $decision === 'approve' )
		{
			$amount = (float) issetVal( $_POST['payment_amount'] );
			$instructions = trim( issetVal( $_POST['payment_instructions'] ) );

			if ( $amount <= 0 )
			{
				$error[] = _( 'Enter a valid payment amount.' );
			}
			elseif ( $instructions === '' )
			{
				$error[] = _( 'Payment instructions are required.' );
			}
			else
			{
				DBUpdate(
					'abugida_applicants',
					[
						'STATUS' => 'APPROVED_FOR_PAYMENT',
						'REGISTRAR_DECISION_REASON' => null,
						'REGISTRAR_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
						'REGISTRAR_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
						'PAYMENT_AMOUNT' => $amount,
						'PAYMENT_INSTRUCTIONS' => $instructions,
						'PAYMENT_STATUS' => 'PENDING',
					],
					[ 'ID' => $applicant_id ]
				);

				AbugidaApplicationHistory( $applicant_id, $from, 'APPROVED_FOR_PAYMENT', 'Application approved for payment' );
				$note[] = button( 'check' ) . '&nbsp;' . _( 'Application approved for payment.' );
			}
		}

		RedirectURL( [ 'modfunc', 'decision' ] );
	}

	if ( $applicant
		&& $_REQUEST['modfunc'] === 'final_confirm'
		&& AllowEdit()
		&& $applicant['STATUS'] === 'PAYMENT_VERIFIED' )
	{
		$grade = (int) $applicant['GRADE_LEVEL'];
		$grade_id = DBGetOne( "SELECT ID
			FROM school_gradelevels
			WHERE SCHOOL_ID='" . UserSchool() . "'
			AND (TITLE='Grade " . $grade . "' OR SHORT_NAME='G" . $grade . "')
			ORDER BY ID
			LIMIT 1" );

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

			if ( filter_var( $applicant['EMAIL'], FILTER_VALIDATE_EMAIL ) )
			{
				require_once 'ProgramFunctions/SendEmail.fnc.php';

				$message = "Your Abugida SIS student account has been created.\n\n" .
					"Username: " . $username . "\n" .
					"Temporary password: " . $temp_password . "\n\n" .
					"Please sign in and change your password.";

				SendEmail( $applicant['EMAIL'], 'Abugida SIS account created', $message );
			}

			$note[] = button( 'check' ) . '&nbsp;' . _( 'Student account created and registration completed.' );
		}

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

		echo '<table class="width-100p cellpadding-5">';
		echo '<tr><td><b>' . _( 'Applicant' ) . '</b></td><td>' .
			AttrEscape( $applicant['FIRST_NAME'] . ' ' . $applicant['LAST_NAME'] ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Phone' ) . '</b></td><td>' . AttrEscape( $applicant['PHONE'] ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Email' ) . '</b></td><td>' . AttrEscape( $applicant['EMAIL'] ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Grade' ) . '</b></td><td>Grade ' . (int) $applicant['GRADE_LEVEL'] . '</td></tr>';
		echo '<tr><td><b>' . _( 'Learning Approach' ) . '</b></td><td>' .
			( $applicant['STUDY_APPROACH'] === 'DISTANCE_LEARNING' ? _( 'Distance Learning' ) : _( 'Online' ) ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Status' ) . '</b></td><td>' . AttrEscape( $applicant['STATUS'] ) . '</td></tr>';
		echo '</table>';

		echo '<br /><div>';
		if ( $applicant['DOCUMENT_STORED_NAME'] )
		{
			echo '<a class="button" href="' . URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id . '&download=document' ) . '">' .
				_( 'Download Supporting Document' ) . '</a> ';
		}
		if ( $applicant['FAYDA_STORED_NAME'] )
		{
			echo '<a class="button" href="' . URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id . '&download=fayda' ) . '">' .
				_( 'Download Fayda ID' ) . '</a>';
		}
		echo '</div>';

		if ( in_array( $applicant['STATUS'], [ 'SUBMITTED', 'UNDER_REVIEW' ], true ) && AllowEdit() )
		{
			echo '<br /><form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id . '&modfunc=decision' ) . '">';
			echo '<fieldset><legend>' . _( 'Registrar Decision' ) . '</legend>';
			echo '<p><label>' . _( 'Payment Amount' ) . '<br><input type="number" min="0" step="0.01" name="payment_amount"></label></p>';
			echo '<p><label>' . _( 'Payment Instructions' ) . '<br><textarea name="payment_instructions" rows="4" class="width-100p"></textarea></label></p>';
			echo '<p><label>' . _( 'Reason (required when rejecting)' ) . '<br><textarea name="reason" rows="3" class="width-100p"></textarea></label></p>';
			echo '<button type="submit" name="decision" value="approve">' . _( 'Approve for Payment' ) . '</button> ';
			echo '<button type="submit" name="decision" value="reject">' . _( 'Reject Application' ) . '</button>';
			echo '</fieldset></form>';
		}
		elseif ( $applicant['STATUS'] === 'DECLINED' )
		{
			echo '<br />' . ErrorMessage( [ _( 'Rejected: ' ) . $applicant['REGISTRAR_DECISION_REASON'] ], 'warning' );
		}
		elseif ( $applicant['STATUS'] === 'PAYMENT_VERIFIED' && AllowEdit() )
		{
			echo '<br /><form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/ApplicationReview.php&applicant_id=' . $applicant_id . '&modfunc=final_confirm' ) . '">';
			echo '<button type="submit">' . _( 'Final Confirm & Create Student Account' ) . '</button>';
			echo '</form>';
		}
		elseif ( $applicant['STATUS'] === 'ACTIVE' )
		{
			echo '<br />' . ErrorMessage(
				[ _( 'Registration completed. Student ID: ' ) . $applicant['STUDENT_ID'] .
					' | ' . _( 'Username: ' ) . $applicant['GENERATED_USERNAME'] ],
				'note'
			);
		}

		return;
	}
}

$rows = DBGet( "SELECT ID,APPLICATION_REFERENCE,FIRST_NAME,LAST_NAME,PHONE,EMAIL,GRADE_LEVEL,STUDY_APPROACH,STATUS,CREATED_AT
	FROM abugida_applicants
	ORDER BY ID DESC" );

$columns = [
	'APPLICATION_REFERENCE' => _( 'Reference' ),
	'FIRST_NAME' => _( 'First Name' ),
	'LAST_NAME' => _( 'Last Name' ),
	'PHONE' => _( 'Phone' ),
	'GRADE_LEVEL' => _( 'Grade' ),
	'STUDY_APPROACH' => _( 'Approach' ),
	'STATUS' => _( 'Status' ),
	'CREATED_AT' => _( 'Created' ),
];

$link = [
	'FULL_NAME' => false,
	'link' => 'Modules.php?modname=Custom/ApplicationReview.php',
	'variables' => [ 'applicant_id' => 'ID' ],
];

ListOutput( $rows, $columns, 'Application', 'Applications', $link );
