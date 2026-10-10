<?php
/**
 * Downloadable Excel template for Abugida SIS grade import.
 */

require_once __DIR__ . '/Warehouse.php';

if ( User( 'PROFILE' ) !== 'admin'
	|| empty( User( 'STAFF_ID' ) ) )
{
	http_response_code( 403 );
	exit( 'Access denied.' );
}

if ( ! class_exists( 'ZipArchive' ) )
{
	http_response_code( 500 );
	exit( 'PHP ZipArchive is required to generate the Excel template.' );
}

$tmp = tempnam( sys_get_temp_dir(), 'abugida_grade_template_' );

if ( $tmp === false )
{
	http_response_code( 500 );
	exit( 'Could not create the Excel template.' );
}

$zip = new ZipArchive();

if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true )
{
	@unlink( $tmp );
	http_response_code( 500 );
	exit( 'Could not create the Excel template.' );
}

$zip->addFromString(
	'[Content_Types].xml',
	'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
	'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
	'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
	'<Default Extension="xml" ContentType="application/xml"/>' .
	'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
	'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
	'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
	'</Types>'
);

$zip->addFromString(
	'_rels/.rels',
	'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
	'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
	'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
	'</Relationships>'
);

$zip->addFromString(
	'xl/workbook.xml',
	'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
	'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
	'<sheets><sheet name="Grade Import" sheetId="1" r:id="rId1"/></sheets>' .
	'</workbook>'
);

$zip->addFromString(
	'xl/_rels/workbook.xml.rels',
	'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
	'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
	'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
	'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
	'</Relationships>'
);

$zip->addFromString(
	'xl/styles.xml',
	'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
	'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
	'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
	'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
	'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
	'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
	'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>' .
	'</styleSheet>'
);

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
	'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
	'<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="28" customWidth="1"/><col min="3" max="3" width="16" customWidth="1"/></cols>' .
	'<sheetData>' .
	'<row r="1">' .
	'<c r="A1" t="inlineStr" s="1"><is><t>Student ID</t></is></c>' .
	'<c r="B1" t="inlineStr" s="1"><is><t>Student Name</t></is></c>' .
	'<c r="C1" t="inlineStr" s="1"><is><t>Final Score</t></is></c>' .
	'</row>' .
	'<row r="2">' .
	'<c r="A2" t="inlineStr"><is><t>ABG123</t></is></c>' .
	'<c r="B2" t="inlineStr"><is><t>Example Student</t></is></c>' .
	'<c r="C2"><v>85</v></c>' .
	'</row>' .
	'</sheetData>' .
	'</worksheet>';

$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
$zip->close();

$filename = 'Abugida_Grade_Import_Template.xlsx';

header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
header( 'Content-Length: ' . filesize( $tmp ) );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, max-age=0, must-revalidate' );

readfile( $tmp );
@unlink( $tmp );
exit;
