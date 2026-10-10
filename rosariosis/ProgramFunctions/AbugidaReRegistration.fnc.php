<?php
/**
 * Abugida existing-student re-registration helpers.
 */

function AbugidaReRegistrationReference()
{
	return 'RR-' . UserSyear() . '-' . strtoupper( substr( bin2hex( random_bytes( 6 ) ), 0, 10 ) );
}

function AbugidaReRegistrationStatusLabel( $status )
{
	$labels = [
		'SUBMITTED' => _( 'Submitted' ),
		'DECLINED' => _( 'Returned for Correction' ),
		'APPROVED_FOR_PAYMENT' => _( 'Approved - Payment Required' ),
		'PAYMENT_SUBMITTED' => _( 'Payment Submitted' ),
		'PAYMENT_DECLINED' => _( 'Payment Rejected' ),
		'PAYMENT_VERIFIED' => _( 'Payment Verified' ),
		'READY_FOR_FINAL' => _( 'Ready for Final Confirmation' ),
		'COMPLETED' => _( 'Completed' ),
	];

	return isset( $labels[$status] ) ? $labels[$status] : $status;
}

function AbugidaReRegistrationHistory( $request_id, $from, $to, $action, $actor_type, $reason = '' )
{
	DBInsert(
		'abugida_reregistration_history',
		[
			'REQUEST_ID' => (int) $request_id,
			'FROM_STATUS' => $from,
			'TO_STATUS' => $to,
			'ACTION' => $action,
			'ACTOR_TYPE' => $actor_type,
			'ACTOR_ID' => User( 'PROFILE' ) === 'student' ? (int) UserStudentID() : (int) User( 'STAFF_ID' ),
			'ACTOR_NAME' => User( 'NAME' ),
			'REASON' => $reason,
		]
	);
}

function AbugidaReRegistrationCurrentEnrollment()
{
	return DBGet( "SELECT se.ID,se.GRADE_ID,se.SYEAR,se.SCHOOL_ID,se.START_DATE,se.END_DATE,
		gl.TITLE AS GRADE_TITLE
		FROM student_enrollment se
		LEFT JOIN school_gradelevels gl ON gl.ID=se.GRADE_ID
		WHERE se.STUDENT_ID='" . UserStudentID() . "'
		AND se.SCHOOL_ID='" . UserSchool() . "'
		AND se.SYEAR='" . UserSyear() . "'
		ORDER BY se.ID DESC
		LIMIT 1" );
}

function AbugidaReRegistrationGrades()
{
	return DBGet( "SELECT ID,TITLE,SHORT_NAME,SORT_ORDER
		FROM school_gradelevels
		WHERE SCHOOL_ID='" . UserSchool() . "'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,ID" );
}

function AbugidaReRegistrationSemesters( $syear = null )
{
	$syear = $syear ? (int) $syear : (int) UserSyear();

	return DBGet( "SELECT MARKING_PERIOD_ID,TITLE,SHORT_NAME,START_DATE,END_DATE,SORT_ORDER
		FROM school_marking_periods
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . $syear . "'
		AND MP='SEM'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );
}

function AbugidaReRegistrationFutureYears()
{
	return DBGet( "SELECT DISTINCT SYEAR,MARKING_PERIOD_ID,TITLE,START_DATE,END_DATE
		FROM school_marking_periods
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND MP='FY'
		AND SYEAR>'" . UserSyear() . "'
		ORDER BY SYEAR" );
}

function AbugidaReRegistrationOpenRequest( $student_id = null )
{
	$student_id = $student_id ? (int) $student_id : (int) UserStudentID();

	return DBGet( "SELECT *
		FROM abugida_reregistration_requests
		WHERE STUDENT_ID='" . $student_id . "'
		AND STATUS NOT IN ('DECLINED','COMPLETED')
		ORDER BY ID DESC
		LIMIT 1" );
}

function AbugidaReRegistrationLatestRequest( $student_id = null )
{
	$student_id = $student_id ? (int) $student_id : (int) UserStudentID();

	return DBGet( "SELECT *
		FROM abugida_reregistration_requests
		WHERE STUDENT_ID='" . $student_id . "'
		ORDER BY ID DESC
		LIMIT 1" );
}

function AbugidaReRegistrationTargetLabel( $request )
{
	if ( ! $request )
	{
		return '';
	}

	$grade = DBGetOne( "SELECT TITLE
		FROM school_gradelevels
		WHERE ID='" . (int) $request['TARGET_GRADE_ID'] . "'
		LIMIT 1" );

	if ( $request['REQUEST_TYPE'] === 'SEMESTER' )
	{
		$semester = DBGetOne( "SELECT TITLE
			FROM school_marking_periods
			WHERE MARKING_PERIOD_ID='" . (int) $request['TARGET_SEMESTER_ID'] . "'
			LIMIT 1" );

		return $semester . ' - ' . $grade;
	}

	return _( 'Academic Year' ) . ' ' . (int) $request['TARGET_SYEAR'] . ' - ' . $grade;
}

function AbugidaReRegistrationFee( $target_syear, $target_grade_id )
{
	return DBGetOne( "SELECT AMOUNT
		FROM abugida_registration_fees
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . (int) $target_syear . "'
		AND GRADE_ID='" . (int) $target_grade_id . "'
		LIMIT 1" );
}

function AbugidaReRegistrationCanFinalize( $request )
{
	return $request
		&& in_array( $request['STATUS'], [ 'PAYMENT_VERIFIED', 'READY_FOR_FINAL' ], true );
}
