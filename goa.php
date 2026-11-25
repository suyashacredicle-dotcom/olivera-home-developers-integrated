
<?php
$pageTitle = "Goa | Olivera Home Developers";
$goaAboutImage = "assets/goaabout.jpg";
$goaImages = [
    "assets/goa3.jpg",
    "assets/goa4.jpg",
    "assets/goa5.jpg"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?></title>

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/goa.css">

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>


<section class="goa-hero">

    <video class="goa-bg-video" autoplay muted loop playsinline preload="metadata" poster="assets/goa2.jpg">
        <source src="assets/goavideo.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>

   

    <div class="goa-hero-content">
        <span class="goa-subtitle">DISCOVER THE BEAUTY OF INDIA</span>

        <h1>Goa</h1>

        <div class="goa-heading-line"></div>

        <p>
            Discover the beauty of Goa, its stunning beaches,
            vibrant lifestyle and exciting investment opportunities.
        </p>

    
    </div>

</section>


<!-- ABOUT SECTION -->
<section class="goa-about">
    <div class="goa-container">
        <div class="goa-about-grid">

      <div class="goa-about-image">
    <img src="<?php echo $goaAboutImage; ?>" alt="Beautiful Goa beach">
</div>

            <div class="goa-about-content">
                <span class="goa-label">ABOUT GOA</span>

                <h2>A Lifestyle Inspired by <span>Nature & Freedom</span></h2>

                <p>
                    Goa is one of India's most popular coastal destinations,
                    known for its beautiful beaches, Portuguese-influenced
                    heritage, colourful culture and natural surroundings.
                </p>

                <p>
                    From peaceful coastal landscapes to lively markets and
                    charming neighbourhoods, Goa offers a distinctive blend
                    of relaxation, culture and modern experiences.
                </p>

            
            </div>

        </div>
    </div>
</section>
  
<!-- GALLERY SECTION -->
<section class="goa-gallery">
    <div class="goa-container">

        <div class="goa-section-heading">
            <span class="goa-label">GOA GALLERY</span>
            <h2>Glimpses of <span>Goa</span></h2>
        </div>

        <div class="goa-gallery-grid">
            <?php foreach ($goaImages as $index => $image): ?>
                <div class="goa-gallery-item">
                    <img
                        src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Goa destination view <?php echo $index + 1; ?>"
                        loading="lazy"
                    >
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>


<!-- HIGHLIGHTS SECTION -->
<section class="goa-highlights">
    <div class="goa-container">

        <div class="goa-section-heading">
            <span class="goa-label">WHY EXPLORE GOA</span>
            <h2>A Destination of <span>Beautiful Experiences</span></h2>
            <p>Discover the lifestyle and attractions that make Goa special.</p>
        </div>

        <div class="goa-card-grid">

            <div class="goa-card">
                <div class="goa-icon">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <h3>Beautiful Beaches</h3>
                <p>Enjoy scenic coastlines, sandy beaches and stunning sunsets.</p>
            </div>

            <div class="goa-card">
                <div class="goa-icon">
                    <i class="fas fa-landmark"></i>
                </div>
                <h3>Rich Heritage</h3>
                <p>Explore historic architecture, churches and cultural landmarks.</p>
            </div>

            <div class="goa-card">
                <div class="goa-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3>Natural Surroundings</h3>
                <p>Experience green landscapes, tropical scenery and peaceful surroundings.</p>
            </div>

            <div class="goa-card">
                <div class="goa-icon">
                    <i class="fas fa-plane"></i>
                </div>
                <h3>Connectivity</h3>
                <p>Goa is served by airports and road and rail connections to major cities.</p>
            </div>

        </div>
    </div>
</section>



<!-- CTA SECTION -->
<section class="goa-cta">
    <div class="goa-container">
        <span>EXPLORE NEW POSSIBILITIES</span>

        <h2>Discover New Opportunities with Olivera</h2>

        <p>
            Connect with our team to learn more about our projects
            and available opportunities.
        </p>

        <a href="contactus.php" class="goa-btn">
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
