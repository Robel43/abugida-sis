<?php
/**
 * Secure re-registration receipt viewer/downloader.
 */

require_once __DIR__ . '/Warehouse.php';

if ( User( 'PROFILE' ) !== 'admin'
	|| empty( User( 'STAFF_ID' ) )
	|| ! AllowUse( 'Custom/ReRegistrationPayments.php' ) )
{
	http_response_code( 403 );
	exit( 'Access denied.' );
}

$request_id = (int) ( $_GET['request_id'] ?? 0 );
$mode = (string) ( $_GET['mode'] ?? 'download' );

if ( ! $request_id )
{
	http_response_code( 400 );
	exit( 'Invalid receipt request.' );
}

$ret = DBGet( "SELECT RECEIPT_STORED_NAME,RECEIPT_ORIGINAL_NAME,RECEIPT_MIME_TYPE
	FROM abugida_reregistration_requests
	WHERE ID='" . $request_id . "'
	AND SCHOOL_ID='" . UserSchool() . "'
	LIMIT 1" );

if ( empty( $ret[1] ) )
{
	http_response_code( 404 );
	exit( 'Re-registration request not found.' );
}

$request = $ret[1];

if ( empty( $request['RECEIPT_STORED_NAME'] ) )
{
	http_response_code( 404 );
	exit( 'Receipt not found.' );
}

$file = __DIR__ . '/assets/FileUploads/ReRegistrationReceipts/' .
	basename( $request['RECEIPT_STORED_NAME'] );

if ( ! is_file( $file ) )
{
	http_response_code( 404 );
	exit( 'Receipt file not found.' );
}

$mime = $request['RECEIPT_MIME_TYPE'];
$allowed_mimes = [
	'application/pdf',
	'image/png',
	'image/jpeg',
];

if ( ! in_array( $mime, $allowed_mimes, true ) )
{
	$mime = 'application/octet-stream';
}

$disposition = $mode === 'inline' ? 'inline' : 'attachment';
$filename = str_replace(
	[ '"', "\r", "\n" ],
	'',
	basename( $request['RECEIPT_ORIGINAL_NAME'] )
);

header( 'Content-Type: ' . $mime );
header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
header( 'Content-Length: ' . filesize( $file ) );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, max-age=0, must-revalidate' );

readfile( $file );
exit;
