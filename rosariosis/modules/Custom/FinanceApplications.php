<?php
/**
 * Abugida SIS - Finance application payment verification.
 */

DrawHeader( ProgramTitle() );

require_once 'ProgramFunctions/AbugidaWorkflow.fnc.php';

if ( ! AbugidaStaffAllowed( 'Custom/FinanceApplications.php' ) )
{
	exit;
}

require_once 'ProgramFunctions/AbugidaEmail.fnc.php';

function AbugidaFinanceHistory( $applicant_id, $from, $to, $action, $reason = '' )
{
	DBInsert(
		'abugida_application_history',
		[
			'APPLICANT_ID' => (int) $applicant_id,
			'FROM_STATUS' => $from,
			'TO_STATUS' => $to,
			'ACTION' => $action,
			'ACTOR_TYPE' => 'FINANCE',
			'ACTOR_ID' => (int) User( 'STAFF_ID' ),
			'ACTOR_NAME' => User( 'NAME' ),
			'REASON' => $reason,
		]
	);
}

if ( ! empty( $_REQUEST['applicant_id'] ) )
{
	$applicant_id = (int) $_REQUEST['applicant_id'];
	$ret = DBGet( "SELECT * FROM abugida_applicants WHERE ID='" . $applicant_id . "' LIMIT 1" );
	$applicant = ! empty( $ret[1] ) ? $ret[1] : null;

	if ( $applicant && ( $_REQUEST['modfunc'] ?? '' ) === 'download_receipt' )
	{
		header( 'Location: application-file.php?applicant_id=' . $applicant_id . '&type=receipt&mode=download' );
		exit;
	}
	if ( $applicant && in_array( $_REQUEST['modfunc'] ?? '', [ 'decision', 'retry_email' ], true ) )
	{
		try
		{
			if ( $_REQUEST['modfunc'] === 'decision' )
			{
				$notification_id = AbugidaReviewDecision( $applicant_id, 'FINANCE', (string) ( $_POST['decision'] ?? '' ), (string) ( $_POST['reason'] ?? '' ) );
				$note[] = 'Finance decision saved.';
			}
			else { $notification_id = (int) ( $_POST['notification_id'] ?? 0 ); }
			$delivery = AbugidaDeliverNotification( $notification_id, $applicant_id, 'FINANCE' );
			$note[] = $delivery === 'SENT' ? 'Notification accepted by the SMTP server.' : 'Notification status: ' . $delivery . '. The saved decision is preserved; see Email notifications below.';
		}
		catch ( RuntimeException $exception )
		{
			$error[] = htmlspecialchars( $exception instanceof PDOException ? 'Unable to save the request. Check that Stage 2 migration 007 is installed.' : $exception->getMessage(), ENT_QUOTES, 'UTF-8' );
		}
	}
	if ( $applicant )
	{
		$ret = DBGet( "SELECT * FROM abugida_applicants WHERE ID='" . $applicant_id . "' LIMIT 1" );
		$applicant = $ret[1];

		echo ErrorMessage( $error );
		echo ErrorMessage( $note, 'note' );

		DrawHeader(
			'<a href="' . URLEscape( 'Modules.php?modname=Custom/FinanceApplications.php' ) . '">' . _( 'Application Payments' ) . '</a> &raquo; ' .
			AttrEscape( $applicant['APPLICATION_REFERENCE'] )
		);

		echo '<table class="width-100p cellpadding-5">';
		echo '<tr><td><b>' . _( 'Applicant' ) . '</b></td><td>' . AttrEscape( $applicant['FIRST_NAME'] . ' ' . $applicant['LAST_NAME'] ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Phone' ) . '</b></td><td>' . AttrEscape( $applicant['PHONE'] ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Amount Due' ) . '</b></td><td>' . AttrEscape( $applicant['PAYMENT_AMOUNT'] ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Payment Status' ) . '</b></td><td>' . AttrEscape( $applicant['PAYMENT_STATUS'] ) . '</td></tr>';
		echo '<tr><td><b>' . _( 'Application Status' ) . '</b></td><td>' . AttrEscape( $applicant['STATUS'] ) . '</td></tr>';
		echo '</table>';

		if ( $applicant['RECEIPT_STORED_NAME'] )
		{
			echo '<br /><a class="button" href="' .
				URLEscape( 'application-file.php?applicant_id=' . $applicant_id . '&type=receipt&mode=download' ) .
				'">' . _( 'Download Payment Receipt' ) . '</a>';
		}

		if ( $applicant['STATUS'] === 'PAYMENT_SUBMITTED' && AllowEdit() )
		{
			echo '<br /><br /><form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/FinanceApplications.php&applicant_id=' . $applicant_id . '&modfunc=decision' ) . '">';
			echo AbugidaCsrfField();
			echo '<fieldset><legend>' . _( 'Finance Decision' ) . '</legend>';
			echo '<p><label>' . _( 'Reason (required when rejecting)' ) . '<br><textarea name="reason" rows="3" class="width-100p"></textarea></label></p>';
			echo '<button type="submit" name="decision" value="approve">' . _( 'Verify Payment' ) . '</button> ';
			echo '<button type="submit" name="decision" value="reject">' . _( 'Reject Payment' ) . '</button>';
			echo '</fieldset></form>';
		}
		elseif ( $applicant['STATUS'] === 'PAYMENT_DECLINED' )
		{
			echo '<br />' . ErrorMessage( [ _( 'Payment rejected: ' ) . AttrEscape( $applicant['FINANCE_DECISION_REASON'] ) ], 'warning' );
		}

		AbugidaNotificationPanel( $applicant_id, 'FINANCE' );
		return;
	}
}

$rows = DBGet( "SELECT ID,APPLICATION_REFERENCE,FIRST_NAME,LAST_NAME,PHONE,PAYMENT_AMOUNT,PAYMENT_STATUS,STATUS,RECEIPT_SUBMITTED_AT
	FROM abugida_applicants
	WHERE STATUS IN ('APPROVED_FOR_PAYMENT','PAYMENT_SUBMITTED','PAYMENT_DECLINED','PAYMENT_VERIFIED')
	ORDER BY ID DESC" );

$columns = [
	'APPLICATION_REFERENCE' => _( 'Reference' ),
	'FIRST_NAME' => _( 'First Name' ),
	'LAST_NAME' => _( 'Last Name' ),
	'PHONE' => _( 'Phone' ),
	'PAYMENT_AMOUNT' => _( 'Amount' ),
	'PAYMENT_STATUS' => _( 'Payment Status' ),
	'STATUS' => _( 'Application Status' ),
	'RECEIPT_SUBMITTED_AT' => _( 'Receipt Submitted' ),
];

$link = [
	'FULL_NAME' => false,
	'link' => 'Modules.php?modname=Custom/FinanceApplications.php',
	'variables' => [ 'applicant_id' => 'ID' ],
];

ListOutput( $rows, $columns, 'Application Payment', 'Application Payments', $link );
