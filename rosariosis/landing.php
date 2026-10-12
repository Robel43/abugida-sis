<?php
/**
 * Public student landing page.
 *
 * Entry point for prospective and existing students.
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
	<div class="landing-topbar">
		<div class="landing-container landing-topbar__inner">
			<div class="landing-topbar__brand">
				<img src="<?php echo abugida_landing_h( $logo_url ); ?>" alt="<?php echo abugida_landing_h( $platform ); ?>">
				<span><?php echo abugida_landing_h( $platform ); ?></span>
			</div>
			<div class="landing-topbar__meta">
				<span>Student Information System</span>
				<a href="index.php?login=1">Login</a>
			</div>
		</div>
	</div>

	<header class="landing-nav">
		<div class="landing-container landing-nav__inner">
			<a class="landing-brand" href="landing.php">
				<img src="<?php echo abugida_landing_h( $logo_url ); ?>" alt="<?php echo abugida_landing_h( $platform ); ?>">
				<div>
					<strong><?php echo abugida_landing_h( $platform ); ?></strong>
					<span><?php echo $brand['tagline'] ? abugida_landing_h( $brand['tagline'] ) : 'Your school journey, in one place'; ?></span>
				</div>
			</a>

			<nav class="landing-nav__links" aria-label="Primary">
				<a href="#home">Home</a>
				<a href="#services">Student Services</a>
				<a href="#how-it-works">How It Works</a>
				<a href="#about">About</a>
			</nav>

			<div class="landing-nav__actions">
				<a class="landing-btn landing-btn--ghost" href="index.php?login=1">Login</a>
				<a class="landing-btn landing-btn--primary" href="registration.php">Register</a>
			</div>
		</div>
	</header>

	<main>
		<section class="landing-hero" id="home">
			<div class="landing-container landing-hero__grid">
				<div class="landing-hero__content">
					<span class="landing-eyebrow">Student portal and registration</span>
					<h1>Start your academic journey with <span><?php echo abugida_landing_h( $platform ); ?></span></h1>
					<p>Register as a new student, sign in to your existing account, follow your academic progress, view grades and payments, and complete re-registration from one secure place.</p>
					<div class="landing-hero__actions">
						<a class="landing-btn landing-btn--primary landing-btn--large" href="registration.php">Register Now</a>
						<a class="landing-btn landing-btn--light landing-btn--large" href="index.php?login=1">Student Login</a>
					</div>
					<div class="landing-hero__trust">
						<span>✓ Secure registration</span>
						<span>✓ Academic records</span>
						<span>✓ Payment tracking</span>
					</div>
				</div>

				<div class="landing-hero__visual" aria-hidden="true">
					<div class="landing-hero__orb landing-hero__orb--one"></div>
					<div class="landing-hero__orb landing-hero__orb--two"></div>
					<div class="landing-portal-card">
						<div class="landing-portal-card__head">
							<img src="<?php echo abugida_landing_h( $logo_url ); ?>" alt="">
							<div><strong><?php echo abugida_landing_h( $platform ); ?></strong><span>Student Dashboard</span></div>
						</div>
						<div class="landing-portal-card__stats">
							<div><span>Academic year</span><strong>2026</strong></div>
							<div><span>Courses</span><strong>8</strong></div>
							<div><span>Status</span><strong>Active</strong></div>
						</div>
						<div class="landing-portal-card__progress">
							<span>Academic progress</span>
							<div><i></i></div>
						</div>
						<div class="landing-portal-card__rows">
							<span></span><span></span><span></span>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="landing-stats">
			<div class="landing-container landing-stats__grid">
				<div><strong>Online</strong><span>Student Registration</span></div>
				<div><strong>24/7</strong><span>Portal Access</span></div>
				<div><strong>1</strong><span>Student Account</span></div>
				<div><strong>100%</strong><span>Responsive Experience</span></div>
			</div>
		</section>

		<section class="landing-section" id="services">
			<div class="landing-container">
				<div class="landing-section__heading">
					<span>Student services</span>
					<h2>Everything you need in one place</h2>
					<p>Use the same platform from your first application through future-year re-registration.</p>
				</div>

				<div class="landing-cards">
					<article class="landing-card">
						<div class="landing-card__icon">01</div>
						<h3>New Student Registration</h3>
						<p>Start an application, save progress, submit documents, follow approval, and complete payment.</p>
						<a href="registration.php">Start registration →</a>
					</article>
					<article class="landing-card">
						<div class="landing-card__icon">02</div>
						<h3>Student Portal</h3>
						<p>View your courses, official grades, class rank, payments, profile, and academic information.</p>
						<a href="index.php?login=1">Login to portal →</a>
					</article>
					<article class="landing-card">
						<div class="landing-card__icon">03</div>
						<h3>Re-Registration</h3>
						<p>Continue into the next semester or academic year without creating a new account.</p>
						<a href="index.php?login=1">Continue studies →</a>
					</article>
				</div>
			</div>
		</section>

		<section class="landing-section landing-section--muted" id="how-it-works">
			<div class="landing-container landing-flow">
				<div class="landing-section__heading landing-section__heading--left">
					<span>Simple process</span>
					<h2>From application to active student account</h2>
					<p>Designed to make the school registration process clear for students and families.</p>
				</div>
				<div class="landing-flow__steps">
					<div><b>1</b><strong>Apply</strong><span>Complete the online student registration form.</span></div>
					<div><b>2</b><strong>Review</strong><span>The Registrar reviews your application and documents.</span></div>
					<div><b>3</b><strong>Pay</strong><span>Submit the required payment receipt when approved.</span></div>
					<div><b>4</b><strong>Access</strong><span>Receive your student account and log in to the portal.</span></div>
				</div>
			</div>
		</section>

		<section class="landing-cta" id="about">
			<div class="landing-container landing-cta__inner">
				<div>
					<span>Ready to begin?</span>
					<h2>Register today or continue with your existing student account.</h2>
				</div>
				<div class="landing-cta__actions">
					<a class="landing-btn landing-btn--light landing-btn--large" href="registration.php">Register</a>
					<a class="landing-btn landing-btn--outline-light landing-btn--large" href="index.php?login=1">Login</a>
				</div>
			</div>
		</section>
	</main>

	<footer class="landing-footer">
		<div class="landing-container landing-footer__inner">
			<div class="landing-footer__brand">
				<img src="<?php echo abugida_landing_h( $logo_url ); ?>" alt="<?php echo abugida_landing_h( $platform ); ?>">
				<div><strong><?php echo abugida_landing_h( $platform ); ?></strong><span>Student Information System</span></div>
			</div>
			<div class="landing-footer__links">
				<a href="registration.php">Register</a>
				<a href="index.php?login=1">Login</a>
			</div>
			<p>&copy; <?php echo date( 'Y' ); ?> <?php echo abugida_landing_h( $platform ); ?></p>
		</div>
	</footer>
</body>
</html>
