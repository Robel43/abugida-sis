<?php
/**
 * Final Grades functions & AJAX modfunc.
 *
 * @see InputFinalGrades.php, Grades.php, Assignments.php & Grades Import module
 *
 * @package RosarioSIS
 */

if ( $_REQUEST['modfunc'] === 'final_grades_all_mp_save_ajax' )
{
	ob_clean();

	// Note: no need to call RedirectURL() & unset $_REQUEST params here as we die just after.
	$cp_ids = empty( $_REQUEST['cp_id'] ) ? '0' : $_REQUEST['cp_id'];
	$semester_id = ! empty( $_REQUEST['semester_id'] ) ?
		$_REQUEST['semester_id'] :
		( ! empty( $_REQUEST['qtr_id'] ) ? $_REQUEST['qtr_id'] : '0' );

	foreach ( (array) $cp_ids as $cp_id )
	{
		FinalGradesAllMPSave( $cp_id, $semester_id );
	}

	die( 1 );
}

/**
 * Automatically calculate & save Course Period's Final Grades using Gradebook Grades
 * Does not include Inactive Students.
 *
 * Call FinalGradesAllMPSave() using 'final_grades_all_mp_save_ajax' modfunc.
 * The benefit of calling FinalGradesAllMPSave() in AJAX is to not make the user wait for the response.
 * Anyway, the user does not need to see the result immediately / on the same screen.
 * The AJAX call runs in the background.
 *
 * @see Grades.php: after Gradebook Grades insert or update.
 * @see Grades Import module: after Gradebook Grades insert or update.
 * @see Assignments.php:
 *      - after Assignment Type's "Percent of Final Grade" update.
 *      - after Assignment insert or "Points"/"Weight" update.
 *      - after Assignment delete.
 * @see MassCreateAssignments.php: after Assignments insert.
 *
 * @since 11.8
 *
 * @param int|array $cp_id  Course Period ID or Course Period IDs array.
 * @param int       $qtr_id Quarter ID.
 *
 * @return boolean True.
 */
function FinalGradesAllMPSaveAJAX( $cp_id, $semester_id )
{
	$url = PreparePHP_SELF( [
		'modname' => $_REQUEST['modname'],
		'modfunc' => 'final_grades_all_mp_save_ajax',
		'cp_id' => $cp_id,
		'semester_id' => $semester_id,
	] );

	?>
	<input type="hidden" disabled id="ajax_url" value="<?php echo $url; ?>" />
	<script src="assets/js/csp/modules/AjaxUrl.js?v=12.5"></script>
	<?php

	return true;
}

/**
 * Automatically calculate and save the selected Semester final percentages.
 *
 * Abugida uses Semesters as the atomic grading period. Quarter and Progress
 * Period records are not required. Legacy Quarter IDs are resolved to their
 * containing Semester when encountered.
 */
