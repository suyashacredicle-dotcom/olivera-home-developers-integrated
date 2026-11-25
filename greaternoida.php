
<?php
$greaterNoidaImage = [
    'image' => 'assets/gnoida1.jpg',
    'alt' => 'Modern buildings in Greater Noida'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Greater Noida Projects | Olivera Home Developers</title>

    <!-- Main CSS -->
    <link rel="stylesheet" href="CSS/style.css">

    <!-- Greater Noida CSS -->
    <link rel="stylesheet" href="CSS/greater-noida.css">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap"
        rel="stylesheet">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>


<!-- =================================
     GREATER NOIDA HERO WITH VIDEO
================================= -->

<section class="gn-hero">

    <video class="gn-bg-video" autoplay muted loop playsinline preload="metadata" poster="assets/noida75.jpg">
        <source src="assets/noida.mp4">
        Your browser does not support HTML5 video.
    </video>

    

    <div class="gn-heading">

        <span>EXPLORE OUR LOCATION</span>

        <h1>Greater Noida Projects</h1>

        <div class="gn-heading-line"></div>

        <p>
            Discover property opportunities in Greater Noida,
            a growing destination with expanding infrastructure
            and connectivity.
        </p>

    </div>

</section>


<!-- =================================
     EXPLORE GREATER NOIDA
================================= -->

<section class="gn-explore">

    <div class="gn-section-heading">

        <span>OUR LOCATION</span>

        <h2>Explore Greater Noida</h2>

        <p>
            Discover the lifestyle and opportunities of Greater Noida.
        </p>

    </div>


    <div class="gn-explore-container">

        <!-- LEFT IMAGE -->
        <div class="gn-explore-image">

            <img
                src="<?php echo htmlspecialchars($greaterNoidaImage['image'], ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($greaterNoidaImage['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                loading="lazy"
            >

        </div>


        <!-- RIGHT CONTENT -->
        <div class="gn-explore-content">

            <span class="gn-explore-label">
                A GROWING URBAN DESTINATION
            </span>

            <h2>
                Discover the Growing City of
                <span>Greater Noida</span>
            </h2>

            <p>
                Greater Noida is a planned urban destination in
                Uttar Pradesh, forming an important part of the
                Delhi NCR region. Known for its wide roads,
                organised sectors and developing infrastructure,
                the city continues to attract residential and
                commercial development.
            </p>

            <p>
                The region offers access to educational institutions,
                healthcare facilities, shopping destinations and
                business centres. Its road and metro connectivity
                help connect residents with other parts of NCR.
            </p>

            <p>
                With ongoing infrastructure development and
                expanding urban facilities, Greater Noida offers
                a range of opportunities for people exploring
                modern property options.
            </p>

           

        </div>

    </div>

</section>


<!-- =================================
     LOCATION ADVANTAGES
================================= -->

<section class="gn-advantages">

    <div class="gn-section-heading">

        <span>LOCATION ADVANTAGES</span>

        <h2>Everything Within Reach</h2>

        <p>
            Explore the facilities and connectivity available
            across Greater Noida.
        </p>

    </div>


    <div class="gn-advantages-grid">

        <!-- ROAD CONNECTIVITY -->
        <div class="gn-advantage-card">

            <i class="fa-solid fa-road"></i>

            <h3>Road Connectivity</h3>

            <p>
                Access to major roads and expressways connecting
                different parts of NCR.
            </p>

        </div>


        <!-- METRO -->
        <div class="gn-advantage-card">

            <i class="fa-solid fa-train-subway"></i>

            <h3>Metro Connectivity</h3>

            <p>
                Metro services connect several areas of Greater
                Noida with the wider NCR network.
            </p>

        </div>


        <!-- HEALTHCARE -->
        <div class="gn-advantage-card">

            <i class="fa-solid fa-hospital"></i>

            <h3>Healthcare</h3>

            <p>
                Hospitals, clinics and healthcare facilities
                are available across the region.
            </p>

        </div>


        <!-- EDUCATION -->
        <div class="gn-advantage-card">

            <i class="fa-solid fa-school"></i>

            <h3>Education</h3>

            <p>
                Schools, colleges and educational institutions
                serve the growing community.
            </p>

        </div>

    </div>

</section>


<!-- =================================
     SITE VISIT CTA
================================= -->

<section class="gn-cta">

    <div class="gn-cta-content">

        <span>YOUR NEXT ADDRESS STARTS HERE</span>

        <h2>
            Explore Property Opportunities in Greater Noida
        </h2>

        <p>
            Connect with our team to learn more about our projects
            and arrange a site visit.
        </p>

        <a href="booksitevisit.php" class="gn-visit-btn">

            Book Your Site Visit

            <i class="fa-solid fa-arrow-right"></i>

        </a>

    </div>

</section>




<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
