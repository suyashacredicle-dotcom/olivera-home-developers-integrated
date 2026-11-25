
<?php
require_once __DIR__ . '/includes/public_form_init.php';
$company = "OLIVERA HOME DEVELOPERS PVT. LTD.";
$year = date("Y");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Book Site Visit | Olivera Home Developers</title>

    <link rel="stylesheet" href="CSS/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>.public-form-success,.public-form-error{padding:13px 16px;margin:0 0 18px;border-radius:8px;font-weight:600}.public-form-success{background:#fff7df;color:#263247;border:1px solid #d49b27}.public-form-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}</style>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<!-- HERO SECTION -->
<section class="site-visit-hero">
    <div class="site-visit-hero-content">
        <span>OLIVERA HOME DEVELOPERS</span>

        <h1>Book Your <strong>Site Visit</strong></h1>

        <p>
            Hamari expert team aapki property journey mein har kadam pe saath hai.
            Site visit book karo aur personally dekho apna future plot!
        </p>

        <a href="#callback-form" class="visit-hero-button">
            Book Your Visit <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
</section>


<!-- CONTACT AND FORM SECTION -->
<section class="site-visit-section">

    <div class="site-visit-container">

        <!-- CONTACT INFORMATION -->
        <div class="site-visit-info">

            <span class="section-label">GET IN TOUCH</span>
            <h2>Let's Connect With You</h2>

            <div class="visit-info-item">
                <div class="visit-info-icon">
                    <i class="fa-solid fa-phone"></i>
                </div>
                <div>
                    <h4>Phone / WhatsApp</h4>
                    <p>+91 9891376111</p>
                </div>
            </div>

            <div class="visit-info-item">
                <div class="visit-info-icon">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div>
                    <h4>Email</h4>
                    <p>info@oliverahomedevelopers.com</p>
                </div>
            </div>

            <div class="visit-info-item">
                <div class="visit-info-icon">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div>
                    <h4>Office Address</h4>
                    <p>
                        B-23/A, 4th Floor, Gauravdeep Heights,
                        Behind Fortis Hospital, Sector 62,
                        Noida – 201301
                    </p>
                </div>
            </div>

            <div class="visit-info-item">
                <div class="visit-info-icon">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div>
                    <h4>Project Locations</h4>
                    <p>
                        Noida International Airport · Greater Noida ·
                        Yamuna Expressway · Mathura · Vrindavan
                    </p>
                </div>
            </div>

            <a href="https://wa.me/916391305365"
               target="_blank"
               rel="noopener noreferrer"
               class="visit-whatsapp-button">
                <i class="fa-brands fa-whatsapp"></i>
                Chat on WhatsApp
            </a>

        </div>


        <!-- CALLBACK FORM -->
        <div class="callback-form-box" id="callback-form">

            <span class="section-label">WE WILL CALL YOU</span>

            <h2>Request a Callback</h2>

            <p>Fill the form and our expert will call you within 24 hours.</p>

            <?php if (($_GET['submitted'] ?? '') === 'success'): ?><p class="public-form-success">Thank you. Your request has been received. Our team will contact you shortly.</p><?php elseif (isset($_GET['submitted'])): ?><p class="public-form-error">We could not submit your request. Please review your details and try again.</p><?php endif; ?>
            <form action="public-form-submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($publicFormCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_type" value="site_visit"
                <input type="hidden" name="return_to" value="booksitevisit.php">>

                <div class="visit-form-group">
                    <label>Full Name</label>
                    <input type="text" name="name"
                           placeholder="Enter your full name" required>
                </div>

                <div class="visit-form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone"
                           placeholder="Enter 10 digit mobile number"
                           pattern="[0-9]{10}" maxlength="10" required>
                </div>

                <div class="visit-form-group">
                    <label>Email Address</label>
                    <input type="email" name="email"
                           placeholder="Enter your email address">
                </div>

                <div class="visit-form-row">

                    <div class="visit-form-group">
                        <label>Select City</label>
                        <select name="city" required>
                            <option value="">-- Select City --</option>
                            <option>Noida</option>
                            <option>Greater Noida</option>
                            <option>Mathura</option>
                            <option>Vrindavan</option>
                        </select>
                    </div>

                    <div class="visit-form-group">
                        <label>Select Type</label>
                        <select name="property_type" required>
                            <option value="">-- Select Type --</option>
                            <option>Residential</option>
                            <option>Commercial</option>
                        </select>
                    </div>

                </div>

                <div class="visit-form-row">

                    <div class="visit-form-group">
                        <label>Select Size</label>
                        <select name="size" required>
                            <option value="">-- Select Size --</option>
                            <option>100 Gaj</option>
                            <option>200 Gaj</option>
                            <option>300 Gaj</option>
                            <option>500 Gaj</option>
                    </select>
                    </div>

                    <div class="visit-form-group">
                        <label>Preferred Visit Date (request)</label>
                        <input type="date" name="visit_date">
                    </div>

                </div>

                <div class="visit-form-group">
                    <label>Message</label>
                    <textarea name="message" rows="3"
                              placeholder="Write your message (optional)"></textarea>
                </div>

                <button type="submit" class="visit-submit-button">
                    <i class="fa-solid fa-calendar-check"></i>
                    Book Site Visit
                </button>

            </form>

        </div>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>


</body>
</html>
