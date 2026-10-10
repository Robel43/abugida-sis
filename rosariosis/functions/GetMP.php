<?php
/**
 * Get Marking Period functions
 *
 * @since 11.1 Allow override GetFullYearMP(), GetAllMP(), GetParentMP(), GetChildrenMP() & GetCurrentMP() functions
 *
 * @package RosarioSIS
 * @package functions
 */

/**
 * Get Marking Period Info
 *
 * Can be called through DBGet()'s functions parameter
 *
 * @global array  $_ROSARIO Sets $_ROSARIO['GetMP']
 *
 * @param  string $mp_id    Marking Period ID.
 * @param  string $column   TITLE|POST_START_DATE|POST_END_DATE|POST_END_DATE|MP|SORT_ORDER|SHORT_NAME|START_DATE|END_DATE|DOES_GRADES|DOES_COMMENTS (optional). Defaults to 'TITLE'.
 *
 * @return string Marking Period Column value
 */
function GetMP( $mp_id, $column = 'TITLE' )
{
	global $_ROSARIO;

	// Mab - need to translate marking_period_id to title to be useful as a function call from dbget
	// also, it doesn't make sense to ask for same thing you give.
	if ( $column === 'MARKING_PERIOD_ID'
		|| ! in_array( $column, [ 'TITLE', 'POST_START_DATE', 'POST_END_DATE', 'POST_END_DATE', 'MP', 'SORT_ORDER', 'SHORT_NAME', 'START_DATE', 'END_DATE', 'DOES_GRADES', 'DOES_COMMENTS' ] ) )
	{
		$column = 'TITLE';
	}

	if ( ! isset( $_ROSARIO['GetMP'] ) )
	{
		$_ROSARIO['GetMP'] = DBGet( "SELECT MARKING_PERIOD_ID,TITLE,POST_START_DATE,
			POST_END_DATE,MP,SORT_ORDER,SHORT_NAME,START_DATE,END_DATE,DOES_GRADES,DOES_COMMENTS
			FROM school_marking_periods
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'", [], [ 'MARKING_PERIOD_ID' ] );
	}

	return empty( $_ROSARIO['GetMP'][ $mp_id ][1][ $column ] ) ?
		'' :
		$_ROSARIO['GetMP'][ $mp_id ][1][ $column ];
}

if ( ! function_exists( 'GetFullYearMP' ) ) :
/**
 * Get Full Year MP ID
 *
 * @since 4.5
 *
 * @return null or FY MP ID.
 */
function GetFullYearMP()
{
	return DBGetOne( "SELECT MARKING_PERIOD_ID
		FROM school_marking_periods
		WHERE MP='FY'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER" );
}
endif;


if ( ! function_exists( 'GetAllMP' ) ) :
/**
 * Get all academic periods relevant to the requested context.
 *
 * Abugida uses a semester-only high-school calendar. Legacy QTR / PRO calls
 * are treated as requests for the containing semester so older RosarioSIS
 * gradebook code continues to work without Quarter records.
 *
 * @param string $mp                SEM|FY (legacy QTR|PRO accepted).
 * @param string $marking_period_id Marking Period ID. Defaults to Full Year.
 * @return string SQL-ready comma-separated quoted IDs.
 */
function GetAllMP( $mp, $marking_period_id = '0' )
{
	static $all_mp = [];

	$fy = GetFullYearMP();

	if ( $marking_period_id < 1 )
	{
		$marking_period_id = $fy;
		$mp = 'FY';
	}

	$current_type = GetMP( $marking_period_id, 'MP' );

	// Legacy Quarter / Progress calls resolve to their containing Semester.
	if ( in_array( $current_type, [ 'QTR', 'PRO' ], true ) )
	{
		$semester_id = $current_type === 'QTR' ?
			GetParentMP( 'SEM', $marking_period_id ) :
			GetParentMP( 'SEM', GetParentMP( 'QTR', $marking_period_id ) );

		if ( $semester_id )
		{
			$marking_period_id = $semester_id;
			$current_type = 'SEM';
		}
	}

	if ( in_array( $mp, [ 'QTR', 'PRO' ], true ) )
	{
		$mp = 'SEM';
	}

	$key = $mp . '-' . $marking_period_id;

	if ( isset( $all_mp[ $key ] ) )
	{
		return $all_mp[ $key ];
	}

	if ( $mp === 'SEM' || $current_type === 'SEM' )
	{
		$semester_id = $current_type === 'SEM' ? $marking_period_id :
			DBGetOne( "SELECT MARKING_PERIOD_ID
				FROM school_marking_periods
				WHERE MP='SEM'
				AND PARENT_ID='" . (int) $fy . "'
				AND SCHOOL_ID='" . UserSchool() . "'
				AND SYEAR='" . UserSyear() . "'
				ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE
				LIMIT 1" );

		$all_mp[ $key ] = "'" . (int) $fy . "','" . (int) $semester_id . "'";

		return $all_mp[ $key ];
	}

	$semester_rows = DBGet( "SELECT MARKING_PERIOD_ID
		FROM school_marking_periods
		WHERE MP='SEM'
		AND PARENT_ID='" . (int) $fy . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );

	$ids = [ "'" . (int) $fy . "'" ];

	foreach ( (array) $semester_rows as $semester )
	{
		$ids[] = "'" . (int) $semester['MARKING_PERIOD_ID'] . "'";
	}

	$all_mp[ $key ] = implode( ',', $ids );

	return $all_mp[ $key ];
}
endif;

if ( ! function_exists( 'GetParentMP' ) ) :
/**
 * Get Parent Marking Period ID
 *
 * @example GetParentMP( 'SEM', UserMP() );
 *
 * @param string $mp                QTR|SEM|FY Marking Period.
 * @param string $marking_period_id Children Marking Period ID.
 *
 * @return string Parent Marking Period ID
 */
function GetParentMP( $mp, $marking_period_id )
{
	static $parent_mp = null;

	if ( is_null( $parent_mp )
		|| ! isset( $parent_mp[ $mp ] ) )
	{
		switch ( $mp )
		{
			case 'QTR':

				$parent_SQL = "SELECT MARKING_PERIOD_ID,PARENT_ID
					FROM school_marking_periods
					WHERE MP='PRO'
					AND SYEAR='" . UserSyear() . "'
					AND SCHOOL_ID='" . UserSchool() . "'";

			break;

			case 'SEM':

				$parent_SQL = "SELECT MARKING_PERIOD_ID,PARENT_ID
					FROM school_marking_periods
					WHERE MP='QTR'
					AND SYEAR='" . UserSyear() . "'
					AND SCHOOL_ID='" . UserSchool() . "'";

			break;

			case 'FY':

				$parent_SQL = "SELECT MARKING_PERIOD_ID,PARENT_ID
					FROM school_marking_periods
					WHERE MP='SEM'
					AND SYEAR='" . UserSyear() . "'
					AND SCHOOL_ID='" . UserSchool() . "'";

			break;

			default:

				return false;
		}

		$parent_mp[ $mp ] = DBGet( $parent_SQL, [], [ 'MARKING_PERIOD_ID' ] );
	}

	return empty( $parent_mp[ $mp ][ $marking_period_id ] ) ?
		'' :
		$parent_mp[ $mp ][ $marking_period_id ][1]['PARENT_ID'];
}
endif;

if ( ! function_exists( 'GetChildrenMP' ) ) :
/**
 * Get child periods for the semester-only Abugida calendar.
 *
 * FY -> Semesters.
 * SEM -> the Semester itself (legacy code previously expected Quarter children).
 * QTR / PRO are compatibility aliases only and never require Quarter records.
 */
function GetChildrenMP( $mp, $marking_period_id = '0' )
{
	$fy = GetFullYearMP();

	if ( $mp === 'FY' )
	{
		$rows = DBGet( "SELECT MARKING_PERIOD_ID
			FROM school_marking_periods
			WHERE MP='SEM'
			AND PARENT_ID='" . (int) $fy . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			AND SYEAR='" . UserSyear() . "'
			ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );

		$ids = [];

		foreach ( (array) $rows as $row )
		{
			$ids[] = "'" . (int) $row['MARKING_PERIOD_ID'] . "'";
		}

		return implode( ',', $ids );
	}

	if ( in_array( $mp, [ 'SEM', 'QTR', 'PRO' ], true ) )
	{
		$type = GetMP( $marking_period_id, 'MP' );

		if ( $type === 'SEM' )
		{
			return "'" . (int) $marking_period_id . "'";
		}

		if ( $type === 'QTR' )
		{
			$semester_id = GetParentMP( 'SEM', $marking_period_id );

			return $semester_id ? "'" . (int) $semester_id . "'" : '';
		}

		if ( $type === 'PRO' )
		{
			$quarter_id = GetParentMP( 'QTR', $marking_period_id );
			$semester_id = $quarter_id ? GetParentMP( 'SEM', $quarter_id ) : 0;

			return $semester_id ? "'" . (int) $semester_id . "'" : '';
		}
	}

	return '';
}
endif;

if ( ! function_exists( 'GetCurrentMP' ) ) :
/**
 * Get Current Marking Period ID
 *
 * Exit with Fatal error if No Marking Period found
 *
 * @example GetCurrentMP( 'QTR', $date, false );
 *
 * @param  string  $mp    PRO|QTR|SEM|FY Marking Period.
 * @param  string  $date  Database Date.
 * @param  boolean $error Fatal error (optional). Defaults to true.
 *
 * @return string Current Marking Period ID
 */
function GetCurrentMP( $mp, $date, $error = true )
{
	static $current_mp = null;

	if ( is_null( $current_mp )
		|| ! isset( $current_mp[ $date ][ $mp ] ) )
	{
		$current_mp[ $date ][ $mp ] = DBGet( "SELECT MARKING_PERIOD_ID
			FROM school_marking_periods
			WHERE MP='" . $mp . "'
			AND '" . $date . "' BETWEEN START_DATE AND END_DATE
			AND SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'" );
	}

	if ( isset( $current_mp[ $date ][ $mp ][1]['MARKING_PERIOD_ID'] ) )
	{
		return $current_mp[ $date ][ $mp ][1]['MARKING_PERIOD_ID'];
	}
	elseif ( $error )
	{
		return ErrorMessage( [ _( 'You are not currently in a marking period' ) ], 'fatal' );
	}
}
endif;
