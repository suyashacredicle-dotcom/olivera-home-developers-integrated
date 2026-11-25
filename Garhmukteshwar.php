
<?php
$pageTitle = "Garhmukteshwar | Olivera Home Developers";

$garhmukteshwarImages = [
    "assets/Garhmukteshwar1.jpg",
    "assets/Garhmukteshwar2.jpg",
    "assets/Garhmukteshwar3.jpg"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <meta name="description" content="Explore property opportunities in Garhmukteshwar with Olivera Home Developers.">

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/garhmukteshwar.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>



<!-- GARHMUKTESHWAR VIDEO HERO SECTION -->

<section class="garhmukteshwar-hero">

    <video class="garhmukteshwar-bg-video"
           autoplay
           muted
           loop
           playsinline
           preload="metadata" poster="assets/Garhmukteshwar1.jpg">

        <source src="assets/Garhmukteshwarvideo.mp4" type="video/mp4">

        Your browser does not support HTML5 video.

    </video>

    

    <div class="garhmukteshwar-content">

        <span class="garhmukteshwar-subtitle">
            SPIRITUAL BEAUTY OF GANGA
        </span>

        <h1>Garhmukteshwar</h1>

        <div class="garhmukteshwar-line"></div>

        <p>
            Discover the spiritual charm of Garhmukteshwar,
            a beautiful destination on the banks of the sacred
            River Ganga, offering peaceful living and investment
            opportunities.
        </p>

        

    </div>

</section>



<!-- ABOUT SECTION -->
<section class="garh-about">

    <div class="garh-about-image">

        <img
            src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1100&q=85"
            alt="Natural landscape">

      

    </div>

    <div class="garh-about-content">

        <span class="garh-subtitle">ABOUT THE CITY</span>

        <h2>Garhmukteshwar – A Place of Faith & Heritage</h2>

        <p>
            Garhmukteshwar is a historic town in the Hapur district
            of Uttar Pradesh, situated on the banks of the sacred
            River Ganga. It is known for its religious importance,
            traditional culture and spiritual atmosphere.
        </p>

        <p>
            The town attracts pilgrims and visitors, especially
            during religious occasions and festivals. Its location
            along the Ganga gives it a distinctive cultural and
            spiritual identity.
        </p>

       

    </div>

</section>


<!-- GALLERY SECTION -->
<section class="garh-gallery-section">

    <div class="garh-heading">

        <span class="garh-subtitle">CITY HIGHLIGHTS</span>

        <h2>Explore Garhmukteshwar</h2>

        <p>
            Discover the natural surroundings and cultural charm
            of this historic destination.
        </p>

    </div>

    <div class="garh-gallery">

        <?php foreach ($garhmukteshwarImages as $index => $image): ?>

            <div class="garh-gallery-item">

                <img
                    src="<?php echo htmlspecialchars($image); ?>"
                    alt="Garhmukteshwar view <?php echo $index + 1; ?>">

                <div class="garh-gallery-overlay">
                    
                </div>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- LOCATION ADVANTAGES -->
<section class="garh-advantages">

    <div class="garh-heading">

        <span class="garh-subtitle">LOCATION HIGHLIGHTS</span>

        <h2>Discover What Makes Garhmukteshwar Special</h2>

        <p>
            A destination recognised for its spiritual,
            cultural and natural significance.
        </p>

    </div>

    <div class="garh-advantages-grid">

        <div class="garh-card">
            <div class="garh-icon">
                <i class="fa-solid fa-water"></i>
            </div>

            <h3>River Ganga</h3>

            <p>
                Located along the sacred River Ganga,
                offering a peaceful and spiritual environment.
            </p>
        </div>


        <div class="garh-card">
            <div class="garh-icon">
                <i class="fa-solid fa-place-of-worship"></i>
            </div>

            <h3>Religious Heritage</h3>

            <p>
                Known for its temples, ghats and
                religious traditions.
            </p>
        </div>


        <div class="garh-card">
            <div class="garh-icon">
                <i class="fa-solid fa-road"></i>
            </div>

            <h3>Road Connectivity</h3>

            <p>
                Connected with nearby towns and cities
                through regional road networks.
            </p>
        </div>


        <div class="garh-card">
            <div class="garh-icon">
                <i class="fa-solid fa-people-group"></i>
            </div>

            <h3>Cultural Traditions</h3>

            <p>
                Experience local traditions, religious
                celebrations and regional culture.
            </p>
        </div>


        <div class="garh-card">
            <div class="garh-icon">
                <i class="fa-solid fa-leaf"></i>
            </div>

            <h3>Natural Surroundings</h3>

            <p>
                Enjoy the natural landscape and the
                peaceful atmosphere near the river.
            </p>
        </div>


        <div class="garh-card">
            <div class="garh-icon">
                <i class="fa-solid fa-location-dot"></i>
            </div>

            <h3>Regional Importance</h3>

            <p>
                An important religious and cultural
                destination in western Uttar Pradesh.
            </p>
        </div>

    </div>

</section>


<!-- CTA SECTION -->
<section class="garh-cta">

    <div class="garh-cta-content">

        <span>OLIVERA HOME DEVELOPERS</span>

        <h2>Explore Opportunities in Garhmukteshwar</h2>

        <p>
            Connect with our team to learn about available
            properties and arrange a site visit.
        </p>

        <div class="garh-buttons garh-cta-buttons">

            <a href="booksitevisit.php" class="garh-btn">
                Book a Site Visit
                <i class="fa-solid fa-arrow-right"></i>
            </a>

            <a href="contactus.php" class="garh-btn garh-btn-outline">
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
