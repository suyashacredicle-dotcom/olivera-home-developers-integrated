
<?php
$gurugramImages = [
    "assets/gurugram2.jpg",
    "assets/gurugram4.jpg",
    "assets/gurugram5.jpg"
];

$pageTitle = "Real Estate in Gurugram | Olivera Home Developers";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?></title>

    <meta name="description" content="Explore real estate opportunities and residential projects in Gurugram with Olivera Home Developers.">

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/gurugram.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- HERO SECTION -->

<section class="gurugram-hero">

    <video class="gurugram-bg-video" autoplay muted loop playsinline preload="metadata" poster="assets/gurugram1.jpg">
        <source src="assets/gurugramvideo.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>



    <div class="gurugram-hero-content">
        <span class="gurugram-subtitle">THE MILLENNIUM CITY</span>

        <h1>Gurugram</h1>

        <div class="gurugram-heading-line"></div>

        <p>
            Discover premium residential and investment opportunities
            in Gurugram, a thriving destination for modern living.
        </p>

    </div>

</section>



<!-- ABOUT SECTION -->
<section class="gurugram-about section-padding">

    <div class="gurugram-about-image">
        <img
            src="assets/gurugram3.jpg"
            alt="Modern commercial buildings in Gurugram">
        
    </div>

    <div class="gurugram-about-content">

        <span class="gurugram-subtitle">ABOUT THE CITY</span>

        <h2>Gurugram – A City of Growth & Opportunities</h2>

        <p>
            Gurugram, located in Haryana, is one of India's prominent
            urban and business destinations. Known for its modern
            skyline, corporate hubs, residential communities and
            expanding infrastructure, the city offers a dynamic
            environment for living and investment.
        </p>

        <p>
            With access to major highways, metro connectivity,
            business districts and lifestyle destinations, Gurugram
            continues to attract homebuyers and investors.
        </p>

    
    </div>

</section>


<!-- IMAGE GALLERY -->
<section class="gurugram-gallery-section">

    <div class="gurugram-section-heading">
        <span class="gurugram-subtitle">CITY HIGHLIGHTS</span>
        <h2>Explore Gurugram</h2>
        <p>A glimpse of the city's modern lifestyle and urban development.</p>
    </div>

    <div class="gurugram-gallery">

        <?php foreach ($gurugramImages as $index => $image): ?>
            <div class="gurugram-gallery-item">
                <img
                    src="<?php echo htmlspecialchars($image); ?>"
                    alt="Gurugram city view <?php echo $index + 1; ?>">

                <div class="gurugram-gallery-overlay">
                  
                </div>
            </div>
        <?php endforeach; ?>

    </div>


    
</section>


<!-- ADVANTAGES SECTION -->
<section class="gurugram-advantages section-padding">

    <div class="gurugram-section-heading">
        <span class="gurugram-subtitle">WHY GURUGRAM</span>
        <h2>A Location That Connects Everything</h2>
        <p>
            Enjoy access to important business, lifestyle and
            infrastructure destinations.
        </p>
    </div>

    <div class="gurugram-advantages-grid">

        <div class="gurugram-advantage-card">
            <div class="gurugram-icon">
                <i class="fa-solid fa-road"></i>
            </div>
            <h3>Excellent Connectivity</h3>
            <p>
                Access to major road networks including NH-48
                and important expressway corridors.
            </p>
        </div>

        <div class="gurugram-advantage-card">
            <div class="gurugram-icon">
                <i class="fa-solid fa-train-subway"></i>
            </div>
            <h3>Metro Connectivity</h3>
            <p>
                Metro services connect several important
                areas of Gurugram with Delhi and nearby locations.
            </p>
        </div>

        <div class="gurugram-advantage-card">
            <div class="gurugram-icon">
                <i class="fa-solid fa-building"></i>
            </div>
            <h3>Business Hub</h3>
            <p>
                A major corporate destination with offices,
                commercial centres and business districts.
            </p>
        </div>

        <div class="gurugram-advantage-card">
            <div class="gurugram-icon">
                <i class="fa-solid fa-hospital"></i>
            </div>
            <h3>Healthcare & Education</h3>
            <p>
                Access to established hospitals, schools
                and educational institutions.
            </p>
        </div>

        <div class="gurugram-advantage-card">
            <div class="gurugram-icon">
                <i class="fa-solid fa-bag-shopping"></i>
            </div>
            <h3>Shopping & Lifestyle</h3>
            <p>
                Shopping malls, restaurants, entertainment
                and lifestyle destinations.
            </p>
        </div>

        <div class="gurugram-advantage-card">
            <div class="gurugram-icon">
                <i class="fa-solid fa-house"></i>
            </div>
            <h3>Modern Communities</h3>
            <p>
                A range of residential neighbourhoods
                and contemporary housing options.
            </p>
        </div>

    </div>

</section>


<!-- CTA SECTION -->
<section class="gurugram-cta">

    <div class="gurugram-cta-content">
        <span>YOUR DREAM HOME STARTS HERE</span>

        <h2>Explore Real Estate Opportunities in Gurugram</h2>

        <p>
            Connect with Olivera Home Developers and take
            the next step towards finding your ideal property.
        </p>

        <div class="gurugram-cta-buttons">
            <a href="booksitevisit.php" class="gurugram-btn">
                Book a Site Visit
                <i class="fa-solid fa-arrow-right"></i>
            </a>

            <a href="contactus.php" class="gurugram-btn gurugram-btn-outline">
                Contact Us
            </a>
        </div>
    </div>
 
</section>

<!-- =================================
     FOOTER SECTION
================================= -->
<?php include __DIR__ . '/includes/footer.php'; ?>








</body>
</html>
