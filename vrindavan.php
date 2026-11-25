
<?php
$vrindavanBuildings = [
    [
        'image' => 'assets/vrindavanimage1.jpg',
        'alt' => 'Modern residential building in Vrindavan'
    ],
    [
        'image' => 'assets/vrindavanimage2.jpg',
        'alt' => 'Premium apartment building in Vrindavan'
    ],
    [
        'image' => 'assets/vrindavanimage3.jpg',
        'alt' => 'Luxury residential property in Vrindavan'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vrindavan / Mathura Projects | Olivera Home Developers</title>

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/vrindavan.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap"
          rel="stylesheet">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- HERO SECTION -->
<section class="vrindavan-hero">

    <video class="vrindavan-bg-video" autoplay muted loop playsinline poster="assets/vrindavanimage1.jpg">
        <source src="assets/vrindavan.mp4" type="video/mp4">
    </video>


    <div class="vrindavan-hero-content">

        <span class="vrindavan-subtitle">A DIVINE DESTINATION</span>

        <h1>Vrindavan / Mathura</h1>

        <div class="vrindavan-heading-line"></div>

        <p>
            Discover peaceful living and property opportunities
            in the spiritual heart of India.
        </p>

       

    </div>
</section>


<!-- ABOUT SECTION -->
<section class="vrindavan-about">

    <div class="vrindavan-about-image">
        <img src="assets/vrindavanresi.jpg"
             alt="Traditional Indian temple architecture">
    </div>

    <div class="vrindavan-about-content">

        <span class="vrindavan-label">ABOUT THE LOCATION</span>

        <h2>
            Experience the Beauty of
            <span>Vrindavan</span>
        </h2>

        <p>
            Vrindavan and Mathura are among India's renowned
            spiritual destinations. Known for their rich cultural
            heritage, temples and peaceful surroundings, these
            locations attract visitors throughout the year.
        </p>

        <p>
            Explore property opportunities in a destination that
            combines spirituality, culture and developing
            infrastructure.
        </p>

       

    </div>
</section>


<!-- EXPLORE VRINDAVAN - THREE BUILDING IMAGES -->
<section class="vrindavan-gallery">

    <div class="vrindavan-section-heading">

        <span>OUR LOCATION</span>

        <h2>Explore Vrindavan</h2>

        <p>
            Explore modern residential architecture and
            property opportunities in Vrindavan.
        </p>

    </div>

   <div class="vrindavan-gallery-grid">

    <?php foreach ($vrindavanBuildings as $item): ?>

        <div class="vrindavan-gallery-item">
            <img
                src="<?php echo htmlspecialchars($item['image']); ?>"
                alt="<?php echo htmlspecialchars($item['alt']); ?>"
                loading="lazy">
        </div>

    <?php endforeach; ?>

</div>

   

</section>


<!-- ADVANTAGES SECTION -->
<section class="vrindavan-advantages">

    <div class="vrindavan-section-heading">

        <span>WHY CHOOSE VRINDAVAN</span>

        <h2>A Destination of Opportunity</h2>

        <p>
            Discover the features that make Vrindavan and Mathura
            special destinations.
        </p>

    </div>

    <div class="vrindavan-advantages-grid">

        <div class="vrindavan-advantage-card">
            <i class="fa-solid fa-place-of-worship"></i>
            <h3>Spiritual Heritage</h3>
            <p>
                Home to renowned temples and important religious
                destinations.
            </p>
        </div>

        <div class="vrindavan-advantage-card">
            <i class="fa-solid fa-road"></i>
            <h3>Connectivity</h3>
            <p>
                Connected to major destinations through road
                and rail networks.
            </p>
        </div>

        <div class="vrindavan-advantage-card">
            <i class="fa-solid fa-hotel"></i>
            <h3>Tourism</h3>
            <p>
                A popular destination for pilgrims and tourists
                throughout the year.
            </p>
        </div>

        <div class="vrindavan-advantage-card">
            <i class="fa-solid fa-house"></i>
            <h3>Property Opportunities</h3>
            <p>
                Explore residential and investment opportunities
                in the region.
            </p>
        </div>

    </div>

</section>


<!-- CTA SECTION -->
<section class="vrindavan-cta">

    <div class="vrindavan-cta-content">

        <span>YOUR NEXT DESTINATION</span>

        <h2>Find Your Place in Vrindavan</h2>

        <p>
            Connect with Olivera Home Developers to explore
            available property opportunities.
        </p>

        <a href="booksitevisit.php" class="vrindavan-btn">
            Book a Site Visit
            <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div>

</section>


<!-- =================================
     FOOTER SECTION
================================= -->
<?php include __DIR__ . '/includes/footer.php'; ?>


</body>
</html>

