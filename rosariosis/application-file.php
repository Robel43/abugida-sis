<?php
/**
 * Secure document viewer/downloader for Abugida online applications.
 */

require_once __DIR__ . '/Warehouse.php';

require_once __DIR__ . '/ProgramFunctions/AbugidaWorkflow.fnc.php';

$applicant_id = (int) ( $_GET['applicant_id'] ?? 0 );
$type = (string) ( $_GET['type'] ?? '' );
$mode = (string) ( $_GET['mode'] ?? 'inline' );
$module = $type === 'receipt' ? 'Custom/FinanceApplications.php' : 'Custom/ApplicationReview.php';
if (!AbugidaStaffAllowed($module)) {
    http_response_code(403);
    exit('Access denied.');
}

$map = [
	'document' => [ 'DOCUMENT_STORED_NAME', 'DOCUMENT_ORIGINAL_NAME', 'DOCUMENT_MIME_TYPE' ],
	'fayda' => [ 'FAYDA_STORED_NAME', 'FAYDA_ORIGINAL_NAME', 'FAYDA_MIME_TYPE' ],
	'receipt' => [ 'RECEIPT_STORED_NAME', 'RECEIPT_ORIGINAL_NAME', 'RECEIPT_MIME_TYPE' ],
];

if ( ! $applicant_id || empty( $map[ $type ] ) )
{
	http_response_code( 400 );
	exit( 'Invalid document request.' );
}

$ret = DBGet( "SELECT *
	FROM abugida_applicants
	WHERE ID='" . $applicant_id . "'
	LIMIT 1" );

if ( empty( $ret[1] ) )
{
	http_response_code( 404 );
	exit( 'Application not found.' );
}

$applicant = $ret[1];
$stored = $applicant[ $map[ $type ][0] ];
$original = $applicant[ $map[ $type ][1] ];
$mime = $applicant[ $map[ $type ][2] ];

if ( ! $stored )
{
	http_response_code( 404 );
	exit( 'Document not found.' );
}

$file = __DIR__ . '/assets/FileUploads/ApplicantDocuments/' . basename( $stored );

$resolved = realpath( $file );
$upload_root = realpath( __DIR__ . '/assets/FileUploads/ApplicantDocuments' );
if ( ! $resolved || ! $upload_root || ! str_starts_with( $resolved, $upload_root . DIRECTORY_SEPARATOR ) || ! is_file( $resolved ) )
{
	http_response_code( 404 );
	exit( 'Document file not found.' );
}

$allowed_mimes = [
	'application/pdf',
	'image/png',
	'image/jpeg',
];

if ( ! in_array( $mime, $allowed_mimes, true ) )
{
	$mime = 'application/octet-stream';
}

$disposition = $mode === 'download' ? 'attachment' : 'inline';
$filename = str_replace( [ '"', "\r", "\n" ], '', basename( $original ) );

header( 'Content-Type: ' . $mime );
header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
header( 'Content-Length: ' . filesize( $file ) );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, no-store' );
header( "Content-Security-Policy: sandbox; default-src 'none'; frame-ancestors 'self'" );
header( 'Referrer-Policy: no-referrer' );

readfile( $file );
exit;
