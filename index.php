<?php
require_once __DIR__ . '/includes/public_form_init.php';
$trackingKeys = ['utm_source','source','utm_medium','utm_campaign','utm_content','utm_term','fbclid','gclid','gbraid','wbraid','ttclid'];
foreach ($trackingKeys as $trackingKey) {
    if (isset($_GET[$trackingKey]) && is_string($_GET[$trackingKey])) {
        $trackingValue = trim(preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $_GET[$trackingKey]));
        if ($trackingValue !== '') {
            $_SESSION['landing_tracking'][$trackingKey] = substr($trackingValue, 0, 255);
        }
    }
}
if (!empty($_SESSION['landing_tracking'])) {
    $tracking = $_SESSION['landing_tracking'];
    $source = strtolower((string)($tracking['utm_source'] ?? $tracking['source'] ?? ''));
    if ($source === '') {
        if (!empty($tracking['fbclid'])) $source = 'facebook';
        elseif (!empty($tracking['gclid']) || !empty($tracking['gbraid']) || !empty($tracking['wbraid'])) $source = 'google';
        elseif (!empty($tracking['ttclid'])) $source = 'other';
        else $source = 'website';
    }
    $aliases = ['fb'=>'facebook','meta'=>'facebook','facebook_ads'=>'facebook','ig'=>'instagram','instagram_ads'=>'instagram','adwords'=>'google','google_ads'=>'google','youtube_ads'=>'youtube'];
    $source = $aliases[$source] ?? $source;
    $_SESSION['ad_source_platform'] = in_array($source, ['website','facebook','instagram','google','youtube','other'], true) ? $source : 'other';
    $_SESSION['ad_source_campaign'] = $tracking['utm_campaign'] ?? null;
    $_SESSION['ad_utm_medium'] = $tracking['utm_medium'] ?? '';
    $_SESSION['ad_utm_content'] = $tracking['utm_content'] ?? '';
    $_SESSION['ad_utm_term'] = $tracking['utm_term'] ?? '';
    foreach (['fbclid','gclid','gbraid','wbraid','ttclid'] as $clickId) {
        $_SESSION['ad_' . $clickId] = $tracking[$clickId] ?? '';
    }
}
$company = "OLIVERA HOME DEVELOPERS PVT. LTD.";
$tagline = "BUILDING YOUR BETTER TOMORROW";
$year = date("Y");

$navigation = [
    "Home" => "#home",
    "Gallery" => "gallery.php",
    "Contact Us" => "contactus.php",
    "About Us" => "about.php",
    "Our Projects" => "ourprojects.php",
    "Director" => "director.php",
    
];

