<?php
/**
 * Lightweight XLSX reader for Abugida grade import.
 *
 * Reads the first worksheet only and returns a zero-based array of row arrays.
 * No external Composer dependency is required.
 */

function AbugidaXlsxColumnIndex( $cell_reference )
{
	preg_match( '/^[A-Z]+/i', $cell_reference, $matches );

	if ( empty( $matches[0] ) )
	{
		return 0;
	}

	$letters = strtoupper( $matches[0] );
	$index = 0;

	for ( $i = 0, $len = strlen( $letters ); $i < $len; $i++ )
	{
		$index = $index * 26 + ( ord( $letters[$i] ) - 64 );
	}

	return $index - 1;
}

function AbugidaReadXlsx( $file_path, &$error_message = '' )
{
	$error_message = '';

	if ( ! class_exists( 'ZipArchive' ) )
	{
		$error_message = 'PHP ZipArchive is not available. Enable the PHP zip extension to import .xlsx files.';

		return false;
	}

	$zip = new ZipArchive();

	if ( $zip->open( $file_path ) !== true )
	{
		$error_message = 'The Excel file could not be opened.';

		return false;
	}

	$shared_strings = [];
	$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );

	if ( $shared_xml !== false )
	{
		$shared = @simplexml_load_string( $shared_xml );

		if ( $shared )
		{
			foreach ( $shared->si as $si )
			{
				$text = '';

				if ( isset( $si->t ) )
				{
					$text = (string) $si->t;
				}
				elseif ( isset( $si->r ) )
				{
					foreach ( $si->r as $run )
					{
						$text .= (string) $run->t;
					}
				}

				$shared_strings[] = $text;
			}
		}
	}

	$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );

	if ( $sheet_xml === false )
	{
		$zip->close();
		$error_message = 'The workbook does not contain a readable first worksheet.';

		return false;
	}

	$sheet = @simplexml_load_string( $sheet_xml );
	$zip->close();

	if ( ! $sheet || ! isset( $sheet->sheetData ) )
	{
		$error_message = 'The first worksheet is not readable.';

		return false;
	}

	$rows = [];

	foreach ( $sheet->sheetData->row as $row )
	{
		$cells = [];

		foreach ( $row->c as $cell )
		{
			$attributes = $cell->attributes();
			$reference = (string) $attributes['r'];
			$type = (string) $attributes['t'];
			$index = AbugidaXlsxColumnIndex( $reference );
			$value = '';

			if ( $type === 'inlineStr' && isset( $cell->is->t ) )
			{
				$value = (string) $cell->is->t;
			}
			elseif ( isset( $cell->v ) )
			{
				$raw = (string) $cell->v;

				if ( $type === 's' )
				{
					$value = isset( $shared_strings[(int) $raw] ) ? $shared_strings[(int) $raw] : '';
				}
				else
				{
					$value = $raw;
				}
			}

			$cells[$index] = trim( $value );
		}

		if ( $cells )
		{
			$max_index = max( array_keys( $cells ) );

			for ( $i = 0; $i <= $max_index; $i++ )
			{
				if ( ! isset( $cells[$i] ) )
				{
					$cells[$i] = '';
				}
			}

			ksort( $cells );
			$rows[] = array_values( $cells );
		}
	}

	return $rows;
}
