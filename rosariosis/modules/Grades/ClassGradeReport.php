<?php
/**
 * Abugida SIS - Class Grade Reports.
 *
 * Staff can review a class / subject Semester grade report and generate
 * printable PDF grade reports for selected students.
 */

require_once 'ProgramFunctions/AbugidaClassRank.fnc.php';

$_REQUEST['grade_id'] = (int) issetVal( $_REQUEST['grade_id'], 0 );
$_REQUEST['course_period_id'] = (int) issetVal( $_REQUEST['course_period_id'], 0 );
$_REQUEST['semester_id'] = (int) issetVal( $_REQUEST['semester_id'], 0 );
$_REQUEST['report_action'] = issetVal( $_REQUEST['report_action'], '' );

function AbugidaReportCoursePeriods( $grade_id )
{
	if ( ! $grade_id )
	{
		return [];
	}

	$grade_title = DBGetOne( "SELECT TITLE
		FROM school_gradelevels
		WHERE ID='" . (int) $grade_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		LIMIT 1" );

	if ( ! preg_match( '/([0-9]+)/', (string) $grade_title, $matches ) )
	{
		return [];
	}

	$grade_number = (int) $matches[1];

	$sql = "SELECT DISTINCT cp.COURSE_PERIOD_ID,
		CONCAT(c.TITLE,' - ',cp.TITLE) AS TITLE,
		cp.MARKING_PERIOD_ID
		FROM course_subjects cs
		JOIN courses c ON c.SUBJECT_ID=cs.SUBJECT_ID
			AND c.SCHOOL_ID=cs.SCHOOL_ID
			AND c.SYEAR=cs.SYEAR
		JOIN course_periods cp ON cp.COURSE_ID=c.COURSE_ID
			AND cp.SCHOOL_ID=c.SCHOOL_ID
			AND cp.SYEAR=c.SYEAR
		WHERE cs.SCHOOL_ID='" . UserSchool() . "'
		AND cs.SYEAR='" . UserSyear() . "'
		AND (
			cs.TITLE='" . $grade_number . "'
			OR cs.TITLE='" . $grade_number . "th'
			OR cs.TITLE='Grade " . $grade_number . "'
			OR cs.TITLE LIKE '%Grade " . $grade_number . "%'
		)";

	if ( User( 'PROFILE' ) === 'teacher' )
	{
		$sql .= " AND (cp.TEACHER_ID='" . User( 'STAFF_ID' ) . "'
			OR cp.SECONDARY_TEACHER_ID='" . User( 'STAFF_ID' ) . "')";
	}

	$sql .= " ORDER BY c.TITLE,cp.TITLE";

	return DBGet( $sql );
}

