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
	'sem2_g12_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2025/02/12N-of-how-to-1.jpg',
	'sem2_g12_social' => 'https://selectacademy.edu.et/wp-content/uploads/2025/02/12S-of-how-to-.jpg',
	'sem2_g11_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2025/02/11-2s-of-how-to-1-1.jpg',
	'sem2_g11_social' => 'https://selectacademy.edu.et/wp-content/uploads/2025/02/11-2s-of-how-to-.jpg',
	'sem2_g10' => 'https://selectacademy.edu.et/wp-content/uploads/2025/02/10-of-how-to-2.jpg',
	'sem2_g9' => 'https://selectacademy.edu.et/wp-content/uploads/2025/02/9-of-how-to-.jpg',
	'short_g12_social' => 'https://selectacademy.edu.et/wp-content/uploads/2025/01/Package-of-how-to-.jpg',
	'short_g12_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2025/01/12N-of-how-to-.jpg',
	'short_g11_social' => 'https://selectacademy.edu.et/wp-content/uploads/2025/01/11s-of-how-to-.jpg',
	'short_g11_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2025/01/11N-of-how-to-.jpg',
	'short_g10' => 'https://selectacademy.edu.et/wp-content/uploads/2025/01/10-of-how-to-.jpg',
	'short_g9' => 'https://selectacademy.edu.et/wp-content/uploads/2025/01/10-of-how-to-1.jpg',
	'esslce_social' => 'https://selectacademy.edu.et/wp-content/uploads/2024/12/ESSLCE-cover-social.jpg',
	'esslce_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2024/12/ESSLCE-cover-natural.jpg',
	'summary_social' => 'https://selectacademy.edu.et/wp-content/uploads/2024/12/Summary-Social-G9-12.jpg',
	'summary_natural' => 'https://selectacademy.edu.et/wp-content/uploads/2024/12/Summary-Natural-G9-121.jpg',
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
	<header class="academy-header">
		<div class="landing-container academy-header__inner">
			<a class="academy-brand" href="landing.php" aria-label="<?php echo abugida_landing_h( $platform ); ?> home">
				<img src="<?php echo abugida_landing_h( $logo_url ); ?>" alt="">
				<span class="academy-brand__text"><strong><?php echo abugida_landing_h( $platform ); ?></strong><?php if ( $brand['tagline'] ) { ?><small><?php echo abugida_landing_h( $brand['tagline'] ); ?></small><?php } ?></span>
			</a>
			<button class="academy-menu-toggle" type="button" aria-controls="academy-mobile-menu" aria-expanded="false">
				<span aria-hidden="true">&#9776;</span><span>Menu</span>
			</button>
			<nav class="academy-menu" id="academy-mobile-menu" aria-label="Primary">
				<a class="is-active" href="#home">Home</a>
				<a href="#grades">Grades 9–12</a>
				<a href="#packages">Packages</a>
				<a href="#esslce">ESSLCE</a>
				<a href="#services">Student Services</a>
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

		<section class="academy-section academy-section--soft" id="packages">
			<div class="landing-container">
				<div class="academy-section__heading">
					<span>Full packages</span>
					<h2>Explore grade-level learning packages</h2>
					<p>Browse grade-level learning packages and continue into the services available through your student account.</p>
				</div>
				<div class="academy-package-grid academy-package-grid--three">
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['grade12_natural'] ); ?>" alt="Grade 12 Natural Science"><div><span>Grade 12</span><h3>Natural Science Full Package</h3><p>Access student services and your academic portal for Grade 12 Natural Science.</p><a href="index.php?login=1">Open portal</a></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['grade12_social'] ); ?>" alt="Grade 12 Social Science"><div><span>Grade 12</span><h3>Social Science Full Package</h3><p>Keep registration, records and portal access together in one place.</p><a href="index.php?login=1">Open portal</a></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['grade11_natural'] ); ?>" alt="Grade 11 Natural Science"><div><span>Grade 11</span><h3>Natural Science Full Package</h3><p>Continue your academic year using the same secure student account.</p><a href="index.php?login=1">Open portal</a></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['grade11_social'] ); ?>" alt="Grade 11 Social Science"><div><span>Grade 11</span><h3>Social Science Full Package</h3><p>View your official courses, grades and account information online.</p><a href="index.php?login=1">Open portal</a></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['grade10'] ); ?>" alt="Grade 10"><div><span>Grade 10</span><h3>Grade 10 Full Package</h3><p>Access a unified student experience from registration through results.</p><a href="index.php?login=1">Open portal</a></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['grade9'] ); ?>" alt="Grade 9"><div><span>Grade 9</span><h3>Grade 9 Full Package</h3><p>Start with online registration and continue with one student identity.</p><a href="registration.php">Register</a></div></article>
				</div>
			</div>
		</section>

		<section class="academy-section">
			<div class="landing-container">
				<div class="academy-section__heading">
					<span>Semester 2</span>
					<h2>Continue your semester without changing accounts</h2>
					<p>The same visual browsing rhythm as the original site, connected here to the SIS re-registration and portal flow.</p>
				</div>
				<div class="academy-package-grid academy-package-grid--three">
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['sem2_g12_natural'] ); ?>" alt="Grade 12 Natural Semester 2"><div><span>Semester 2</span><h3>Grade 12 Natural</h3><p>Continue with your existing account and semester registration.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['sem2_g12_social'] ); ?>" alt="Grade 12 Social Semester 2"><div><span>Semester 2</span><h3>Grade 12 Social</h3><p>Keep your official student identity across the semester.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['sem2_g11_natural'] ); ?>" alt="Grade 11 Natural Semester 2"><div><span>Semester 2</span><h3>Grade 11 Natural</h3><p>Use re-registration when your next semester becomes available.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['sem2_g11_social'] ); ?>" alt="Grade 11 Social Semester 2"><div><span>Semester 2</span><h3>Grade 11 Social</h3><p>Continue without creating a duplicate student account.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['sem2_g10'] ); ?>" alt="Grade 10 Semester 2"><div><span>Semester 2</span><h3>Grade 10</h3><p>Access courses, grades and payments from the student portal.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['sem2_g9'] ); ?>" alt="Grade 9 Semester 2"><div><span>Semester 2</span><h3>Grade 9</h3><p>Progress to the next semester through the same portal.</p></div></article>
				</div>
			</div>
		</section>

		<section class="academy-section academy-section--soft">
			<div class="landing-container">
				<div class="academy-section__heading">
					<span>Short notes & summaries</span>
					<h2>Quick-access academic resources</h2>
					<p>Visual content blocks based on the original package catalogue, while keeping the SIS actions focused on registration and portal access.</p>
				</div>
				<div class="academy-package-grid academy-package-grid--three">
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['short_g12_social'] ); ?>" alt="Grade 12 Social short notes"><div><span>Short Notes</span><h3>Grade 12 Social</h3><p>Use your portal account to access your school services and records.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['short_g12_natural'] ); ?>" alt="Grade 12 Natural short notes"><div><span>Short Notes</span><h3>Grade 12 Natural</h3><p>Stay connected to your student record and official results.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['short_g11_social'] ); ?>" alt="Grade 11 Social short notes"><div><span>Short Notes</span><h3>Grade 11 Social</h3><p>Review your academic information from one account.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['short_g11_natural'] ); ?>" alt="Grade 11 Natural short notes"><div><span>Short Notes</span><h3>Grade 11 Natural</h3><p>Keep the same identity across future semesters and years.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['short_g10'] ); ?>" alt="Grade 10 short notes"><div><span>Short Notes</span><h3>Grade 10</h3><p>Access your student profile and academic records online.</p></div></article>
					<article class="academy-package-card"><img src="<?php echo abugida_landing_h( $reference_assets['short_g9'] ); ?>" alt="Grade 9 short notes"><div><span>Short Notes</span><h3>Grade 9</h3><p>Begin with online registration and continue into the portal.</p></div></article>
				</div>
			</div>
		</section>

		<section class="academy-section" id="esslce">
			<div class="landing-container">
				<div class="academy-section__heading">
					<span>ESSLCE preparation</span>
					<h2>Exam preparation and summary collections</h2>
					<p>These visual cards mirror the exam-preparation blocks present in the source homepage.</p>
				</div>
				<div class="academy-feature-grid">
					<article class="academy-feature-card academy-feature-card--large"><img src="<?php echo abugida_landing_h( $reference_assets['esslce_natural'] ); ?>" alt="ESSLCE Natural"><div><span>ESSLCE Prep Kit</span><h3>Natural Science</h3><p>Keep your official student account and academic records separate from learning content while accessing both through a coherent experience.</p><a href="index.php?login=1">Student login</a></div></article>
					<article class="academy-feature-card academy-feature-card--large"><img src="<?php echo abugida_landing_h( $reference_assets['esslce_social'] ); ?>" alt="ESSLCE Social"><div><span>ESSLCE Prep Kit</span><h3>Social Science</h3><p>Use the same student identity for school administration and future academic services.</p><a href="index.php?login=1">Student login</a></div></article>
					<article class="academy-feature-card"><img src="<?php echo abugida_landing_h( $reference_assets['summary_natural'] ); ?>" alt="Natural summary"><div><span>Summary</span><h3>Grade 9–12 Natural</h3><p>Academic summary collection.</p></div></article>
					<article class="academy-feature-card"><img src="<?php echo abugida_landing_h( $reference_assets['summary_social'] ); ?>" alt="Social summary"><div><span>Summary</span><h3>Grade 9–12 Social</h3><p>Academic summary collection.</p></div></article>
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

	<script>
	(function(){
		var button=document.querySelector('.academy-menu-toggle');
		var menu=document.getElementById('academy-mobile-menu');
		if(!button||!menu)return;
		button.addEventListener('click',function(){
			var open=menu.classList.toggle('is-open');
			button.setAttribute('aria-expanded',open?'true':'false');
		});
		menu.addEventListener('click',function(event){
			if(event.target.closest('a')&&window.matchMedia('(max-width:760px)').matches){
				menu.classList.remove('is-open');
				button.setAttribute('aria-expanded','false');
			}
		});
		window.addEventListener('resize',function(){
			if(!window.matchMedia('(max-width:760px)').matches){
				menu.classList.remove('is-open');
				button.setAttribute('aria-expanded','false');
			}
		});
	})();
	</script>
</body>
</html>
