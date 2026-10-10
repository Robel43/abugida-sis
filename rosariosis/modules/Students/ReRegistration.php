<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';
require_once 'ProgramFunctions/AbugidaReRegistration.fnc.php';

AbugidaStudentPortalGuard();

if ( empty( $_SESSION['abugida_rereg_csrf'] ) )
{
	$_SESSION['abugida_rereg_csrf'] = bin2hex( random_bytes( 32 ) );
}

$error = [];
$note = [];

$current_RET = AbugidaReRegistrationCurrentEnrollment();
$current = ! empty( $current_RET[1] ) ? $current_RET[1] : [];
$grades = AbugidaReRegistrationGrades();
$semesters = AbugidaReRegistrationSemesters();
$future_semesters = AbugidaReRegistrationFutureSemesters();
$current_semester_id = AbugidaReRegistrationCurrentSemesterId();
$future_years = AbugidaReRegistrationFutureYears();

if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
{
	if ( empty( $_POST['csrf'] )
		|| ! hash_equals( $_SESSION['abugida_rereg_csrf'], (string) $_POST['csrf'] ) )
	{
		$error[] = _( 'Your session expired. Refresh the page and try again.' );
	}
	elseif ( issetVal( $_POST['action'] ) === 'submit_request' )
	{
		$open_RET = AbugidaReRegistrationOpenRequest();

		if ( ! empty( $open_RET[1] ) )
		{
			$error[] = _( 'You already have a re-registration request in progress.' );
		}
		elseif ( ! $current )
		{
			$error[] = _( 'Your current enrollment could not be found.' );
		}
		else
		{
			$type = issetVal( $_POST['request_type'], '' );
			$target_syear = 0;
			$target_grade_id = 0;
			$target_semester_id = null;

			if ( $type === 'SEMESTER' )
			{
				$target_syear = (int) UserSyear();
				$target_grade_id = (int) $current['GRADE_ID'];
				$target_semester_id = (int) issetVal( $_POST['target_semester_id'], 0 );

				$valid_semester = false;

				foreach ( (array) $future_semesters as $semester )
				{
					if ( (int) $semester['MARKING_PERIOD_ID'] === $target_semester_id )
					{
						$valid_semester = true;
						break;
					}
				}

				if ( ! $valid_semester
					|| ( $current_semester_id && $target_semester_id === $current_semester_id ) )
				{
					$error[] = _( 'Select a future Semester that has not started yet.' );
				}
			}
			elseif ( $type === 'YEAR' )
			{
				$target_syear = (int) issetVal( $_POST['target_syear'], 0 );
				$target_grade_id = (int) issetVal( $_POST['target_grade_id'], 0 );

				$valid_year = false;
				foreach ( (array) $future_years as $year )
				{
					if ( (int) $year['SYEAR'] === $target_syear )
					{
						$valid_year = true;
						break;
					}
				}

				$valid_grade = false;
				foreach ( (array) $grades as $grade )
				{
					if ( (int) $grade['ID'] === $target_grade_id )
					{
						$valid_grade = true;
						break;
					}
				}

				if ( ! $valid_year )
				{
					$error[] = _( 'Select a configured future academic year.' );
				}

				if ( ! $valid_grade )
				{
					$error[] = _( 'Select a valid target grade.' );
				}
			}
			else
			{
				$error[] = _( 'Select Semester or Academic Year re-registration.' );
			}

			if ( empty( $error ) )
			{
				$duplicate_where = "STUDENT_ID='" . UserStudentID() . "'
					AND SCHOOL_ID='" . UserSchool() . "'
					AND REQUEST_TYPE='" . DBEscapeString( $type ) . "'
					AND TARGET_SYEAR='" . (int) $target_syear . "'
					AND TARGET_GRADE_ID='" . (int) $target_grade_id . "'
					AND STATUS='COMPLETED'";

				if ( $type === 'SEMESTER' )
				{
					$duplicate_where .= " AND TARGET_SEMESTER_ID='" . (int) $target_semester_id . "'";
				}

				$already_completed = DBGetOne( "SELECT ID
					FROM abugida_reregistration_requests
					WHERE " . $duplicate_where . "
					LIMIT 1" );

				if ( $already_completed )
				{
					$error[] = _( 'You are already registered for the selected target period.' );
				}
			}

			if ( empty( $error ) )
			{
				$reference = AbugidaReRegistrationReference();

				$request_id = DBInsert(
					'abugida_reregistration_requests',
					[
						'REQUEST_REFERENCE' => $reference,
						'STUDENT_ID' => (int) UserStudentID(),
						'SCHOOL_ID' => (int) UserSchool(),
						'FROM_SYEAR' => (int) UserSyear(),
						'FROM_GRADE_ID' => (int) $current['GRADE_ID'],
						'REQUEST_TYPE' => $type,
						'TARGET_SYEAR' => $target_syear,
						'TARGET_GRADE_ID' => $target_grade_id,
						'TARGET_SEMESTER_ID' => $target_semester_id,
						'STATUS' => 'SUBMITTED',
						'PAYMENT_STATUS' => 'NOT_REQUIRED',
					],
					'id'
				);

				AbugidaReRegistrationHistory(
					$request_id,
					null,
					'SUBMITTED',
					'Re-registration request submitted',
					'STUDENT'
				);

				$note[] = button( 'check' ) . '&nbsp;' . _( 'Your re-registration request was submitted.' );
			}
		}
	}
	elseif ( issetVal( $_POST['action'] ) === 'upload_receipt' )
	{
		$request_id = (int) issetVal( $_POST['request_id'], 0 );
		$request_RET = DBGet( "SELECT *
			FROM abugida_reregistration_requests
			WHERE ID='" . $request_id . "'
			AND STUDENT_ID='" . UserStudentID() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			LIMIT 1" );
		$request = ! empty( $request_RET[1] ) ? $request_RET[1] : [];

		if ( ! $request
			|| ! in_array( $request['STATUS'], [ 'APPROVED_FOR_PAYMENT', 'PAYMENT_DECLINED' ], true ) )
		{
			$error[] = _( 'Payment is not available for this request.' );
		}
		elseif ( empty( $_FILES['receipt'] )
			|| $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE )
		{
			$error[] = _( 'Upload your payment receipt.' );
		}
		else
		{
			$file = $_FILES['receipt'];

			if ( $file['error'] !== UPLOAD_ERR_OK )
			{
				$error[] = _( 'The receipt upload failed.' );
			}
			elseif ( (int) $file['size'] > 5242880 )
			{
				$error[] = _( 'The receipt must be 5 MB or smaller.' );
			}
			else
			{
				$finfo = new finfo( FILEINFO_MIME_TYPE );
				$mime = $finfo->file( $file['tmp_name'] );
				$allowed = [
					'application/pdf' => 'pdf',
					'image/png' => 'png',
					'image/jpeg' => 'jpg',
				];

				if ( ! isset( $allowed[$mime] ) )
				{
					$error[] = _( 'Only PDF, PNG, or JPG receipts are allowed.' );
				}
				else
				{
					$dir = 'assets/FileUploads/ReRegistrationReceipts';

					if ( ! is_dir( $dir ) )
					{
						mkdir( $dir, 0750, true );
					}

					$deny_file = $dir . '/.htaccess';
					if ( ! is_file( $deny_file ) )
					{
						file_put_contents( $deny_file, "Require all denied\n" );
					}

					$stored = 'rereg-' . $request_id . '-' . bin2hex( random_bytes( 12 ) ) . '.' . $allowed[$mime];

					if ( ! move_uploaded_file( $file['tmp_name'], $dir . '/' . $stored ) )
					{
						$error[] = _( 'Unable to save the receipt.' );
					}
					else
					{
						if ( $request['RECEIPT_STORED_NAME'] )
						{
							$old = $dir . '/' . basename( $request['RECEIPT_STORED_NAME'] );
							if ( is_file( $old ) )
							{
								@unlink( $old );
							}
						}

						$from = $request['STATUS'];

						DBUpdate(
							'abugida_reregistration_requests',
							[
								'STATUS' => 'PAYMENT_SUBMITTED',
								'PAYMENT_STATUS' => 'SUBMITTED',
								'RECEIPT_STORED_NAME' => $stored,
								'RECEIPT_ORIGINAL_NAME' => basename( $file['name'] ),
								'RECEIPT_MIME_TYPE' => $mime,
								'RECEIPT_SIZE' => (int) $file['size'],
								'RECEIPT_SUBMITTED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
								'FINANCE_DECISION_REASON' => null,
							],
							[ 'ID' => $request_id ]
						);

						AbugidaReRegistrationHistory(
							$request_id,
							$from,
							'PAYMENT_SUBMITTED',
							'Payment receipt submitted',
							'STUDENT'
						);

						$note[] = button( 'check' ) . '&nbsp;' . _( 'Your payment receipt was submitted for verification.' );
					}
				}
			}
		}
	}
}

$latest_RET = AbugidaReRegistrationLatestRequest();
$latest = ! empty( $latest_RET[1] ) ? $latest_RET[1] : [];
$open_RET = AbugidaReRegistrationOpenRequest();
$open = ! empty( $open_RET[1] ) ? $open_RET[1] : [];

DrawHeader( _( 'Re-Registration' ) );
AbugidaStudentPortalStyles();

echo ErrorMessage( $error );
echo ErrorMessage( $note, 'note' );

echo '<style>
	.abg-rereg-choice{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:14px 0}
	.abg-rereg-option{border:1px solid #dbe3ef;border-radius:14px;padding:16px;background:#fbfdff}
	.abg-rereg-option label{font-weight:800;color:#0f172a}
	.abg-rereg-form select,.abg-rereg-form input[type=file]{width:100%;max-width:520px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:9px;background:#fff}
	.abg-rereg-row{margin:15px 0}
	.abg-rereg-status{display:inline-flex;padding:7px 11px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:800}
	.abg-rereg-alert{padding:13px 15px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;margin:14px 0}
	@media(max-width:680px){.abg-rereg-choice{grid-template-columns:1fr}}
</style>';

echo '<div class="abg-student-shell">';
echo '<div class="abg-page-hero"><h2>' . _( 'Re-Registration' ) . '</h2><p>' .
	_( 'Continue your studies without creating a new account. Request registration for the next Semester or a future academic year and target grade.' ) .
	'</p></div>';

if ( $latest )
{
	echo '<div class="abg-student-section">';
	echo '<div class="abg-section-header"><div><h3>' . _( 'Latest Request' ) . '</h3><p>' .
		AttrEscape( $latest['REQUEST_REFERENCE'] ) . '</p></div><span class="abg-rereg-status">' .
		AttrEscape( AbugidaReRegistrationStatusLabel( $latest['STATUS'] ) ) . '</span></div>';

	echo '<div class="abg-info-list">';
	echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Request Type' ) . '</div><div class="abg-info-value">' .
		AttrEscape( $latest['REQUEST_TYPE'] === 'SEMESTER' ? _( 'Semester' ) : _( 'Academic Year' ) ) . '</div></div>';
	echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Requested For' ) . '</div><div class="abg-info-value">' .
		AttrEscape( AbugidaReRegistrationTargetLabel( $latest ) ) . '</div></div>';
	echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Submitted' ) . '</div><div class="abg-info-value">' .
		AttrEscape( $latest['CREATED_AT'] ) . '</div></div>';
	echo '<div class="abg-info-item"><div class="abg-student-label">' . _( 'Payment Status' ) . '</div><div class="abg-info-value">' .
		AttrEscape( $latest['PAYMENT_STATUS'] ) . '</div></div>';
	echo '</div>';

	if ( $latest['STATUS'] === 'DECLINED' )
	{
		echo '<div class="abg-rereg-alert"><b>' . _( 'Registrar feedback:' ) . '</b> ' .
			AttrEscape( $latest['REGISTRAR_DECISION_REASON'] ) . '</div>';
	}
	elseif ( $latest['STATUS'] === 'PAYMENT_DECLINED' )
	{
		echo '<div class="abg-rereg-alert"><b>' . _( 'Payment feedback:' ) . '</b> ' .
			AttrEscape( $latest['FINANCE_DECISION_REASON'] ) . '</div>';
	}

	if ( in_array( $latest['STATUS'], [ 'APPROVED_FOR_PAYMENT', 'PAYMENT_DECLINED' ], true ) )
	{
		echo '<div class="abg-student-section" style="margin-bottom:0">';
		echo '<div class="abg-section-header"><div><h3>' . _( 'Registration Payment' ) . '</h3><p>' .
			AttrEscape( $latest['PAYMENT_INSTRUCTIONS'] ) . '</p></div></div>';
		echo '<div class="abg-student-value">' . Currency( $latest['PAYMENT_AMOUNT'] ) . '</div>';
		echo '<form class="abg-rereg-form" method="POST" enctype="multipart/form-data">';
		echo '<input type="hidden" name="csrf" value="' . AttrEscape( $_SESSION['abugida_rereg_csrf'] ) . '">';
		echo '<input type="hidden" name="action" value="upload_receipt">';
		echo '<input type="hidden" name="request_id" value="' . (int) $latest['ID'] . '">';
		echo '<div class="abg-rereg-row"><label><b>' . _( 'Payment receipt' ) . '</b></label><br><br>';
		echo '<input type="file" name="receipt" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" required>';
		echo '<div class="abg-student-subvalue">' . _( 'PDF, PNG, or JPG. Maximum 5 MB.' ) . '</div></div>';
		echo '<button class="abg-student-link" type="submit">' . _( 'Submit Payment Receipt' ) . '</button>';
		echo '</form></div>';
	}

	if ( $latest['STATUS'] === 'COMPLETED' )
	{
		echo '<div class="abg-rereg-alert" style="background:#ecfdf3;border-color:#bbf7d0;color:#166534">' .
			_( 'Your re-registration is complete. Your existing student account remains active.' ) . '</div>';
	}

	echo '</div>';
}

if ( ! $open )
{
	echo '<div class="abg-student-section">';
	echo '<div class="abg-section-header"><div><h3>' . _( 'Start a Re-Registration Request' ) . '</h3><p>' .
		_( 'Choose whether you are continuing into another Semester or registering for a future academic year.' ) .
		'</p></div></div>';

	echo '<form class="abg-rereg-form" method="POST">';
	echo '<input type="hidden" name="csrf" value="' . AttrEscape( $_SESSION['abugida_rereg_csrf'] ) . '">';
	echo '<input type="hidden" name="action" value="submit_request">';

	echo '<div class="abg-rereg-choice">';
	echo '<div class="abg-rereg-option"><label><input type="radio" name="request_type" value="SEMESTER" required> ' .
		_( 'Next Semester' ) . '</label><p class="abg-student-muted">' .
		_( 'Continue in your current academic year and current grade.' ) . '</p></div>';
	echo '<div class="abg-rereg-option"><label><input type="radio" name="request_type" value="YEAR" required> ' .
		_( 'New Academic Year' ) . '</label><p class="abg-student-muted">' .
		_( 'Register for a configured future academic year and select the target grade.' ) . '</p></div>';
	echo '</div>';

	echo '<div class="abg-rereg-row"><label><b>' . _( 'Target Semester' ) . '</b></label><br><br>';
	echo '<select name="target_semester_id"><option value="">' . _( 'Select Semester' ) . '</option>';
	foreach ( (array) $future_semesters as $semester )
	{
		$semester_start_label = $semester['START_DATE'] ?
			date( 'M j, Y', strtotime( $semester['START_DATE'] ) ) :
			'';

		echo '<option value="' . (int) $semester['MARKING_PERIOD_ID'] . '">' .
			AttrEscape( $semester['TITLE'] ) .
			( $semester_start_label ? ' (' . AttrEscape( $semester_start_label ) . ')' : '' ) .
			'</option>';
	}
	echo '</select><div class="abg-student-subvalue">' .
		_( 'Only Semesters that have not started yet are available. Sidebar Semester selection does not affect eligibility.' ) . '</div></div>';

	echo '<div class="abg-rereg-row"><label><b>' . _( 'Target Academic Year' ) . '</b></label><br><br>';
	echo '<select name="target_syear"><option value="">' . _( 'Select Academic Year' ) . '</option>';
	foreach ( (array) $future_years as $year )
	{
		echo '<option value="' . (int) $year['SYEAR'] . '">' . (int) $year['SYEAR'] . ' - ' . AttrEscape( $year['TITLE'] ) . '</option>';
	}
	echo '</select><div class="abg-student-subvalue">' .
		_( 'Only academic years already configured by the school appear here.' ) . '</div></div>';

	echo '<div class="abg-rereg-row"><label><b>' . _( 'Target Grade' ) . '</b></label><br><br>';
	echo '<select name="target_grade_id"><option value="">' . _( 'Select Grade' ) . '</option>';
	foreach ( (array) $grades as $grade )
	{
		echo '<option value="' . (int) $grade['ID'] . '">' . AttrEscape( $grade['TITLE'] ) . '</option>';
	}
	echo '</select><div class="abg-student-subvalue">' .
		_( 'Used only for New Academic Year requests. The Registrar confirms the requested grade before final enrollment.' ) . '</div></div>';

	echo '<button class="abg-student-link" type="submit">' . _( 'Submit Re-Registration Request' ) . '</button>';
	echo '</form></div>';
}

echo '</div>';
