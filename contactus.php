
<?php
require_once __DIR__ . '/includes/public_form_init.php';

$company = "OLIVERA HOME DEVELOPERS PVT. LTD.";
$year = date("Y");

$navigation = [
    "Home" => "index.php#home",
    "About Us" => "about.php",
    "Our Projects" => "ourprojects.php",
    "Gallery" => "gallery.php",
    "Become Our Partner" => "becomeourpartner.php",
    "Contact Us" => "contactus.php"
];

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact Us | Olivera Home Developers</title>

    <!-- CSS FILE -->
    <link rel="stylesheet" href="CSS/style.css">

    <!-- GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- FONT AWESOME -->
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>.public-form-success,.public-form-error{padding:13px 16px;margin:0 0 18px;border-radius:8px;font-weight:600}.public-form-success{background:#fff7df;color:#263247;border:1px solid #d49b27}.public-form-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}</style>
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<!-- ================= NAVBAR ================= -->





<!-- ================= CONTACT HERO ================= -->

<section class="contact-hero">

    <div class="contact-hero-content">

    

        <h1>
            Book Your <strong>Site Visit</strong>
        </h1>

        <p2>
            Hamari expert team aapki property journey mein har kadam pe saath hai.
            Site visit book karo aur personally dekho apna future plot!
        </p2>

    </div>

</section>



<!-- ================= CONTACT DETAILS + FORM ================= -->

<section class="contact-main">

    <div class="contact-container">


        <!-- LEFT SIDE CONTACT INFORMATION -->

        <div class="contact-information">

            <span class="contact-label">GET IN TOUCH</span>

            <h2>
                Let's Build Your
                <span>Better Tomorrow</span>
            </h2>

            <p class="contact-intro">
                Our expert team is ready to guide you through
                every step of your property journey.
            </p>


            <!-- PHONE -->

            <div class="contact-item">

                <div class="contact-icon">
                    <i class="fa-solid fa-phone"></i>
                </div>

                <div class="contact-item-content">

                    <h3>Phone / WhatsApp</h3>

                    <a href="tel:+919891376111">
                        +91 9891376111
                    </a>

                </div>

            </div>


            <!-- EMAIL -->

            <div class="contact-item">

                <div class="contact-icon">
                    <i class="fa-solid fa-envelope"></i>
                </div>

                <div class="contact-item-content">

                    <h3>Email</h3>

                    <a href="mailto:info@oliverahomedevelopers.com">
                        info@oliverahomedevelopers.com
                    </a>

                </div>

            </div>


            <!-- OFFICE ADDRESS -->

            <div class="contact-item">

                <div class="contact-icon">
                    <i class="fa-solid fa-building"></i>
                </div>

                <div class="contact-item-content">

                    <h3>Office Address</h3>

                    <p>
                        B-23/A, 4th Floor, Gauravdeep Heights,
                        Behind Fortis Hospital, Sector 62,
                        Noida – 201301
                    </p>

                </div>

            </div>


            <!-- PROJECT LOCATIONS -->

            <div class="contact-item">

                <div class="contact-icon">
                    <i class="fa-solid fa-location-dot"></i>
                </div>

                <div class="contact-item-content">

                    <h3>Project Locations</h3>

                    <p>
                        Noida International Airport · Greater Noida ·
                        Yamuna Expressway · Mathura · Vrindavan
                    </p>

                </div>

            </div>


            <!-- WHATSAPP BUTTON -->

            <a
                href="https://wa.me/916391305365"
                target="_blank"
                rel="noopener noreferrer"
                class="contact-whatsapp"
            >

                <i class="fa-brands fa-whatsapp"></i>

                Chat on WhatsApp

            </a>

        </div>



        <!-- RIGHT SIDE CALLBACK FORM -->

        <div class="contact-form-card">

            <span class="contact-label">BOOK YOUR PROPERTY VISIT</span>

            <h2>Request a Callback</h2>

            <p>
                Fill the form and our expert will call you within 24 hours.
            </p>


            <?php if (($_GET['submitted'] ?? '') === 'success'): ?><p class="public-form-success">Thank you. Your request has been received. Our team will contact you shortly.</p><?php elseif (isset($_GET['submitted'])): ?><p class="public-form-error">We could not submit your request. Please review your details and try again.</p><?php endif; ?>
            <form action="public-form-submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($publicFormCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_type" value="callback"
                <input type="hidden" name="return_to" value="contactus.php">>


                <!-- NAME -->

                <div class="contact-form-group">

                    <label for="name">Full Name</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter your full name"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="contact-form-group">

                    <label for="phone">Phone Number</label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="Enter your 10-digit mobile number"
                        pattern="[0-9]{10}"
                        maxlength="10"
                        required
                    >

                </div>


                <!-- CITY -->

                <div class="contact-form-group">

                    <label for="city">Select City</label>

                    <select id="city" name="city" required>

                        <option value="">-- Select City --</option>

                        <option value="Noida">Noida</option>

                        <option value="Greater Noida">Greater Noida</option>

                        <option value="Mathura">Mathura</option>

                    </select>

                </div>


                <!-- PROPERTY TYPE -->

                <div class="contact-form-group">

                    <label for="type">Select Type</label>

                    <select id="type" name="property_type" required>

                        <option value="">-- Select Type --</option>

                        <option value="Residential">Residential</option>

                        <option value="Commercial">Commercial</option>

                    </select>

                </div>


                <!-- PLOT SIZE -->

                <div class="contact-form-group">

                    <label for="size">Select Size</label>

                    <select id="size" name="plot_size" required>

                        <option value="">-- Select Size --</option>

                        <option value="100 Gaj">100 Gaj</option>

                        <option value="200 Gaj">200 Gaj</option>

                    </select>

                </div>


                <!-- SUBMIT BUTTON -->

                <button type="submit" class="contact-submit-btn">

                    <i class="fa-regular fa-calendar-check"></i>

                    Book Site Visit Now

                </button>


            </form>

        </div>

    </div>

</section>



<!-- ================= FOOTER ================= -->
<?php include __DIR__ . '/includes/footer.php'; ?>


</body>
</html>