$isOurprojectsPage = basename($_SERVER['PHP_SELF']) === 'ourprojects.php';
$isAboutPage = basename($_SERVER['PHP_SELF']) === 'about.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Olivera Home Developers | Official Website</title>

    <!-- CSS FILE -->
    <link rel="stylesheet" href="CSS/style.css">

    <!-- GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>.hero-video-fallback{background:linear-gradient(120deg,rgba(4,19,44,.88),rgba(4,19,44,.52)),url("assets/8_G9_A1217_e4035ceb54.jpg") center/cover no-repeat}.public-form-success,.public-form-error{margin:0 0 18px;padding:14px 16px;border:1px solid #d49b27;border-radius:8px;background:#fff7df;color:#28354a;font-weight:600}.public-form-error{padding:14px 16px;border:1px solid #fecaca;border-radius:8px;background:#fee2e2;color:#991b1b;font-weight:600}</style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>



<!-- ================= HERO SECTION ================= -->

<section class="hero" id="home">

    <!-- BACKGROUND VIDEO -->
    <div class="hero-video hero-video-fallback" role="presentation" aria-hidden="true"></div>

    <!-- NAVY OVERLAY -->
   

    <!-- HERO CONTENT -->
    <div class="hero-content">

       

        <h1>
    OLIVERA HOME
    <span>DEVELOPERS</span>
</h1>

        <p class="hero-description">
            Premium residential &amp; commercial plots near
            Noida International Airport, Yamuna Expressway,
            Mathura &amp; Vrindavan. Transparent dealings.
            Freehold. RERA Compliant.
        </p>

        <div class="hero-buttons">

            <a href="ourprojects.php" class="primary-button">
                Explore Our Projects
                <i class="fa-solid fa-arrow-right"></i>
            </a>

            <a href="booksitevisit.php" class="secondary-button">
                Book Site Visit
            </a>

        </div>

    </div>

    <!-- TRUST BAR -->
 
<div class="hero-features">

    <div class="feature-item">
        <div class="feature-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <span>100% Freehold Plots</span>
    </div>

    <div class="feature-item">
        <div class="feature-icon">
            <i class="fa-solid fa-file-circle-check"></i>
        </div>
        <span>RERA Compliant</span>
    </div>

    <div class="feature-item">
        <div class="feature-icon">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <span>Transparent Dealings</span>
    </div>

</div>


   

    </div>

    

</section>




<!-- ABOUT US SECTION -->


<section class="olivera-about" id="about">

    <div class="olivera-about-container">

        <div class="olivera-about-heading">
            <span>DISCOVER OLIVERA</span>
            <h2>ABOUT <em>US</em></h2>
            <div class="about-heading-line"></div>
            <p>Building Dreams. Creating Opportunities.</p>
        </div>

        <div class="olivera-about-main">

            <div class="olivera-about-text">
                <span class="about-small-label">WHO WE ARE</span>

                <h3>More Than Properties,<br>
                    <em>We Build Relationships.</em>
                </h3>

                <p>
                    Olivera Home Developers Pvt. Ltd. is committed
                    to creating meaningful real estate opportunities
                    and helping people move closer to their dream
                    properties.
                </p>

                <p>
                    With a focus on thoughtful planning, customer
                    relationships and emerging real estate destinations,
                    we aim to make every property journey more informed
                    and rewarding.
                </p>

                <a href="#callback-form" class="about-discover-btn">
                    DISCOVER MORE
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="olivera-experience-box">
                <div class="experience-decoration"></div>
                <span class="experience-caption">OUR EXPERIENCE</span>
                <h2>15<span>+</span></h2>
                <h3>Years of Experience</h3>
                <p>Committed to trust, growth and meaningful real estate opportunities.</p>
                <div class="experience-bottom-line"></div>
            </div>

        </div>

        <div class="olivera-about-cards">

            <div class="olivera-about-card">
                <i class="fa-solid fa-eye"></i>
                <h3>Our Vision</h3>
                <p>
                    To become a trusted name in real estate by
                    creating meaningful property opportunities.
                </p>
            </div>

            <div class="olivera-about-card">
                <i class="fa-solid fa-bullseye"></i>
                <h3>Our Mission</h3>
                <p>
                    To guide customers through thoughtful planning
                    and customer-focused real estate solutions.
                </p>
            </div>

            <div class="olivera-about-card">
                <i class="fa-solid fa-handshake"></i>
                <h3>Our Values</h3>
                <p>
                    To build lasting relationships through trust,
                    commitment and transparency.
                </p>
            </div>

        </div>

    </div>
</section>


<!-- ABOUT SECTION -->
<section class="about-section" id="about">

    <div class="about-heading">
        <span class="section-label">GET TO KNOW US</span>
        <h2>Creating Spaces, <span>Building Legacies.</span></h2>
        <p>
            At Olivera Home Developers Pvt. Ltd., we believe real estate
            is more than land and buildings. It is about creating
            meaningful spaces, building communities and securing
            brighter futures.
        </p>
    </div>

   
<section class="olivera-about-editorial">

    <!-- WHO WE ARE -->
    <div class="about-editorial-row">

        <div class="about-editorial-image">
            <img src="https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1000&q=85"
                 alt="Beautiful residential property">
        </div>

        <div class="about-editorial-content">
            <span class="editorial-label">01 / WHO WE ARE</span>

            <h2>More Than Real Estate</h2>

            <p>
                At Olivera Home Developers Pvt. Ltd., we believe that
                every property represents an opportunity for a better
                future. Real estate is not just about developing
                properties; it is about creating meaningful spaces,
                building relationships and turning aspirations into
                addresses.
            </p>

            <p>
                With a focus on thoughtful development, quality,
                transparency and customer satisfaction, we strive
                to make every property journey a rewarding experience.
            </p>

            <a href="about.php" class="editorial-link">
                Discover Our Story <span>→</span>
            </a>
        </div>

    </div>


    <!-- OUR VISION -->
    <div class="about-editorial-row editorial-reverse">

        <div class="about-editorial-image">
            <img src="https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1000&q=85"
                 alt="Modern residential community">
        </div>

        <div class="about-editorial-content">
            <span class="editorial-label">02 / OUR VISION</span>

            <h2>Shaping Tomorrow's <em>World.</em></h2>

            <p>
                Our vision is to become a trusted partner in ambitious
                urban and industrial development by creating spaces
                that inspire progress and open doors to new
                opportunities.
            </p>

            <p>
                We aspire to develop communities where families can
                flourish, businesses can grow and investors can
                discover meaningful opportunities. Through strategic
                planning and a forward-looking approach, we aim to
                contribute to a better and more promising future.
            </p>

            <a href="about.php" class="editorial-link">
                Discover Our Vision <span>→</span>
            </a>
        </div>

    </div>


    <!-- OUR MISSION -->
    <div class="about-editorial-row">

        <div class="about-editorial-image">
            <img src="https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1000&q=85"
                 alt="Elegant modern home">
        </div>

        <div class="about-editorial-content">
            <span class="editorial-label">03 / OUR MISSION</span>

            <h2>Trust in Every <em>Step.</em></h2>

            <p>
                Our mission is to put our clients first and make every
                property journey simple, transparent and rewarding.
                We combine local market understanding, professional
                guidance and honest communication to help customers
                make confident property decisions.
            </p>

            <p>
                We are committed to building lasting relationships
                with clients, landowners, investors and business
                partners through responsible development, reliable
                support and a consistent focus on long-term value.
            </p>

            <a href="contactus.php" class="editorial-link">
                Connect With Us <span>→</span>
            </a>
        </div>

    </div>

</section>
</section>

    
<!-- WORLD CLASS AMENITIES -->
<section class="olivera-amenities">

    <div class="amenities-heading">
        <span>LIVE THE LIFESTYLE YOU DESERVE</span>
        <h2>World-Class <strong>Amenities</strong></h2>
        <p>
            Our township is designed for comfortable, secure and modern
            living with everything within reach.
        </p>
    </div>

    <div class="amenities-grid">

        <div class="amenity-card">
            <i class="fa-solid fa-camera"></i>
            <h3>CCTV Surveillance</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-shield-halved"></i>
            <h3>24/7 Security</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-tree"></i>
            <h3>Children's Park</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-lightbulb"></i>
            <h3>Street Lighting</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-building"></i>
            <h3>Club House</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-place-of-worship"></i>
            <h3>Temple</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-road"></i>
            <h3>Wide Paved Roads</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-border-all"></i>
            <h3>Boundary Wall</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-school"></i>
            <h3>Nearby Schools</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-hospital"></i>
            <h3>Nearby Hospital</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-highway"></i>
            <h3>Highway Access</h3>
        </div>

        <div class="amenity-card">
            <i class="fa-solid fa-store"></i>
            <h3>Commercial Shops</h3>
        </div>

    </div>

</section>


<!-- EASY PAYMENT PLANS -->
<section class="payment-plans">

    <div class="payment-heading">
        <span>FLEXIBLE OWNERSHIP OPTIONS</span>
        <h2>Easy Payment <strong>Plans</strong></h2>
        <p>
            Choose a plan that suits your budget. Payment goes directly
            to company account. 0% interest EMI available.
        </p>
    </div>

    <div class="payment-grid">

        <!-- PLAN 1 -->
        <div class="payment-card">

            <div class="plan-number">01</div>

            <h3>Full Payment</h3>

            <p class="plan-subtitle">Pay once, own forever</p>

            <div class="plan-divider"></div>

            <ul>
                <li><i class="fa-solid fa-check"></i> 100% payment upfront</li>
                <li><i class="fa-solid fa-check"></i> Immediate possession</li>
                <li><i class="fa-solid fa-check"></i> Registry included</li>
                <li><i class="fa-solid fa-check"></i> Mutation (Dakhil Kharij) included</li>
            </ul>

            <a href="property-enquiry-start.php" class="plan-btn">Enquire Now
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>


        <!-- PLAN 2 -->
        <div class="payment-card popular-plan">

            <div class="popular-label">
                <i class="fa-solid fa-star"></i> MOST POPULAR
            </div>

            <div class="plan-number">02</div>

            <h3>60 – 40 Plan</h3>

            <p class="plan-subtitle">Get possession fast, pay rest easy</p>

            <div class="plan-divider"></div>

            <ul>
                <li><i class="fa-solid fa-check"></i> Pay 60% → Get possession + Registry</li>
                <li><i class="fa-solid fa-check"></i> Remaining 40% in easy EMI</li>
                <li><i class="fa-solid fa-check"></i> 0% Interest for 1 Year</li>
                <li><i class="fa-solid fa-check"></i> Direct payment to company account</li>
            </ul>

            <p class="popular-description">
                Most popular — best balance of ownership and flexibility.
            </p>

            <a href="property-enquiry-start.php" class="plan-btn">Enquire Now
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>


        <!-- PLAN 3 -->
        <div class="payment-card">

            <div class="plan-number">03</div>

            <h3>40 – 60 Plan</h3>

            <p class="plan-subtitle">Start with minimum, pay over 2 years</p>

            <div class="plan-divider"></div>

            <ul>
                <li><i class="fa-solid fa-check"></i> Pay 40% → Get BBA Stamp Agreement</li>
                <li><i class="fa-solid fa-check"></i> Remaining 60% in easy EMI</li>
                <li><i class="fa-solid fa-check"></i> 0% Interest for 2 Years</li>
                <li><i class="fa-solid fa-check"></i> Ideal for first-time investors</li>
            </ul>

            <a href="property-enquiry-start.php" class="plan-btn">Enquire Now
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>

    </div>

    <p class="payment-note">
        <i class="fa-solid fa-circle-info"></i>
        EDC: ₹400/Gaj extra. Plot Registry amount extra as applicable.
    </p>

</section>


<section class="team-section" id="team">

    <div class="team-heading">
        <span>OUR PEOPLE</span>
        <h2>Meet Our Team</h2>
        <p>Our dedicated professionals are committed to building your better tomorrow.</p>
    </div>

    <div class="team-container">

        <div class="team-card">
            <img src="assets/Managing_Director.png" alt="Team Member">
            <div class="team-info">
                <h3>Rahul Sharma</h3>
                <p>Managing Director</p>
            </div>
        </div>

        <div class="team-card">
            <img src="assets/Project Manager.webp" alt="Team Member">
            <div class="team-info">
                <h3>Priya Sharma</h3>
                <p>Project Manager</p>
            </div>
        </div>

        <div class="team-card">
            <img src="assets/Sales Manager.webp" alt="Team Member">
            <div class="team-info">
                <h3>Amit Kumar</h3>
                <p>Sales Manager</p>
            </div>
        </div>

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
                <input type="hidden" name="form_type" value="partner">
                <input type="hidden" name="return_to" value="index.php#partner-form">

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

            <?php if (($_GET['submitted'] ?? '') === 'success'): ?><p class="public-form-success">Thank you. Your site visit request has been received. Our team will contact you to confirm the visit.</p><?php elseif (isset($_GET['submitted'])): ?><p class="public-form-error">We could not submit your request. Please review your details and try again.</p><?php endif; ?>
            <form action="public-form-submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($publicFormCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="form_type" value="site_visit">
                <input type="hidden" name="return_to" value="index.php#callback-form">

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
                        <label>Preferred Visit Date</label>
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






<!-- ================= DROPDOWN JAVASCRIPT ================= -->

<script>
const dropdowns = [
    {
        button: document.getElementById("projectsBtn"),
        menu: document.getElementById("projectsDropdown")
    },
    {
        button: document.getElementById("brochureBtn"),
        menu: document.getElementById("brochureDropdown")
    }
];

dropdowns.forEach(item => {

    if (!item.button || !item.menu) return;

    item.button.addEventListener("click", function(event) {

        event.stopPropagation();

        dropdowns.forEach(other => {
            if (other !== item && other.button && other.menu) {
                other.menu.classList.remove("show");
                other.button.setAttribute("aria-expanded", "false");
            }
        });

        const open = item.menu.classList.toggle("show");

        item.button.setAttribute("aria-expanded", open);

    });

});

document.addEventListener("click", function(event) {

    dropdowns.forEach(item => {

        if (!item.button || !item.menu) return;

        if (
            !item.button.contains(event.target) &&
            !item.menu.contains(event.target)
        ) {
            item.menu.classList.remove("show");
            item.button.setAttribute("aria-expanded", "false");
        }

    });

});

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        dropdowns.forEach(item => {

            if (!item.button || !item.menu) return;

            item.menu.classList.remove("show");
            item.button.setAttribute("aria-expanded", "false");

        });

    }

});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
