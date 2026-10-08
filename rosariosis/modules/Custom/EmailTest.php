<?php
/**
 * Abugida SIS - SMTP email test utility.
 */

DrawHeader( ProgramTitle() );

if ( User( 'PROFILE' ) !== 'admin' )
{
	exit;
}

require_once 'ProgramFunctions/AbugidaEmail.fnc.php';

$test_note = [];
$test_error = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST'
	&& AllowEdit() )
{
	$recipient = trim( (string) issetVal( $_POST['test_email'], '' ) );

	if ( ! filter_var( $recipient, FILTER_VALIDATE_EMAIL ) )
	{
		$test_error[] = _( 'Enter a valid recipient email address.' );
	}
	else
	{
		$sent = AbugidaSendEmail(
			$recipient,
			'Abugida SIS SMTP test',
			"This is a test email from Abugida SIS.\n\nIf you received this message, the SMTP configuration is working."
		);

		if ( $sent )
		{
			$test_note[] = button( 'check' ) . '&nbsp;' . _( 'Test email sent successfully.' );
		}
		else
		{
			global $AbugidaMailLastError;
			$test_error[] = _( 'Email could not be sent.' ) .
				( $AbugidaMailLastError ? ' ' . $AbugidaMailLastError : '' );
		}
	}
}

echo ErrorMessage( $test_error );
echo ErrorMessage( $test_note, 'note' );

echo '<div style="max-width:760px;background:#fff;border:1px solid #d9e1ea;border-radius:10px;padding:18px">';
echo '<p>' . _( 'Use this page to verify the SMTP configuration before testing applicant approval emails.' ) . '</p>';
echo '<form method="POST">';
echo '<label><b>' . _( 'Recipient Email' ) . '</b><br>';
echo '<input type="email" name="test_email" required style="width:100%;max-width:520px;padding:9px;border:1px solid #cbd5e1;border-radius:6px"></label><br><br>';
if ( AllowEdit() )
{
	echo '<button type="submit" style="padding:10px 16px;border:0;border-radius:7px;background:#1677c8;color:#fff;font-weight:700;cursor:pointer">' . _( 'Send Test Email' ) . '</button>';
}
echo '</form></div>';
