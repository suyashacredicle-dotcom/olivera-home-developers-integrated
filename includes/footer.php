<?php require_once __DIR__ . '/site_bootstrap.php'; ?>

<!-- WHATSAPP FLOATING BUTTON -->
<a
    href="https://wa.me/916391305365"
    class="whatsapp-button"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Contact us on WhatsApp">
    <i class="fa-brands fa-whatsapp"></i>
</a>

<footer class="olivera-footer">

    <div class="footer-main">

        <!-- LEFT COLUMN: LOGO + CONTACT -->
        <div class="footer-brand">

            <a href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="footer-logo">
                <img
                    src="<?php echo htmlspecialchars(site_url('assets/WhatsApp Image 2026-11-16 at 3.29.00 PM.jpeg'), ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Olivera Home Developers">
            </a>

            <h3>Contact Us</h3>

            <a href="tel:+919891376111" class="footer-contact-item">
                <i class="fa-solid fa-phone"></i>
                <span>+91 9891376111</span>
            </a>

            <a href="mailto:info@oliverahomedevelopers.com" class="footer-contact-item">
                <i class="fa-solid fa-envelope"></i>
                <span>info@oliverahomedevelopers.com</span>
            </a>

            <a href="https://wa.me/916391305365"
               target="_blank"
               rel="noopener noreferrer"
               class="footer-contact-item">
                <i class="fa-brands fa-whatsapp"></i>
                <span>WhatsApp Us</span>
            </a>

            <div class="footer-social">
    <a href="https://www.instagram.com/" target="_blank"
       rel="noopener noreferrer" class="instagram" aria-label="Instagram">
        <i class="fa-brands fa-instagram"></i>
    </a>

    <a href="https://www.facebook.com/" target="_blank"
       rel="noopener noreferrer" class="facebook" aria-label="Facebook">
        <i class="fa-brands fa-facebook-f"></i>
    </a>

    <a href="https://www.linkedin.com/" target="_blank"
       rel="noopener noreferrer" class="linkedin" aria-label="LinkedIn">
        <i class="fa-brands fa-linkedin-in"></i>
    </a>

    <a href="https://www.youtube.com/" target="_blank"
       rel="noopener noreferrer" class="youtube" aria-label="YouTube">
        <i class="fa-brands fa-youtube"></i>
    </a>

    
    <a href="https://telegram.org/" target="_blank"
       rel="noopener noreferrer" class="telegram" aria-label="Telegram">
        <i class="fa-brands fa-telegram"></i>
    </a>


</div>
           
        </div>


        <!-- MIDDLE COLUMN: QUICK LINKS + LOCATIONS -->
        <div class="footer-navigation">

            <div class="footer-links">

                <h3>Quick Links</h3>

                <a href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Home</a>
                <a href="<?php echo htmlspecialchars(site_url('ourprojects.php'), ENT_QUOTES, 'UTF-8'); ?>">Our Projects</a>
                <a href="<?php echo htmlspecialchars(site_url('index.php#amenities'), ENT_QUOTES, 'UTF-8'); ?>">Amenities</a>
                <a href="<?php echo htmlspecialchars(site_url('index.php#payment-plans'), ENT_QUOTES, 'UTF-8'); ?>">Payment Plans</a>
                <a href="<?php echo htmlspecialchars(site_url('gallery.php'), ENT_QUOTES, 'UTF-8'); ?>">Gallery</a>
                <a href="<?php echo htmlspecialchars(site_url('becomeourpartner.php'), ENT_QUOTES, 'UTF-8'); ?>">Become Our Partner</a>
                <a href="<?php echo htmlspecialchars(site_url('contactus.php'), ENT_QUOTES, 'UTF-8'); ?>">Contact Us</a>

            </div>

            <div class="footer-links footer-locations">

                <h3>Our Locations</h3>

                <a href="<?php echo htmlspecialchars(site_url('noida.php'), ENT_QUOTES, 'UTF-8'); ?>">Noida</a>
                <a href="<?php echo htmlspecialchars(site_url('vrindavan.php'), ENT_QUOTES, 'UTF-8'); ?>">Vrindavan</a>
                <a href="<?php echo htmlspecialchars(site_url('faridabad.php'), ENT_QUOTES, 'UTF-8'); ?>">Faridabad</a>
                <a href="<?php echo htmlspecialchars(site_url('faridabad.php'), ENT_QUOTES, 'UTF-8'); ?>">Yamuna Expressway</a>
                <a href="<?php echo htmlspecialchars(site_url('delhi.php'), ENT_QUOTES, 'UTF-8'); ?>">Delhi NCR</a>

            </div>

        </div>


        <!-- RIGHT COLUMN: OFFICE ADDRESS + MAP -->
        <div class="footer-address">

            <h3>Office Address</h3>

            <p class="footer-address-text">

                <i class="fa-solid fa-location-dot"></i>

                <span>
                    B-23/A, 4th Floor, Gauravdeep Heights,
                    Behind Fortis Hospital, Sector 62,
                    Noida – 201301
                </span>

            </p>

            <h3 class="footer-map-title">Find Us On Map</h3>

            <div class="footer-map-box">

                <div id="olivera-dark-map"></div>

                <a
                    href="https://www.google.com/maps/search/?api=1&query=B-23%2FA+Gauravdeep+Heights+Sector+62+Noida"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="footer-map-link">

                    <i class="fa-solid fa-location-arrow"></i>
                    Get Directions

                </a>

            </div>

        </div>

    </div>


    <!-- BOTTOM COPYRIGHT -->
    <div class="footer-bottom">

        <p>
            © <?php echo date("Y"); ?>
            Olivera Home Developers Pvt. Ltd. All Rights Reserved.
        </p>

        <p>
            Powered by
            <a href="https://credicletech.com/"
               target="_blank"
               rel="noopener noreferrer">
                Credicle Fintech Private Limited
            </a>
        </p>

    </div>

</footer>


<!-- DARK MAP LIBRARY -->
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const mapElement = document.getElementById("olivera-dark-map");

    if (!mapElement || typeof L === "undefined") {
        console.error("Map library or map element not found.");
        return;
    }

    const map = L.map("olivera-dark-map").setView(
        [28.6270, 77.3740],
        13
    );

    L.tileLayer(
        "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
        {
            attribution: "",
            subdomains: "abcd",
            maxZoom: 20
        }
    ).addTo(map);

    L.marker([28.6270, 77.3740])
        .addTo(map)
        .bindPopup("Olivera Home Developers, Sector 62, Noida");

    setTimeout(function () {
        map.invalidateSize();
    }, 500);

});
</script>
