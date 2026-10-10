<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'Payments' ) );
AbugidaStudentPortalStyles();

$billing = AbugidaStudentPortalBilling();

$status_class = 'neutral';

if ( $billing['STATUS'] === _( 'Paid' ) )
{
	$status_class = 'success';
}
elseif ( $billing['STATUS'] === _( 'Partially Paid' ) )
{
	$status_class = 'warning';
}
elseif ( $billing['STATUS'] === _( 'Unpaid' ) )
{
	$status_class = 'danger';
}
elseif ( $billing['STATUS'] === _( 'No Charges' ) )
{
	$status_class = 'info';
}

echo '<div class="abg-student-shell">';
echo '<div class="abg-page-hero"><h2>' . _( 'Payments' ) . '</h2><p>' .
	_( 'Track current-year charges, recorded payments, outstanding balance, and payment status.' ) . '</p></div>';

echo '<div class="abg-student-grid">';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Total Charges' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['FEES'] ) . '</div><div class="abg-student-subvalue">' . _( 'Current academic year' ) . '</div></div>';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Total Paid' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['PAYMENTS'] ) . '</div><div class="abg-student-subvalue">' . _( 'Verified payments recorded in SIS' ) . '</div></div>';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Outstanding Balance' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['BALANCE'] ) . '</div><div class="abg-student-subvalue">' . _( 'Charges less recorded payments' ) . '</div></div>';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Status' ) . '</div><div class="abg-student-value"><span class="abg-badge ' .
	$status_class . '">' . AttrEscape( $billing['STATUS'] ) . '</span></div><div class="abg-student-subvalue">' .
	_( 'Based on current-year billing records' ) . '</div></div>';
echo '</div>';

echo '<div class="abg-student-section">';
echo '<div class="abg-section-header"><div><h3>' . _( 'Recent Payments' ) . '</h3><p>' .
	_( 'Your most recent recorded payments for the current academic year.' ) . '</p></div></div>';

if ( $billing['RECENT'] )
{
	echo '<div class="abg-student-table-wrap"><table class="abg-student-table"><thead><tr>';
	echo '<th>' . _( 'Date' ) . '</th><th>' . _( 'Amount' ) . '</th><th>' . _( 'Comment' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( (array) $billing['RECENT'] as $payment )
	{
		echo '<tr><td>' . ProperDate( $payment['PAYMENT_DATE'] ) . '</td>';
		echo '<td><span class="abg-grade-value">' . Currency( $payment['AMOUNT'] ) . '</span></td>';
		echo '<td>' . AttrEscape( $payment['COMMENTS'] ) . '</td></tr>';
	}

	echo '</tbody></table></div>';
}
else
{
	echo '<div class="abg-empty">' . _( 'No payments have been recorded for the current academic year.' ) . '</div>';
}

echo '</div></div>';
