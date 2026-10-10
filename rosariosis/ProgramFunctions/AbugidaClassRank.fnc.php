<?php
/**
 * Abugida percentage-based semester and full-year class rank.
 *
 * Semester average = arithmetic mean of all official subject percentages
 * recorded for the student in that Semester.
 *
 * Full-year cumulative average = arithmetic mean of Semester 1 and
 * Semester 2 averages. A full-year rank is calculated only when both
 * Semester averages are available.
 *
 * Ranking is within the student's grade level for the school and academic year.
 * Equal averages share the same competition rank (1, 2, 2, 4).
 */

function AbugidaRankLatestEnrollmentGradeSQL( $student_alias = 'g' )
{
	return "(SELECT se2.GRADE_ID
		FROM student_enrollment se2
		WHERE se2.STUDENT_ID=" . $student_alias . ".STUDENT_ID
		AND se2.SCHOOL_ID=" . $student_alias . ".SCHOOL_ID
		AND se2.SYEAR=" . $student_alias . ".SYEAR
		ORDER BY se2.ID DESC
		LIMIT 1)";
}

function AbugidaRankSaveCohort( $rows, $period_type, $marking_period_id )
{
	if ( ! $rows )
	{
		return 0;
	}

	$by_grade = [];

	foreach ( (array) $rows as $row )
	{
		$grade_id = (int) $row['GRADE_ID'];

		if ( ! $grade_id )
		{
			continue;
		}

		if ( ! isset( $by_grade[$grade_id] ) )
		{
			$by_grade[$grade_id] = [];
		}

		$by_grade[$grade_id][] = $row;
	}

	$saved = 0;

	foreach ( $by_grade as $grade_id => $students )
	{
		usort(
			$students,
			function ( $a, $b )
			{
				$a_avg = round( (float) $a['AVERAGE_PERCENT'], 2 );
				$b_avg = round( (float) $b['AVERAGE_PERCENT'], 2 );

				if ( $a_avg === $b_avg )
				{
					return (int) $a['STUDENT_ID'] <=> (int) $b['STUDENT_ID'];
				}

				return $a_avg < $b_avg ? 1 : -1;
			}
		);

		$cohort_size = count( $students );
		$last_average = null;
		$current_rank = 0;

		foreach ( $students as $index => $student )
		{
			$average = round( (float) $student['AVERAGE_PERCENT'], 2 );

			if ( $last_average === null || $average !== $last_average )
			{
				$current_rank = $index + 1;
				$last_average = $average;
			}

			$where = [
				'SCHOOL_ID' => UserSchool(),
				'SYEAR' => UserSyear(),
				'STUDENT_ID' => (int) $student['STUDENT_ID'],
				'PERIOD_TYPE' => $period_type,
				'MARKING_PERIOD_ID' => (int) $marking_period_id,
			];

			$values = [
				'GRADE_ID' => (int) $grade_id,
				'AVERAGE_PERCENT' => number_format( $average, 2, '.', '' ),
				'SUBJECT_COUNT' => (int) $student['SUBJECT_COUNT'],
				'RANK_POSITION' => $current_rank,
				'COHORT_SIZE' => $cohort_size,
				'CALCULATED_AT' => date( 'Y-m-d H:i:s' ),
			];

			$existing_id = DBGetOne( "SELECT id
				FROM abugida_student_academic_rank
				WHERE school_id='" . UserSchool() . "'
				AND syear='" . UserSyear() . "'
				AND student_id='" . (int) $student['STUDENT_ID'] . "'
				AND period_type='" . DBEscapeString( $period_type ) . "'
				AND marking_period_id='" . (int) $marking_period_id . "'
				LIMIT 1" );

			if ( $existing_id )
			{
				DBUpdate( 'abugida_student_academic_rank', $values, [ 'ID' => (int) $existing_id ] );
			}
			else
			{
				DBInsert( 'abugida_student_academic_rank', $where + $values );
			}

			$saved++;
		}
	}

	return $saved;
}

function AbugidaRankRecalculateSemester( $semester_id )
{
	if ( ! $semester_id || GetMP( $semester_id, 'MP' ) !== 'SEM' )
	{
		return false;
	}

	DBQuery( "DELETE FROM abugida_student_academic_rank
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		AND PERIOD_TYPE='SEM'
		AND MARKING_PERIOD_ID='" . (int) $semester_id . "'" );

	$grade_sql = AbugidaRankLatestEnrollmentGradeSQL( 'g' );

	$rows = DBGet( "SELECT g.STUDENT_ID,
		" . $grade_sql . " AS GRADE_ID,
		ROUND(AVG(g.GRADE_PERCENT),2) AS AVERAGE_PERCENT,
		COUNT(DISTINCT g.COURSE_PERIOD_ID) AS SUBJECT_COUNT
		FROM student_report_card_grades g
		WHERE g.SCHOOL_ID='" . UserSchool() . "'
		AND g.SYEAR='" . UserSyear() . "'
		AND g.MARKING_PERIOD_ID='" . (int) $semester_id . "'
		AND g.GRADE_PERCENT IS NOT NULL
		GROUP BY g.STUDENT_ID
		HAVING GRADE_ID IS NOT NULL" );

	AbugidaRankSaveCohort( $rows, 'SEM', $semester_id );

	$fy_id = GetParentMP( 'FY', $semester_id );

	if ( $fy_id )
	{
		AbugidaRankRecalculateFullYear( $fy_id );
	}

	return true;
}

function AbugidaRankRecalculateFullYear( $full_year_id )
{
	if ( ! $full_year_id || GetMP( $full_year_id, 'MP' ) !== 'FY' )
	{
		return false;
	}

	$semesters = DBGet( "SELECT MARKING_PERIOD_ID
		FROM school_marking_periods
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		AND MP='SEM'
		AND DOES_GRADES='Y'
		AND PARENT_ID='" . (int) $full_year_id . "'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );

	if ( count( (array) $semesters ) < 2 )
	{
		return false;
	}

	$semester_ids = [];

	foreach ( (array) $semesters as $semester )
	{
		$semester_ids[] = (int) $semester['MARKING_PERIOD_ID'];
	}

	DBQuery( "DELETE FROM abugida_student_academic_rank
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		AND PERIOD_TYPE='FY'
		AND MARKING_PERIOD_ID='" . (int) $full_year_id . "'" );

	$rows = DBGet( "SELECT student_id AS STUDENT_ID,
		grade_id AS GRADE_ID,
		ROUND(AVG(average_percent),2) AS AVERAGE_PERCENT,
		SUM(subject_count) AS SUBJECT_COUNT,
		COUNT(*) AS SEMESTER_COUNT
		FROM abugida_student_academic_rank
		WHERE school_id='" . UserSchool() . "'
		AND syear='" . UserSyear() . "'
		AND period_type='SEM'
		AND marking_period_id IN(" . implode( ',', $semester_ids ) . ")
		GROUP BY student_id,grade_id
		HAVING SEMESTER_COUNT=" . count( $semester_ids ) );

	AbugidaRankSaveCohort( $rows, 'FY', $full_year_id );

	return true;
}

function AbugidaRankGetStudent( $student_id, $period_type, $marking_period_id )
{
	return DBGet( "SELECT average_percent AS AVERAGE_PERCENT,
		rank_position AS RANK_POSITION,
		cohort_size AS COHORT_SIZE,
		subject_count AS SUBJECT_COUNT
		FROM abugida_student_academic_rank
		WHERE school_id='" . UserSchool() . "'
		AND syear='" . UserSyear() . "'
		AND student_id='" . (int) $student_id . "'
		AND period_type='" . DBEscapeString( $period_type ) . "'
		AND marking_period_id='" . (int) $marking_period_id . "'
		LIMIT 1" );
}
