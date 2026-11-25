
<?php
$pageTitle = "Dubai | Olivera Home Developers";

$dubaiImages = [
    "https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1200&q=85",
    "https://images.unsplash.com/photo-1518684079-3c830dcef090?auto=format&fit=crop&w=900&q=85",
    "https://images.unsplash.com/photo-1512632578888-169bbbc64f33?auto=format&fit=crop&w=900&q=85"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?></title>

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/dubai.css">

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>


<!-- DUBAI VIDEO HERO SECTION -->

<section class="dubai-hero">

    <video class="dubai-bg-video"
           autoplay
           muted
           loop
           playsinline
           preload="metadata" poster="assets/images (19).jpg">

        <source src="assets/dubaivideo.mp4" type="video/mp4">

        Your browser does not support HTML5 video.

    </video>

    

    <div class="dubai-hero-content">

        <span class="dubai-subtitle">
            DISCOVER LUXURY & OPPORTUNITY
        </span>

        <h1>Experience the Beauty of <span>Dubai</span></h1>

        <div class="dubai-heading-line"></div>

        <p>
            Discover a world of luxury, modern architecture and
            exceptional investment opportunities in Dubai.
        </p>

      

    </div>

</section>


<!-- ABOUT SECTION -->
<section class="dubai-about">
    <div class="dubai-container">
        <div class="dubai-about-grid">

            <div class="dubai-about-image">
                <img
                    src="<?php echo $dubaiImages[0]; ?>"
                    alt="Dubai city skyline"
                >
            </div>

            <div class="dubai-about-content">
                <span class="dubai-label">ABOUT DUBAI</span>

                <h2>A City of <span>Vision & Excellence</span></h2>

                <p>
                    Dubai is one of the world's most recognised destinations,
                    known for its impressive architecture, modern infrastructure
                    and vibrant lifestyle.
                </p>

                <p>
                    From iconic landmarks and luxury shopping to beautiful
                    waterfront destinations, Dubai offers a unique blend of
                    innovation, culture and global experiences.
                </p>

               
            </div>

        </div>
    </div>
</section>



<!-- GALLERY SECTION -->
<section class="dubai-gallery">
    <div class="dubai-container">

        <div class="dubai-section-heading">
            <span class="dubai-label">DUBAI GALLERY</span>
            <h2>Glimpses of <span>Dubai</span></h2>
        </div>

        <div class="dubai-gallery-grid">
            <?php foreach ($dubaiImages as $index => $image): ?>
                <div class="dubai-gallery-item">
                    <img
                        src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Dubai destination view <?php echo $index + 1; ?>"
                        loading="lazy"
                    >
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- HIGHLIGHTS SECTION -->
<section class="dubai-highlights">
    <div class="dubai-container">

        <div class="dubai-section-heading">
            <span class="dubai-label">EXPLORE DUBAI</span>
            <h2>A Destination of <span>Global Experiences</span></h2>
            <p>Discover the features that make Dubai an extraordinary destination.</p>
        </div>

        <div class="dubai-card-grid">

            <div class="dubai-card">
                <div class="dubai-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3>Iconic Architecture</h3>
                <p>Explore impressive skyscrapers and world-famous architectural landmarks.</p>
            </div>

            <div class="dubai-card">
                <div class="dubai-icon">
                    <i class="fas fa-plane"></i>
                </div>
                <h3>Global Connectivity</h3>
                <p>Experience an international destination with extensive air connectivity.</p>
            </div>

            <div class="dubai-card">
                <div class="dubai-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3>Luxury Lifestyle</h3>
                <p>Discover premium shopping, dining and entertainment experiences.</p>
            </div>

            <div class="dubai-card">
                <div class="dubai-icon">
                    <i class="fas fa-water"></i>
                </div>
                <h3>Waterfront Living</h3>
                <p>Enjoy beautiful beaches, waterfront views and leisure destinations.</p>
            </div>

        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="dubai-cta">
    <div class="dubai-container">
        <span>EXPLORE NEW OPPORTUNITIES</span>

        <h2>Discover the World with Olivera</h2>

        <p>
            Connect with our team to learn more about our projects
            and available opportunities.
        </p>

        <a href="contactus.php" class="dubai-btn">
            Contact Us <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>
<!-- =================================
     FOOTER SECTION
================================= -->

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
