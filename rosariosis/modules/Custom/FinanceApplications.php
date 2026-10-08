<?php
/**
 * Abugida SIS - Finance application payment verification.
 */

DrawHeader( ProgramTitle() );

if ( User( 'PROFILE' ) !== 'admin' )
{
	exit;
}

function AbugidaFinanceEmail( $email, $subject, $message )
{
	if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) )
	{
		require_once 'ProgramFunctions/SendEmail.fnc.php';
		SendEmail( $email, $subject, $message );
	}
}

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

	if ( $applicant && $_REQUEST['modfunc'] === 'download_receipt' )
	{
		$file = 'assets/FileUploads/ApplicantDocuments/' . basename( $applicant['RECEIPT_STORED_NAME'] );

		if ( is_file( $file ) )
		{
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', basename( $applicant['RECEIPT_ORIGINAL_NAME'] ) ) . '"' );
			header( 'Content-Length: ' . filesize( $file ) );
			readfile( $file );
		}
		exit;
	}

	if ( $applicant
		&& $_REQUEST['modfunc'] === 'decision'
		&& AllowEdit() )
	{
		$decision = issetVal( $_POST['decision'] );
		$reason = trim( issetVal( $_POST['reason'] ) );
		$from = $applicant['STATUS'];

		if ( $decision === 'approve'
			&& $applicant['STATUS'] === 'PAYMENT_SUBMITTED' )
		{
			DBUpdate(
				'abugida_applicants',
				[
					'STATUS' => 'PAYMENT_VERIFIED',
					'PAYMENT_STATUS' => 'VERIFIED',
					'FINANCE_DECISION_REASON' => null,
					'FINANCE_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
					'FINANCE_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
				],
				[ 'ID' => $applicant_id ]
			);

			AbugidaFinanceHistory( $applicant_id, $from, 'PAYMENT_VERIFIED', 'Payment verified' );
			AbugidaFinanceEmail( $applicant['EMAIL'], 'Abugida SIS payment verified', "Your payment has been verified. The Registrar will now complete your enrollment and create your student account." );
			$note[] = button( 'check' ) . '&nbsp;' . _( 'Payment verified.' );
		}
		elseif ( $decision === 'reject'
			&& $applicant['STATUS'] === 'PAYMENT_SUBMITTED' )
		{
			if ( $reason === '' )
			{
				$error[] = _( 'A rejection reason is required.' );
			}
			else
			{
				DBUpdate(
					'abugida_applicants',
					[
						'STATUS' => 'PAYMENT_DECLINED',
						'PAYMENT_STATUS' => 'DECLINED',
						'FINANCE_DECISION_REASON' => $reason,
						'FINANCE_REVIEWED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
						'FINANCE_REVIEWED_BY' => (int) User( 'STAFF_ID' ),
					],
					[ 'ID' => $applicant_id ]
				);

				AbugidaFinanceHistory( $applicant_id, $from, 'PAYMENT_DECLINED', 'Payment rejected', $reason );
				AbugidaFinanceEmail( $applicant['EMAIL'], 'Abugida SIS payment update', "Your payment proof was not approved.\n\nReason: " . $reason . "\n\nReturn to the registration portal using your phone number and upload a corrected receipt." );
				$note[] = button( 'check' ) . '&nbsp;' . _( 'Payment rejected.' );
			}
		}

		RedirectURL( [ 'modfunc', 'decision' ] );
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
				URLEscape( 'Modules.php?modname=Custom/FinanceApplications.php&applicant_id=' . $applicant_id . '&modfunc=download_receipt' ) .
				'">' . _( 'Download Payment Receipt' ) . '</a>';
		}

		if ( $applicant['STATUS'] === 'PAYMENT_SUBMITTED' && AllowEdit() )
		{
			echo '<br /><br /><form method="POST" action="' .
				URLEscape( 'Modules.php?modname=Custom/FinanceApplications.php&applicant_id=' . $applicant_id . '&modfunc=decision' ) . '">';
			echo '<fieldset><legend>' . _( 'Finance Decision' ) . '</legend>';
			echo '<p><label>' . _( 'Reason (required when rejecting)' ) . '<br><textarea name="reason" rows="3" class="width-100p"></textarea></label></p>';
			echo '<button type="submit" name="decision" value="approve">' . _( 'Verify Payment' ) . '</button> ';
			echo '<button type="submit" name="decision" value="reject">' . _( 'Reject Payment' ) . '</button>';
			echo '</fieldset></form>';
		}
		elseif ( $applicant['STATUS'] === 'PAYMENT_DECLINED' )
		{
			echo '<br />' . ErrorMessage( [ _( 'Payment rejected: ' ) . $applicant['FINANCE_DECISION_REASON'] ], 'warning' );
		}

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
