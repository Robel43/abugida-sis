<?php
/**
 * Abugida SIS - Percentage Class Rank.
 *
 * Semester rank is based on the arithmetic mean of all official subject
 * percentages in the selected Semester.
 *
 * Full-year rank is based on the arithmetic mean of Semester 1 and Semester 2
 * averages. Ranking is within the selected grade level.
 */

require_once 'ProgramFunctions/AbugidaClassRank.fnc.php';

DrawHeader( ProgramTitle() );

if ( User( 'PROFILE' ) !== 'admin' )
{
	exit;
}

$_REQUEST['grade_id'] = (int) issetVal( $_REQUEST['grade_id'], 0 );
$_REQUEST['period_key'] = issetVal( $_REQUEST['period_key'], '' );

$grades = DBGet( "SELECT ID,TITLE,SORT_ORDER
	FROM school_gradelevels
	WHERE SCHOOL_ID='" . UserSchool() . "'
	ORDER BY SORT_ORDER IS NULL,SORT_ORDER,ID" );

$full_year_id = GetFullYearMP();

$semesters = DBGet( "SELECT MARKING_PERIOD_ID,TITLE,SORT_ORDER
	FROM school_marking_periods
	WHERE SCHOOL_ID='" . UserSchool() . "'
	AND SYEAR='" . UserSyear() . "'
	AND MP='SEM'
	AND DOES_GRADES='Y'
	ORDER BY SORT_ORDER IS NULL,SORT_ORDER,START_DATE" );

$periods = [];

foreach ( (array) $semesters as $semester )
{
	$periods['SEM:' . $semester['MARKING_PERIOD_ID']] = $semester['TITLE'];
}

if ( $full_year_id )
{
	$periods['FY:' . $full_year_id] = _( 'Full Year' );
}

if ( isset( $_POST['rank_action'] )
	&& $_POST['rank_action'] === 'recalculate'
	&& AllowEdit() )
{
	list( $period_type, $period_id ) = array_pad( explode( ':', $_REQUEST['period_key'], 2 ), 2, 0 );
	$period_id = (int) $period_id;

	if ( $period_type === 'SEM' )
	{
		AbugidaRankRecalculateSemester( $period_id );
	}
	elseif ( $period_type === 'FY' )
	{
		AbugidaRankRecalculateFullYear( $period_id );
	}
}

echo '<style>
	.abg-rank-wrap{max-width:1100px}
	.abg-rank-card{background:#fff;border:1px solid #d9e1ea;border-radius:12px;padding:22px;margin:16px 0}
	.abg-rank-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
	.abg-rank-field label{display:block;font-weight:700;margin-bottom:7px}
	.abg-rank-field select{width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;background:#fff}
	.abg-rank-actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
	.abg-rank-btn{border:0;border-radius:8px;background:#1677c8;color:#fff;padding:10px 16px;font-weight:700;cursor:pointer}
	.abg-rank-table{width:100%;border-collapse:collapse;margin-top:16px}
	.abg-rank-table th,.abg-rank-table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
	.abg-rank-table th{background:#f8fafc}
	.abg-rank-help{color:#667085;line-height:1.5}
	@media(max-width:760px){.abg-rank-grid{grid-template-columns:1fr}}
</style>';

echo '<div class="abg-rank-wrap"><div class="abg-rank-card">';
echo '<h3>' . _( 'Class Rank' ) . '</h3>';
echo '<p class="abg-rank-help">' .
	_( 'Semester rank is based on the average of all official subject percentages. Full Year rank is based on the average of Semester 1 and Semester 2 averages. Students are ranked only against students in the same grade level.' ) .
	'</p>';

echo '<form method="POST">';
echo '<div class="abg-rank-grid">';

echo '<div class="abg-rank-field"><label>' . _( 'Grade' ) . '</label>';
echo '<select name="grade_id"><option value="">' . _( 'Select Grade' ) . '</option>';
foreach ( (array) $grades as $grade )
{
	$selected = $_REQUEST['grade_id'] === (int) $grade['ID'] ? ' selected' : '';
	echo '<option value="' . (int) $grade['ID'] . '"' . $selected . '>' . AttrEscape( $grade['TITLE'] ) . '</option>';
}
echo '</select></div>';

echo '<div class="abg-rank-field"><label>' . _( 'Period' ) . '</label>';
echo '<select name="period_key"><option value="">' . _( 'Select Semester / Full Year' ) . '</option>';
foreach ( $periods as $key => $title )
{
	$selected = $_REQUEST['period_key'] === $key ? ' selected' : '';
	echo '<option value="' . AttrEscape( $key ) . '"' . $selected . '>' . AttrEscape( $title ) . '</option>';
}
echo '</select></div>';

echo '</div>';
echo '<div class="abg-rank-actions">';
echo '<button class="abg-rank-btn" type="submit" name="rank_action" value="view">' . _( 'View Rank' ) . '</button>';
if ( AllowEdit() )
{
	echo '<button class="abg-rank-btn" type="submit" name="rank_action" value="recalculate">' . _( 'Recalculate' ) . '</button>';
}
echo '</div></form>';
echo '</div>';

if ( $_REQUEST['grade_id'] && $_REQUEST['period_key'] )
{
	list( $period_type, $period_id ) = array_pad( explode( ':', $_REQUEST['period_key'], 2 ), 2, 0 );
	$period_id = (int) $period_id;

	$rows = DBGet( "SELECT r.rank_position AS RANK_POSITION,
		r.average_percent AS AVERAGE_PERCENT,
		r.subject_count AS SUBJECT_COUNT,
		r.cohort_size AS COHORT_SIZE,
		r.student_id AS STUDENT_ID,
		CONCAT(s.FIRST_NAME,' ',s.LAST_NAME) AS STUDENT_NAME
		FROM abugida_student_academic_rank r
		JOIN students s ON s.STUDENT_ID=r.student_id
		WHERE r.school_id='" . UserSchool() . "'
		AND r.syear='" . UserSyear() . "'
		AND r.grade_id='" . (int) $_REQUEST['grade_id'] . "'
		AND r.period_type='" . DBEscapeString( $period_type ) . "'
		AND r.marking_period_id='" . $period_id . "'
		ORDER BY r.rank_position,r.average_percent DESC,r.student_id" );

	echo '<div class="abg-rank-card">';
	echo '<h3>' . AttrEscape( isset( $periods[$_REQUEST['period_key']] ) ? $periods[$_REQUEST['period_key']] : _( 'Class Rank' ) ) . '</h3>';

	if ( empty( $rows ) )
	{
		echo '<p class="abg-rank-help">' . _( 'No calculated rank is available yet. Import or save semester grades, or use Recalculate.' ) . '</p>';
	}
	else
	{
		echo '<div style="overflow:auto"><table class="abg-rank-table"><thead><tr>';
		echo '<th>' . _( 'Rank' ) . '</th>';
		echo '<th>' . _( 'Student' ) . '</th>';
		echo '<th>' . _( 'Student ID' ) . '</th>';
		echo '<th>' . _( 'Average' ) . '</th>';
		echo '<th>' . ( $period_type === 'SEM' ? _( 'Subjects Counted' ) : _( 'Semester Subject Records' ) ) . '</th>';
		echo '<th>' . _( 'Class Size' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( (array) $rows as $row )
		{
			echo '<tr>';
			echo '<td><b>' . (int) $row['RANK_POSITION'] . '</b></td>';
			echo '<td>' . AttrEscape( $row['STUDENT_NAME'] ) . '</td>';
			echo '<td>' . (int) $row['STUDENT_ID'] . '</td>';
			echo '<td>' . number_format( (float) $row['AVERAGE_PERCENT'], 2 ) . '%</td>';
			echo '<td>' . (int) $row['SUBJECT_COUNT'] . '</td>';
			echo '<td>' . (int) $row['COHORT_SIZE'] . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	echo '</div>';
}

echo '</div>';
