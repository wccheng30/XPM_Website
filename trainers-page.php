<?php
// Include database connection
require_once 'includes/database.php';

// Set page title
$page_title = "Expert Trainers - XpressPoint";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Power Up Mentor (PUM) - Expert HR Trainers from Melaka. A collective of passionate, experienced trainers united to elevate the visibility and professionalism of Melaka's training industry">
    <meta name="keywords" content="Power Up Mentor, PUM, HR trainers, Melaka trainers, expert trainers, HR training, professional development, certified trainers, training collective">
    <meta name="author" content="Xpress Point Management Services">
    <title>Power Up Mentor (PUM) - Expert Trainers - XpressPoint</title>
    <?php $xpm_page_styles = ['pages/trainers.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php $xpm_active = 'programmes'; include 'partials/site-nav.php'; ?>

    <main id="main">
        <header class="page-hero page-hero-center">
            <div class="container">
                <nav class="breadcrumb breadcrumb-center" aria-label="Breadcrumb" data-reveal="fade">
                    <a href="index.html">Home</a><i class="fas fa-chevron-right"></i>
                    <a href="programmes.php">Programmes</a><i class="fas fa-chevron-right"></i>
                    <span>Expert Trainers</span>
                </nav>
                <img src="assets/images/PowerUpMentor.png" alt="Power Up Mentor (PUM) Logo" class="pum-hero-logo" data-reveal="scale">
                <h1 data-split>Power Up Mentor</h1>
                <p class="lead" data-reveal>A collective effort to unite passionate, experienced, and like-minded trainers from Melaka with a shared purpose: to elevate the visibility, credibility, and professionalism of Melaka's training industry, both within and beyond the state.</p>
            </div>
        </header>

        <?php include 'trainers.php'; ?>

        <section class="section section-alt">
            <div class="container">
                <div class="cta-band" data-reveal>
                    <div>
                        <h2>Train with Melaka's best.</h2>
                        <p>Explore upcoming public programmes or talk to us about in-house training for your team.</p>
                    </div>
                    <div class="cluster">
                        <a href="programmes.php" class="btn btn-primary btn-lg">Browse programmes <i class="fas fa-arrow-right"></i></a>
                        <a href="index.html#contact" class="btn btn-outline btn-lg">Contact us</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'partials/site-footer.php'; ?>
</body>
</html>
