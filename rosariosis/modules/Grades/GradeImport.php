<?php
/**
 * Abugida SIS - Excel Grade Import.
 *
 * Imports official final percentage grades from an XLSX worksheet after
 * validating the selected grade, course period, marking period, and roster.
 */

require_once 'modules/Grades/includes/Grades.fnc.php';
require_once 'modules/Grades/includes/ClassRank.inc.php';
require_once 'ProgramFunctions/_makeLetterGrade.fnc.php';
require_once 'ProgramFunctions/AbugidaXlsx.fnc.php';

DrawHeader( ProgramTitle() );

if ( User( 'PROFILE' ) !== 'admin' )
{
	exit;
}

$error = [];
$note = [];

$_REQUEST['grade_id'] = (int) issetVal( $_REQUEST['grade_id'], 0 );
$_REQUEST['course_period_id'] = (int) issetVal( $_REQUEST['course_period_id'], 0 );
$_REQUEST['mp_id'] = (int) issetVal( $_REQUEST['mp_id'], 0 );

function AbugidaGradeImportStudentId( $value )
{
	$value = strtoupper( trim( (string) $value ) );

	if ( preg_match( '/^ABG([0-9]+)$/', $value, $matches ) )
	{
		return (int) $matches[1];
	}

	return ctype_digit( $value ) ? (int) $value : 0;
}

function AbugidaGradeImportNormalizeHeader( $value )
{
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9]+/', ' ', $value );

	return trim( $value );
}


