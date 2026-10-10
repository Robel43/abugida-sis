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

function AbugidaXlsxTextFromSharedString( $node )
{
	$text = '';

	foreach ( $node->xpath( './/*[local-name()="t"]' ) as $text_node )
	{
		$text .= (string) $text_node;
	}

	return $text;
}

function AbugidaReadXlsx( $file_path, &$error_message = '' )
{
	$error_message = '';

	if ( ! class_exists( 'ZipArchive' ) )
	{
		$error_message = 'PHP ZipArchive is not available. Enable the PHP zip extension to import .xlsx files.';

		return false;
	}

	if ( ! function_exists( 'simplexml_load_string' ) )
	{
		$error_message = 'PHP SimpleXML is not available. Enable the PHP XML extension to import .xlsx files.';

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
			$items = $shared->xpath( '/*[local-name()="sst"]/*[local-name()="si"]' );

			foreach ( (array) $items as $item )
			{
				$shared_strings[] = AbugidaXlsxTextFromSharedString( $item );
			}
		}
	}

	$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
	$zip->close();

	if ( $sheet_xml === false )
	{
		$error_message = 'The workbook does not contain a readable first worksheet.';

		return false;
	}

	$sheet = @simplexml_load_string( $sheet_xml );

	if ( ! $sheet )
	{
		$error_message = 'The first worksheet is not readable.';

		return false;
	}

	$row_nodes = $sheet->xpath( '/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]' );

	if ( ! $row_nodes )
	{
		$error_message = 'The first worksheet does not contain readable rows.';

		return false;
	}

	$rows = [];

	foreach ( $row_nodes as $row )
	{
		$cells = [];
		$cell_nodes = $row->xpath( './*[local-name()="c"]' );

		foreach ( (array) $cell_nodes as $cell )
		{
			$attributes = $cell->attributes();
			$reference = (string) $attributes['r'];
			$type = (string) $attributes['t'];
			$index = AbugidaXlsxColumnIndex( $reference );
			$value = '';

			if ( $type === 'inlineStr' )
			{
				$text_nodes = $cell->xpath( './*[local-name()="is"]//*[local-name()="t"]' );

				foreach ( (array) $text_nodes as $text_node )
				{
					$value .= (string) $text_node;
				}
			}
			else
			{
				$value_nodes = $cell->xpath( './*[local-name()="v"]' );
				$raw = ! empty( $value_nodes[0] ) ? (string) $value_nodes[0] : '';

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