function FinalGradesAllMPSave( $cp_id, $semester_id )
{
	if ( ! $cp_id || ! $semester_id )
	{
		return false;
	}

	$type = GetMP( $semester_id, 'MP' );

	if ( $type === 'QTR' )
	{
		$semester_id = GetParentMP( 'SEM', $semester_id );
	}
	elseif ( $type === 'PRO' )
	{
		$quarter_id = GetParentMP( 'QTR', $semester_id );
		$semester_id = $quarter_id ? GetParentMP( 'SEM', $quarter_id ) : 0;
	}

	if ( ! $semester_id || GetMP( $semester_id, 'MP' ) !== 'SEM' )
	{
		return false;
	}

	$final_grades = FinalGradesSemesterCalculate( $cp_id, $semester_id );

	if ( ! $final_grades )
	{
		return false;
	}

	FinalGradesSave( $cp_id, $semester_id, $final_grades );

	$teacher_id = DBGetOne( "SELECT TEACHER_ID
		FROM course_periods
		WHERE COURSE_PERIOD_ID='" . (int) $cp_id . "'" );

	$current_completed = (bool) DBGetOne( "SELECT 1
		FROM grades_completed
		WHERE STAFF_ID='" . (int) $teacher_id . "'
		AND MARKING_PERIOD_ID='" . (int) $semester_id . "'
		AND COURSE_PERIOD_ID='" . (int) $cp_id . "'" );

	if ( ! $current_completed )
	{
		DBInsert(
			'grades_completed',
			[
				'STAFF_ID' => (int) $teacher_id,
				'MARKING_PERIOD_ID' => (int) $semester_id,
				'COURSE_PERIOD_ID' => (int) $cp_id,
			]
		);
	}

	return true;
}


/**
 * Automatically calculate Course Period's Final Grades using Gradebook Grades
 * (Quarter or Progress Period)
 *
 * @uses FinalGradesGetAssignmentsPoints()
 * @uses _makeLetterGrade()
 *
 * @since 11.8
 * @since 11.8.5 Fix Final Grade calculation when both "Weight Assignments" & "Weight Assignment Categories" checked
 * @since 12.2 Add $assignment_type_id param
 * @since 12.2 Save null percent: N/A final grade
 * @since 12.2.3 Fix SQL error when percent grade > 999.9
 * @since 12.4.3 Fix PHP fatal error division by zero: Percent Total is 0%, N/A final grade
 *
 * @param int  $cp_id              Course Period ID.
 * @param int  $mp_id              Marking Period ID.
 * @param int  $assignment_type_id Assignment Type ID (optional). Defaults to 0 (all).
 *
 * @return array Final Grades, else empty.
 */
function FinalGradesSemesterCalculate( $cp_id, $mp_id, $assignment_type_id = 0 )
{
	$mp = GetMP( $mp_id, 'MP' );

	if ( $mp === 'QTR' )
	{
		$mp_id = GetParentMP( 'SEM', $mp_id );
		$mp = 'SEM';
	}
	elseif ( $mp === 'PRO' )
	{
		$quarter_id = GetParentMP( 'QTR', $mp_id );
		$mp_id = $quarter_id ? GetParentMP( 'SEM', $quarter_id ) : 0;
		$mp = $mp_id ? 'SEM' : '';
	}

	if ( ! $cp_id || $mp !== 'SEM' )
	{
		return [];
	}

	// First, check Course Period exists and is graded.
	$grade_scale_id = DBGetOne( "SELECT GRADE_SCALE_ID
		FROM course_periods
		WHERE COURSE_PERIOD_ID='" . (int) $cp_id . "'" );

	if ( ! $grade_scale_id )
	{
		return [];
	}

	// Then, check Course Period has assignments and points.
	$points_RET = FinalGradesGetAssignmentsPoints( $cp_id, $mp_id, $assignment_type_id );

	if ( ! $points_RET )
	{
		return [];
	}

	$teacher_id = DBGetOne( "SELECT TEACHER_ID
		FROM course_periods
		WHERE COURSE_PERIOD_ID='" . (int) $cp_id . "'" );

	$gradebook_config = ProgramUserConfig( 'Gradebook', $teacher_id );

	require_once 'ProgramFunctions/_makeLetterGrade.fnc.php';

	$import_RET = [];

	foreach ( (array) $points_RET as $student_id => $student )
	{
		$total = $total_percent = $total_weighted = $total_weights = 0;

		foreach ( (array) $student as $partial_points )
		{
			/**
			 * Do not include Extra Credit assignments
			 * when Total Points is 0 for the Type
			 * if the Gradebook is configured to Weight Grades:
			 * Division by zero is impossible.
			 */
			if ( $partial_points['PARTIAL_TOTAL'] != 0
				|| empty( $gradebook_config['WEIGHT'] ) )
			{
				$total += $partial_points['PARTIAL_POINTS'] * ( ! empty( $gradebook_config['WEIGHT'] ) ?
					$partial_points['FINAL_GRADE_PERCENT'] / $partial_points['PARTIAL_TOTAL'] :
					1
				);

				$total_percent += ( ! empty( $gradebook_config['WEIGHT'] ) ?
					$partial_points['FINAL_GRADE_PERCENT'] :
					$partial_points['PARTIAL_TOTAL']
				);

				if ( ! empty( $gradebook_config['WEIGHT_ASSIGNMENTS'] ) )
				{
					// @since 11.0 Add Weight Assignments option
					$total_weighted += ( ! empty( $gradebook_config['WEIGHT'] ) && $partial_points['PARTIAL_WEIGHT'] ?
						$partial_points['FINAL_GRADE_PERCENT'] *
							( $partial_points['PARTIAL_WEIGHTED_GRADE'] / $partial_points['PARTIAL_WEIGHT'] ) :
						$partial_points['PARTIAL_WEIGHTED_GRADE'] );

					$total_weights += $partial_points['PARTIAL_WEIGHT'];
				}
			}
		}

		if ( $total_percent != 0 )
		{
			$total /= $total_percent;
		}
		else
		{
			/**
			 * Excused (*) for all assignments case
			 * or only E/C (extra credit) assignments case
			 *
			 * @since 12.2 Save null percent: N/A final grade
			 */
			$total = null;
		}

		if ( ! empty( $gradebook_config['WEIGHT_ASSIGNMENTS'] )
			&& $total_weights > 0
			&& $total_percent != 0 )
		{
			// @since 11.0 Add Weight Assignments option
			$total = $total_weighted / $total_weights;

			if ( ! empty( $gradebook_config['WEIGHT'] ) )
			{
				$total = $total_weighted / $total_percent;
			}
		}

		if ( ! is_null( $total ) )
		{
			if ( $total > 9.999 )
			{
				// Fix SQL error when percent grade > 999.9
				$total = '9.999';
			}
			elseif ( $total < 0 )
			{
				$total = '0';
			}
		}

		$import_RET[$student_id] = [
			1 => [
				'REPORT_CARD_GRADE_ID' => _makeLetterGrade( $total, $cp_id, 0, 'ID' ),
				'GRADE_LETTER' => _makeLetterGrade( $total, $cp_id, 0, 'TITLE' ),
				'GRADE_PERCENT' => is_null( $total ) ? null : round( $total * 100, 1 ),
			],
		];
	}

	return $import_RET;
}


/**
 * Legacy RosarioSIS compatibility wrapper.
 * Quarter / Progress requests are resolved to their containing Semester.
 */
function FinalGradesQtrOrProCalculate( $cp_id, $mp_id, $assignment_type_id = 0 )
{
	return FinalGradesSemesterCalculate( $cp_id, $mp_id, $assignment_type_id );
}


/**
 * Automatically calculate Course Period's Final Grades using Gradebook Grades
 * (Semester or Full Year)
 *
 * @uses _makeLetterGrade()
 *
 * @since 11.8
 * @since 12.2.3 Fix SQL error when percent grade > 999.9
 * @since 12.3 Save null percent: N/A final grade
 *
 * @global $warning Warning: Add "Final Grading Percentages are not configured."
 *
 * @param int    $cp_id Course Period ID.
 * @param int    $mp_id Marking Period ID.
 * @param string $mode  Mode: continue or fail. Fail: return empty if warning.
 *
 * @return array Final Grades, else empty.
 */
function FinalGradesSemOrFYCalculate( $cp_id, $mp_id, $mode = 'continue' )
{
	$mp = GetMP( $mp_id, 'MP' );

	if ( ! $cp_id || ! in_array( $mp, [ 'SEM', 'FY' ], true ) )
	{
		return false;
	}

	if ( $mp === 'SEM' )
	{
		return FinalGradesSemesterCalculate( $cp_id, $mp_id );
	}

	$semester_rows = DBGet( "SELECT MARKING_PERIOD_ID
		FROM school_marking_periods
		WHERE MP='SEM'
		AND PARENT_ID='" . (int) $mp_id . "'
		AND DOES_GRADES='Y'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );

	if ( ! $semester_rows )
	{
		return [];
	}

	$semester_ids = array_map(
		function ( $row ) { return (int) $row['MARKING_PERIOD_ID']; },
		(array) $semester_rows
	);

	$percent_rows = DBGet( "SELECT STUDENT_ID,GRADE_PERCENT
		FROM student_report_card_grades
		WHERE COURSE_PERIOD_ID='" . (int) $cp_id . "'
		AND MARKING_PERIOD_ID IN(" . implode( ',', $semester_ids ) . ")
		AND GRADE_PERCENT IS NOT NULL", [], [ 'STUDENT_ID' ] );

	$results = [];

	foreach ( (array) $percent_rows as $student_id => $grades )
	{
		$total = 0;
		$count = 0;

		foreach ( (array) $grades as $grade )
		{
			if ( $grade['GRADE_PERCENT'] === null || $grade['GRADE_PERCENT'] === '' )
			{
				continue;
			}

			$total += (float) $grade['GRADE_PERCENT'];
			$count++;
		}

		if ( ! $count )
		{
			continue;
		}

		$percent = round( $total / $count, 1 );
		$ratio = $percent / 100;

		$results[$student_id] = [
			1 => [
				'REPORT_CARD_GRADE_ID' => _makeLetterGrade( $ratio, $cp_id, 0, 'ID' ),
				'GRADE_LETTER' => _makeLetterGrade( $ratio, $cp_id, 0, 'TITLE' ),
				'GRADE_PERCENT' => $percent,
			],
		];
	}

	return $results;
}


/**
 * Get Assignments Points in order to calculate Course Period's Final Grades
 * (Quarter or Progress Period)
 *
 * @since 11.8
 * @since 11.8.5 Fix Final Grade calculation when "Weight Assignments" checked & excused
 * @since 12.2 Add $assignment_type_id param
 *
 * @global $_ROSARIO['User'] if we need to impersonate Teacher (when admin & outside Teacher Programs)
 *
 * @param int $cp_id              Course Period ID.
 * @param int $mp_id              Marking Period ID.
 * @param int $assignment_type_id Assignment Type ID (optional). Defaults to 0 (all).
 *
 * @return array Points or empty.
 */
function FinalGradesGetAssignmentsPoints( $cp_id, $mp_id, $assignment_type_id = 0 )
{
	global $_ROSARIO;

	$mp = GetMP( $mp_id, 'MP' );

	if ( $mp === 'QTR' )
	{
		$mp_id = GetParentMP( 'SEM', $mp_id );
		$mp = 'SEM';
	}
	elseif ( $mp === 'PRO' )
	{
		$quarter_id = GetParentMP( 'QTR', $mp_id );
		$mp_id = $quarter_id ? GetParentMP( 'SEM', $quarter_id ) : 0;
		$mp = $mp_id ? 'SEM' : '';
	}

	if ( ! $cp_id || $mp !== 'SEM' )
	{
		return [];
	}

	$teacher_id = DBGetOne( "SELECT TEACHER_ID
		FROM course_periods
		WHERE COURSE_PERIOD_ID='" . (int) $cp_id . "'" );

	$gradebook_config = ProgramUserConfig( 'Gradebook', $teacher_id );

	// Note: The 'active assignment' determination is not fully correct.  It would be easy to be fully correct here but the same determination
	// as in Grades.php is used to avoid apparent inconsistencies in the grade calculations.  See also the note at top of Grades.php.
	$extra['SELECT_ONLY'] = "s.STUDENT_ID, gt.ASSIGNMENT_TYPE_ID,
	sum(CASE WHEN gg.POINTS<0 THEN '0' ELSE gg.POINTS END) AS PARTIAL_POINTS,
	sum(CASE WHEN gg.POINTS<0 THEN '0' ELSE ga.POINTS END) AS PARTIAL_TOTAL,gt.FINAL_GRADE_PERCENT";

	if ( ! empty( $gradebook_config['WEIGHT_ASSIGNMENTS'] ) )
	{
		// @since 11.0 Add Weight Assignments option
		$extra['SELECT_ONLY'] .= ",sum(CASE WHEN gg.POINTS<0 THEN '0' ELSE
			(CASE WHEN ga.WEIGHT IS NULL THEN '0' ELSE ga.WEIGHT END) END) AS PARTIAL_WEIGHT,
			sum(CASE WHEN gg.POINTS<0 THEN '0' ELSE (gg.POINTS/ga.POINTS)*ga.WEIGHT END) AS PARTIAL_WEIGHTED_GRADE";
	}

	$semester_id = $mp_id;

	$extra['FROM'] = " JOIN gradebook_assignments ga ON
		(((ga.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID
				OR ga.COURSE_ID=cp.COURSE_ID)
				AND ga.STAFF_ID=cp.TEACHER_ID)
			AND ga.MARKING_PERIOD_ID='" . (int) $semester_id . "')
		LEFT OUTER JOIN gradebook_grades gg ON
		(gg.STUDENT_ID=s.STUDENT_ID
			AND gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID
			AND gg.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID),gradebook_assignment_types gt";

	// Check Current date.
	$extra['WHERE'] = " AND gt.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID
		AND gt.COURSE_ID=cp.COURSE_ID
		AND (gg.POINTS IS NOT NULL
			OR (ga.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=ga.ASSIGNED_DATE)
			AND (ga.DUE_DATE IS NULL OR CURRENT_DATE>=ga.DUE_DATE)
			OR CURRENT_DATE>(SELECT END_DATE
				FROM school_marking_periods
				WHERE MARKING_PERIOD_ID=ga.MARKING_PERIOD_ID))";

	if ( $assignment_type_id )
	{
		$extra['WHERE'] .= " AND ga.ASSIGNMENT_TYPE_ID='" . (int) $assignment_type_id . "'";
	}

	// Check Student enrollment.
	$extra['WHERE'] .= " AND (gg.POINTS IS NOT NULL
		OR ga.DUE_DATE IS NULL
		OR ((ga.DUE_DATE>=ss.START_DATE
			AND (ss.END_DATE IS NULL OR ga.DUE_DATE<=ss.END_DATE))
		AND (ga.DUE_DATE>=ssm.START_DATE
			AND (ssm.END_DATE IS NULL OR ga.DUE_DATE<=ssm.END_DATE))))";

	if ( ! empty( $gradebook_config['WEIGHT_ASSIGNMENTS'] ) )
	{
		// @since 11.0 Add Weight Assignments option
		// Exclude Extra Credit assignments.
		$extra['WHERE'] .= " AND ga.POINTS>0";
	}


	$extra['GROUP'] = "gt.ASSIGNMENT_TYPE_ID,gt.FINAL_GRADE_PERCENT,s.STUDENT_ID";

	$extra['group'] = [ 'STUDENT_ID' ];

	$is_teacher = User( 'PROFILE' ) === 'teacher';

	if ( ! $is_teacher )
	{
		// Fix SQL error, run GetStuList() as Teacher. For example in MassCreateAssignments.php
		UserImpersonateTeacher( $teacher_id );

		$_SESSION['UserCoursePeriod'] = $cp_id;
	}

	$points_RET = GetStuList( $extra );

	if ( ! $is_teacher )
	{
		// Undo UserImpersonateTeacher().
		$_ROSARIO['User'][1] = $_ROSARIO['User'][0];

		unset( $_SESSION['UserCoursePeriod'] );
	}

	return $points_RET;
}

/**
 * Save Final Grades to database
 * Adapted for call after FinalGradesSemOrFYCalculate() or FinalGradesQtrOrProCalculate()
 * Should work even for a Course Period not in current School / Year.
 *
 * @since 11.8
 * @since 12.2 Save null percent: N/A final grade
 *
 * @param int   $cp_id        Course Period ID.
 * @param int   $mp_id        Marking Period ID.
 * @param array $final_grades Final Grades array, with student ID as key.
 *
 * @return bool True if saved, else false.
 */
function FinalGradesSave( $cp_id, $mp_id, $final_grades )
{
	static $course_period_RET = [];

	if ( ! $cp_id
		|| ! GetMP( $mp_id )
		|| ! $final_grades )
	{
		return false;
	}

	if ( empty( $course_period_RET[ $cp_id ] ) )
	{
		$course_period_RET[ $cp_id ] = DBGet( "SELECT SYEAR,SCHOOL_ID,MP
			FROM course_periods
			WHERE COURSE_PERIOD_ID='" . (int) $cp_id . "'" );
	}

	$cp = $course_period_RET[ $cp_id ][1];

	$current_RET = DBGet( "SELECT STUDENT_ID
		FROM student_report_card_grades
		WHERE COURSE_PERIOD_ID='" . (int) $cp_id . "'
		AND MARKING_PERIOD_ID='" . (int) $mp_id . "'", [], [ 'STUDENT_ID' ] );

	$course_RET = DBGet( "SELECT cp.COURSE_ID,c.TITLE AS COURSE_NAME,cp.TITLE,
		cp.GRADE_SCALE_ID,credit('" . (int) $cp_id . "','" . (int) $mp_id . "') AS CREDITS,
		DOES_CLASS_RANK AS CLASS_RANK,c.CREDIT_HOURS
		FROM course_periods cp,courses c
		WHERE cp.COURSE_ID=c.COURSE_ID
		AND cp.COURSE_PERIOD_ID='" . (int) $cp_id . "'" );

	$grade_scale_id = $course_RET[1]['GRADE_SCALE_ID'];

	if ( ! $grade_scale_id )
	{
		return false;
	}

	foreach ( (array) $final_grades as $student_id => $final_grade )
	{
		if ( empty( $final_grade[1]['REPORT_CARD_GRADE_ID'] )
			|| ! array_key_exists( 'GRADE_PERCENT', $final_grade[1] ) )
		{
			continue;
		}

		$grade = $final_grade[1]['REPORT_CARD_GRADE_ID'];
		$letter = issetVal( $final_grade[1]['GRADE_LETTER'], '' );

		$columns = [
			'REPORT_CARD_GRADE_ID' => $grade,
			'GRADE_PERCENT' => $final_grade[1]['GRADE_PERCENT'],
			'GRADE_LETTER' => DBEscapeString( $letter ),
			'COURSE_TITLE' => DBEscapeString( $course_RET[1]['COURSE_NAME'] ),
		];

		if ( isset( $final_grade[1]['COMMENT'] ) )
		{
			$columns['COMMENT'] = $final_grade[1]['COMMENT'];
		}

		$where_columns = [
			'SYEAR' => $cp['SYEAR'],
			'SCHOOL_ID' => (int) $cp['SCHOOL_ID'],
			'COURSE_PERIOD_ID' => (int) $cp_id,
			'MARKING_PERIOD_ID' => (int) $mp_id,
			'STUDENT_ID' => (int) $student_id,
		];

		DBUpsert(
			'student_report_card_grades',
			$columns,
			$where_columns,
			( empty( $current_RET[ $student_id ][1] ) ? 'insert' : 'update' )
		);
	}

	return true;
}
