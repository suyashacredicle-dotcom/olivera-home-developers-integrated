<?php
// gallerysection.php - Achievements & Recognition Page for Olivera Home Developers
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Achievements &amp; Recognition | Olivera Home Developers</title>

    <!-- CSS -->
    <link rel="stylesheet" href="CSS/style.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=Montserrat:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<!-- ================= NAVBAR ================= -->
<?php include __DIR__ . '/includes/header.php'; ?>


<!-- ================= ACHIEVEMENTS SECTION ================= -->
<section class="director-achievements-alt" id="achievements">

    <div class="achievement-alt-container">

        <!-- SECTION HEADING -->
        <div class="achievement-alt-heading">
            <span>THE JOURNEY OF EXCELLENCE</span>
            <h2>Achievements &amp; <em>Recognition</em></h2>
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
                    HONOUR &amp; RECOGNITION
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
                    PLANNING &amp; EXECUTION
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
                    <span>Driven by Planning &amp; Vision</span>
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

                <h3>Leadership &amp; Growth</h3>

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


<!-- ================= FOOTER ================= -->
<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>