
<?php

$year = date("Y");

$isAboutPage = true;

$navigation = [
    "Home" => "#home",
    "About Us" => "about.php",
    "Our Projects" => "ourprojects.php",
    "Gallery" => "gallery.php",
    "Become Our Partner" => "becomeourpartner.php",
    "Contact Us" => "contactus.php"
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>About Us | Olivera Home Developers</title>

    <!-- CSS -->
    <link rel="stylesheet" href="CSS/style.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

<!-- ================= NAVBAR ================= -->

<?php include __DIR__ . '/includes/header.php'; ?>


<!-- ================= ABOUT SECTION ================= -->
  <div class="olivera-about-heading">
   
    <h2>ABOUT <em>US</em></h2>
    <div class="about-heading-line"></div>
    <p>Building Dreams. Creating Opportunities.</p>
</div>
<section class="about" id="about">

    <div class="about-image">
        <img
            src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1000"
            alt="Modern Olivera Property"
        >
    </div>

    <div class="about-content">

        <p class="section-label">ABOUT OLIVERA</p>

        <h2>
            Building Your<br>
            Better <span>Tomorrow.</span>
        </h2>

        <p>
            At Olivera Home Developers, we believe that every property
            represents an opportunity for a better future.
            We focus on quality, transparency and thoughtful development
            to help our customers find the right property.
        </p>

        <a href="contactus.php" class="text-link">
            Discover Our Vision &rarr;
        </a>

    </div>

</section>


<!-- ================= OUR VISION SECTION ================= -->

<section class="vision">

    <div class="vision-image">

        <img
            src="https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=900&q=80"
            alt="Modern residential development"
        >

    </div>

    <div class="vision-content">

        <p class="section-label">OUR FUTURE</p>

        <h2>
            Our <span>Vision</span>
        </h2>

        <p>
            To be the foundational partner for the world's most
            ambitious urban and industrial developments — creating
            spaces where families grow, businesses thrive, and
            futures are secured.
        </p>

    </div>

</section>


<!-- ================= OUR MISSION SECTION ================= -->

<section class="mission">

    <div class="mission-content">

        <p class="section-label">WHAT DRIVES US</p>

        <h2>
            Our <span>Mission</span>
        </h2>

        <!-- Mission 1 -->
        <div class="mission-item">

            <div class="mission-icon">
                <i class="fa-solid fa-handshake"></i>
            </div>

            <div>
                <h3>The Client-First Approach</h3>

                <p>
                    We don't just close deals; we open doors to new
                    opportunities. Your goals dictate our strategy.
                </p>
            </div>

        </div>

        <!-- Mission 2 -->
        <div class="mission-item">

            <div class="mission-icon">
                <i class="fa-solid fa-location-dot"></i>
            </div>

            <div>
                <h3>Hyper-Local Expertise</h3>

                <p>
                    We know the neighborhoods, the market cycles,
                    and the hidden gems that can make all the difference.
                </p>
            </div>

        </div>

    </div>

    <div class="mission-image">

        <img
            src="https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=900&q=80"
            alt="Modern residential property"
        >

    </div>

</section>




<!-- ================= OUR SERVICES SECTION ================= -->

<section class="services" id="services">

    <div class="services-heading">
        <p class="section-label">WHAT WE DO</p>

        <h2>Our <span>Services</span></h2>

        <p>
            Professional real estate solutions crafted for buyers,
            investors, and landowners.
        </p>
    </div>

    <div class="services-container">

        <!-- Residential Plots -->
        <div class="service-card">
            <div class="service-icon">
                <i class="fa-solid fa-house"></i>
            </div>

            <h3>Residential Plots</h3>

            <p>
                Premium plotted developments in high-growth locations.
            </p>
        </div>

        <!-- Commercial Spaces -->
        <div class="service-card">
            <div class="service-icon">
                <i class="fa-solid fa-building"></i>
            </div>

            <h3>Commercial Spaces</h3>

            <p>
                Smart investment opportunities for businesses and investors.
            </p>
        </div>

        <!-- Property Investment -->
        <div class="service-card">
            <div class="service-icon">
                <i class="fa-solid fa-chart-line"></i>
            </div>

            <h3>Property Investment</h3>

            <p>
                Guidance for profitable and secure real estate investments.
            </p>
        </div>

        <!-- Legal Assistance -->
        <div class="service-card">
            <div class="service-icon">
                <i class="fa-solid fa-file-contract"></i>
            </div>

            <h3>Legal Assistance</h3>

            <p>
                Transparent paperwork, registry, and compliance support.
            </p>
        </div>

    </div>

</section>


<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
