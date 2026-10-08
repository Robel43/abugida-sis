<?php
/**
 * Abugida SIS public online registration.
 *
 * Applicants are kept separate from permanent student records.
 * No permanent student or user account is created on this page.
 */

require_once __DIR__ . '/Warehouse.php';

const ABUGIDA_REG_MAX_UPLOAD_BYTES = 5242880;

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
		! empty( $applicant['STUDY_APPROACH'] ),
		! empty( $applicant['DOCUMENT_STORED_NAME'] ),
		! empty( $applicant['FAYDA_STORED_NAME'] ),
	];

	$done = count( array_filter( $checks ) );

	return (int) round( ( $done / count( $checks ) ) * 100 );
}

function abugida_reg_store_upload( $input_name, $applicant_id, $prefix, $current_stored_name = '' )
{
	if ( empty( $_FILES[ $input_name ] )
		|| $_FILES[ $input_name ]['error'] === UPLOAD_ERR_NO_FILE )
	{
		return null;
	}

	$file = $_FILES[ $input_name ];

	if ( $file['error'] !== UPLOAD_ERR_OK )
	{
		throw new RuntimeException( 'The upload failed. Please try again.' );
	}

	if ( (int) $file['size'] > ABUGIDA_REG_MAX_UPLOAD_BYTES )
	{
		throw new RuntimeException( 'Each file must be 5 MB or smaller.' );
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

	$deny_file = $upload_dir . '/.htaccess';

	if ( ! file_exists( $deny_file ) )
	{
		@file_put_contents( $deny_file, "Require all denied\n" );
	}

	$stored_name = $prefix . '-' . (int) $applicant_id . '-' . bin2hex( random_bytes( 12 ) ) . '.' . $allowed[ $mime ];
	$destination = $upload_dir . '/' . $stored_name;

	if ( ! move_uploaded_file( $file['tmp_name'], $destination ) )
	{
		throw new RuntimeException( 'Unable to save the uploaded file.' );
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
				$study_approach = trim( (string) ( $_POST['study_approach'] ?? '' ) );

				if ( $email !== '' && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
				{
					$errors[] = 'Enter a valid email address.';
				}

				if ( $grade !== 0 && ( $grade < 7 || $grade > 12 ) )
				{
					$errors[] = 'Select a grade from Grade 7 to Grade 12.';
				}

				if ( $study_approach !== '' && ! in_array( $study_approach, [ 'ONLINE', 'DISTANCE_LEARNING' ], true ) )
				{
					$errors[] = 'Select a valid learning approach.';
				}

				$document_upload = null;
				$fayda_upload = null;

				if ( ! $errors )
				{
					try
					{
						$document_upload = abugida_reg_store_upload(
							'document',
							$applicant_id,
							'document',
							(string) ( $applicant['DOCUMENT_STORED_NAME'] ?? '' )
						);

						$fayda_upload = abugida_reg_store_upload(
							'fayda_id',
							$applicant_id,
							'fayda',
							(string) ( $applicant['FAYDA_STORED_NAME'] ?? '' )
						);
					}
					catch ( RuntimeException $e )
					{
						$errors[] = $e->getMessage();
					}
				}

				$has_document = $document_upload || ! empty( $applicant['DOCUMENT_STORED_NAME'] );
				$has_fayda = $fayda_upload || ! empty( $applicant['FAYDA_STORED_NAME'] );

				if ( $action === 'submit' )
				{
					if ( $first_name === '' ) $errors[] = 'First name is required.';
					if ( $last_name === '' ) $errors[] = 'Last name is required.';
					if ( $email === '' ) $errors[] = 'Email is required.';
					if ( $grade < 7 || $grade > 12 ) $errors[] = 'Grade is required.';
					if ( $study_approach === '' ) $errors[] = 'Select Online or Distance Learning.';
					if ( ! $has_document ) $errors[] = 'Upload the required supporting document.';
					if ( ! $has_fayda ) $errors[] = 'Upload the Fayda ID file.';
				}

				if ( ! $errors )
				{
					$set = [
						"FIRST_NAME='" . DBEscapeString( $first_name ) . "'",
						"LAST_NAME='" . DBEscapeString( $last_name ) . "'",
						"EMAIL='" . DBEscapeString( $email ) . "'",
						'GRADE_LEVEL=' . ( $grade ? (int) $grade : 'NULL' ),
						"STUDY_APPROACH='" . DBEscapeString( $study_approach ) . "'",
						'UPDATED_AT=NOW()',
					];

					if ( $document_upload )
					{
						$set[] = "DOCUMENT_STORED_NAME='" . DBEscapeString( $document_upload['stored_name'] ) . "'";
						$set[] = "DOCUMENT_ORIGINAL_NAME='" . DBEscapeString( $document_upload['original_name'] ) . "'";
						$set[] = "DOCUMENT_MIME_TYPE='" . DBEscapeString( $document_upload['mime_type'] ) . "'";
						$set[] = 'DOCUMENT_SIZE=' . (int) $document_upload['file_size'];
					}

					if ( $fayda_upload )
					{
						$set[] = "FAYDA_STORED_NAME='" . DBEscapeString( $fayda_upload['stored_name'] ) . "'";
						$set[] = "FAYDA_ORIGINAL_NAME='" . DBEscapeString( $fayda_upload['original_name'] ) . "'";
						$set[] = "FAYDA_MIME_TYPE='" . DBEscapeString( $fayda_upload['mime_type'] ) . "'";
						$set[] = 'FAYDA_SIZE=' . (int) $fayda_upload['file_size'];
					}

					$current_step = 1;
					if ( $first_name !== '' && $last_name !== '' && $email !== '' ) $current_step = 2;
					if ( $current_step >= 2 && $grade >= 7 && $grade <= 12 && $study_approach !== '' ) $current_step = 3;
					if ( $has_document && $has_fayda ) $current_step = 4;

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
	$notice = 'Progress saved. You can return later with the same phone number.';
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
		:root{
			--navy:#0f1f38;
			--blue:#2563eb;
			--blue2:#1d4ed8;
			--sky:#eef4ff;
			--ink:#182230;
			--muted:#667085;
			--line:#dbe3ef;
			--surface:#ffffff;
			--bg:#f4f7fb;
			--ok:#087443;
			--danger:#b42318;
		}
		*{box-sizing:border-box}
		body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#eef4ff 0,#f7f9fc 45%,#eef2f8 100%);color:var(--ink);min-height:100vh}
		.page{max-width:1120px;margin:0 auto;padding:42px 20px 64px}
		.topbar{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:26px}
		.identity{display:flex;align-items:center;gap:14px}
		.mark{width:48px;height:48px;border-radius:14px;background:linear-gradient(145deg,var(--blue),#60a5fa);display:grid;place-items:center;color:#fff;font-size:23px;font-weight:800;box-shadow:0 10px 24px rgba(37,99,235,.24)}
		.identity h1{font-size:22px;margin:0;color:var(--navy)}
		.identity p{font-size:13px;color:var(--muted);margin:3px 0 0}
		.secure{font-size:13px;color:var(--muted);background:rgba(255,255,255,.75);border:1px solid var(--line);padding:8px 12px;border-radius:999px}
		.hero{display:grid;grid-template-columns:minmax(0,.82fr) minmax(0,1.18fr);background:var(--surface);border:1px solid rgba(219,227,239,.9);border-radius:24px;overflow:hidden;box-shadow:0 24px 70px rgba(15,31,56,.10)}
		.intro{padding:44px;background:linear-gradient(155deg,#102345 0%,#17356b 60%,#1d4ed8 140%);color:#fff;position:relative}
		.intro:after{content:"";position:absolute;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.06);right:-80px;bottom:-80px}
		.kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 10px;border-radius:999px;background:rgba(255,255,255,.11);font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
		.intro h2{font-size:36px;line-height:1.12;margin:24px 0 14px;letter-spacing:-.02em}
		.intro p{margin:0;color:#d9e5ff;line-height:1.7}
		.steps{margin:30px 0 0;padding:0;list-style:none;display:grid;gap:15px}
		.steps li{display:flex;gap:12px;align-items:flex-start;color:#e8efff}
		.stepdot{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.13);display:grid;place-items:center;font-size:12px;font-weight:800;flex:0 0 26px}
		.panel{padding:38px}
		.panel-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:24px}
		.panel-head h3{margin:0;font-size:25px;color:var(--navy)}
		.panel-head p{margin:6px 0 0;color:var(--muted);line-height:1.6;font-size:14px}
		.badge{background:var(--sky);color:var(--blue2);border-radius:999px;padding:7px 10px;font-size:12px;font-weight:800;white-space:nowrap}
		.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
		.full{grid-column:1/-1}
		.field label{display:block;font-size:13px;font-weight:750;margin-bottom:7px;color:#344054}
		input,select{width:100%;padding:13px 14px;border:1px solid #cfd8e6;border-radius:11px;font-size:15px;background:#fff;color:var(--ink);transition:.18s ease}
		input:focus,select:focus{outline:none;border-color:#4f83f1;box-shadow:0 0 0 4px rgba(37,99,235,.10)}
		input[type=file]{padding:10px;background:#fbfcfe}
		.readonly{background:#f7f9fc}
		.upload-card{border:1px dashed #b8c6dc;border-radius:14px;padding:16px;background:#fafcff}
		.upload-title{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:5px}
		.upload-title strong{font-size:14px}
		.file-chip{font-size:11px;font-weight:800;background:#eef4ff;color:#2457c5;padding:5px 8px;border-radius:999px}
		.help{font-size:12px;color:var(--muted);margin-top:7px;line-height:1.5}
		.current{margin-top:8px;font-size:12px;color:var(--ok);font-weight:700}
		button,.button{border:0;border-radius:11px;padding:13px 18px;font-size:14px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:.18s ease}
		.primary{background:var(--blue);color:#fff;box-shadow:0 8px 18px rgba(37,99,235,.20)}
		.primary:hover{background:var(--blue2);transform:translateY(-1px)}
		.secondary{background:#eef2f7;color:#344054}
		.secondary:hover{background:#e5ebf3}
		.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:26px}
		.alert{padding:13px 16px;border-radius:11px;margin:0 0 18px;font-size:14px}
		.alert.ok{background:#eaf7ef;color:var(--ok);border:1px solid #c7ead5}
		.alert.error{background:#fff0ef;color:var(--danger);border:1px solid #f5c7c2}
		.progress-wrap{margin-bottom:26px}
		.progress-row{display:flex;justify-content:space-between;gap:10px;color:var(--muted);font-size:12px;margin-bottom:7px}
		.progress{height:8px;background:#e8edf5;border-radius:999px;overflow:hidden}
		.progress span{display:block;height:100%;background:linear-gradient(90deg,var(--blue),#60a5fa);border-radius:999px}
		.meta{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px}
		.meta-card{background:#f8fafc;border:1px solid #e6ebf2;border-radius:11px;padding:10px 12px;font-size:12px;color:var(--muted)}
		.meta-card strong{color:var(--ink)}
		.submitted{padding:18px;border-radius:14px;background:#f7fafc;border:1px solid var(--line);margin-bottom:20px}
		.footer-note{text-align:center;color:var(--muted);font-size:12px;margin-top:18px}
		@media(max-width:860px){.hero{grid-template-columns:1fr}.intro{padding:30px}.intro h2{font-size:30px}.panel{padding:28px}}
		@media(max-width:640px){.page{padding:20px 14px 40px}.topbar{align-items:flex-start}.secure{display:none}.grid{grid-template-columns:1fr}.full{grid-column:auto}.intro{padding:25px}.panel{padding:22px}.actions{flex-direction:column}.actions button,.actions .button{width:100%}}
	</style>
</head>
<body>
<div class="page">
	<div class="topbar">
		<div class="identity">
			<div class="mark">A</div>
			<div>
				<h1>Abugida SIS</h1>
				<p>Online student registration</p>
			</div>
		</div>
		<div class="secure">Registration portal</div>
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

	<div class="hero">
		<aside class="intro">
			<span class="kicker">Student application</span>
			<h2>Register for the new academic year.</h2>
			<p>Complete the form once, save your progress whenever you need, and return later using the same phone number.</p>
			<ul class="steps">
				<li><span class="stepdot">1</span><span>Enter your contact and personal information.</span></li>
				<li><span class="stepdot">2</span><span>Select your grade and preferred learning approach.</span></li>
				<li><span class="stepdot">3</span><span>Upload your supporting document and Fayda ID.</span></li>
				<li><span class="stepdot">4</span><span>Review and submit your application.</span></li>
			</ul>
		</aside>

		<main class="panel">
		<?php if ( ! $applicant ) { ?>
			<div class="panel-head">
				<div>
					<h3>Start or continue</h3>
					<p>Use the same phone number each time. Your unfinished registration will be restored automatically.</p>
				</div>
				<span class="badge">Step 1</span>
			</div>
			<form method="post">
				<input type="hidden" name="csrf" value="<?php echo abugida_reg_h( $_SESSION['abugida_reg_csrf'] ); ?>">
				<input type="hidden" name="action" value="access">
				<div class="field">
					<label for="phone">Phone Number</label>
					<input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0912 345 678" required>
					<div class="help">Use a phone number you can remember; it is used to resume your saved application.</div>
				</div>
				<div class="actions">
					<button type="submit" class="primary">Continue registration</button>
				</div>
			</form>
		<?php } else { ?>
			<?php $progress = abugida_reg_progress( $applicant ); ?>
			<div class="panel-head">
				<div>
					<h3><?php echo $applicant['STATUS'] === 'DRAFT' ? 'Registration details' : 'Application submitted'; ?></h3>
					<p><?php echo $applicant['STATUS'] === 'DRAFT' ? 'Complete all required information, then submit when you are ready.' : 'Your information has been saved and is awaiting Registrar review.'; ?></p>
				</div>
				<span class="badge"><?php echo abugida_reg_h( $applicant['STATUS'] ); ?></span>
			</div>

			<div class="meta">
				<div class="meta-card">Reference<br><strong><?php echo abugida_reg_h( $applicant['APPLICATION_REFERENCE'] ); ?></strong></div>
				<div class="meta-card">Phone<br><strong><?php echo abugida_reg_h( $applicant['PHONE'] ); ?></strong></div>
			</div>

			<div class="progress-wrap">
				<div class="progress-row"><span>Application progress</span><strong><?php echo (int) $progress; ?>%</strong></div>
				<div class="progress"><span style="width:<?php echo (int) $progress; ?>%"></span></div>
			</div>

			<?php if ( $applicant['STATUS'] === 'DRAFT' ) { ?>
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" name="csrf" value="<?php echo abugida_reg_h( $_SESSION['abugida_reg_csrf'] ); ?>">
					<div class="grid">
						<div class="field">
							<label for="first_name">First Name *</label>
							<input id="first_name" name="first_name" value="<?php echo abugida_reg_h( $applicant['FIRST_NAME'] ); ?>" autocomplete="given-name">
						</div>
						<div class="field">
							<label for="last_name">Last Name *</label>
							<input id="last_name" name="last_name" value="<?php echo abugida_reg_h( $applicant['LAST_NAME'] ); ?>" autocomplete="family-name">
						</div>
						<div class="field">
							<label for="email">Email Address *</label>
							<input id="email" name="email" type="email" value="<?php echo abugida_reg_h( $applicant['EMAIL'] ); ?>" autocomplete="email" placeholder="student@example.com">
						</div>
						<div class="field">
							<label for="grade_level">Applying for *</label>
							<select id="grade_level" name="grade_level">
								<option value="">Select grade</option>
								<?php for ( $grade = 7; $grade <= 12; $grade++ ) { ?>
									<option value="<?php echo $grade; ?>"<?php echo (int) $applicant['GRADE_LEVEL'] === $grade ? ' selected' : ''; ?>>Grade <?php echo $grade; ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="field full">
							<label for="study_approach">Learning Approach *</label>
							<select id="study_approach" name="study_approach">
								<option value="">Select learning approach</option>
								<option value="ONLINE"<?php echo $applicant['STUDY_APPROACH'] === 'ONLINE' ? ' selected' : ''; ?>>Online</option>
								<option value="DISTANCE_LEARNING"<?php echo $applicant['STUDY_APPROACH'] === 'DISTANCE_LEARNING' ? ' selected' : ''; ?>>Distance Learning</option>
							</select>
							<div class="help">Choose how you want to attend your studies.</div>
						</div>

						<div class="upload-card">
							<div class="upload-title"><strong>Supporting Document *</strong><span class="file-chip">PDF / PNG</span></div>
							<input id="document" name="document" type="file" accept=".pdf,.png,application/pdf,image/png">
							<div class="help">Upload the required supporting document. Maximum 5 MB.</div>
							<?php if ( ! empty( $applicant['DOCUMENT_ORIGINAL_NAME'] ) ) { ?>
								<div class="current">Uploaded: <?php echo abugida_reg_h( $applicant['DOCUMENT_ORIGINAL_NAME'] ); ?></div>
							<?php } ?>
						</div>

						<div class="upload-card">
							<div class="upload-title"><strong>Fayda ID *</strong><span class="file-chip">PDF / PNG</span></div>
							<input id="fayda_id" name="fayda_id" type="file" accept=".pdf,.png,application/pdf,image/png">
							<div class="help">Upload a clear PDF or PNG copy of the applicant's Fayda ID. Maximum 5 MB.</div>
							<?php if ( ! empty( $applicant['FAYDA_ORIGINAL_NAME'] ) ) { ?>
								<div class="current">Uploaded: <?php echo abugida_reg_h( $applicant['FAYDA_ORIGINAL_NAME'] ); ?></div>
							<?php } ?>
						</div>
					</div>
					<div class="actions">
						<button type="submit" name="action" value="submit" class="primary">Submit application</button>
						<button type="submit" name="action" value="save" class="secondary">Save for later</button>
						<a class="button secondary" href="registration.php?switch=1">Change phone number</a>
					</div>
				</form>
			<?php } else { ?>
				<div class="submitted">
					<strong>Application received.</strong>
					<p>The application is now read-only while it awaits Registrar review.</p>
				</div>
				<div class="grid">
					<div class="field"><label>First Name</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['FIRST_NAME'] ); ?>" readonly></div>
					<div class="field"><label>Last Name</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['LAST_NAME'] ); ?>" readonly></div>
					<div class="field"><label>Email</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['EMAIL'] ); ?>" readonly></div>
					<div class="field"><label>Grade</label><input class="readonly" value="Grade <?php echo (int) $applicant['GRADE_LEVEL']; ?>" readonly></div>
					<div class="field"><label>Learning Approach</label><input class="readonly" value="<?php echo $applicant['STUDY_APPROACH'] === 'DISTANCE_LEARNING' ? 'Distance Learning' : 'Online'; ?>" readonly></div>
					<div class="field"><label>Supporting Document</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['DOCUMENT_ORIGINAL_NAME'] ); ?>" readonly></div>
					<div class="field"><label>Fayda ID</label><input class="readonly" value="<?php echo abugida_reg_h( $applicant['FAYDA_ORIGINAL_NAME'] ); ?>" readonly></div>
				</div>
				<div class="actions">
					<a class="button secondary" href="registration.php?switch=1">Use another phone number</a>
				</div>
			<?php } ?>
		<?php } ?>
		</main>
	</div>
	<div class="footer-note">Abugida SIS • Student Registration Portal</div>
</div>
</body>
</html>
