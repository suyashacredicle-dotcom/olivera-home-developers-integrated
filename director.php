<?php
// Company aur Director details
$director_name = "Deepak Sharma"; // Yahan Director ka naam likhein
$director_designation = "Managing Director";
$company_name = "Olivera Home Developers Pvt. Ltd.";
$company_tagline = "Building Your Better Tomorrow";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Director Profile - <?php echo $company_name; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Gold & Blue Theme CSS -->
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
    <?php // include 'header.php'; ?>

    <!-- Royal Hero Section matching Logo Theme -->
    <header class="director-hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-center text-lg-start mb-4 mb-lg-0">
                    
                    <h1 class="display-4 fw-bold text-white mb-3">Shaping Skylines & Building Your Better Tomorrow</h1>
                    <p class="lead text-light opacity-85 mb-4">Meet the visionary leadership driving architectural excellence, trust, and quality construction.</p>
                    <div class="d-flex justify-content-center justify-content-lg-start gap-3">
                        
                        <a href="gallery-section.php" class="btn btn-gold px-4 py-2">
                                  View Gallery
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="hero-img-wrapper">
                        <img src="assets/director7.jpeg" alt="Director at Work" class="img-fluid rounded-4 shadow-lg">
                    </div>
                </div>
            </div>
        </div>
    </header>

 
<!-- DIRECTOR EXPERIENCE STATS -->

<section class="olivera-horizontal-stats">
    <div class="horizontal-stats-inner">

        <div class="horizontal-stat">
            <h3>15+</h3>
            <p>Years Experience</p>
        </div>

        <div class="horizontal-stat">
            <h3>2011</h3>
            <p>Journey Began</p>
        </div>

        <div class="horizontal-stat">
            <h3>Pan-India</h3>
            <p>Project Presence</p>
        </div>

        <div class="horizontal-stat">
            <h3>Awarded</h3>
            <p>Real Estate Recognition</p>
        </div>

    </div>
</section>


    <!-- Detailed Profile Section -->
    <section id="profile-details" class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <div class="profile-portrait-box position-relative">
                    <img src="assets/director2.jpeg" alt="<?php echo $director_name; ?>" class="img-fluid rounded-4 shadow">
                    <div class="experience-badge-floating">
                        
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <span class="gold-text fw-semibold text-uppercase tracking-wider fs-6">From The Director's Desk</span>
                <h2 class="fw-bold display-6 mb-2 text-dark"><?php echo $director_name; ?></h2>
                <h5 class="text-secondary fw-normal mb-4"><?php echo $director_designation; ?>, <?php echo$company_name; ?></h5>
                
                <div class="gold-quote-box mb-4">
                    <i class="fas fa-quote-left gold-text fa-2x mb-2 d-block"></i>
                    <p class="mb-0 fst-italic text-dark fw-medium">"Humara maksad sirf ghar banana nahi hai, balki har parivar ke liye ek behtar aur surakshit bhavishya ('Building Your Better Tomorrow') tayar karna hai."</p>
                </div>

                <p class="text-muted lh-lg">
                    Welcome to <strong><?php echo $company_name; ?></strong>. Under the dynamic and inspiring leadership of <strong><?php echo $director_name; ?></strong>, our company stands as a symbol of trust, architectural innovation, and unmatched quality in real estate development.
                </p>

                <div class="row mt-4 g-3">
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-gold me-3"><i class="fas fa-check-circle gold-text"></i></div>
                            <span class="fw-semibold text-dark">Quality Construction</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-gold me-3"><i class="fas fa-check-circle gold-text"></i></div>
                            <span class="fw-semibold text-dark">Architectural Vision</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-gold me-3"><i class="fas fa-check-circle gold-text"></i></div>
                            <span class="fw-semibold text-dark">Client Trust & Integrity</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-gold me-3"><i class="fas fa-check-circle gold-text"></i></div>
                            <span class="fw-semibold text-dark">Sustainable Growth</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


