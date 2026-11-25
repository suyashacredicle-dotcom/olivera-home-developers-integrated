
<?php
$noidaImages = [
    [
        'image' => 'assets/noida75.jpg',
        'alt' => 'Modern commercial buildings in Noida'
    ],
    [
        'image' => 'assets/images (20).jpg',
        'alt' => 'Modern urban architecture in Noida'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Noida Projects | Olivera Home Developers</title>

    <!-- Main Website CSS -->
    <link rel="stylesheet" href="CSS/style.css">

    <!-- Noida Page CSS -->
    <link rel="stylesheet" href="CSS/noida.css">

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
     NOIDA HERO SECTION WITH VIDEO
================================= -->

<section class="noida-hero">

    <!-- Background Video -->
    <video class="noida-bg-video" autoplay muted loop playsinline preload="metadata" poster="assets/8_G9_A1217_e4035ceb54.jpg">
        <source src="assets/1102286-uhd_4096_2160_24fps.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>

    <!-- Dark Overlay -->
   

    <!-- Hero Content -->
    <div class="noida-heading">

        <span>EXPLORE OUR LOCATION</span>

        <h1>Noida Projects</h1>

        <div class="noida-heading-line"></div>

        <p>
            Discover premium property opportunities in Noida,
            a rapidly developing destination with excellent connectivity.
        </p>

    </div>

</section>



<!-- EXPLORE NOIDA SECTION -->
<section class="noida-gallery">

    <div class="noida-section-heading">
        <span>OUR LOCATION</span>
        <h2>Explore Noida</h2>
        <p>Discover the lifestyle and opportunities of Noida.</p>
    </div>

    <div class="noida-explore-container">

        <!-- LEFT SIDE IMAGE -->
        <div class="noida-explore-image">
            <img
                src="assets/noida75.jpg"
                alt="Modern buildings in Noida"
                loading="lazy"
            >
        </div>

        <!-- RIGHT SIDE CONTENT -->
        <div class="noida-explore-content">

            <span class="noida-explore-label">THE CITY OF OPPORTUNITIES</span>

            <h2>Discover the Growing City of <span>Noida</span></h2>

            <p>
                Noida, located in Uttar Pradesh, is one of the prominent
                urban destinations in the Delhi NCR region. Known for its
                planned sectors, modern infrastructure and growing
                commercial developments, the city offers a contemporary
                urban lifestyle.
            </p>

            <p>
                With metro connectivity, educational institutions,
                healthcare facilities, shopping destinations and
                business centres, Noida provides access to essential
                facilities for everyday living.
            </p>

            <p>
                Its developing residential and commercial landscape
                makes Noida an attractive destination for people
                exploring modern property opportunities.
            </p>

           

        </div>

    </div>

</section>



<!-- =================================
     LOCATION ADVANTAGES
================================= -->

<section class="noida-advantages">

    <div class="noida-section-heading">

        <span>LOCATION ADVANTAGES</span>

        <h2>Everything Within Reach</h2>

        <p>
            Noida offers access to essential lifestyle and urban facilities.
        </p>

    </div>


    <div class="noida-advantages-grid">

        <!-- Metro -->
        <div class="noida-advantage-card">

            <i class="fa-solid fa-train-subway"></i>

            <h3>Metro Connectivity</h3>

            <p>
                Metro connectivity across several parts of Noida and Delhi NCR.
            </p>

        </div>


        <!-- Shopping -->
        <div class="noida-advantage-card">

            <i class="fa-solid fa-cart-shopping"></i>

            <h3>Shopping & Malls</h3>

            <p>
                Shopping destinations, malls, restaurants and entertainment options.
            </p>

        </div>


        <!-- Healthcare -->
        <div class="noida-advantage-card">

            <i class="fa-solid fa-hospital"></i>

            <h3>Healthcare</h3>

            <p>
                Access to hospitals, clinics and healthcare facilities.
            </p>

        </div>


        <!-- Education -->
        <div class="noida-advantage-card">

            <i class="fa-solid fa-school"></i>

            <h3>Education</h3>

            <p>
                Schools, colleges and educational institutions in the region.
            </p>

        </div>

    </div>

</section>


<!-- =================================
     SITE VISIT CTA
================================= -->

<section class="noida-cta">

    <div class="noida-cta-content">

        <span>YOUR NEXT ADDRESS STARTS HERE</span>

        <h2>Explore Property Opportunities in Noida</h2>

        <p>
            Connect with our team to learn more about our projects
            and arrange a site visit.
        </p>

        <a href="booksitevisit.php" class="noida-visit-btn">

            Book Your Site Visit

            <i class="fa-solid fa-arrow-right"></i>

        </a>

    </div>

</section>



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
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const map = L.map("olivera-dark-map").setView(
        [28.6270, 77.3740],
        13
    );

    L.tileLayer(
        "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
        {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
            subdomains: "abcd",
            maxZoom: 20
        }
    ).addTo(map);

    L.marker([28.6270, 77.3740])
        .addTo(map)
        .bindPopup("Olivera Home Developers<br>Sector 62, Noida")
        .openPopup();

});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
