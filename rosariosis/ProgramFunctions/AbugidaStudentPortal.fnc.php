<?php
/**
 * Abugida student portal helpers.
 *
 * All helpers derive the student from the authenticated session. Student-facing
 * pages must never accept a student_id from GET or POST.
 */

require_once 'ProgramFunctions/AbugidaClassRank.fnc.php';

function AbugidaStudentPortalGuard()
{
	if ( User( 'PROFILE' ) !== 'student' )
	{
		echo ErrorMessage( [ _( 'This page is available only to the authenticated student.' ) ] );
		exit;
	}

	if ( ! UserStudentID() && ! empty( $_SESSION['STUDENT_ID'] ) )
	{
		SetUserStudentID( $_SESSION['STUDENT_ID'] );
	}

	if ( ! UserStudentID() )
	{
		echo ErrorMessage( [ _( 'Student session could not be resolved.' ) ] );
		exit;
	}

	if ( ! UserSchool() )
	{
		$school_id = DBGetOne( "SELECT SCHOOL_ID
			FROM student_enrollment
			WHERE STUDENT_ID='" . UserStudentID() . "'
			AND SYEAR='" . UserSyear() . "'
			ORDER BY ID DESC
			LIMIT 1" );

		if ( $school_id )
		{
			$_SESSION['UserSchool'] = $school_id;
		}
	}

	if ( ! UserSchool() )
	{
		echo ErrorMessage( [ _( 'No school enrollment is available for the current academic year.' ) ] );
		exit;
	}
}

function AbugidaStudentPortalEnrollment()
{
	return DBGet( "SELECT se.GRADE_ID,se.START_DATE,se.END_DATE,
		gl.TITLE AS GRADE_TITLE,s.TITLE AS SCHOOL_TITLE
		FROM student_enrollment se
		LEFT JOIN school_gradelevels gl ON gl.ID=se.GRADE_ID
		LEFT JOIN schools s ON s.ID=se.SCHOOL_ID AND s.SYEAR=se.SYEAR
		WHERE se.STUDENT_ID='" . UserStudentID() . "'
		AND se.SCHOOL_ID='" . UserSchool() . "'
		AND se.SYEAR='" . UserSyear() . "'
		ORDER BY se.ID DESC
		LIMIT 1" );
}

function AbugidaStudentPortalSemesters()
{
	return DBGet( "SELECT MARKING_PERIOD_ID,TITLE,SHORT_NAME,START_DATE,END_DATE
		FROM school_marking_periods
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		AND MP='SEM'
		AND DOES_GRADES='Y'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );
}

function AbugidaStudentPortalCourses()
{
	$rows = DBGet( "SELECT DISTINCT sch.COURSE_PERIOD_ID,sch.MARKING_PERIOD_ID,
		sch.START_DATE,sch.END_DATE,
		c.TITLE AS COURSE_TITLE,cp.TITLE AS COURSE_PERIOD_TITLE,
		cp.SHORT_NAME,cp.TEACHER_ID,cp.SECONDARY_TEACHER_ID,
		mp.TITLE AS SEMESTER_TITLE
		FROM schedule sch
		JOIN course_periods cp ON cp.COURSE_PERIOD_ID=sch.COURSE_PERIOD_ID
		JOIN courses c ON c.COURSE_ID=cp.COURSE_ID
		LEFT JOIN school_marking_periods mp ON mp.MARKING_PERIOD_ID=sch.MARKING_PERIOD_ID
		WHERE sch.STUDENT_ID='" . UserStudentID() . "'
		AND sch.SCHOOL_ID='" . UserSchool() . "'
		AND sch.SYEAR='" . UserSyear() . "'
		ORDER BY mp.START_DATE,c.TITLE,cp.TITLE" );

	foreach ( (array) $rows as $key => $row )
	{
		$rows[$key]['TEACHER'] = $row['TEACHER_ID'] ? GetTeacher( $row['TEACHER_ID'] ) : '';

		if ( $row['SECONDARY_TEACHER_ID'] )
		{
			$secondary = GetTeacher( $row['SECONDARY_TEACHER_ID'] );

			if ( $secondary )
			{
				$rows[$key]['TEACHER'] .= ( $rows[$key]['TEACHER'] ? ', ' : '' ) . $secondary;
			}
		}

		$today = DBDate();

		if ( $row['START_DATE'] && $today < $row['START_DATE'] )
		{
			$rows[$key]['STATUS'] = _( 'Upcoming' );
		}
		elseif ( $row['END_DATE'] && $today > $row['END_DATE'] )
		{
			$rows[$key]['STATUS'] = _( 'Completed' );
		}
		else
		{
			$rows[$key]['STATUS'] = _( 'Active' );
		}
	}

	return $rows;
}

function AbugidaStudentPortalGrades()
{
	$rows = DBGet( "SELECT c.COURSE_ID,c.TITLE AS COURSE_TITLE,
		g.MARKING_PERIOD_ID,g.GRADE_PERCENT
		FROM student_report_card_grades g
		JOIN course_periods cp ON cp.COURSE_PERIOD_ID=g.COURSE_PERIOD_ID
		JOIN courses c ON c.COURSE_ID=cp.COURSE_ID
		JOIN school_marking_periods mp ON mp.MARKING_PERIOD_ID=g.MARKING_PERIOD_ID
		WHERE g.STUDENT_ID='" . UserStudentID() . "'
		AND g.SCHOOL_ID='" . UserSchool() . "'
		AND g.SYEAR='" . UserSyear() . "'
		AND mp.MP='SEM'
		AND g.GRADE_PERCENT IS NOT NULL
		ORDER BY c.TITLE,mp.START_DATE" );

	$grades = [];

	foreach ( (array) $rows as $row )
	{
		$course_id = (int) $row['COURSE_ID'];

		if ( ! isset( $grades[$course_id] ) )
		{
			$grades[$course_id] = [
				'COURSE_TITLE' => $row['COURSE_TITLE'],
				'SEMESTERS' => [],
			];
		}

		$grades[$course_id]['SEMESTERS'][(int) $row['MARKING_PERIOD_ID']] =
			(float) $row['GRADE_PERCENT'];
	}

	return $grades;
}

function AbugidaStudentPortalRankSummary()
{
	$summary = [
		'SEMESTERS' => [],
		'FULL_YEAR' => [],
	];

	$semesters = AbugidaStudentPortalSemesters();

	foreach ( (array) $semesters as $semester )
	{
		$semester_id = (int) $semester['MARKING_PERIOD_ID'];
		$ret = AbugidaRankGetStudent( UserStudentID(), 'SEM', $semester_id );

		if ( ! empty( $ret[1] ) )
		{
			$summary['SEMESTERS'][$semester_id] = $ret[1] + [
				'TITLE' => $semester['TITLE'],
			];
		}
	}

	$full_year_id = GetFullYearMP();

	if ( $full_year_id )
	{
		$ret = AbugidaRankGetStudent( UserStudentID(), 'FY', $full_year_id );

		if ( ! empty( $ret[1] ) )
		{
			$summary['FULL_YEAR'] = $ret[1];
		}
	}

	return $summary;
}

function AbugidaStudentPortalBilling()
{
	$fees = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0)
		FROM billing_fees
		WHERE STUDENT_ID='" . UserStudentID() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" );

	$payments = (float) DBGetOne( "SELECT COALESCE(SUM(AMOUNT),0)
		FROM billing_payments
		WHERE STUDENT_ID='" . UserStudentID() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'" );

	$balance = $fees - $payments;

	if ( $fees <= 0 )
	{
		$status = _( 'No Charges' );
	}
	elseif ( $balance <= 0 )
	{
		$status = _( 'Paid' );
	}
	elseif ( $payments > 0 )
	{
		$status = _( 'Partially Paid' );
	}
	else
	{
		$status = _( 'Unpaid' );
	}

	$recent = DBGet( "SELECT AMOUNT,PAYMENT_DATE,COMMENTS
		FROM billing_payments
		WHERE STUDENT_ID='" . UserStudentID() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		ORDER BY PAYMENT_DATE DESC,ID DESC
		LIMIT 10" );

	return [
		'FEES' => $fees,
		'PAYMENTS' => $payments,
		'BALANCE' => $balance,
		'STATUS' => $status,
		'RECENT' => $recent,
	];
}

function AbugidaStudentPortalProfile()
{
	$student = DBGet( "SELECT STUDENT_ID,FIRST_NAME,MIDDLE_NAME,LAST_NAME,USERNAME
		FROM students
		WHERE STUDENT_ID='" . UserStudentID() . "'
		LIMIT 1" );

	$enrollment = AbugidaStudentPortalEnrollment();

	return [
		'STUDENT' => ! empty( $student[1] ) ? $student[1] : [],
		'ENROLLMENT' => ! empty( $enrollment[1] ) ? $enrollment[1] : [],
	];
}

function AbugidaStudentPortalStyles()
{
	echo '<style>
		.abg-student-shell{max-width:1180px;margin:0 auto}
		.abg-student-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:16px 0}
		.abg-student-card{background:#fff;border:1px solid #dfe5ec;border-radius:12px;padding:18px}
		.abg-student-card h3{margin:0 0 8px}
		.abg-student-label{font-size:12px;color:#667085;text-transform:uppercase;letter-spacing:.04em}
		.abg-student-value{font-size:22px;font-weight:700;margin-top:6px}
		.abg-student-muted{color:#667085}
		.abg-student-table{width:100%;border-collapse:collapse;margin-top:12px}
		.abg-student-table th,.abg-student-table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
		.abg-student-table th{background:#f8fafc}
		.abg-student-section{background:#fff;border:1px solid #dfe5ec;border-radius:12px;padding:20px;margin:16px 0}
		.abg-student-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
		.abg-student-link{display:inline-block;padding:9px 13px;border-radius:8px;background:#1677c8;color:#fff;text-decoration:none;font-weight:700}
		.abg-status{font-weight:700}
		@media(max-width:900px){.abg-student-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
		@media(max-width:620px){.abg-student-grid{grid-template-columns:1fr}}
	</style>';
}