<!-- DIRECTOR'S JOURNEY ARTICLE -->
<section class="director-journey-section">
    <div class="container">

        <div class="journey-heading text-center">
            <span class="journey-tag">THE JOURNEY OF LEADERSHIP</span>
            <h2>Building Trust. <span>Creating Landmarks.</span></h2>
            <p>A journey shaped by experience, vision and a commitment to real estate development.</p>
        </div>

        <div class="journey-article">

            <div class="article-date">
                <i class="fa-regular fa-calendar"></i>
                Since 2011
            </div>

            <h3>A Vision That Began in 2011</h3>

            <p>
                With a journey that began in 2011, Deepak Sharma brings
                over 15 years of experience in the real estate sector.
                Over the years, his professional journey has been guided
                by a vision to build meaningful opportunities, establish
                lasting relationships and contribute to the development
                of real estate projects across India.
            </p>

            <p>
                His experience spans multiple locations, including
                Noida, Delhi, Gurugram, Jewar, Vrindavan, Uttarakhand
                and Goa in India, along with Dubai. This broad geographical
                presence reflects an interest in diverse real estate
                markets and opportunities across different regions.
            </p>

            <p>
                At Olivera Home Developers Pvt. Ltd., this experience
                and vision are reflected in an approach focused on
                transparency, professional relationships and long-term
                growth. The aim is to create value for customers,
                associates, landowners and business partners through
                responsible real estate development.
            </p>

            <p>
                Recognition through a real estate award marks an
                important milestone in this professional journey.
                For the company, every achievement represents a
                continued commitment to quality, trust and the vision
                of building a better tomorrow.
            </p>

            <blockquote>
                “Our journey is not only about developing properties;
                it is about building trust, creating opportunities
                and shaping a better future.”
            </blockquote>

            <div class="article-signature">
                <strong>Deepak Sharma</strong>
                <span>Managing Director</span>
                <span>Olivera Home Developers Pvt. Ltd.</span>
            </div>

        </div>
    </div>
</section>

<!-- PROJECT PRESENCE -->
<section class="project-presence-section">
    <div class="container">

        <div class="text-center mb-5">
            <span class="journey-tag">OUR GEOGRAPHICAL REACH</span>
            <h2 class="presence-title">A Growing <span>Project Presence</span></h2>
            <p class="text-muted">
                Exploring real estate opportunities across India and beyond.
            </p>
        </div>

        <div class="location-grid">

            <div class="location-card">
                <i class="fa-solid fa-location-dot"></i>
                <h4>Noida</h4>
                <p>Uttar Pradesh, India</p>
            </div>

            <div class="location-card">
                <i class="fa-solid fa-location-dot"></i>
                <h4>Delhi</h4>
                <p>India</p>
            </div>

            <div class="location-card">
                <i class="fa-solid fa-location-dot"></i>
                <h4>Gurugram</h4>
                <p>Haryana, India</p>
            </div>

            <div class="location-card">
                <i class="fa-solid fa-location-dot"></i>
                <h4>Jewar</h4>
                <p>Uttar Pradesh, India</p>
            </div>

            <div class="location-card">
                <i class="fa-solid fa-location-dot"></i>
                <h4>Vrindavan</h4>
                <p>Uttar Pradesh, India</p>
            </div>

            <div class="location-card">
                <i class="fa-solid fa-location-dot"></i>
                <h4>Uttarakhand</h4>
                <p>India</p>
            </div>

            <div class="location-card">
                <i class="fa-solid fa-location-dot"></i>
                <h4>Goa</h4>
                <p>India</p>
            </div>

            <div class="location-card international">
                <i class="fa-solid fa-globe"></i>
                <h4>Dubai</h4>
                <p>United Arab Emirates</p>
            </div>

        </div>
    </div>
</section>


    <!-- Gallery & Achievements Section -->
    

