<?php
/**
 * Abugida SIS applicant payment page.
 */

require_once __DIR__ . '/Warehouse.php';

const ABUGIDA_PAYMENT_MAX_UPLOAD_BYTES = 5242880;

function abugida_payment_h( $value )
{
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

$applicant_id = (int) ( $_SESSION['abugida_applicant_id'] ?? 0 );
$phone = (string) ( $_SESSION['abugida_applicant_phone'] ?? '' );

$ret = $applicant_id && $phone ? DBGet( "SELECT *
	FROM abugida_applicants
	WHERE ID='" . $applicant_id . "'
	AND PHONE='" . DBEscapeString( $phone ) . "'
	LIMIT 1" ) : [];

$applicant = ! empty( $ret[1] ) ? $ret[1] : null;

if ( ! $applicant )
{
	header( 'Location: registration.php' );
	exit;
}

if ( empty( $_SESSION['abugida_payment_csrf'] ) )
{
	$_SESSION['abugida_payment_csrf'] = bin2hex( random_bytes( 32 ) );
}

$errors = [];
$notice = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST'
	&& in_array( $applicant['STATUS'], [ 'APPROVED_FOR_PAYMENT', 'PAYMENT_DECLINED' ], true ) )
{
	if ( empty( $_POST['csrf'] )
		|| ! hash_equals( $_SESSION['abugida_payment_csrf'], (string) $_POST['csrf'] ) )
	{
		$errors[] = 'Your session expired. Please refresh the page.';
	}
	elseif ( empty( $_FILES['receipt'] )
		|| $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE )
	{
		$errors[] = 'Upload your payment receipt.';
	}
	else
	{
		$file = $_FILES['receipt'];

		if ( $file['error'] !== UPLOAD_ERR_OK )
		{
			$errors[] = 'The receipt upload failed.';
		}
		elseif ( (int) $file['size'] > ABUGIDA_PAYMENT_MAX_UPLOAD_BYTES )
		{
			$errors[] = 'The receipt must be 5 MB or smaller.';
		}
		else
		{
			$finfo = new finfo( FILEINFO_MIME_TYPE );
			$mime = $finfo->file( $file['tmp_name'] );
			$allowed = [
				'application/pdf' => 'pdf',
				'image/png' => 'png',
				'image/jpeg' => 'jpg',
			];

			if ( ! isset( $allowed[ $mime ] ) )
			{
				$errors[] = 'Only PDF, PNG, or JPG receipts are allowed.';
			}
			else
			{
				$dir = __DIR__ . '/assets/FileUploads/ApplicantDocuments';

				if ( ! is_dir( $dir ) )
				{
					mkdir( $dir, 0750, true );
				}

				$stored = 'receipt-' . $applicant_id . '-' . bin2hex( random_bytes( 12 ) ) . '.' . $allowed[ $mime ];

				if ( ! move_uploaded_file( $file['tmp_name'], $dir . '/' . $stored ) )
				{
					$errors[] = 'Unable to save the receipt.';
				}
				else
				{
					if ( $applicant['RECEIPT_STORED_NAME'] )
					{
						$old = $dir . '/' . basename( $applicant['RECEIPT_STORED_NAME'] );

						if ( is_file( $old ) )
						{
							@unlink( $old );
						}
					}

					$from = $applicant['STATUS'];

					DBUpdate(
						'abugida_applicants',
						[
							'STATUS' => 'PAYMENT_SUBMITTED',
							'PAYMENT_STATUS' => 'SUBMITTED',
							'RECEIPT_STORED_NAME' => $stored,
							'RECEIPT_ORIGINAL_NAME' => basename( $file['name'] ),
							'RECEIPT_MIME_TYPE' => $mime,
							'RECEIPT_SIZE' => (int) $file['size'],
							'RECEIPT_SUBMITTED_AT' => DBDate() . ' ' . date( 'H:i:s' ),
							'FINANCE_DECISION_REASON' => null,
						],
						[ 'ID' => $applicant_id ]
					);

					DBInsert(
						'abugida_application_history',
						[
							'APPLICANT_ID' => $applicant_id,
							'FROM_STATUS' => $from,
							'TO_STATUS' => 'PAYMENT_SUBMITTED',
							'ACTION' => 'Payment receipt submitted',
							'ACTOR_TYPE' => 'APPLICANT',
							'ACTOR_NAME' => $applicant['FIRST_NAME'] . ' ' . $applicant['LAST_NAME'],
						]
					);

					header( 'Location: registration-payment.php?submitted=1' );
					exit;
				}
			}
		}
	}
}

$ret = DBGet( "SELECT * FROM abugida_applicants WHERE ID='" . $applicant_id . "' LIMIT 1" );
$applicant = $ret[1];

if ( isset( $_GET['submitted'] ) )
{
	$notice = 'Your payment receipt has been submitted for verification.';
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment | Abugida SIS</title>
<style>
body{margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#182230}
.wrap{max-width:760px;margin:48px auto;padding:0 18px}
.card{background:#fff;border:1px solid #dbe3ef;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(15,31,56,.08)}
h1{margin-top:0}.muted{color:#667085}.amount{font-size:34px;font-weight:800;margin:18px 0}
.notice{padding:12px 14px;border-radius:10px;margin-bottom:16px;background:#eaf7ef;color:#087443}
.error{padding:12px 14px;border-radius:10px;margin-bottom:16px;background:#fff0ef;color:#b42318}
.instructions{white-space:pre-wrap;background:#f8fafc;border:1px solid #e5eaf1;border-radius:12px;padding:16px;margin:18px 0}
input[type=file]{width:100%;padding:12px;border:1px solid #d4dbe6;border-radius:10px}
button,a{display:inline-block;margin-top:18px;padding:12px 16px;border-radius:10px;text-decoration:none;border:0;font-weight:700}
button{background:#2563eb;color:#fff}.back{background:#eef2f7;color:#344054;margin-left:8px}
.status{display:inline-block;padding:6px 10px;border-radius:999px;background:#eef4ff;color:#1d4ed8;font-size:12px;font-weight:800}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<h1>Registration Payment</h1>
<p class="muted">Application <?php echo abugida_payment_h( $applicant['APPLICATION_REFERENCE'] ); ?></p>
<span class="status"><?php echo abugida_payment_h( $applicant['PAYMENT_STATUS'] ); ?></span>

<?php if ( $notice ) { ?><div class="notice"><?php echo abugida_payment_h( $notice ); ?></div><?php } ?>
<?php if ( $errors ) { ?><div class="error"><?php echo abugida_payment_h( implode( ' ', $errors ) ); ?></div><?php } ?>

<div class="amount">ETB <?php echo number_format( (float) $applicant['PAYMENT_AMOUNT'], 2 ); ?></div>
<div class="instructions"><?php echo abugida_payment_h( $applicant['PAYMENT_INSTRUCTIONS'] ); ?></div>

<?php if ( $applicant['STATUS'] === 'PAYMENT_DECLINED' ) { ?>
<div class="error"><strong>Payment rejected:</strong> <?php echo abugida_payment_h( $applicant['FINANCE_DECISION_REASON'] ); ?></div>
<?php } ?>

<?php if ( in_array( $applicant['STATUS'], [ 'APPROVED_FOR_PAYMENT', 'PAYMENT_DECLINED' ], true ) ) { ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?php echo abugida_payment_h( $_SESSION['abugida_payment_csrf'] ); ?>">
<label><strong>Upload payment receipt</strong></label><br><br>
<input type="file" name="receipt" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" required>
<p class="muted">PDF, PNG, or JPG. Maximum 5 MB.</p>
<button type="submit">Submit Receipt</button>
<a class="back" href="registration.php">Back to Registration</a>
</form>
<?php } elseif ( $applicant['STATUS'] === 'PAYMENT_SUBMITTED' ) { ?>
<p>Your receipt is waiting for Finance verification.</p>
<a class="back" href="registration.php">Back to Registration</a>
<?php } elseif ( $applicant['STATUS'] === 'PAYMENT_VERIFIED' ) { ?>
<p>Your payment has been verified. The Registrar will complete your enrollment.</p>
<a class="back" href="registration.php">Back to Registration</a>
<?php } elseif ( $applicant['STATUS'] === 'ACTIVE' ) { ?>
<p>Your registration is complete. Your student account has been created.</p>
<a class="back" href="index.php">Student Login</a>
<?php } else { ?>
<p>Payment is not available for this application yet.</p>
<a class="back" href="registration.php">Back to Registration</a>
<?php } ?>
</div>
</div>
</body>
</html>
