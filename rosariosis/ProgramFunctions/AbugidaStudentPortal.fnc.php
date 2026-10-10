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
		.abg-student-shell{
			max-width:1200px;
			margin:0 auto;
			padding:8px 8px 28px;
		}
		.abg-page-hero{
			position:relative;
			overflow:hidden;
			background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 55%,#38bdf8 100%);
			color:#fff;
			border-radius:20px;
			padding:28px 30px;
			margin:8px 0 20px;
			box-shadow:0 18px 45px rgba(15,23,42,.14);
		}
		.abg-page-hero:after{
			content:"";
			position:absolute;
			width:220px;
			height:220px;
			border-radius:50%;
			background:rgba(255,255,255,.08);
			right:-70px;
			top:-90px;
		}
		.abg-page-hero h2{
			position:relative;
			z-index:1;
			margin:0 0 8px;
			font-size:30px;
			line-height:1.15;
			font-weight:800;
			letter-spacing:-.02em;
			color:#fff;
		}
		.abg-page-hero p{
			position:relative;
			z-index:1;
			margin:0;
			max-width:760px;
			font-size:14px;
			line-height:1.6;
			color:rgba(255,255,255,.9);
		}
		.abg-student-grid{
			display:grid;
			grid-template-columns:repeat(4,minmax(0,1fr));
			gap:16px;
			margin:16px 0;
		}
		.abg-student-card{
			background:#fff;
			border:1px solid #e5e7eb;
			border-radius:16px;
			padding:19px;
			box-shadow:0 8px 24px rgba(15,23,42,.07);
			transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;
		}
		.abg-student-card:hover{
			transform:translateY(-2px);
			border-color:#cbd5e1;
			box-shadow:0 14px 34px rgba(15,23,42,.11);
		}
		.abg-student-card h3{
			margin:0 0 8px;
			color:#0f172a;
		}
		.abg-student-label{
			font-size:11px;
			font-weight:800;
			color:#64748b;
			text-transform:uppercase;
			letter-spacing:.08em;
		}
		.abg-student-value{
			font-size:26px;
			line-height:1.15;
			font-weight:800;
			color:#0f172a;
			margin-top:7px;
		}
		.abg-student-subvalue{
			font-size:12px;
			line-height:1.5;
			color:#64748b;
			margin-top:8px;
		}
		.abg-student-muted{
			color:#64748b;
			font-size:13px;
			line-height:1.55;
		}
		.abg-student-section{
			background:#fff;
			border:1px solid #e5e7eb;
			border-radius:16px;
			padding:22px;
			margin:16px 0;
			box-shadow:0 8px 24px rgba(15,23,42,.06);
		}
		.abg-section-header{
			display:flex;
			align-items:flex-start;
			justify-content:space-between;
			gap:16px;
			flex-wrap:wrap;
			margin-bottom:14px;
		}
		.abg-section-header h3{
			margin:0;
			font-size:20px;
			font-weight:800;
			color:#0f172a;
		}
		.abg-section-header p{
			margin:5px 0 0;
			color:#64748b;
			font-size:13px;
			line-height:1.5;
		}
		.abg-student-actions{
			display:flex;
			gap:10px;
			flex-wrap:wrap;
			margin-top:16px;
		}
		.abg-student-link{
			display:inline-flex;
			align-items:center;
			justify-content:center;
			min-height:40px;
			padding:9px 14px;
			border-radius:10px;
			background:#2563eb;
			border:1px solid #2563eb;
			color:#fff !important;
			text-decoration:none !important;
			font-weight:800;
			font-size:13px;
			box-shadow:0 7px 18px rgba(37,99,235,.20);
			transition:background .18s ease,transform .18s ease,box-shadow .18s ease;
		}
		.abg-student-link:hover{
			background:#1d4ed8;
			border-color:#1d4ed8;
			transform:translateY(-1px);
			box-shadow:0 9px 22px rgba(37,99,235,.26);
		}
		.abg-student-link.secondary{
			background:#fff;
			color:#2563eb !important;
			border-color:#cbd5e1;
			box-shadow:none;
		}
		.abg-student-link.secondary:hover{
			background:#f8fafc;
		}
		.abg-student-table-wrap{
			overflow:auto;
			border:1px solid #e5e7eb;
			border-radius:14px;
			background:#fff;
		}
		.abg-student-table{
			width:100%;
			border-collapse:separate;
			border-spacing:0;
			margin:0;
			font-size:14px;
		}
		.abg-student-table th,
		.abg-student-table td{
			padding:12px 14px;
			border-bottom:1px solid #e5e7eb;
			text-align:left;
			vertical-align:middle;
		}
		.abg-student-table th{
			background:#f8fafc;
			color:#334155;
			font-size:11px;
			font-weight:800;
			text-transform:uppercase;
			letter-spacing:.06em;
			white-space:nowrap;
		}
		.abg-student-table tbody tr:hover{
			background:#f8fbff;
		}
		.abg-student-table tbody tr:last-child td{
			border-bottom:none;
		}
		.abg-badge{
			display:inline-flex;
			align-items:center;
			padding:6px 10px;
			border-radius:999px;
			font-size:11px;
			font-weight:800;
			white-space:nowrap;
		}
		.abg-badge.success{background:#dcfce7;color:#166534}
		.abg-badge.warning{background:#fef3c7;color:#92400e}
		.abg-badge.danger{background:#fee2e2;color:#991b1b}
		.abg-badge.info{background:#dbeafe;color:#1d4ed8}
		.abg-badge.neutral{background:#f1f5f9;color:#475569}
		.abg-info-list{
			display:grid;
			grid-template-columns:repeat(2,minmax(0,1fr));
			gap:14px;
			margin-top:14px;
		}
		.abg-info-item{
			background:#fbfdff;
			border:1px solid #e5e7eb;
			border-radius:13px;
			padding:14px 16px;
		}
		.abg-info-value{
			margin-top:5px;
			color:#0f172a;
			font-size:15px;
			font-weight:750;
		}
		.abg-empty{
			padding:22px;
			border:1px dashed #cbd5e1;
			border-radius:13px;
			background:#f8fafc;
			color:#64748b;
			text-align:center;
			font-size:13px;
		}
		.abg-grade-value{
			font-weight:800;
			color:#0f172a;
		}
		@media(max-width:1000px){
			.abg-student-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
		}
		@media(max-width:640px){
			.abg-student-shell{padding:4px 2px 22px}
			.abg-page-hero{padding:22px 20px;border-radius:16px}
			.abg-page-hero h2{font-size:24px}
			.abg-student-grid,.abg-info-list{grid-template-columns:1fr}
			.abg-student-value{font-size:23px}
			.abg-student-section{padding:18px}
			.abg-student-table th,.abg-student-table td{padding:10px 12px}
		}
	</style>';
}
