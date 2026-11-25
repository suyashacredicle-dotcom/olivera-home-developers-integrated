
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Become Our Partner | Olivera Home Developers</title>

    <link rel="stylesheet" href="CSS/style.css">

    
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>.public-form-success,.public-form-error{padding:13px 16px;margin:0 0 18px;border-radius:8px;font-weight:600}.public-form-success{background:#fff7df;color:#263247;border:1px solid #d49b27}.public-form-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}</style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>




<!-- HERO SECTION -->
<section class="partner-hero">

    <!-- Background Video -->
    <video class="partner-bg-video" autoplay muted loop playsinline poster="assets/8_G9_A1217_e4035ceb54.jpg">
        <source src="assets/becomeourpartner.mp4" type="video/mp4">
    </video>

    <!-- Dark Overlay -->
  

    <div class="partner-hero-content">

        <span>GROW TOGETHER WITH OLIVERA</span>

        <h1>Become Our <strong>Partner</strong></h1>

        <p>
            Build lasting relationships and explore new opportunities
            with Olivera Home Developers. Let's create success together.
        </p>

        <a href="#partner-form" class="partner-btn">
            Partner With Us
            <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div>
</section>


<!-- WHY PARTNER SECTION -->
<section class="partner-benefits">

    <div class="partner-heading">
        <span>WHY CHOOSE OLIVERA</span>
        <h2>Grow Your Business <strong>With Us</strong></h2>
        <p>
            We believe in strong partnerships built on trust,
            transparency and shared growth.
        </p>
    </div>

    <div class="partner-cards">

        <div class="partner-card">
            <i class="fa-solid fa-handshake"></i>
            <h3>Trusted Partnership</h3>
            <p>
                Build a long-term relationship with a team
                that values trust and transparency.
            </p>
        </div>

        <div class="partner-card">
            <i class="fa-solid fa-chart-line"></i>
            <h3>Business Growth</h3>
            <p>
                Explore new business opportunities and
                grow together in the real estate sector.
            </p>
        </div>

        <div class="partner-card">
            <i class="fa-solid fa-people-group"></i>
            <h3>Professional Support</h3>
            <p>
                Work with a dedicated team focused on
                collaboration and professional support.
            </p>
        </div>

    </div>
</section>


<!-- PARTNERSHIP OPPORTUNITIES -->
<section class="partner-opportunities">

    <div class="partner-opportunity-content">
        <span>OUR PARTNERSHIP NETWORK</span>

        <h2>Partnership <strong>Opportunities</strong></h2>

        <p>
            We welcome individuals and businesses interested
            in creating meaningful opportunities in real estate.
        </p>

        <ul>
            <li><i class="fa-solid fa-check"></i> Channel Partners</li>
            <li><i class="fa-solid fa-check"></i> Landowners</li>
            <li><i class="fa-solid fa-check"></i> Real Estate Associates</li>
            <li><i class="fa-solid fa-check"></i> Business Investors</li>
        </ul>
    </div>
     <!-- IMAGE HERE -->
    <div class="partner-opportunity-image">
        <img src="assets/oliveraparty.png" alt="Olivera Partnership">
    </div>

    

</section>



<!-- PARTNER FORM SECTION -->
<section class="partner-form-section" id="partner-form">

    <div class="partner-layout">

        <!-- LEFT SIDE: FORM -->
        <div class="partner-form-wrapper">

            <div class="partner-form-heading">
                <span>LET'S CONNECT</span>
                <h2>Become a <strong>Partner</strong></h2>
                <p>
                    Share your details with us. Our team will connect
                    with you to discuss partnership opportunities.
                </p>
            </div>

            <?php if (($_GET['submitted'] ?? '') === 'success'): ?><p class="public-form-success">Thank you. Your partnership enquiry has been received.</p><?php elseif (isset($_GET['submitted'])): ?><p class="public-form-error">We could not submit your request. Please review your details and try again.</p><?php endif; ?>
            <form class="partner-form" action="public-form-submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($publicFormCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_type" value="partner"
                <input type="hidden" name="return_to" value="becomeourpartner.php">>

                <div class="partner-form-grid">

                    <div class="partner-field">
                        <label>Full Name *</label>
                        <input type="text" name="name"
                        placeholder="Enter your full name" required>
                    </div>

                    <div class="partner-field">
                        <label>Mobile Number *</label>
                        <input type="tel" name="phone"
                        placeholder="Enter mobile number"
                        pattern="[0-9]{10}" maxlength="10" required>
                    </div>

                    <div class="partner-field">
                        <label>Email Address *</label>
                        <input type="email" name="email"
                        placeholder="Enter your email" required>
                    </div>

                    <div class="partner-field">
                        <label>City</label>
                        <input type="text" name="city"
                        placeholder="Enter your city">
                    </div>

                    <div class="partner-field">
                        <label>Partnership Type *</label>
                        <select name="partnership" required>
                            <option value="">Select Partnership Type</option>
                            <option>Channel Partner</option>
                            <option>Landowner</option>
                            <option>Real Estate Associate</option>
                            <option>Business Investor</option>
                            <option>Other</option>
                        </select>
                    </div>

                    <div class="partner-field">
                        <label>Investment Range</label>
                        <select name="investment">
                            <option value="">Select Range</option>
                            <option>Below ₹10 Lakhs</option>
                            <option>₹10 - ₹25 Lakhs</option>
                            <option>₹25 - ₹50 Lakhs</option>
                            <option>Above ₹50 Lakhs</option>
                            <option>Not Applicable</option>
                        </select>
                    </div>

                    <div class="partner-field full-width">
                        <label>Message</label>
                        <textarea name="message" rows="4"
                        placeholder="Tell us about your partnership interest"></textarea>
                    </div>

                </div>

                <button type="submit" class="partner-submit">
                    Submit Partnership Enquiry
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>

        </div>


        <!-- RIGHT SIDE: EXPLANATION -->
        <div class="partner-info-wrapper">

            <span class="partner-info-label">GROW WITH OLIVERA</span>

            <h2>Let's Build <span>Success Together.</span></h2>

            <p class="partner-info-intro">
                At Olivera Home Developers, we believe strong
                partnerships create stronger opportunities.
                Join us and become a part of our growing real
                estate journey.
            </p>

            <div class="partner-benefit">

                <div class="partner-benefit-icon">
                    <i class="fa-solid fa-handshake"></i>
                </div>

                <div>
                    <h3>Trusted Collaboration</h3>
                    <p>
                        Build long-term business relationships
                        with a team committed to transparency
                        and trust.
                    </p>
                </div>

            </div>

            <div class="partner-benefit">

                <div class="partner-benefit-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </div>

                <div>
                    <h3>Growth Opportunities</h3>
                    <p>
                        Explore new real estate opportunities
                        and grow your professional network
                        with us.
                    </p>
                </div>

            </div>

            <div class="partner-benefit">

                <div class="partner-benefit-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <h3>Dedicated Support</h3>
                    <p>
                        Our team will guide you through the
                        partnership process and help you
                        understand the opportunities.
                    </p>
                </div>

            </div>

            <div class="partner-process">
                <h3>How It Works</h3>

                <div class="process-step">
                    <span>01</span>
                    <p>Submit your partnership enquiry.</p>
                </div>

                <div class="process-step">
                    <span>02</span>
                    <p>Our team reviews your details.</p>
                </div>

                <div class="process-step">
                    <span>03</span>
                    <p>We connect to discuss opportunities.</p>
                </div>
            </div>

            <div class="partner-bottom-note">
                <i class="fa-solid fa-star"></i>
                Together, we create opportunities that last.
            </div>

        </div>

    </div>

</section>



<!-- CONTACT CTA -->
<section class="partner-cta">

    <h2>Let's Build Success <strong>Together</strong></h2>

    <p>
        Have questions about partnership opportunities?
        Connect with our team today.
    </p>

    <a href="https://wa.me/916391305365" target="_blank">
        <i class="fa-brands fa-whatsapp"></i>
        Connect on WhatsApp
    </a>

</section>


<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
