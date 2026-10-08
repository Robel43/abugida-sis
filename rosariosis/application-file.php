<?php
/**
 * Secure document viewer/downloader for Abugida online applications.
 */

require_once __DIR__ . '/Warehouse.php';

if ( User( 'PROFILE' ) !== 'admin'
	|| empty( User( 'STAFF_ID' ) ) )
{
	http_response_code( 403 );
	exit( 'Access denied.' );
}

$applicant_id = (int) ( $_GET['applicant_id'] ?? 0 );
$type = (string) ( $_GET['type'] ?? '' );
$mode = (string) ( $_GET['mode'] ?? 'inline' );

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

if ( ! is_file( $file ) )
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
header( 'Cache-Control: private, max-age=0, must-revalidate' );

readfile( $file );
exit;
