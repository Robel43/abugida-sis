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
		$error;

	if ( ! filter_var( $to, FILTER_VALIDATE_EMAIL ) )
	{
		return false;
	}

	if ( empty( $AbugidaMailHost )
		|| empty( $AbugidaMailUsername )
		|| empty( $AbugidaMailPassword )
		|| empty( $AbugidaMailFrom ) )
	{
		$error[] = _( 'Abugida SMTP email is not configured. Add the mail settings to config.inc.php.' );

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
	catch ( PHPMailer\PHPMailer\Exception $e )
	{
		$error[] = $e->getMessage();

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
