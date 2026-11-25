
<?php
$pageTitle = " ";

$khatuImages = [
    "assets/khatushyam1.jpg",
    "assets/khatushyam2.jpg",
    "assets/khatushyam3.jpg"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?></title>

    <meta name="description" content="Explore property opportunities near Khatu Shyam Ji with Olivera Home Developers.">
       <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/khatu-shyam.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>


<!-- HERO SECTION -->

<section class="khatu-hero">

    <video class="khatu-bg-video" autoplay muted loop playsinline preload="metadata" poster="assets/khatushyam1.jpg">
        <source src="assets/khatushyam.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>

  

    <div class="khatu-hero-content">
        <span class="khatu-subtitle">A SACRED DESTINATION</span>

        <h1>Khatu Shyam Ji</h1>

        <div class="khatu-heading-line"></div>

        <p>
            Explore the spiritual charm of Khatu Shyam Ji
            and discover opportunities in this sacred destination.
        </p>

       
    </div>

</section>



<!-- ABOUT SECTION -->
<section class="khatu-about">

    <div class="khatu-about-image">

        <img
            src="assets/khatushyam5.jpg"
            alt="Traditional Indian temple architecture">

     

    </div>

    <div class="khatu-about-content">

        <span class="khatu-subtitle">ABOUT THE DESTINATION</span>

        <h2>Khatu Shyam Ji – A Place of Faith & Devotion</h2>

        <p>
            Khatu Shyam Ji, situated in the Sikar district of Rajasthan,
            is an important pilgrimage destination dedicated to
            Lord Shyam. Devotees from different parts of India
            visit the temple throughout the year.
        </p>

        <p>
            The town is known for its devotional atmosphere,
            traditional culture and religious significance.
            Its identity as a pilgrimage destination makes it
            an important place in Rajasthan.
        </p>

       

    </div>

</section>


<!-- GALLERY SECTION -->
<section class="khatu-gallery-section">

    <div class="khatu-heading">
        <span class="khatu-subtitle">VISUAL EXPERIENCES</span>
        <h2>Explore the Beauty of Khatu</h2>
        <p>
            Discover the traditional architecture and cultural
            atmosphere of this spiritual destination.
        </p>
    </div>

    <div class="khatu-gallery">

        <?php foreach ($khatuImages as $index => $image): ?>

            <div class="khatu-gallery-item">

                <img
                    src="<?php echo htmlspecialchars($image); ?>"
                    alt="Khatu Shyam destination image <?php echo $index + 1; ?>">

                <div class="khatu-gallery-overlay">
                    <span></span>
                </div>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- LOCATION ADVANTAGES -->
<section class="khatu-advantages">

    <div class="khatu-heading">
        <span class="khatu-subtitle">LOCATION HIGHLIGHTS</span>

        <h2>What Makes Khatu Special?</h2>

        <p>
            A destination recognised for its religious,
            cultural and regional importance.
        </p>
    </div>


    <div class="khatu-advantages-grid">

        <div class="khatu-card">
            <div class="khatu-icon">
                <i class="fa-solid fa-place-of-worship"></i>
            </div>

            <h3>Religious Significance</h3>

            <p>
                Home to the famous Khatu Shyam Ji Temple,
                visited by devotees from across India.
            </p>
        </div>


        <div class="khatu-card">
            <div class="khatu-icon">
                <i class="fa-solid fa-route"></i>
            </div>

            <h3>Road Connectivity</h3>

            <p>
                Connected to nearby towns and cities
                through regional road networks.
            </p>
        </div>


        <div class="khatu-card">
            <div class="khatu-icon">
                <i class="fa-solid fa-people-group"></i>
            </div>

            <h3>Devotional Community</h3>

            <p>
                A vibrant pilgrimage environment with
                visitors throughout the year.
            </p>
        </div>


        <div class="khatu-card">
            <div class="khatu-icon">
                <i class="fa-solid fa-landmark"></i>
            </div>

            <h3>Rich Culture</h3>

            <p>
                Experience Rajasthan's traditional
                culture, heritage and local hospitality.
            </p>
        </div>


        <div class="khatu-card">
            <div class="khatu-icon">
                <i class="fa-solid fa-hotel"></i>
            </div>

            <h3>Visitor Facilities</h3>

            <p>
                Accommodation, food and other facilities
                serve the needs of visiting pilgrims.
            </p>
        </div>


        <div class="khatu-card">
            <div class="khatu-icon">
                <i class="fa-solid fa-location-dot"></i>
            </div>

            <h3>Regional Importance</h3>

            <p>
                An important destination in the Sikar
                region of Rajasthan.
            </p>
        </div>

    </div>

</section>


<!-- CTA SECTION -->
<section class="khatu-cta">

    <div class="khatu-cta-content">

        <span>OLIVERA HOME DEVELOPERS</span>

        <h2>Explore Opportunities Around Khatu Shyam Ji</h2>

        <p>
            Connect with our team to learn more about our
            property offerings and available site visits.
        </p>

        <div class="khatu-buttons khatu-cta-buttons">

            <a href="booksitevisit.php" class="khatu-btn">
                Book a Site Visit
                <i class="fa-solid fa-arrow-right"></i>
            </a>

            <a href="contactus.php" class="khatu-btn khatu-btn-outline">
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
