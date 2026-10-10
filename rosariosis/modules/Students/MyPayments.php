<?php

require_once 'ProgramFunctions/AbugidaStudentPortal.fnc.php';

AbugidaStudentPortalGuard();

DrawHeader( _( 'Payments' ) );
AbugidaStudentPortalStyles();

$billing = AbugidaStudentPortalBilling();

echo '<div class="abg-student-shell">';
echo '<div class="abg-student-grid">';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Total Charges' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['FEES'] ) . '</div></div>';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Total Paid' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['PAYMENTS'] ) . '</div></div>';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Outstanding Balance' ) . '</div><div class="abg-student-value">' .
	Currency( $billing['BALANCE'] ) . '</div></div>';
echo '<div class="abg-student-card"><div class="abg-student-label">' . _( 'Status' ) . '</div><div class="abg-student-value">' .
	AttrEscape( $billing['STATUS'] ) . '</div></div>';
echo '</div>';

echo '<div class="abg-student-section"><h3>' . _( 'Recent Payments' ) . '</h3>';
echo '<div style="overflow:auto"><table class="abg-student-table"><thead><tr>';
echo '<th>' . _( 'Date' ) . '</th><th>' . _( 'Amount' ) . '</th><th>' . _( 'Comment' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( (array) $billing['RECENT'] as $payment )
{
	echo '<tr><td>' . ProperDate( $payment['PAYMENT_DATE'] ) . '</td>';
	echo '<td>' . Currency( $payment['AMOUNT'] ) . '</td>';
	echo '<td>' . AttrEscape( $payment['COMMENTS'] ) . '</td></tr>';
}

if ( ! $billing['RECENT'] )
{
	echo '<tr><td colspan="3">' . _( 'No payments have been recorded for the current academic year.' ) . '</td></tr>';
}

echo '</tbody></table></div></div></div>';
