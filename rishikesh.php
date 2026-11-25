
<?php
$pageTitle = "Rishikesh | Olivera Home Developers";

// About section image
$aboutImage = "assets/rishikesh5.jpg";

// Gallery section images (About image excluded)
$galleryImages = [
    "assets/rishikesh3.jpg",
    "assets/rishikesh4.jpg",
    "assets/rishikesh1.jpg"
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?></title>

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/rishikesh.css">

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- HERO SECTION WITH BACKGROUND VIDEO -->

<section class="rishikesh-hero">

    <video class="rishikesh-bg-video"
           autoplay
           muted
           loop
           playsinline
           preload="metadata" poster="assets/rishikesh1.jpg">

        <source src="assets/rishikeshvideo.mp4" type="video/mp4">

        Your browser does not support HTML5 video.

    </video>

   

    <div class="rishikesh-hero-content">

        <span class="rishikesh-subtitle">
            DISCOVER THE BEAUTY OF UTTARAKHAND
        </span>

        <h1>Experience the Serenity of <span>Rishikesh</span></h1>

        <div class="rishikesh-heading-line"></div>

        <p>
            A beautiful destination where spirituality, nature and peaceful living
            come together.
        </p>

    </div>

</section>


<!-- INTRODUCTION SECTION -->

<section class="rishikesh-about section-padding">

    <div class="rishikesh-container">

        <div class="rishikesh-about-grid">

            <div class="rishikesh-about-image">

                <img
                    src="<?php echo htmlspecialchars($aboutImage, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Beautiful view of Rishikesh"
                >

            </div>

            <div class="rishikesh-about-content">

                <span class="rishikesh-label">ABOUT RISHIKESH</span>

                <h2>A Perfect Blend of <span>Nature & Peace</span></h2>

                <p>
                    Located in the foothills of the Himalayas, Rishikesh is known
                    for its natural beauty, spiritual atmosphere and the sacred
                    River Ganga.
                </p>

                <p>
                    With its scenic surroundings, peaceful environment and
                    growing infrastructure, Rishikesh offers a unique lifestyle
                    for people seeking a connection with nature.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- GALLERY SECTION -->

<section class="rishikesh-gallery section-padding">

    <div class="rishikesh-container">

        <div class="rishikesh-section-heading">

            <span class="rishikesh-label">EXPLORE THE DESTINATION</span>

            <h2>Glimpses of <span>Rishikesh</span></h2>

        </div>

        <div class="rishikesh-gallery-grid">

            <?php foreach ($galleryImages as $index => $image): ?>

                <div class="rishikesh-gallery-item">

                    <img
                        src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Rishikesh destination view <?php echo $index + 1; ?>"
                        loading="lazy"
                    >

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<!-- HIGHLIGHTS SECTION -->

<section class="rishikesh-highlights section-padding">

    <div class="rishikesh-container">

        <div class="rishikesh-section-heading">

            <span class="rishikesh-label">WHY RISHIKESH</span>

            <h2>A Destination of <span>Endless Possibilities</span></h2>

            <p>Discover what makes Rishikesh a special place to live and visit.</p>

        </div>

        <div class="rishikesh-card-grid">

            <div class="rishikesh-card">

                <div class="rishikesh-icon">
                    <i class="fas fa-mountain-sun"></i>
                </div>

                <h3>Scenic Beauty</h3>

                <p>Enjoy beautiful Himalayan views, green surroundings and fresh air.</p>

            </div>

            <div class="rishikesh-card">

                <div class="rishikesh-icon">
                    <i class="fas fa-water"></i>
                </div>

                <h3>River Ganga</h3>

                <p>Experience the charm of the sacred Ganga and its peaceful riverbanks.</p>

            </div>

            <div class="rishikesh-card">

                <div class="rishikesh-icon">
                    <i class="fas fa-om"></i>
                </div>

                <h3>Spiritual Heritage</h3>

                <p>Explore temples, ashrams and the spiritual culture of the city.</p>

            </div>

            <div class="rishikesh-card">

                <div class="rishikesh-icon">
                    <i class="fas fa-route"></i>
                </div>

                <h3>Connectivity</h3>

                <p>Rishikesh is connected to nearby destinations through road and rail networks.</p>

            </div>

        </div>

    </div>

</section>


<!-- CTA SECTION -->

<section class="rishikesh-cta">

    <div class="rishikesh-container">

        <span>YOUR NEXT DESTINATION AWAITS</span>

        <h2>Discover New Possibilities with Olivera</h2>

        <p>Connect with our team to know more about our projects and opportunities.</p>

        <a href="contactus.php" class="rishikesh-btn">
            Contact Us <i class="fas fa-arrow-right"></i>
        </a>

    </div>

</section>


<!-- FOOTER SECTION -->

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