function AbugidaGradeImportGradeNumber( $grade_id )
{
	$grade_title = DBGetOne( "SELECT TITLE
		FROM school_gradelevels
		WHERE ID='" . (int) $grade_id . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		LIMIT 1" );

	if ( preg_match( '/([0-9]+)/', (string) $grade_title, $matches ) )
	{
		return (int) $matches[1];
	}

	return 0;
}

function AbugidaGradeImportSubjectIdsForGrade( $grade_id )
{
	$grade_number = AbugidaGradeImportGradeNumber( $grade_id );

	if ( ! $grade_number )
	{
		return [];
	}

	$subjects = DBGet( "SELECT SUBJECT_ID,TITLE
		FROM course_subjects
		WHERE SCHOOL_ID='" . UserSchool() . "'
		AND SYEAR='" . UserSyear() . "'
		ORDER BY SORT_ORDER IS NULL,SORT_ORDER,TITLE" );

	$subject_ids = [];

	foreach ( (array) $subjects as $subject )
	{
		if ( preg_match( '/(^|[^0-9])' . $grade_number . '([^0-9]|$)/', (string) $subject['TITLE'] ) )
		{
			$subject_ids[] = (int) $subject['SUBJECT_ID'];
		}
	}

	return $subject_ids;
}

function AbugidaGradeImportName( $student_id )
{
	return DBGetOne( "SELECT CONCAT(FIRST_NAME,' ',LAST_NAME)
		FROM students
		WHERE STUDENT_ID='" . (int) $student_id . "'
		LIMIT 1" );
}

function AbugidaGradeImportRecord( $student_id, $course_period_id, $mp_id, $score )
{
	$course_RET = DBGet( "SELECT cp.COURSE_ID,c.TITLE AS COURSE_NAME,cp.GRADE_SCALE_ID,
		credit('" . (int) $course_period_id . "','" . (int) $mp_id . "') AS CREDITS,
		cp.DOES_CLASS_RANK AS CLASS_RANK,c.CREDIT_HOURS
		FROM course_periods cp,courses c
		WHERE cp.COURSE_ID=c.COURSE_ID
		AND cp.COURSE_PERIOD_ID='" . (int) $course_period_id . "'
		LIMIT 1" );

	if ( empty( $course_RET[1] ) )
	{
		return false;
	}

	$course = $course_RET[1];
	$grade_id = _makeLetterGrade( $score / 100, $course_period_id, 0, 'ID' );

	if ( ! $grade_id )
	{
		return false;
	}

	$grade_RET = DBGet( "SELECT rcg.ID,rcg.TITLE,rcg.GPA_VALUE AS WEIGHTED_GP,
		rcg.UNWEIGHTED_GP,gs.GP_SCALE,gs.GP_PASSING_VALUE
		FROM report_card_grades rcg,report_card_grade_scales gs
		WHERE rcg.GRADE_SCALE_ID=gs.ID
		AND rcg.ID='" . (int) $grade_id . "'
		LIMIT 1" );

	if ( empty( $grade_RET[1] ) )
	{
		return false;
	}

	$grade = $grade_RET[1];

	return [
		'SYEAR' => UserSyear(),
		'SCHOOL_ID' => UserSchool(),
		'STUDENT_ID' => (int) $student_id,
		'COURSE_PERIOD_ID' => (int) $course_period_id,
		'MARKING_PERIOD_ID' => (int) $mp_id,
		'REPORT_CARD_GRADE_ID' => (int) $grade_id,
		'GRADE_PERCENT' => number_format( (float) $score, 2, '.', '' ),
		'COMMENT' => '',
		'GRADE_LETTER' => DBEscapeString( $grade['TITLE'] ),
		'WEIGHTED_GP' => $grade['WEIGHTED_GP'],
		'UNWEIGHTED_GP' => $grade['UNWEIGHTED_GP'],
		'GP_SCALE' => $grade['GP_SCALE'],
		'COURSE_TITLE' => DBEscapeString( $course['COURSE_NAME'] ),
		'CREDIT_ATTEMPTED' => $course['CREDITS'],
		'CREDIT_EARNED' => ( (float) $grade['WEIGHTED_GP'] && $grade['WEIGHTED_GP'] >= $grade['GP_PASSING_VALUE'] ? $course['CREDITS'] : '0' ),
		'CLASS_RANK' => $course['CLASS_RANK'],
		'CREDIT_HOURS' => $course['CREDIT_HOURS'],
	];
}

$grades = DBGet( "SELECT ID,TITLE,SHORT_NAME,SORT_ORDER
	FROM school_gradelevels
	WHERE SCHOOL_ID='" . UserSchool() . "'
	ORDER BY SORT_ORDER,ID" );

$marking_periods = DBGet( "SELECT MARKING_PERIOD_ID,TITLE,MP,START_DATE,END_DATE
	FROM school_marking_periods
	WHERE SCHOOL_ID='" . UserSchool() . "'
	AND SYEAR='" . UserSyear() . "'
	AND MP='SEM'
	ORDER BY SORT_ORDER,START_DATE,MARKING_PERIOD_ID" );

$course_periods = [];

if ( $_REQUEST['grade_id'] )
{
	$subject_ids = AbugidaGradeImportSubjectIdsForGrade( $_REQUEST['grade_id'] );

	if ( $subject_ids )
	{
		$subject_id_list = implode( ',', array_map( 'intval', $subject_ids ) );

		$course_periods = DBGet( "SELECT DISTINCT cp.COURSE_PERIOD_ID,
			CONCAT(c.TITLE,' - ',cp.TITLE) AS TITLE
			FROM course_periods cp
			JOIN courses c ON c.COURSE_ID=cp.COURSE_ID
			WHERE cp.SCHOOL_ID='" . UserSchool() . "'
			AND cp.SYEAR='" . UserSyear() . "'
			AND c.SUBJECT_ID IN(" . $subject_id_list . ")
			ORDER BY c.TITLE,cp.TITLE" );
	}
}

if ( isset( $_POST['grade_import_action'] )
	&& $_POST['grade_import_action'] === 'preview'
	&& AllowEdit() )
{
	if ( ! $_REQUEST['grade_id'] || ! $_REQUEST['course_period_id'] || ! $_REQUEST['mp_id'] )
	{
		$error[] = _( 'Select the grade, subject / section, and marking period before uploading the Excel file.' );
	}
	elseif ( empty( $_FILES['grade_file']['tmp_name'] ) )
	{
		$error[] = _( 'Choose an Excel .xlsx file.' );
	}
	else
	{
		$file = $_FILES['grade_file'];
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( $extension !== 'xlsx' )
		{
			$error[] = _( 'Only .xlsx Excel files are supported in this version.' );
		}
		elseif ( $file['size'] > 5 * 1024 * 1024 )
		{
			$error[] = _( 'The Excel file must be 5 MB or smaller.' );
		}
		else
		{
			$xlsx_error = '';
			$rows = AbugidaReadXlsx( $file['tmp_name'], $xlsx_error );

			if ( $rows === false )
			{
				$error[] = $xlsx_error;
			}
			elseif ( count( $rows ) < 2 )
			{
				$error[] = _( 'The Excel file does not contain any grade rows.' );
			}
			else
			{
				$headers = array_map( 'AbugidaGradeImportNormalizeHeader', $rows[0] );
				$aliases = [
					'student_id' => [ 'student id', 'studentid', 'id' ],
					'student_name' => [ 'student name', 'name', 'full name' ],
					'final_score' => [ 'final score', 'score', 'final grade', 'grade percent', 'percent' ],
				];
				$indexes = [];

				foreach ( $aliases as $key => $possible )
				{
					foreach ( $possible as $alias )
					{
						$found = array_search( $alias, $headers, true );

						if ( $found !== false )
						{
							$indexes[$key] = $found;
							break;
						}
					}
				}

				if ( ! isset( $indexes['student_id'], $indexes['student_name'], $indexes['final_score'] ) )
				{
					$error[] = _( 'The first row must contain Student ID, Student Name, and Final Score columns.' );
				}
				else
				{
					$roster_RET = DBGet( "SELECT DISTINCT s.STUDENT_ID
						FROM schedule s
						JOIN student_enrollment se ON se.STUDENT_ID=s.STUDENT_ID
							AND se.SCHOOL_ID=s.SCHOOL_ID
							AND se.SYEAR=s.SYEAR
						WHERE s.SCHOOL_ID='" . UserSchool() . "'
						AND s.SYEAR='" . UserSyear() . "'
						AND s.COURSE_PERIOD_ID='" . (int) $_REQUEST['course_period_id'] . "'
						AND se.GRADE_ID='" . (int) $_REQUEST['grade_id'] . "'", [], [ 'STUDENT_ID' ] );
					$roster = array_keys( $roster_RET );
					$seen = [];
					$preview = [];

					for ( $i = 1; $i < count( $rows ); $i++ )
					{
						$row_number = $i + 1;
						$row = $rows[$i];
						$raw_id = isset( $row[$indexes['student_id']] ) ? $row[$indexes['student_id']] : '';
						$student_id = AbugidaGradeImportStudentId( $raw_id );
						$excel_name = trim( (string) ( isset( $row[$indexes['student_name']] ) ? $row[$indexes['student_name']] : '' ) );
						$score_raw = trim( (string) ( isset( $row[$indexes['final_score']] ) ? $row[$indexes['final_score']] : '' ) );

						if ( $raw_id === '' && $excel_name === '' && $score_raw === '' )
						{
							continue;
						}

						$status = 'VALID';
						$message = '';
						$sis_name = $student_id ? AbugidaGradeImportName( $student_id ) : '';

						if ( ! $student_id )
						{
							$status = 'ERROR';
							$message = 'Invalid Student ID.';
						}
						elseif ( isset( $seen[$student_id] ) )
						{
							$status = 'ERROR';
							$message = 'Duplicate Student ID in Excel file.';
						}
						elseif ( ! in_array( $student_id, $roster ) )
						{
							$status = 'ERROR';
							$message = 'Student is not in the selected grade / course roster.';
						}
						elseif ( $score_raw === '' || ! is_numeric( $score_raw ) )
						{
							$status = 'ERROR';
							$message = 'Final Score must be numeric.';
						}
						elseif ( (float) $score_raw < 0 || (float) $score_raw > 100 )
						{
							$status = 'ERROR';
							$message = 'Final Score must be between 0 and 100.';
						}

						if ( $student_id )
						{
							$seen[$student_id] = true;
						}

						if ( $status === 'VALID'
							&& $excel_name !== ''
							&& $sis_name !== ''
							&& strtolower( preg_replace( '/\s+/', ' ', $excel_name ) ) !== strtolower( preg_replace( '/\s+/', ' ', $sis_name ) ) )
						{
							$message = 'Student name differs from the SIS record; Student ID will be used for matching.';
						}

						$existing = $student_id ? DBGetOne( "SELECT GRADE_PERCENT
							FROM student_report_card_grades
							WHERE STUDENT_ID='" . (int) $student_id . "'
							AND COURSE_PERIOD_ID='" . (int) $_REQUEST['course_period_id'] . "'
							AND MARKING_PERIOD_ID='" . (int) $_REQUEST['mp_id'] . "'
							AND SCHOOL_ID='" . UserSchool() . "'
							AND SYEAR='" . UserSyear() . "'
							LIMIT 1" ) : null;

						if ( $status === 'VALID'
							&& $existing !== null
							&& $existing !== false
							&& $existing !== '' )
						{
							$status = 'EXISTING';
							$message = 'An official grade already exists. Replacement requires explicit confirmation.';
						}

						$preview[] = [
							'row_number' => $row_number,
							'student_id' => $student_id,
							'excel_name' => $excel_name,
							'sis_name' => $sis_name,
							'score' => is_numeric( $score_raw ) ? (float) $score_raw : $score_raw,
							'existing' => $existing,
							'status' => $status,
							'message' => $message,
						];
					}

					$_SESSION['AbugidaGradeImportPreview'] = [
						'grade_id' => $_REQUEST['grade_id'],
						'course_period_id' => $_REQUEST['course_period_id'],
						'mp_id' => $_REQUEST['mp_id'],
						'filename' => basename( $file['name'] ),
						'rows' => $preview,
					];
				}
			}
		}
	}
}

if ( isset( $_POST['grade_import_action'] )
	&& $_POST['grade_import_action'] === 'confirm'
	&& AllowEdit() )
{
	$session_import = issetVal( $_SESSION['AbugidaGradeImportPreview'], [] );

	if ( empty( $session_import['rows'] ) )
	{
		$error[] = _( 'The import preview has expired. Upload the Excel file again.' );
	}
	else
	{
		$replace_existing = ! empty( $_POST['replace_existing'] );
		$valid_rows = [];
		$blocking_errors = 0;

		foreach ( $session_import['rows'] as $row )
		{
			if ( $row['status'] === 'ERROR' )
			{
				$blocking_errors++;
			}
			elseif ( $row['status'] === 'EXISTING' && ! $replace_existing )
			{
				continue;
			}
			else
			{
				$valid_rows[] = $row;
			}
		}

		if ( $blocking_errors )
		{
			$error[] = _( 'Fix all Excel validation errors before importing grades.' );
		}
		elseif ( ! $valid_rows )
		{
			$error[] = _( 'There are no grades eligible for import.' );
		}
		else
		{
			DBQuery( 'START TRANSACTION' );

			DBInsert(
				'abugida_grade_import_batches',
				[
					'SCHOOL_ID' => UserSchool(),
					'SYEAR' => UserSyear(),
					'GRADE_ID' => (int) $session_import['grade_id'],
					'COURSE_PERIOD_ID' => (int) $session_import['course_period_id'],
					'MARKING_PERIOD_ID' => (int) $session_import['mp_id'],
					'SOURCE_FILENAME' => DBEscapeString( $session_import['filename'] ),
					'TOTAL_ROWS' => count( $session_import['rows'] ),
					'IMPORTED_ROWS' => 0,
					'SKIPPED_ROWS' => 0,
					'IMPORTED_BY' => (int) User( 'STAFF_ID' ),
				]
			);

			$batch_id = DBGetOne( 'SELECT LAST_INSERT_ID()' );
			$imported = 0;
			$skipped = 0;
			$failed = false;

			foreach ( $session_import['rows'] as $row )
			{
				$action = 'SKIPPED';
				$message = $row['message'];

				if ( $row['status'] === 'ERROR'
					|| ( $row['status'] === 'EXISTING' && ! $replace_existing ) )
				{
					$skipped++;
				}
				else
				{
					$record = AbugidaGradeImportRecord(
						$row['student_id'],
						$session_import['course_period_id'],
						$session_import['mp_id'],
						(float) $row['score']
					);

					if ( ! $record )
					{
						$failed = true;
						$action = 'ERROR';
						$message = 'Could not resolve the score to the configured grading scale.';
					}
					elseif ( $row['status'] === 'EXISTING' )
					{
						DBUpdate(
							'student_report_card_grades',
							$record,
							[
								'STUDENT_ID' => (int) $row['student_id'],
								'COURSE_PERIOD_ID' => (int) $session_import['course_period_id'],
								'MARKING_PERIOD_ID' => (int) $session_import['mp_id'],
								'SYEAR' => UserSyear(),
								'SCHOOL_ID' => UserSchool(),
							]
						);
						$action = 'REPLACED';
						$message = 'Existing official grade replaced by confirmed Excel import.';
						$imported++;
					}
					else
					{
						DBInsert( 'student_report_card_grades', $record );
						$action = 'IMPORTED';
						$message = 'Official grade imported.';
						$imported++;
					}
				}

				DBInsert(
					'abugida_grade_import_rows',
					[
						'BATCH_ID' => (int) $batch_id,
						'EXCEL_ROW_NUMBER' => (int) $row['row_number'],
						'STUDENT_ID' => $row['student_id'] ? (int) $row['student_id'] : null,
						'STUDENT_NAME' => DBEscapeString( $row['sis_name'] ? $row['sis_name'] : $row['excel_name'] ),
						'OLD_PERCENT' => is_numeric( $row['existing'] ) ? $row['existing'] : null,
						'NEW_PERCENT' => is_numeric( $row['score'] ) ? $row['score'] : null,
						'ACTION' => $action,
						'MESSAGE' => DBEscapeString( $message ),
					]
				);
			}

			if ( $failed )
			{
				DBQuery( 'ROLLBACK' );
				$error[] = _( 'The import was cancelled because one or more scores could not be mapped to the configured grading scale.' );
			}
			else
			{
				DBUpdate(
					'abugida_grade_import_batches',
					[
						'IMPORTED_ROWS' => $imported,
						'SKIPPED_ROWS' => $skipped,
					],
					[ 'ID' => (int) $batch_id ]
				);

				DBQuery( 'COMMIT' );
				ClassRankCalculateAddMP( (int) $session_import['mp_id'] );
				unset( $_SESSION['AbugidaGradeImportPreview'] );
				$note[] = sprintf( _( '%d grades imported successfully. %d rows skipped.' ), $imported, $skipped );
			}
		}
	}
}

echo ErrorMessage( $error );
echo ErrorMessage( $note, 'note' );

$preview_data = issetVal( $_SESSION['AbugidaGradeImportPreview'], [] );

echo '<style>
	.abg-import-wrap{max-width:1180px}
	.abg-import-card{background:#fff;border:1px solid #d9e1ea;border-radius:12px;padding:24px;margin:16px 0;box-shadow:0 1px 2px rgba(16,24,40,.04)}
	.abg-import-card h3{margin:0 0 14px;font-size:24px;line-height:1.25;color:#101828}
	.abg-import-help{margin:0 0 22px;color:#667085;line-height:1.55;font-size:15px}
	.abg-import-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 24px;align-items:end}
	.abg-import-field{min-width:0}
	.abg-import-field label{display:block;font-weight:700;margin:0 0 7px;color:#101828;font-size:15px;line-height:1.35}
	.abg-field-help{margin-top:6px;color:#667085;font-size:13px;line-height:1.4}
	.abg-import-field select{display:block;width:100%;height:46px;min-height:46px;padding:0 42px 0 13px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#101828;font:inherit;font-size:15px;line-height:46px;box-sizing:border-box;vertical-align:middle}
	.abg-import-field select:focus,.abg-file-control:focus-within{outline:0;border-color:#1677c8;box-shadow:0 0 0 3px rgba(22,119,200,.12)}
	.abg-file-input{display:block;width:100%;min-height:46px;padding:7px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#344054;font:inherit;font-size:14px;line-height:normal;box-sizing:border-box;overflow:visible;white-space:nowrap}
	.abg-file-input:focus{outline:0;border-color:#1677c8;box-shadow:0 0 0 3px rgba(22,119,200,.12)}
	.abg-file-input::file-selector-button{min-width:160px;height:30px;margin:0 12px 0 0;padding:0 14px;border:1px solid #d0d5dd;border-radius:6px;background:#f8fafc;color:#344054;font-weight:700;cursor:pointer;line-height:28px}
	.abg-template-box{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;margin:0 0 20px;border:1px solid #dbe7f3;border-radius:10px;background:#f8fbff}
	.abg-template-copy{min-width:0}.abg-template-copy b{display:block;margin-bottom:3px;color:#101828}.abg-template-copy span{color:#667085;font-size:14px;line-height:1.4}
	.abg-template-link{display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:9px 14px;border-radius:7px;background:#eef6ff;color:#1269b1;font-weight:700;text-decoration:none;border:1px solid #cfe3f8}
	.abg-import-actions{display:flex;gap:10px;flex-wrap:wrap;margin:22px 0 0}
	.abg-import-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 17px;border:0;border-radius:8px;background:#1677c8;color:#fff;font-weight:700;line-height:1.2;cursor:pointer;text-decoration:none}
	.abg-import-btn:hover{background:#1269b1}
	.abg-import-table{width:100%;border-collapse:collapse;margin-top:12px}
	.abg-import-table th,.abg-import-table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
	.abg-import-table th{background:#f8fafc}
	.abg-status-valid{color:#067647;font-weight:700}.abg-status-existing{color:#b54708;font-weight:700}.abg-status-error{color:#b42318;font-weight:700}
	@media(max-width:760px){.abg-import-card{padding:18px}.abg-import-grid{grid-template-columns:1fr}.abg-import-actions{flex-direction:column}.abg-import-btn{width:100%}.abg-template-box{align-items:flex-start;flex-direction:column}.abg-template-link{width:100%;box-sizing:border-box}}
</style>';

echo '<div class="abg-import-wrap">';
echo '<div class="abg-import-card">';
echo '<h3>' . _( 'Excel Grade Import' ) . '</h3>';
echo '<p class="abg-import-help">' .
	_( 'Select the grade, subject / section and marking period, then upload an Excel .xlsx file. The first worksheet must contain Student ID, Student Name and Final Score columns. Grades are validated before anything is saved.' ) .
	'</p>';

echo '<div class="abg-template-box">';
echo '<div class="abg-template-copy"><b>' . _( 'Need the correct Excel format?' ) . '</b><span>' .
	_( 'Download the official template, enter the students and final scores, then upload the completed file below.' ) .
	'</span></div>';
echo '<a class="abg-template-link" href="' . URLEscape( 'grade-import-template.php' ) . '">' . _( 'Download Excel Template' ) . '</a>';
echo '</div>';

echo '<form method="POST" enctype="multipart/form-data">';
echo '<div class="abg-import-grid">';

echo '<div class="abg-import-field"><label>' . _( 'Grade' ) . '</label>';
echo '<select name="grade_id"><option value="">' . _( 'Select Grade' ) . '</option>';
foreach ( (array) $grades as $grade )
{
	$selected = $_REQUEST['grade_id'] == $grade['ID'] ? ' selected' : '';
	echo '<option value="' . (int) $grade['ID'] . '"' . $selected . '>' . AttrEscape( $grade['TITLE'] ) . '</option>';
}
echo '</select></div>';

echo '<div class="abg-import-field"><label>' . _( 'Subject / Section' ) . '</label>';
echo '<select name="course_period_id"><option value="">' .
	( $_REQUEST['grade_id'] && empty( $course_periods ) ? _( 'No Subject / Section Found' ) : _( 'Select Subject / Section' ) ) .
	'</option>';
foreach ( (array) $course_periods as $cp )
{
	$selected = $_REQUEST['course_period_id'] == $cp['COURSE_PERIOD_ID'] ? ' selected' : '';
	echo '<option value="' . (int) $cp['COURSE_PERIOD_ID'] . '"' . $selected . '>' . AttrEscape( $cp['TITLE'] ) . '</option>';
}
echo '</select>';
if ( $_REQUEST['grade_id'] && empty( $course_periods ) )
{
	echo '<div class="abg-field-help">' . _( 'No course periods were found under the course subject for the selected grade.' ) . '</div>';
}
echo '</div>';

echo '<div class="abg-import-field"><label>' . _( 'Semester / Marking Period' ) . '</label>';
echo '<select name="mp_id"><option value="">' . _( 'Select Marking Period' ) . '</option>';
foreach ( (array) $marking_periods as $mp )
{
	$selected = $_REQUEST['mp_id'] == $mp['MARKING_PERIOD_ID'] ? ' selected' : '';
	echo '<option value="' . (int) $mp['MARKING_PERIOD_ID'] . '"' . $selected . '>' . AttrEscape( $mp['TITLE'] ) . '</option>';
}
echo '</select></div>';

echo '<div class="abg-import-field"><label>' . _( 'Excel File (.xlsx)' ) . '</label>';
echo '<input class="abg-file-input" type="file" name="grade_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"></div>';
echo '</div>';
echo '<div class="abg-import-actions">';
echo '<button class="abg-import-btn" type="submit" name="grade_import_action" value="load_context">' . _( 'Load Subjects / Sections' ) . '</button>';
echo '<button class="abg-import-btn" type="submit" name="grade_import_action" value="preview">' . _( 'Validate & Preview' ) . '</button>';
echo '</div>';
echo '</form>';
echo '</div>';

if ( ! empty( $preview_data['rows'] ) )
{
	$error_count = 0;
	$existing_count = 0;

	foreach ( $preview_data['rows'] as $row )
	{
		if ( $row['status'] === 'ERROR' ) $error_count++;
		if ( $row['status'] === 'EXISTING' ) $existing_count++;
	}

	echo '<div class="abg-import-card">';
	echo '<h3>' . _( 'Import Preview' ) . '</h3>';
	echo '<p><b>' . AttrEscape( $preview_data['filename'] ) . '</b> &mdash; ' .
		count( $preview_data['rows'] ) . ' ' . _( 'rows' ) . ', ' .
		$error_count . ' ' . _( 'errors' ) . ', ' .
		$existing_count . ' ' . _( 'existing grades' ) . '</p>';

	echo '<div style="overflow:auto"><table class="abg-import-table"><thead><tr>';
	echo '<th>' . _( 'Excel Row' ) . '</th><th>' . _( 'Student ID' ) . '</th><th>' . _( 'Excel Name' ) . '</th><th>' . _( 'SIS Name' ) . '</th><th>' . _( 'Final Score' ) . '</th><th>' . _( 'Current Grade' ) . '</th><th>' . _( 'Status' ) . '</th><th>' . _( 'Message' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $preview_data['rows'] as $row )
	{
		$class = $row['status'] === 'VALID' ? 'abg-status-valid' : ( $row['status'] === 'EXISTING' ? 'abg-status-existing' : 'abg-status-error' );
		echo '<tr>';
		echo '<td>' . (int) $row['row_number'] . '</td>';
		echo '<td>' . (int) $row['student_id'] . '</td>';
		echo '<td>' . AttrEscape( $row['excel_name'] ) . '</td>';
		echo '<td>' . AttrEscape( $row['sis_name'] ) . '</td>';
		echo '<td>' . AttrEscape( $row['score'] ) . '</td>';
		echo '<td>' . AttrEscape( $row['existing'] ) . '</td>';
		echo '<td class="' . $class . '">' . AttrEscape( $row['status'] ) . '</td>';
		echo '<td>' . AttrEscape( $row['message'] ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table></div>';

	if ( ! $error_count && AllowEdit() )
	{
		echo '<form method="POST" style="margin-top:16px">';
		echo '<input type="hidden" name="grade_import_action" value="confirm">';

		if ( $existing_count )
		{
			echo '<p><label><input type="checkbox" name="replace_existing" value="1"> <b>' .
				_( 'Replace existing official grades shown above' ) . '</b></label></p>';
		}

		echo '<button class="abg-import-btn" type="submit">' . _( 'Confirm Grade Import' ) . '</button>';
		echo '</form>';
	}
	else if ( $error_count )
	{
		echo '<p class="abg-status-error">' . _( 'Import is blocked until all validation errors are corrected in the Excel file.' ) . '</p>';
	}

	echo '</div>';
}

echo '</div>';
