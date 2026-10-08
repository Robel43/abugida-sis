<?php
/**
 * Abugida SIS public online registration.
 *
 * Applicants are kept separate from permanent student records.
 * No RosarioSIS student or user account is created on this page.
 */

require_once __DIR__ . '/Warehouse.php';

const ABUGIDA_REG_MAX_UPLOAD_BYTES = 5242880; // 5 MB.

if ( empty( $_SESSION['abugida_reg_csrf'] ) )
{
	$_SESSION['abugida_reg_csrf'] = bin2hex( random_bytes( 32 ) );
}

function abugida_reg_h( $value )
{
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function abugida_reg_phone( $phone )
{
	$digits = preg_replace( '/\D+/', '', (string) $phone );

	if ( strlen( $digits ) === 10 && str_starts_with( $digits, '09' ) )
	{
		$digits = '251' . substr( $digits, 1 );
	}
	elseif ( strlen( $digits ) === 9 && str_starts_with( $digits, '9' ) )
	{
		$digits = '251' . $digits;
	}

	return $digits;
}

function abugida_reg_valid_phone( $phone )
{
	return (bool) preg_match( '/^[0-9]{9,15}$/', $phone );
}

function abugida_reg_fetch( $applicant_id, $phone = '' )
{
	$where = "ID='" . (int) $applicant_id . "'";

	if ( $phone !== '' )
	{
		$where .= " AND PHONE='" . DBEscapeString( $phone ) . "'";
	}

	$ret = DBGet( "SELECT *
		FROM abugida_applicants
		WHERE " . $where . "
		LIMIT 1" );

	return ! empty( $ret[1] ) ? $ret[1] : null;
}

function abugida_reg_progress( $applicant )
{
	$checks = [
		! empty( $applicant['FIRST_NAME'] ),
		! empty( $applicant['LAST_NAME'] ),
		! empty( $applicant['EMAIL'] ),
		! empty( $applicant['GRADE_LEVEL'] ),
		! empty( $applicant['DOCUMENT_STORED_NAME'] ),
	];

	$done = count( array_filter( $checks ) );

	return (int) round( ( $done / count( $checks ) ) * 100 );
}

function abugida_reg_store_upload( $applicant_id, $current_stored_name = '' )
{
	if ( empty( $_FILES['document'] )
		|| $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE )
	{
		return null;
	}

	$file = $_FILES['document'];

	if ( $file['error'] !== UPLOAD_ERR_OK )
	{
		throw new RuntimeException( 'The document upload failed. Please try again.' );
	}

	if ( (int) $file['size'] > ABUGIDA_REG_MAX_UPLOAD_BYTES )
	{
		throw new RuntimeException( 'The document must be 5 MB or smaller.' );
	}

	$finfo = new finfo( FILEINFO_MIME_TYPE );
	$mime = $finfo->file( $file['tmp_name'] );

	$allowed = [
		'application/pdf' => 'pdf',
		'image/png' => 'png',
	];

	if ( ! isset( $allowed[ $mime ] ) )
	{
		throw new RuntimeException( 'Only PDF and PNG files are allowed.' );
	}

	$upload_dir = __DIR__ . '/assets/FileUploads/ApplicantDocuments';

	if ( ! is_dir( $upload_dir ) && ! mkdir( $upload_dir, 0750, true ) && ! is_dir( $upload_dir ) )
	{
		throw new RuntimeException( 'Unable to create the applicant document directory.' );
	}

	// Prevent direct browser access to uploaded registration documents on Apache.
	$deny_file = $upload_dir . '/.htaccess';

	if ( ! file_exists( $deny_file ) )
	{
		@file_put_contents( $deny_file, "Require all denied\n" );
	}

	$stored_name = 'app-' . (int) $applicant_id . '-' . bin2hex( random_bytes( 12 ) ) . '.' . $allowed[ $mime ];
	$destination = $upload_dir . '/' . $stored_name;

	if ( ! move_uploaded_file( $file['tmp_name'], $destination ) )
	{
		throw new RuntimeException( 'Unable to save the uploaded document.' );
	}

	if ( $current_stored_name !== '' )
	{
		$old = $upload_dir . '/' . basename( $current_stored_name );

		if ( is_file( $old ) )
		{
			@unlink( $old );
		}
	}

	return [
		'stored_name' => $stored_name,
		'original_name' => basename( (string) $file['name'] ),
		'mime_type' => $mime,
		'file_size' => (int) $file['size'],
	];
}

$errors = [];
$notice = '';

if ( isset( $_GET['switch'] ) )
{
	unset( $_SESSION['abugida_applicant_id'], $_SESSION['abugida_applicant_phone'] );
	header( 'Location: registration.php' );
	exit;
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
{
	if ( empty( $_POST['csrf'] )
		|| ! hash_equals( $_SESSION['abugida_reg_csrf'], (string) $_POST['csrf'] ) )
	{
		$errors[] = 'Your session expired. Please refresh the page and try again.';
	}
	else
	{
		$action = isset( $_POST['action'] ) ? (string) $_POST['action'] : '';

		if ( $action === 'access' )
		{
			$phone = abugida_reg_phone( $_POST['phone'] ?? '' );

			if ( ! abugida_reg_valid_phone( $phone ) )
			{
				$errors[] = 'Enter a valid phone number.';
			}
			else
			{
				$ret = DBGet( "SELECT ID
					FROM abugida_applicants
					WHERE PHONE='" . DBEscapeString( $phone ) . "'
					ORDER BY ID DESC
					LIMIT 1" );

				if ( ! empty( $ret[1]['ID'] ) )
				{
					$applicant_id = (int) $ret[1]['ID'];
				}
				else
				{
					DBQuery( "INSERT INTO abugida_applicants
						(PHONE, STATUS, CURRENT_STEP, CREATED_AT, UPDATED_AT)
						VALUES
						('" . DBEscapeString( $phone ) . "', 'DRAFT', 1, NOW(), NOW())" );

					$applicant_id = (int) DBLastInsertID();
					$reference = 'ABG-APP-' . date( 'Y' ) . '-' . str_pad( (string) $applicant_id, 6, '0', STR_PAD_LEFT );

					DBQuery( "UPDATE abugida_applicants
						SET APPLICATION_REFERENCE='" . DBEscapeString( $reference ) . "'
						WHERE ID='" . $applicant_id . "'" );
				}

				$_SESSION['abugida_applicant_id'] = $applicant_id;
				$_SESSION['abugida_applicant_phone'] = $phone;

				header( 'Location: registration.php' );
				exit;
			}
		}
		elseif ( $action === 'save' || $action === 'submit' )
		{
			$applicant_id = (int) ( $_SESSION['abugida_applicant_id'] ?? 0 );
			$phone = (string) ( $_SESSION['abugida_applicant_phone'] ?? '' );
			$applicant = abugida_reg_fetch( $applicant_id, $phone );

			if ( ! $applicant )
			{
				$errors[] = 'Application session not found. Enter your phone number again.';
			}
			elseif ( $applicant['STATUS'] !== 'DRAFT' )
			{
				$errors[] = 'This application has already been submitted and cannot be edited.';
			}
			else
			{
				$first_name = trim( (string) ( $_POST['first_name'] ?? '' ) );
				$last_name = trim( (string) ( $_POST['last_name'] ?? '' ) );
				$email = trim( (string) ( $_POST['email'] ?? '' ) );
				$grade = (int) ( $_POST['grade_level'] ?? 0 );

				if ( $email !== '' && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
				{
					$errors[] = 'Enter a valid email address.';
				}

				if ( $grade !== 0 && ( $grade < 7 || $grade > 12 ) )
				{
					$errors[] = 'Select a grade from Grade 7 to Grade 12.';
				}

				$upload = null;

				if ( ! $errors )
				{
					try
					{
						$upload = abugida_reg_store_upload(
							$applicant_id,
							(string) ( $applicant['DOCUMENT_STORED_NAME'] ?? '' )
						);
					}
					catch ( RuntimeException $e )
					{
						$errors[] = $e->getMessage();
					}
				}

				$has_document = $upload || ! empty( $applicant['DOCUMENT_STORED_NAME'] );

				if ( $action === 'submit' )
				{
					if ( $first_name === '' )
					{
						$errors[] = 'First name is required.';
					}
					if ( $last_name === '' )
					{
						$errors[] = 'Last name is required.';
					}
					if ( $email === '' )
					{
						$errors[] = 'Email is required.';
					}
					if ( $grade < 7 || $grade > 12 )
					{
						$errors[] = 'Grade is required.';
					}
					if ( ! $has_document )
					{
						$errors[] = 'Upload the required PDF or PNG document.';
					}
				}

				if ( ! $errors )
				{
					$set = [
						"FIRST_NAME='" . DBEscapeString( $first_name ) . "'",
						"LAST_NAME='" . DBEscapeString( $last_name ) . "'",
						"EMAIL='" . DBEscapeString( $email ) . "'",
						'GRADE_LEVEL=' . ( $grade ? (int) $grade : 'NULL' ),
						'UPDATED_AT=NOW()',
					];

					if ( $upload )
					{
						$set[] = "DOCUMENT_STORED_NAME='" . DBEscapeString( $upload['stored_name'] ) . "'";
						$set[] = "DOCUMENT_ORIGINAL_NAME='" . DBEscapeString( $upload['original_name'] ) . "'";
						$set[] = "DOCUMENT_MIME_TYPE='" . DBEscapeString( $upload['mime_type'] ) . "'";
						$set[] = 'DOCUMENT_SIZE=' . (int) $upload['file_size'];
					}

					$current_step = 1;
					if ( $first_name !== '' && $last_name !== '' && $email !== '' )
					{
						$current_step = 2;
					}
					if ( $current_step >= 2 && $grade >= 7 && $grade <= 12 )
					{
						$current_step = 3;
					}
					if ( $has_document )
					{
						$current_step = 4;
					}

					$set[] = 'CURRENT_STEP=' . $current_step;

					if ( $action === 'submit' )
					{
						$set[] = "STATUS='SUBMITTED'";
						$set[] = 'SUBMITTED_AT=NOW()';
						$set[] = 'CURRENT_STEP=5';
					}

					DBQuery( "UPDATE abugida_applicants
						SET " . implode( ', ', $set ) . "
						WHERE ID='" . $applicant_id . "'
						AND PHONE='" . DBEscapeString( $phone ) . "'" );

					header( 'Location: registration.php?' . ( $action === 'submit' ? 'submitted=1' : 'saved=1' ) );
					exit;
				}
			}
		}
	}
}

$applicant = null;

if ( ! empty( $_SESSION['abugida_applicant_id'] )
	&& ! empty( $_SESSION['abugida_applicant_phone'] ) )
{
	$applicant = abugida_reg_fetch(
		(int) $_SESSION['abugida_applicant_id'],
		(string) $_SESSION['abugida_applicant_phone']
	);
}

if ( isset( $_GET['saved'] ) )
{
	$notice = 'Your progress has been saved. You can leave this page and continue later using the same phone number.';
}
elseif ( isset( $_GET['submitted'] ) )
{
	$notice = 'Your registration application has been submitted successfully.';
}

?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Online Registration | Abugida SIS</title>
	<style>
		:root{--ink:#152238;--muted:#667085;--line:#dfe4ea;--accent:#1769e0;--soft:#f5f7fb;--ok:#157347;--danger:#b42318}
		*{box-sizing:border-box}
		body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--soft);color:var(--ink)}
		.shell{max-width:900px;margin:48px auto;padding:0 20px}
		.brand{margin-bottom:24px}
		.brand h1{margin:0;font-size:30px}
		.brand p{margin:7px 0 0;color:var(--muted)}
		.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;box-shadow:0 6px 24px rgba(16,24,40,.06)}
		.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
		.full{grid-column:1/-1}
		label{display:block;font-weight:700;margin-bottom:7px}
		input,select{width:100%;padding:12px 13px;border:1px solid #cbd3dc;border-radius:8px;font-size:16px;background:#fff}
		input:focus,select:focus{outline:2px solid rgba(23,105,224,.18);border-color:var(--accent)}
		button,.button{border:0;border-radius:8px;padding:12px 18px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
		.primary{background:var(--accent);color:#fff}
		.secondary{background:#eef2f7;color:var(--ink)}
		.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:22px}
		.alert{padding:13px 15px;border-radius:8px;margin:0 0 20px}
		.alert.ok{background:#eaf6ef;color:var(--ok)}
		.alert.error{background:#fff0ef;color:var(--danger)}
		.progress{height:9px;background:#e9edf2;border-radius:99px;overflow:hidden;margin:9px 0 22px}
		.progress span{display:block;height:100%;background:var(--accent)}
		.meta{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;color:var(--muted);font-size:14px;margin-bottom:8px}
		.status{font-weight:700;color:var(--ink)}
		.help{font-size:13px;color:var(--muted);margin-top:6px}
		.readonly{background:#f8fafc}
		@media(max-width:680px){.shell{margin:24px auto}.card{padding:20px}.grid{grid-template-columns:1fr}.full{grid-column:auto}}
	</style>
</head>
<body>
<div class="shell">
	<div class="brand">
		<h1>Abugida SIS Online Registration</h1>
		<p>Start a new application or continue an unfinished registration using your phone number.</p>
	</div>

	<?php if ( $notice !== '' ) { ?>
		<div class="alert ok"><?php echo abugida_reg_h( $notice ); ?></div>
	<?php } ?>

	<?php if ( $errors ) { ?>
		<div class="alert error">
			<strong>Please correct the following:</strong>
			<ul>
				<?php foreach ( $errors as $error ) { ?><li><?php echo abugida_reg_h( $error ); ?></li><?php } ?>
			</ul>
		</div>
	<?php } ?>

	<?php if ( ! $applicant ) { ?>
		<div class="card">
			<h2>Start or continue registration</h2>
			<p>Enter the phone number you will use for this application. If you already started, your saved information will be loaded.</p>
			<form method="post">
				<input type="hidden" name="csrf" value="<?php echo abugida_reg_h( $_SESSION['abugida_reg_csrf'] ); ?>">
				<input type="hidden" name="action" value="access">
				<label for="phone">Phone Number</label>
				<input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0912345678" required>
				<div class="actions">
					<button type="submit" class="primary">Continue</button>
				</div>
			</form>
		</div>
	<?php } else { ?>
		<?php $progress = abugida_reg_progress( $applicant ); ?>
		<div class="card">
			<div class="meta">
				<span>Application: <strong><?php echo abugida_reg_h( $applicant['APPLICATION_REFERENCE'] ); ?></strong></span>
				<span class="status">Status: <?php echo abugida_reg_h( $applicant['STATUS'] ); ?></span>
			</div>
			<div class="progress"><span style="width:<?php echo (int) $progress; ?>%"></span></div>

			<?php if ( $applicant['STATUS'] === 'DRAFT' ) { ?>
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" name="csrf" value="<?php echo abugida_reg_h( $_SESSION['abugida_reg_csrf'] ); ?>">
					<div class="grid">
						<div>
							<label for="first_name">First Name *</label>
							<input id="first_name" name="first_name" value="<?php echo abugida_reg_h( $applicant['FIRST_NAME'] ); ?>" autocomplete="given-name">
						</div>
						<div>
							<label for="last_name">Last Name *</label>
							<input id="last_name" name="last_name" value="<?php echo abugida_reg_h( $applicant['LAST_NAME'] ); ?>" autocomplete="family-name">
						</div>
						<div>
							<label for="phone_display">Phone Number</label>
							<input id="phone_display" class="readonly" value="<?php echo abugida_reg_h( $applicant['PHONE'] ); ?>" readonly>
						</div>
						<div>
							<label for="email">Email *</label>
							<input id="email" name="email" type="email" value="<?php echo abugida_reg_h( $applicant['EMAIL'] ); ?>" autocomplete="email">
						</div>
						<div>
							<label for="grade_level">Grade *</label>
							<select id="grade_level" name="grade_level">
								<option value="">Select grade</option>
								<?php for ( $grade = 7; $grade <= 12; $grade++ ) { ?>
									<option value="<?php echo $grade; ?>"<?php echo (int) $applicant['GRADE_LEVEL'] === $grade ? ' selected' : ''; ?>>Grade <?php echo $grade; ?></option>
								<?php } ?>
							</select>
						</div>
						<div>
							<label for="document">Supporting Document *</label>
							<input id="document" name="document" type="file" accept=".pdf,.png,application/pdf,image/png">
							<div class="help">PDF or PNG, maximum 5 MB.</div>
							<?php if ( ! empty( $applicant['DOCUMENT_ORIGINAL_NAME'] ) ) { ?>
								<div class="help">Current file: <?php echo abugida_reg_h( $applicant['DOCUMENT_ORIGINAL_NAME'] ); ?></div>
							<?php } ?>
						</div>
					</div>
					<div class="actions">
						<button type="submit" name="action" value="save" class="secondary">Save and continue later</button>
						<button type="submit" name="action" value="submit" class="primary">Submit application</button>
						<a class="button secondary" href="registration.php?switch=1">Use another phone number</a>
					</div>
				</form>
			<?php } else { ?>
				<h2>Application submitted</h2>
				<p>Your application is no longer editable from this page. The Registrar will review the submitted information.</p>
				<div class="grid">
					<div><label>First Name</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['FIRST_NAME'] ); ?>" readonly></div>
					<div><label>Last Name</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['LAST_NAME'] ); ?>" readonly></div>
					<div><label>Phone</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['PHONE'] ); ?>" readonly></div>
					<div><label>Email</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['EMAIL'] ); ?>" readonly></div>
					<div><label>Grade</label><input class="readonly" value="Grade <?php echo (int) $applicant['GRADE_LEVEL']; ?>" readonly></div>
					<div><label>Document</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['DOCUMENT_ORIGINAL_NAME'] ); ?>" readonly></div>
				</div>
				<div class="actions">
					<a class="button secondary" href="registration.php?switch=1">Use another phone number</a>
				</div>
			<?php } ?>
		</div>
	<?php } ?>
</div>
</body>
</html>
