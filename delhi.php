
<?php
$delhiImages = [
    [
        "image" => "assets/delhi1.webp",
        "alt" => "India Gate, New Delhi"
    ],
    [
        "image" => "assets/delhi3.avif",
        "alt" => "Delhi city architecture"
    ],
    [
        "image" => "assets/delhi4.jpg",
        "alt" => "Delhi heritage architecture"
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delhi | Olivera Home Developers</title>

    <meta name="description" content="Explore Delhi with Olivera Home Developers. Discover location advantages, connectivity and real estate opportunities.">

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/delhi.css">

    <link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- HERO SECTION WITH BACKGROUND VIDEO -->
<section class="delhi-hero">

    <video class="delhi-bg-video"
           autoplay
           muted
           loop
           playsinline
           preload="metadata" poster="assets/delhi1.webp">

        <source src="assets/indiagatevideo.mp4" type="video/mp4">

        Your browser does not support HTML5 video.

    </video>

   

    <div class="delhi-hero-content">

        <span class="delhi-subtitle">
            THE HEART OF INDIA
        </span>

        <h1>Delhi</h1>

        <div class="delhi-heading-line"></div>

        <p>
            Discover residential and investment opportunities
            in India's vibrant capital city.
        </p>

        

    </div>

</section>


<!-- ABOUT DELHI -->
<section class="delhi-about">

    <div class="delhi-about-image">
        <img src="https://images.unsplash.com/photo-1587474260584-136574528ed5?auto=format&fit=crop&w=1200&q=85"
             alt="India Gate Delhi">
    </div>

    <div class="delhi-about-content">

        <span class="delhi-small-title">THE CAPITAL OF INDIA</span>

        <h2>Explore the Beauty of <span>Delhi</span></h2>

        <p>
            Delhi is one of India's most important metropolitan cities,
            known for its rich history, iconic monuments, modern
            infrastructure and vibrant lifestyle.
        </p>

        <p>
            With its established road networks, metro connectivity,
            commercial centres and educational institutions, Delhi
            continues to be an important destination for residential
            and business opportunities.
        </p>

        

    </div>

</section>


<!-- GALLERY -->
<section class="delhi-gallery">

    <div class="delhi-section-heading">
        <span>OUR LOCATION</span>
        <h2>Explore Delhi</h2>
        <p>
            Discover the architecture, heritage and lifestyle
            of India's capital.
        </p>
    </div>

    <div class="delhi-gallery-grid">

        <?php foreach ($delhiImages as $item): ?>

            <div class="delhi-gallery-item">

                <img
                    src="<?php echo htmlspecialchars($item['image']); ?>"
                    alt="<?php echo htmlspecialchars($item['alt']); ?>"
                    loading="lazy">

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- ADVANTAGES -->
<section class="delhi-advantages">

    <div class="delhi-section-heading">
        <span>WHY DELHI</span>
        <h2>A City of Opportunities</h2>
        <p>
            Delhi offers a combination of connectivity,
            infrastructure and urban development.
        </p>
    </div>

    <div class="delhi-advantages-grid">

        <div class="delhi-advantage-card">
            <i class="fa-solid fa-road"></i>
            <h3>Road Connectivity</h3>
            <p>
                Well-developed road networks connect Delhi
                with major cities and surrounding regions.
            </p>
        </div>

        <div class="delhi-advantage-card">
            <i class="fa-solid fa-train-subway"></i>
            <h3>Metro Connectivity</h3>
            <p>
                Delhi Metro provides convenient connectivity
                across several important areas.
            </p>
        </div>

        <div class="delhi-advantage-card">
            <i class="fa-solid fa-building"></i>
            <h3>Urban Infrastructure</h3>
            <p>
                A developed urban environment with commercial,
                residential and business centres.
            </p>
        </div>

        <div class="delhi-advantage-card">
            <i class="fa-solid fa-graduation-cap"></i>
            <h3>Education & Healthcare</h3>
            <p>
                Access to educational institutions,
                hospitals and essential services.
            </p>
        </div>

    </div>

</section>


<!-- CTA SECTION -->
<section class="delhi-cta">

    <div class="delhi-cta-content">

        <span>YOUR NEXT STEP STARTS HERE</span>

        <h2>Find Your Opportunity with Olivera</h2>

        <p>
            Connect with our team to know more about
            our real estate projects and opportunities.
        </p>

        <a href="booksitevisit.php" class="delhi-btn delhi-btn-gold">
            Book a Site Visit
            <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div>

</section>

<!-- ================= WHATSAPP BUTTON ================= -->
<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
