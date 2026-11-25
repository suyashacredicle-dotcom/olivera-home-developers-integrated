
<?php
$pageTitle = "Dehradun | Olivera Home Developers";

$dehradunImages = [
    "assets/dehradun1.jpg",
    "assets/dehradun2.jpg",
    "assets/dehradun3.jpg"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <meta name="description" content="Explore property opportunities in Dehradun with Olivera Home Developers.">

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/dehradun.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>


<!-- HERO SECTION -->

<!-- DEHRADUN VIDEO HERO SECTION -->

<section class="dehradun-hero">

    <video class="dehradun-bg-video" autoplay muted loop playsinline preload="metadata" poster="assets/dehradun1.jpg">
        <source src="assets/dehradun.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>

   

    <div class="dehradun-hero-content">

        <span class="dehradun-subtitle">
            THE BEAUTY OF DEV BHOOMI
        </span>

        <h1>Dehradun</h1>

        <div class="dehradun-heading-line"></div>

        <p>
            Discover premium property and investment opportunities
            in Dehradun, surrounded by beautiful mountains,
            greenery and a peaceful lifestyle.
        </p>

        

    </div>

</section>



<!-- ABOUT SECTION -->
<section class="dehradun-about">

    <div class="dehradun-about-image">

        <img
            src="https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&fit=crop&w=1100&q=85"
            alt="Mountain landscape">

       

    </div>

    <div class="dehradun-about-content">

        <span class="dehradun-subtitle">ABOUT THE CITY</span>

        <h2>Dehradun – Where Nature Meets Modern Living</h2>

        <p>
            Dehradun, the capital of Uttarakhand, is known for its
            scenic landscapes, pleasant surroundings and rich
            cultural heritage. Located in the Doon Valley,
            the city is surrounded by beautiful hills.
        </p>

        <p>
            With educational institutions, healthcare facilities,
            growing infrastructure and connectivity to nearby
            destinations, Dehradun offers a distinctive lifestyle
            for residents and visitors.
        </p>


    </div>

</section>


<!-- GALLERY SECTION -->
<section class="dehradun-gallery-section">

    <div class="dehradun-heading">

        <span class="dehradun-subtitle">CITY HIGHLIGHTS</span>

        <h2>Explore the Beauty of Dehradun</h2>

        <p>
            Discover the natural landscapes and peaceful
            surroundings of this beautiful city.
        </p>

    </div>

    <div class="dehradun-gallery">

        <?php foreach ($dehradunImages as $index => $image): ?>

            <div class="dehradun-gallery-item">

                <img
                    src="<?php echo htmlspecialchars($image); ?>"
                    alt="Dehradun landscape <?php echo $index + 1; ?>">

                <div class="dehradun-gallery-overlay">
                    
                </div>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- LOCATION ADVANTAGES -->
<section class="dehradun-advantages">

    <div class="dehradun-heading">

        <span class="dehradun-subtitle">LOCATION HIGHLIGHTS</span>

        <h2>Why Explore Dehradun?</h2>

        <p>
            A destination offering natural beauty,
            essential facilities and regional connectivity.
        </p>

    </div>

    <div class="dehradun-advantages-grid">

        <div class="dehradun-card">
            <div class="dehradun-icon">
                <i class="fa-solid fa-mountain-sun"></i>
            </div>

            <h3>Scenic Surroundings</h3>

            <p>
                Enjoy the beautiful Doon Valley,
                green landscapes and nearby hills.
            </p>
        </div>


        <div class="dehradun-card">
            <div class="dehradun-icon">
                <i class="fa-solid fa-road"></i>
            </div>

            <h3>Road Connectivity</h3>

            <p>
                Connected to nearby cities and popular
                destinations through regional highways.
            </p>
        </div>


        <div class="dehradun-card">
            <div class="dehradun-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>

            <h3>Education Hub</h3>

            <p>
                Known for reputed schools, colleges
                and educational institutions.
            </p>
        </div>


        <div class="dehradun-card">
            <div class="dehradun-icon">
                <i class="fa-solid fa-hospital"></i>
            </div>

            <h3>Healthcare Facilities</h3>

            <p>
                Access to hospitals, healthcare centres
                and medical facilities.
            </p>
        </div>


        <div class="dehradun-card">
            <div class="dehradun-icon">
                <i class="fa-solid fa-tree"></i>
            </div>

            <h3>Natural Lifestyle</h3>

            <p>
                A distinctive environment with green
                spaces and outdoor experiences.
            </p>
        </div>


        <div class="dehradun-card">
            <div class="dehradun-icon">
                <i class="fa-solid fa-plane"></i>
            </div>

            <h3>Airport Connectivity</h3>

            <p>
                Jolly Grant Airport provides air connectivity
                to Dehradun and surrounding regions.
            </p>
        </div>

    </div>

</section>


<!-- CTA SECTION -->
<section class="dehradun-cta">

    <div class="dehradun-cta-content">

        <span>OLIVERA HOME DEVELOPERS</span>

        <h2>Explore Property Opportunities in Dehradun</h2>

        <p>
            Connect with our team to learn more about
            available properties and arrange a site visit.
        </p>

        <div class="dehradun-buttons dehradun-cta-buttons">

            <a href="booksitevisit.php" class="dehradun-btn">
                Book a Site Visit
                <i class="fa-solid fa-arrow-right"></i>
            </a>

            <a href="contactus.php" class="dehradun-btn dehradun-btn-outline">
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
