<?php
/**
 * Public student landing page.
 *
 * Entry point for prospective and existing students.
 * Layout inspired by the Select Academy public site while preserving
 * configurable Abugida branding and SIS-specific actions.
 */

require_once __DIR__ . '/Warehouse.php';
require_once __DIR__ . '/ProgramFunctions/AbugidaBranding.fnc.php';

$brand = AbugidaBranding();
$platform = $brand['platform_name'];

function abugida_landing_h( $value )
{
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

$logo_url = AbugidaBrandingUrl( $brand['logo'] );
$favicon_url = AbugidaBrandingUrl( $brand['favicon'] );

$reference_assets = [
	'hero' => 'https://selectacademy.edu.et/wp-content/uploads/2024/12/girls-with-mobi-green.jpg',
	'icon_courses' => 'https://selectacademy.edu.et/wp-content/uploads/2024/11/Icon-1.png',
	'icon_students' => 'https://selectacademy.edu.et/wp-content/uploads/2024/11/Icon-2.png',
	'icon_teachers' => 'https://selectacademy.edu.et/wp-content/uploads/2024/11/Icon-4.png',
	'grade12_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2026/01/3.png',
	'grade12_social' => 'https://selectacademy.edu.et/wp-content/uploads/2026/01/2.png',
	'grade11_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2026/01/5.png',
	'grade11_social' => 'https://selectacademy.edu.et/wp-content/uploads/2026/01/4.png',
	'grade10' => 'https://selectacademy.edu.et/wp-content/uploads/2026/01/6.png',
	'grade9' => 'https://selectacademy.edu.et/wp-content/uploads/2026/01/7.png',
];
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo abugida_landing_h( $platform ); ?></title>
	<link rel="icon" href="<?php echo abugida_landing_h( $favicon_url ); ?>">
	<link rel="stylesheet" href="assets/css/abugida-landing-2026.css?v=<?php echo (int) @filemtime( __DIR__ . '/assets/css/abugida-landing-2026.css' ); ?>">
	<style>:root{--landing-primary:<?php echo abugida_landing_h( $brand['primary'] ); ?>;--landing-secondary:<?php echo abugida_landing_h( $brand['secondary'] ); ?>}</style>
</head>
<body>
	<div class="academy-top">
		<div class="landing-container academy-top__inner">
			<a class="academy-logo" href="landing.php">
				<img src="<?php echo abugida_landing_h( $logo_url ); ?>" alt="<?php echo abugida_landing_h( $platform ); ?>">
				<span><?php echo abugida_landing_h( strtoupper( $platform ) ); ?></span>
			</a>
			<div class="academy-top__contact">
				<span>Student Information System</span>
				<a href="index.php?login=1">Login</a>
				<a href="registration.php">Register</a>
			</div>
		</div>
	</div>

	<header class="academy-header">
		<div class="landing-container academy-header__inner">
			<nav class="academy-menu" aria-label="Primary">
				<a class="is-active" href="#home">Home</a>
				<a href="#grades">Grades</a>
				<a href="#services">Student Services</a>
				<a href="#how-it-works">How It Works</a>
				<a href="#about">About</a>
			</nav>
			<div class="academy-account">
				<a href="index.php?login=1">Login</a>
				<a class="academy-register" href="registration.php">Register</a>
			</div>
		</div>
	</header>

	<main>
		<section class="academy-hero" id="home">
			<div class="landing-container academy-hero__grid">
				<div class="academy-hero__copy">
					<span class="academy-kicker"><?php echo abugida_landing_h( $platform ); ?></span>
					<h1>Your choice for <span>simple, secure student services</span></h1>
					<p>Apply online, access your student portal, follow grades and payments, and continue into the next semester or academic year from one responsive system.</p>
					<div class="academy-hero__actions">
						<a class="academy-btn academy-btn--primary" href="registration.php">Register Now</a>
						<a class="academy-btn academy-btn--outline" href="index.php?login=1">Student Login</a>
					</div>
				</div>
				<div class="academy-hero__image">
					<img src="<?php echo abugida_landing_h( $reference_assets['hero'] ); ?>" alt="Students learning with a mobile device">
				</div>
			</div>
		</section>

		<section class="academy-stats" aria-label="Platform highlights">
			<div class="landing-container academy-stats__grid">
				<article>
					<img src="<?php echo abugida_landing_h( $reference_assets['icon_courses'] ); ?>" alt="">
					<div><strong>Online</strong><span>Student Registration</span></div>
				</article>
				<article>
					<img src="<?php echo abugida_landing_h( $reference_assets['icon_teachers'] ); ?>" alt="">
					<div><strong>24/7</strong><span>Portal Access</span></div>
				</article>
				<article>
					<img src="<?php echo abugida_landing_h( $reference_assets['icon_students'] ); ?>" alt="">
					<div><strong>1 Account</strong><span>Across Academic Years</span></div>
				</article>
			</div>
		</section>

		<section class="academy-section" id="grades">
			<div class="landing-container">
				<div class="academy-section__heading">
					<span>Academic access</span>
					<h2>One portal for every stage of the student journey</h2>
					<p>Use the same identity from initial application through enrollment, academic reporting and re-registration.</p>
				</div>

				<div class="academy-package-grid">
					<article class="academy-package-card">
						<img src="<?php echo abugida_landing_h( $reference_assets['grade9'] ); ?>" alt="Grade 9">
						<div><span>Grade 9</span><h3>Start strong</h3><p>Registration, academic records and student portal access.</p></div>
					</article>
					<article class="academy-package-card">
						<img src="<?php echo abugida_landing_h( $reference_assets['grade10'] ); ?>" alt="Grade 10">
						<div><span>Grade 10</span><h3>Stay connected</h3><p>Keep track of your subjects, payments and official results.</p></div>
					</article>
					<article class="academy-package-card">
						<img src="<?php echo abugida_landing_h( $reference_assets['grade11_natural'] ); ?>" alt="Grade 11">
						<div><span>Grade 11</span><h3>Progress with confidence</h3><p>Access academic information and continue enrollment securely.</p></div>
					</article>
					<article class="academy-package-card">
						<img src="<?php echo abugida_landing_h( $reference_assets['grade12_natural'] ); ?>" alt="Grade 12">
						<div><span>Grade 12</span><h3>Finish prepared</h3><p>Review your official academic record and complete your final-year services.</p></div>
					</article>
				</div>
			</div>
		</section>

		<section class="academy-section academy-section--soft" id="services">
			<div class="landing-container">
				<div class="academy-section__heading">
					<span>Student services</span>
					<h2>Everything students need, clearly organized</h2>
				</div>
				<div class="academy-service-grid">
					<article>
						<div class="academy-service-icon">01</div>
						<h3>New Student Registration</h3>
						<p>Submit an application, upload required documents, follow review and complete payment.</p>
						<a href="registration.php">Start registration</a>
					</article>
					<article>
						<div class="academy-service-icon">02</div>
						<h3>Student Portal</h3>
						<p>View courses, official grades, class rank, profile details and payment history.</p>
						<a href="index.php?login=1">Login to portal</a>
					</article>
					<article>
						<div class="academy-service-icon">03</div>
						<h3>Re-Registration</h3>
						<p>Continue into a future semester or academic year without creating a new account.</p>
						<a href="index.php?login=1">Continue studies</a>
					</article>
				</div>
			</div>
		</section>

		<section class="academy-section academy-process" id="how-it-works">
			<div class="landing-container academy-process__grid">
				<div class="academy-process__visual">
					<img src="<?php echo abugida_landing_h( $reference_assets['grade12_social'] ); ?>" alt="Student registration and portal">
				</div>
				<div>
					<div class="academy-section__heading academy-section__heading--left">
						<span>How it works</span>
						<h2>A straightforward path from application to active student</h2>
					</div>
					<div class="academy-steps">
						<div><b>1</b><span><strong>Apply online</strong><small>Complete the registration form and submit your information.</small></span></div>
						<div><b>2</b><span><strong>Registrar review</strong><small>Your application and documents are reviewed by the school.</small></span></div>
						<div><b>3</b><span><strong>Complete payment</strong><small>Upload the required receipt when your application is approved.</small></span></div>
						<div><b>4</b><span><strong>Access your account</strong><small>Use the same student identity for portal access and future re-registration.</small></span></div>
					</div>
				</div>
			</div>
		</section>

		<section class="academy-cta" id="about">
			<div class="landing-container academy-cta__inner">
				<div>
					<span><?php echo abugida_landing_h( $platform ); ?></span>
					<h2>Ready to begin your student journey?</h2>
					<p>Register as a new student or sign in with your existing account.</p>
				</div>
				<div class="academy-cta__actions">
					<a class="academy-btn academy-btn--light" href="registration.php">Register</a>
					<a class="academy-btn academy-btn--outline-light" href="index.php?login=1">Login</a>
				</div>
			</div>
		</section>
	</main>

	<footer class="academy-footer">
		<div class="landing-container academy-footer__inner">
			<div class="academy-footer__brand">
				<img src="<?php echo abugida_landing_h( $logo_url ); ?>" alt="<?php echo abugida_landing_h( $platform ); ?>">
				<div><strong><?php echo abugida_landing_h( $platform ); ?></strong><span>Student Information System</span></div>
			</div>
			<nav>
				<a href="#home">Home</a>
				<a href="registration.php">Register</a>
				<a href="index.php?login=1">Login</a>
			</nav>
			<p>&copy; <?php echo date( 'Y' ); ?> <?php echo abugida_landing_h( $platform ); ?></p>
		</div>
	</footer>
</body>
</html>
