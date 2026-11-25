
<?php

$faridabadImages = [
    [
        'image' => 'assets/faridabad4.jpg',
        'alt' => 'Modern commercial buildings'
    ],
    [
        'image' => 'assets/faridabad1.jpg',
        'alt' => 'Modern urban architecture'
    ],
    [
        'image' => 'assets/faridabad2.jpg',
        'alt' => 'Modern property development'
    ]
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faridabad Projects | Olivera Home Developers</title>

    <!-- Main CSS -->
    <link rel="stylesheet" href="CSS/style.css">

    <!-- Faridabad CSS -->
    <link rel="stylesheet" href="CSS/faridabad.css">

    <!-- Font Awesome -->
    <link
     rel="stylesheet"      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap"
          rel="stylesheet">


</head>

<body>

<!-- HEADER -->
<?php include __DIR__ . '/includes/header.php'; ?>


<!-- HERO SECTION -->

<!-- HERO SECTION WITH BACKGROUND VIDEO -->
<section class="faridabad-hero">

    <video class="faridabad-bg-video"
           autoplay
           muted
           loop
           playsinline
           preload="metadata" poster="assets/faridabad1.jpg">

        <source src="assets/faridabadvideo.mp4" type="video/mp4">

        Your browser does not support HTML5 video.

    </video>

    

    <div class="faridabad-hero-content">

        <span class="faridabad-subtitle">
            A CITY OF OPPORTUNITIES
        </span>

        <h1>Faridabad</h1>

        <div class="faridabad-heading-line"></div>

        <p>
            Discover residential and investment opportunities
            in one of Haryana's important urban destinations.
        </p>

        

    </div>

</section>



<!-- ABOUT SECTION -->
<section class="faridabad-about">

    <div class="faridabad-about-image">

        <img
            src="assets/faridabad3.jpg"
            alt="Modern city buildings">

    </div>

    <div class="faridabad-about-content">

        <span class="faridabad-label">ABOUT THE LOCATION</span>

        <h2>
            Discover the Potential of
            <span>Faridabad</span>
        </h2>

        <p>
            Faridabad is an important city in Haryana and part
            of the National Capital Region. Its location near
            Delhi and its developing urban infrastructure make
            it an attractive destination to explore.
        </p>

        <p>
            Discover property opportunities in a city that
            connects residential living, business and urban growth.
        </p>

        

    </div>

</section>


<!-- GALLERY SECTION -->
<section class="faridabad-gallery">

    <div class="faridabad-section-heading">

        <span>OUR LOCATION</span>

        <h2>Explore Faridabad</h2>

        <p>
            Explore the city, its modern architecture and
            developing urban surroundings.
        </p>

    </div>

    <div class="faridabad-gallery-grid">

        <?php foreach ($faridabadImages as $item): ?>

            <div class="faridabad-gallery-item">

                <img
                    src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars($item['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy">

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- ADVANTAGES SECTION -->
<section class="faridabad-advantages">

    <div class="faridabad-section-heading">

        <span>WHY CHOOSE FARIDABAD</span>

        <h2>A Growing Urban Destination</h2>

        <p>
            Explore the key features of Faridabad.
        </p>

    </div>

    <div class="faridabad-advantages-grid">

        <div class="faridabad-advantage-card">

            <i class="fa-solid fa-road"></i>

            <h3>Connectivity</h3>

            <p>
                Convenient road and metro connectivity
                with Delhi and nearby NCR cities.
            </p>

        </div>

        <div class="faridabad-advantage-card">

            <i class="fa-solid fa-building"></i>

            <h3>Urban Development</h3>

            <p>
                An established urban centre with
                residential and commercial areas.
            </p>

        </div>

        <div class="faridabad-advantage-card">

            <i class="fa-solid fa-hospital"></i>

            <h3>Healthcare</h3>

            <p>
                Access to hospitals and healthcare
                facilities across the city.
            </p>

        </div>

        <div class="faridabad-advantage-card">

            <i class="fa-solid fa-house"></i>

            <h3>Property Opportunities</h3>

            <p>
                Explore residential and property
                opportunities in the region.
            </p>

        </div>

    </div>

</section>


<!-- CTA SECTION -->
<section class="faridabad-cta">

    <div class="faridabad-cta-content">

        <span>YOUR NEXT DESTINATION</span>

        <h2>Find Your Place in Faridabad</h2>

        <p>
            Connect with Olivera Home Developers to explore
            available property opportunities.
        </p>

        <a href="booksitevisit.php" class="faridabad-btn">
            Book a Site Visit
            <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div>

</section>

<!-- ================= PREMIUM FOOTER ================= -->
<!-- ================= WHATSAPP BUTTON ================= -->

<a
    href="https://wa.me/916391305365"
    class="whatsapp-button"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Contact us on WhatsApp"
>
    <i class="fa-brands fa-whatsapp"></i>
</a>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>