function AbugidaReportStudentSemesterGrades( $student_id, $semester_id )
{
	return DBGet( "SELECT g.GRADE_PERCENT,
		g.COURSE_TITLE,
		g.COURSE_PERIOD_ID
		FROM student_report_card_grades g
		WHERE g.SCHOOL_ID='" . UserSchool() . "'
		AND g.SYEAR='" . UserSyear() . "'
		AND g.STUDENT_ID='" . (int) $student_id . "'
		AND g.MARKING_PERIOD_ID='" . (int) $semester_id . "'
		AND g.GRADE_PERCENT IS NOT NULL
		ORDER BY g.COURSE_TITLE,g.COURSE_PERIOD_ID" );
}

function AbugidaReportStudentName( $student_id )
{
	return DBGetOne( "SELECT CONCAT(FIRST_NAME,' ',LAST_NAME)
		FROM students
		WHERE STUDENT_ID='" . (int) $student_id . "'
		LIMIT 1" );
}

function AbugidaReportGradeTitle( $student_id )
{
	return DBGetOne( "SELECT gl.TITLE
		FROM student_enrollment se
		JOIN school_gradelevels gl ON gl.ID=se.GRADE_ID
		WHERE se.STUDENT_ID='" . (int) $student_id . "'
		AND se.SCHOOL_ID='" . UserSchool() . "'
		AND se.SYEAR='" . UserSyear() . "'
		ORDER BY se.ID DESC
		LIMIT 1" );
}


function AbugidaReportClassStudents( $grade_id, $course_period_id, $semester_id )
{
	return DBGet( "SELECT DISTINCT s.STUDENT_ID,
		CONCAT(s.FIRST_NAME,' ',s.LAST_NAME) AS STUDENT_NAME,
		g.GRADE_PERCENT
		FROM schedule sch
		JOIN students s ON s.STUDENT_ID=sch.STUDENT_ID
		LEFT JOIN student_report_card_grades g ON g.STUDENT_ID=s.STUDENT_ID
			AND g.SCHOOL_ID=sch.SCHOOL_ID
			AND g.SYEAR=sch.SYEAR
			AND g.COURSE_PERIOD_ID=sch.COURSE_PERIOD_ID
			AND g.MARKING_PERIOD_ID='" . (int) $semester_id . "'
		JOIN student_enrollment se ON se.STUDENT_ID=s.STUDENT_ID
			AND se.SCHOOL_ID=sch.SCHOOL_ID
			AND se.SYEAR=sch.SYEAR
		WHERE sch.SCHOOL_ID='" . UserSchool() . "'
		AND sch.SYEAR='" . UserSyear() . "'
		AND sch.COURSE_PERIOD_ID='" . (int) $course_period_id . "'
		AND sch.MARKING_PERIOD_ID='" . (int) $semester_id . "'
		AND se.GRADE_ID='" . (int) $grade_id . "'
		ORDER BY s.LAST_NAME,s.FIRST_NAME,s.STUDENT_ID" );
}


function AbugidaReportCanAccessCoursePeriod( $course_period_id )
{
	if ( ! $course_period_id )
	{
		return false;
	}

	$where_teacher = '';

	if ( User( 'PROFILE' ) === 'teacher' )
	{
		$where_teacher = " AND (cp.TEACHER_ID='" . User( 'STAFF_ID' ) . "'
			OR cp.SECONDARY_TEACHER_ID='" . User( 'STAFF_ID' ) . "')";
	}

	return (bool) DBGetOne( "SELECT cp.COURSE_PERIOD_ID
		FROM course_periods cp
		WHERE cp.COURSE_PERIOD_ID='" . (int) $course_period_id . "'
		AND cp.SCHOOL_ID='" . UserSchool() . "'
		AND cp.SYEAR='" . UserSyear() . "'" .
		$where_teacher . "
		LIMIT 1" );
}

function AbugidaReportAllowedStudentIds( $grade_id, $course_period_id, $semester_id )
{
	$rows = AbugidaReportClassStudents( $grade_id, $course_period_id, $semester_id );
	$ids = [];

	foreach ( (array) $rows as $row )
	{
		$ids[] = (int) $row['STUDENT_ID'];
	}

	return $ids;
}

function AbugidaReportStudentHTML( $student_id, $semester_id )
{
	$name = AbugidaReportStudentName( $student_id );
	$grade_title = AbugidaReportGradeTitle( $student_id );
	$semester_title = GetMP( $semester_id );
	$grades = AbugidaReportStudentSemesterGrades( $student_id, $semester_id );
	$rank_RET = AbugidaRankGetStudent( $student_id, 'SEM', $semester_id );
	$rank = ! empty( $rank_RET[1] ) ? $rank_RET[1] : [];

	$full_year_id = GetParentMP( 'FY', $semester_id );
	$fy_RET = $full_year_id ? AbugidaRankGetStudent( $student_id, 'FY', $full_year_id ) : [];
	$fy = ! empty( $fy_RET[1] ) ? $fy_RET[1] : [];

	$html = '<div class="abg-student-report">';
	$html .= '<h2>' . AttrEscape( SchoolInfo( 'TITLE' ) ) . '</h2>';
	$html .= '<h3>' . _( 'Student Grade Report' ) . '</h3>';
	$html .= '<table class="abg-info"><tr><td><b>' . _( 'Student' ) . ':</b> ' . AttrEscape( $name ) . '</td>';
	$html .= '<td><b>' . _( 'Student ID' ) . ':</b> ' . (int) $student_id . '</td></tr>';
	$html .= '<tr><td><b>' . _( 'Grade' ) . ':</b> ' . AttrEscape( $grade_title ) . '</td>';
	$html .= '<td><b>' . _( 'Semester' ) . ':</b> ' . AttrEscape( $semester_title ) . '</td></tr></table>';

	$html .= '<table class="abg-report-table"><thead><tr><th>' . _( 'Subject' ) . '</th><th>' . _( 'Percentage' ) . '</th></tr></thead><tbody>';

	foreach ( (array) $grades as $grade )
	{
		$html .= '<tr><td>' . AttrEscape( $grade['COURSE_TITLE'] ) . '</td><td>' .
			number_format( (float) $grade['GRADE_PERCENT'], 2 ) . '%</td></tr>';
	}

	if ( ! $grades )
	{
		$html .= '<tr><td colspan="2">' . _( 'No official grades are available.' ) . '</td></tr>';
	}

	$html .= '</tbody></table>';

	$html .= '<div class="abg-summary">';

	if ( $rank )
	{
		$html .= '<p><b>' . _( 'Semester Average' ) . ':</b> ' .
			number_format( (float) $rank['AVERAGE_PERCENT'], 2 ) . '%</p>';
		$html .= '<p><b>' . _( 'Semester Class Rank' ) . ':</b> ' .
			(int) $rank['RANK_POSITION'] . ' / ' . (int) $rank['COHORT_SIZE'] . '</p>';
	}
	else
	{
		$html .= '<p><b>' . _( 'Semester Average / Rank' ) . ':</b> ' . _( 'Not calculated yet' ) . '</p>';
	}

	if ( $fy )
	{
		$html .= '<p><b>' . _( 'Full Year Cumulative Average' ) . ':</b> ' .
			number_format( (float) $fy['AVERAGE_PERCENT'], 2 ) . '%</p>';
		$html .= '<p><b>' . _( 'Full Year Class Rank' ) . ':</b> ' .
			(int) $fy['RANK_POSITION'] . ' / ' . (int) $fy['COHORT_SIZE'] . '</p>';
	}

	$html .= '</div></div>';

	return $html;
}

if ( $_REQUEST['report_action'] === 'student_pdf'
	&& ! empty( $_REQUEST['student_ids'] )
	&& $_REQUEST['grade_id']
	&& $_REQUEST['course_period_id']
	&& $_REQUEST['semester_id'] )
{
	$student_ids = array_values( array_filter( array_map( 'intval', (array) $_REQUEST['student_ids'] ) ) );

	if ( ! AbugidaReportCanAccessCoursePeriod( $_REQUEST['course_period_id'] ) )
	{
		echo ErrorMessage( [ _( 'You are not allowed to access reports for this class.' ) ] );
		exit;
	}

	$allowed_student_ids = AbugidaReportAllowedStudentIds(
		$_REQUEST['grade_id'],
		$_REQUEST['course_period_id'],
		$_REQUEST['semester_id']
	);

	$student_ids = array_values( array_intersect( $student_ids, $allowed_student_ids ) );

	if ( $student_ids )
	{
		$reports = [];

		foreach ( $student_ids as $student_id )
		{
			$reports[] = AbugidaReportStudentHTML( $student_id, $_REQUEST['semester_id'] );
		}

		$handle = PDFStart( [ 'mode' => 0 ] );

		if ( empty( $GLOBALS['wkhtmltopdfPath'] ) )
		{
			echo '<div class="abg-browser-print"><button type="button" onclick="window.print()">' .
				_( 'Print / Save as PDF' ) . '</button></div>';
		}

		echo '<style>
			.abg-student-report{font-family:Arial,sans-serif;font-size:12px}
			.abg-student-report h2,.abg-student-report h3{text-align:center;margin:5px 0}
			.abg-info,.abg-report-table{width:100%;border-collapse:collapse;margin:12px 0}
			.abg-info td{padding:5px}
			.abg-report-table th,.abg-report-table td{border:1px solid #777;padding:7px}
			.abg-report-table th{background:#eee}
			.abg-summary{margin-top:14px;border:1px solid #999;padding:10px}
			@media print{.abg-browser-print{display:none}}
		</style>';

		echo implode( '<div style="page-break-after:always"></div>', $reports );
		PDFStop( $handle );
		exit;
	}
}

if ( $_REQUEST['report_action'] === 'class_pdf'
	&& $_REQUEST['grade_id']
	&& $_REQUEST['course_period_id']
	&& $_REQUEST['semester_id'] )
{
	$allowed = AbugidaReportCanAccessCoursePeriod( $_REQUEST['course_period_id'] );

	if ( $allowed )
	{
		$cp = DBGet( "SELECT cp.TITLE AS CP_TITLE,c.TITLE AS COURSE_TITLE
			FROM course_periods cp
			JOIN courses c ON c.COURSE_ID=cp.COURSE_ID
			WHERE cp.COURSE_PERIOD_ID='" . (int) $_REQUEST['course_period_id'] . "'
			LIMIT 1" );

		$students = AbugidaReportClassStudents(
			$_REQUEST['grade_id'],
			$_REQUEST['course_period_id'],
			$_REQUEST['semester_id']
		);

		$handle = PDFStart( [ 'mode' => 0 ] );

		if ( empty( $GLOBALS['wkhtmltopdfPath'] ) )
		{
			echo '<div class="abg-browser-print"><button type="button" onclick="window.print()">' .
				_( 'Print / Save as PDF' ) . '</button></div>';
		}


		echo '<style>
			body{font-family:Arial,sans-serif;font-size:11px}
			h2,h3{text-align:center;margin:5px 0}
			table{width:100%;border-collapse:collapse;margin-top:12px}
			th,td{border:1px solid #777;padding:6px;text-align:left}
			th{background:#eee}
			@media print{.abg-browser-print{display:none}}
		</style>';

		echo '<h2>' . AttrEscape( SchoolInfo( 'TITLE' ) ) . '</h2>';
		echo '<h3>' . _( 'Class Grade Report' ) . '</h3>';

		if ( ! empty( $cp[1] ) )
		{
			echo '<p><b>' . _( 'Class / Subject' ) . ':</b> ' .
				AttrEscape( $cp[1]['COURSE_TITLE'] . ' - ' . $cp[1]['CP_TITLE'] ) . '<br>';
		}

		echo '<b>' . _( 'Semester' ) . ':</b> ' . AttrEscape( GetMP( $_REQUEST['semester_id'] ) ) . '</p>';

		echo '<table><thead><tr><th>' . _( 'Rank' ) . '</th><th>' . _( 'Student ID' ) . '</th><th>' .
			_( 'Student' ) . '</th><th>' . _( 'Subject Grade' ) . '</th><th>' .
			_( 'Semester Average' ) . '</th></tr></thead><tbody>';

		foreach ( (array) $students as $student )
		{
			$rank_RET = AbugidaRankGetStudent( $student['STUDENT_ID'], 'SEM', $_REQUEST['semester_id'] );
			$rank = ! empty( $rank_RET[1] ) ? $rank_RET[1] : [];

			echo '<tr><td>' . ( $rank ? (int) $rank['RANK_POSITION'] : '-' ) . '</td>';
			echo '<td>' . (int) $student['STUDENT_ID'] . '</td>';
			echo '<td>' . AttrEscape( $student['STUDENT_NAME'] ) . '</td>';
			echo '<td>' . ( $student['GRADE_PERCENT'] === null || $student['GRADE_PERCENT'] === '' ? '-' :
				number_format( (float) $student['GRADE_PERCENT'], 2 ) . '%' ) . '</td>';
			echo '<td>' . ( $rank ? number_format( (float) $rank['AVERAGE_PERCENT'], 2 ) . '%' : '-' ) . '</td></tr>';
		}

		echo '</tbody></table>';

		PDFStop( $handle );
		exit;
	}
}

DrawHeader( ProgramTitle() );

echo '<script src="assets/js/csp/modules/grades/ClassGradeReport.js?v=1"></script>';

global $wkhtmltopdfPath;
$pdf_available = ! empty( $wkhtmltopdfPath );


$grades = DBGet( "SELECT ID,TITLE,SORT_ORDER
	FROM school_gradelevels
	WHERE SCHOOL_ID='" . UserSchool() . "'
	ORDER BY SORT_ORDER IS NULL,SORT_ORDER,ID" );

$semesters = DBGet( "SELECT MARKING_PERIOD_ID,TITLE,SORT_ORDER
	FROM school_marking_periods
	WHERE SCHOOL_ID='" . UserSchool() . "'
	AND SYEAR='" . UserSyear() . "'
	AND MP='SEM'
	AND DOES_GRADES='Y'
	ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );

$course_periods = AbugidaReportCoursePeriods( $_REQUEST['grade_id'] );

echo '<style>
	.abg-class-report{max-width:1200px}
	.abg-card{background:#fff;border:1px solid #d9e1ea;border-radius:12px;padding:22px;margin:16px 0}
	.abg-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
	.abg-field label{display:block;font-weight:700;margin-bottom:7px}
	.abg-field select{width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;background:#fff}
	.abg-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
	.abg-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;background:#1677c8;color:#fff;padding:10px 16px;font-weight:700;cursor:pointer;text-decoration:none}
	.abg-table{width:100%;border-collapse:collapse;margin-top:14px}
	.abg-table th,.abg-table td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left}
	.abg-table th{background:#f8fafc}
	.abg-muted{color:#667085}
	.abg-print-note{margin-top:12px;padding:10px 12px;border:1px solid #f2c94c;border-radius:8px;background:#fff8db;color:#6b5900}
	@media(max-width:800px){.abg-grid{grid-template-columns:1fr}}
</style>';

echo '<div class="abg-class-report">';
echo '<div class="abg-card"><h3>' . _( 'Class Grade Report' ) . '</h3>';
echo '<p class="abg-muted">' . _( 'Select a grade, class / subject and semester to view official percentages. You can generate printable reports for a full class or selected students.' ) . '</p>';

if ( ! $pdf_available )
{
	echo '<div class="abg-print-note">' .
		_( 'PDF conversion is not configured on this server. Reports will open as print-ready HTML; use your browser Print command and choose Save as PDF. When wkhtmltopdf is configured, these buttons download PDF files directly.' ) .
		'</div>';
}

echo '<form method="GET" action="Modules.php" id="abugida-class-grade-report-form">';
echo '<input type="hidden" name="modname" value="Grades/ClassGradeReport.php">';
echo '<div class="abg-grid">';

echo '<div class="abg-field"><label>' . _( 'Grade' ) . '</label><select name="grade_id" id="abugida-class-grade-report-grade">';
echo '<option value="">' . _( 'Select Grade' ) . '</option>';
foreach ( (array) $grades as $grade )
{
	echo '<option value="' . (int) $grade['ID'] . '"' .
		( $_REQUEST['grade_id'] === (int) $grade['ID'] ? ' selected' : '' ) . '>' .
		AttrEscape( $grade['TITLE'] ) . '</option>';
}
echo '</select></div>';

echo '<div class="abg-field"><label>' . _( 'Class / Subject' ) . '</label><select name="course_period_id">';
echo '<option value="">' . _( 'Select Class / Subject' ) . '</option>';
foreach ( (array) $course_periods as $cp )
{
	echo '<option value="' . (int) $cp['COURSE_PERIOD_ID'] . '"' .
		( $_REQUEST['course_period_id'] === (int) $cp['COURSE_PERIOD_ID'] ? ' selected' : '' ) . '>' .
		AttrEscape( $cp['TITLE'] ) . '</option>';
}
echo '</select></div>';

echo '<div class="abg-field"><label>' . _( 'Semester' ) . '</label><select name="semester_id">';
echo '<option value="">' . _( 'Select Semester' ) . '</option>';
foreach ( (array) $semesters as $sem )
{
	echo '<option value="' . (int) $sem['MARKING_PERIOD_ID'] . '"' .
		( $_REQUEST['semester_id'] === (int) $sem['MARKING_PERIOD_ID'] ? ' selected' : '' ) . '>' .
		AttrEscape( $sem['TITLE'] ) . '</option>';
}
echo '</select></div>';

echo '</div><div class="abg-actions"><button class="abg-btn" type="submit">' . _( 'View Report' ) . '</button></div></form>';
echo '</div>';

if ( $_REQUEST['grade_id'] && $_REQUEST['course_period_id'] && $_REQUEST['semester_id'] )
{
	$selected_cp = DBGet( "SELECT cp.COURSE_PERIOD_ID,cp.TITLE AS CP_TITLE,c.TITLE AS COURSE_TITLE
		FROM course_periods cp
		JOIN courses c ON c.COURSE_ID=cp.COURSE_ID
		WHERE cp.COURSE_PERIOD_ID='" . (int) $_REQUEST['course_period_id'] . "'
		AND cp.SCHOOL_ID='" . UserSchool() . "'
		AND cp.SYEAR='" . UserSyear() . "'
		LIMIT 1" );

	if ( ! AbugidaReportCanAccessCoursePeriod( $_REQUEST['course_period_id'] ) )
	{
		$selected_cp = [];
	}

	if ( ! empty( $selected_cp[1] ) )
	{
		$students = AbugidaReportClassStudents(
			$_REQUEST['grade_id'],
			$_REQUEST['course_period_id'],
			$_REQUEST['semester_id']
		);

		echo '<div class="abg-card">';
		echo '<h3>' . AttrEscape( $selected_cp[1]['COURSE_TITLE'] . ' - ' . $selected_cp[1]['CP_TITLE'] ) . '</h3>';
		echo '<p class="abg-muted">' . AttrEscape( GetMP( $_REQUEST['semester_id'] ) ) . '</p>';

		echo '<div class="abg-actions">';
		echo '<a class="abg-btn" target="_blank" href="' . URLEscape(
			'Modules.php?modname=Grades/ClassGradeReport.php&report_action=class_pdf&_ROSARIO_PDF=true' .
			'&grade_id=' . (int) $_REQUEST['grade_id'] .
			'&course_period_id=' . (int) $_REQUEST['course_period_id'] .
			'&semester_id=' . (int) $_REQUEST['semester_id']
		) . '">' . ( $pdf_available ? _( 'Download Class Report PDF' ) : _( 'Print / Save Class Report as PDF' ) ) . '</a>';
		echo '</div>';

		echo '<form method="POST" action="Modules.php?modname=Grades/ClassGradeReport.php&_ROSARIO_PDF=true" target="_blank">';
		echo '<input type="hidden" name="report_action" value="student_pdf">';
		echo '<input type="hidden" name="grade_id" value="' . (int) $_REQUEST['grade_id'] . '">';
		echo '<input type="hidden" name="course_period_id" value="' . (int) $_REQUEST['course_period_id'] . '">';
		echo '<input type="hidden" name="semester_id" value="' . (int) $_REQUEST['semester_id'] . '">';
		echo '<table class="abg-table"><thead><tr>';
		echo '<th><input type="checkbox" class="onclick-checkall" data-name-like="student_ids"></th>';
		echo '<th>' . _( 'Student ID' ) . '</th><th>' . _( 'Student' ) . '</th>';
		echo '<th>' . _( 'Subject Grade' ) . '</th><th>' . _( 'Semester Average' ) . '</th><th>' . _( 'Class Rank' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( (array) $students as $student )
		{
			$rank_RET = AbugidaRankGetStudent( $student['STUDENT_ID'], 'SEM', $_REQUEST['semester_id'] );
			$rank = ! empty( $rank_RET[1] ) ? $rank_RET[1] : [];

			echo '<tr>';
			echo '<td><input type="checkbox" name="student_ids[]" value="' . (int) $student['STUDENT_ID'] . '"></td>';
			echo '<td>' . (int) $student['STUDENT_ID'] . '</td>';
			echo '<td>' . AttrEscape( $student['STUDENT_NAME'] ) . '</td>';
			echo '<td>' . ( $student['GRADE_PERCENT'] === null || $student['GRADE_PERCENT'] === '' ? '&mdash;' :
				number_format( (float) $student['GRADE_PERCENT'], 2 ) . '%' ) . '</td>';
			echo '<td>' . ( $rank ? number_format( (float) $rank['AVERAGE_PERCENT'], 2 ) . '%' : '&mdash;' ) . '</td>';
			echo '<td>' . ( $rank ? (int) $rank['RANK_POSITION'] . ' / ' . (int) $rank['COHORT_SIZE'] : '&mdash;' ) . '</td>';
			echo '</tr>';
		}

		if ( ! $students )
		{
			echo '<tr><td colspan="6">' . _( 'No students were found in this class for the selected Semester.' ) . '</td></tr>';
		}

		echo '</tbody></table>';

		if ( $students )
		{
			echo '<div class="abg-actions">';
			echo '<button class="abg-btn" type="submit">' .
				( $pdf_available ? _( 'Download Selected Student Reports PDF' ) : _( 'Print / Save Selected Student Reports as PDF' ) ) .
				'</button>';
			echo '</div>';
		}

		echo '</form></div>';
	}
}

echo '</div>';
