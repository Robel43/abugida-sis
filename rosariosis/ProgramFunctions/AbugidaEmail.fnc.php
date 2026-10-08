<?php
/**
 * Abugida SIS SMTP email helper.
 *
 * Configure the AbugidaMail* variables in config.inc.php.
 */

function AbugidaSendEmail( $to, $subject, $message )
{
	global $AbugidaMailHost,
		$AbugidaMailPort,
		$AbugidaMailUsername,
		$AbugidaMailPassword,
		$AbugidaMailFrom,
		$AbugidaMailFromName,
		$AbugidaMailEncryption,
		$error,
		$AbugidaMailLastError;

	$AbugidaMailLastError = '';

	if ( ! filter_var( $to, FILTER_VALIDATE_EMAIL ) )
	{
		return false;
	}

	if ( empty( $AbugidaMailHost )
		|| empty( $AbugidaMailUsername )
		|| empty( $AbugidaMailPassword )
		|| empty( $AbugidaMailFrom ) )
	{
		$AbugidaMailLastError = 'SMTP configuration is incomplete.';
		$error[] = _( 'Abugida SMTP email is not configured. Add the mail settings to config.inc.php.' );
		error_log( '[Abugida SMTP] ' . $AbugidaMailLastError );

		return false;
	}

	try
	{
		$mail = new PHPMailer\PHPMailer\PHPMailer( true );
		$mail->isSMTP();
		$mail->Host = $AbugidaMailHost;
		$mail->SMTPAuth = true;
		$mail->Username = $AbugidaMailUsername;
		$mail->Password = $AbugidaMailPassword;
		$mail->Port = ! empty( $AbugidaMailPort ) ? (int) $AbugidaMailPort : 587;
		$mail->Timeout = 15;
		$mail->SMTPKeepAlive = false;

		if ( ! empty( $AbugidaMailEncryption ) )
		{
			$mail->SMTPSecure = $AbugidaMailEncryption;
		}

		$mail->CharSet = 'UTF-8';
		$mail->setFrom(
			$AbugidaMailFrom,
			! empty( $AbugidaMailFromName ) ? $AbugidaMailFromName : 'Abugida SIS'
		);
		$mail->addAddress( $to );
		$mail->Subject = $subject;
		$mail->Body = $message;
		$mail->AltBody = trim( strip_tags( $message ) );

		return $mail->send();
	}
	catch ( Throwable $e )
	{
		$AbugidaMailLastError = $e->getMessage();
		$error[] = $AbugidaMailLastError;
		error_log( '[Abugida SMTP] ' . $AbugidaMailLastError );

		return false;
	}
}

function AbugidaPublicURL( $path, $query = [] )
{
	$is_https = ! empty( $_SERVER['HTTPS'] )
		&& $_SERVER['HTTPS'] !== 'off';

	$scheme = $is_https ? 'https' : 'http';
	$host = ! empty( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
	$base = rtrim( str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ) ), '/' );

	$url = $scheme . '://' . $host .
		( $base && $base !== '/' ? $base : '' ) .
		'/' . ltrim( $path, '/' );

	if ( $query )
	{
		$url .= '?' . http_build_query( $query );
	}

	return $url;
}