<!-- DIRECTOR ACHIEVEMENTS SECTION -->
<section class="director-achievements-alt">

    <div class="achievement-alt-container">

        <!-- SECTION HEADING -->
        <div class="achievement-alt-heading">
            <span>THE JOURNEY OF EXCELLENCE</span>
            <h2>Achievements & <em>Recognition</em></h2>
            <p>
                A reflection of leadership, professional dedication
                and milestones achieved throughout the journey.
            </p>
        </div>


        <!-- ACHIEVEMENT 1: DESCRIPTION LEFT, IMAGE RIGHT -->
        <div class="achievement-alt-row">

            <div class="achievement-alt-content">
                <span class="achievement-alt-number">01</span>

                <span class="achievement-alt-label">
                    <i class="fa-solid fa-award"></i>
                    AWARD RECOGNITION
                </span>

                <h3>Samman Prateek Award</h3>

                <p>
                    A proud moment in the professional journey of
                    Deepak Sharma. Honoured with the Samman Prateek,
                    this recognition represents an important milestone
                    and reflects his continued dedication, leadership
                    and commitment to growth in the real estate sector.
                </p>

                <div class="achievement-alt-footer">
                    <i class="fa-solid fa-trophy"></i>
                    <span>A Milestone Worth Celebrating</span>
                </div>
            </div>

            <div class="achievement-alt-image">
                <img src="assets/director5.jpeg"
                     alt="Deepak Sharma receiving Samman Prateek Award">
                <span class="achievement-image-caption">
                    HONOUR & RECOGNITION
                </span>
            </div>

        </div>


        <!-- ACHIEVEMENT 2: IMAGE LEFT, DESCRIPTION RIGHT -->
        <div class="achievement-alt-row reverse">

            <div class="achievement-alt-image">
                <img src="assets/director3.jpeg"
                     alt="Director reviewing project plans">
                <span class="achievement-image-caption">
                    STRATEGIC VISION
                </span>
            </div>

            <div class="achievement-alt-content">
                <span class="achievement-alt-number">02</span>

                <span class="achievement-alt-label">
                    <i class="fa-solid fa-compass-drafting"></i>
                    PLANNING & EXECUTION
                </span>

                <h3>Vision Into Action</h3>

                <p>
                    Strategic planning and careful evaluation are
                    important parts of every real estate development.
                    Reviewing project layouts and blueprints reflects
                    a focused approach towards planning, coordination
                    and informed decision-making.
                </p>

                <div class="achievement-alt-footer">
                    <i class="fa-solid fa-building"></i>
                    <span>Driven by Planning & Vision</span>
                </div>
            </div>

        </div>


        <!-- ACHIEVEMENT 3: DESCRIPTION LEFT, IMAGE RIGHT -->
        <div class="achievement-alt-row">

            <div class="achievement-alt-content">
                <span class="achievement-alt-number">03</span>

                <span class="achievement-alt-label">
                    <i class="fa-solid fa-star"></i>
                    LEADERSHIP
                </span>

                <h3>Leadership & Growth</h3>

                <p>
                    Since beginning his journey in 2011, Deepak Sharma
                    has built over 15 years of professional experience.
                    His journey reflects a continued focus on building
                    relationships, exploring opportunities and
                    contributing to Olivera Home Developers' vision.
                </p>

                <div class="achievement-alt-footer">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>15+ Years of Experience</span>
                </div>
            </div>

            <div class="achievement-alt-image">
                <img src="assets/director6.jpeg"
                     alt="Director of Olivera Home Developers">
                <span class="achievement-image-caption">
                    PROFESSIONAL JOURNEY
                </span>
            </div>

        </div>

    </div>

</section>



        <!-- ================= PREMIUM FOOTER ================= -->
<!-- ================= PREMIUM FOOTER ================= -->

</div>
    

    <?php // include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openModal(imgSrc, captionText) {
            document.getElementById('modalImage').src = imgSrc;
            document.getElementById('modalCaption').innerText = captionText;
            var myModal = new bootstrap.Modal(document.getElementById('imageModal'));
            myModal.show();
        }
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>