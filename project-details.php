
<?php

// --------------------------------------------------
// DATABASE AND HELPERS
// --------------------------------------------------

require_once __DIR__ . "/config/database.php";

function projectDetailEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// --------------------------------------------------
// GET PROJECT SLUG
// --------------------------------------------------

$slug = trim((string)($_GET['location'] ?? ''));

if (
    $slug === '' ||
    !preg_match('/^[a-zA-Z0-9-]+$/', $slug)
) {
    http_response_code(404);
    exit("Project not found.");
}

// --------------------------------------------------
// FETCH PUBLISHED PROJECT
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM projects
    WHERE slug = :slug
      AND status = 'published'
    LIMIT 1
");

$stmt->execute([
    ':slug' => $slug
]);

$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(404);
    exit("Project not found or unavailable.");
}

// --------------------------------------------------
// PROJECT CONTENT
// --------------------------------------------------

$title = $project['title'] ?? '';
$shortDescription = $project['short_description'] ?? '';
$heroDescription = $project['hero_description'] ?: $shortDescription;

$cardImage = $project['card_image'] ?? '';
$detailImage = $project['detail_image'] ?: $cardImage;

$introLabel = $project['intro_label'] ?: 'DISCOVER OLIVERA';
$introHeading = $project['intro_heading'] ?: $title;
$introContent = $project['intro_content'] ?? '';

$advantagesHeading = $project['advantages_heading'] ?: 'Why Choose This Location?';
$advantagesDescription = $project['advantages_description'] ?? '';

$ctaHeading = $project['cta_heading'] ?: 'Interested in This Project?';
$ctaDescription = $project['cta_description'] ?: 'Connect with our team to learn more and arrange a site visit.';

// --------------------------------------------------
// ADVANTAGES JSON
// --------------------------------------------------

$advantages = [];

if (!empty($project['advantages_json'])) {

    $decodedAdvantages = json_decode(
        $project['advantages_json'],
        true
    );

    if (is_array($decodedAdvantages)) {
        $advantages = $decodedAdvantages;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo projectDetailEscape($title); ?> | Olivera Home Developers</title>

    <meta
        name="description"
        content="<?php echo projectDetailEscape($shortDescription); ?>">

    <link rel="stylesheet" href="CSS/style.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <style>

        :root {
            --project-navy: #071b38;
            --project-navy-light: #102b50;
            --project-gold: #d6a63b;
            --project-gold-light: #edca76;
            --project-ivory: #f7f5ef;
            --project-text: #344054;
        }

        .dynamic-project-page {
            font-family: 'Montserrat', sans-serif;
            color: var(--project-text);
        }

        .dynamic-project-hero {
            min-height: 580px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 100px 25px;
            background:
                linear-gradient(
                    135deg,
                    rgba(4, 19, 44, .88),
                    rgba(7, 29, 66, .66)
                ),
                url('<?php echo projectDetailEscape($cardImage); ?>')
                center/cover no-repeat;
        }

        .dynamic-project-hero-content {
            max-width: 850px;
            color: #fff;
        }

        .dynamic-project-hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(42px, 7vw, 76px);
            color: #fff;
            margin: 0 0 20px;
        }

        .dynamic-project-hero p {
            font-size: 16px;
            line-height: 1.9;
            color: rgba(255,255,255,.9);
            max-width: 700px;
            margin: auto;
        }

        .dynamic-project-label {
            color: var(--project-gold-light);
            letter-spacing: 4px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 18px;
            display: block;
        }

        .dynamic-project-section {
            padding: 90px 7%;
        }

        .dynamic-project-intro {
            background: var(--project-ivory);
        }

        .dynamic-project-container {
            max-width: 1200px;
            margin: auto;
        }

        .dynamic-project-intro-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 55px;
        }

        .dynamic-project-image {
            width: 100%;
            height: 430px;
            object-fit: cover;
            border-radius: 5px;
            box-shadow: 0 15px 40px rgba(7,27,56,.13);
        }

        .dynamic-project-intro h2,
        .dynamic-project-advantages h2,
        .dynamic-project-cta h2 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--project-navy);
            font-size: clamp(34px, 4vw, 48px);
            margin: 12px 0 22px;
            line-height: 1.15;
        }

        .dynamic-project-intro p,
        .dynamic-project-advantages p {
            font-size: 14px;
            line-height: 2;
            margin-bottom: 15px;
        }

        .dynamic-project-advantages {
            background: #fff;
        }

        .dynamic-project-advantages-header {
            max-width: 750px;
            text-align: center;
            margin: 0 auto 45px;
        }

        .dynamic-project-advantages-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .dynamic-project-advantage {
            padding: 30px 25px;
            background: var(--project-ivory);
            border-bottom: 3px solid var(--project-gold);
            border-radius: 4px;
        }

        .dynamic-project-advantage i {
            color: var(--project-gold);
            font-size: 25px;
            margin-bottom: 18px;
        }

        .dynamic-project-advantage h3 {
            color: var(--project-navy);
            font-size: 16px;
            margin: 0 0 12px;
        }

        .dynamic-project-advantage p {
            font-size: 13px;
            margin: 0;
        }

        .dynamic-project-cta {
            padding: 85px 7%;
            background: linear-gradient(
                135deg,
                var(--project-navy),
                var(--project-navy-light)
            );
            text-align: center;
            color: #fff;
        }

        .dynamic-project-cta h2 {
            color: #fff;
        }

        .dynamic-project-cta p {
            color: rgba(255,255,255,.85);
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .dynamic-project-buttons {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .dynamic-project-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 28px;
            background: var(--project-gold);
            color: var(--project-navy);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            border-radius: 3px;
            transition: .3s ease;
        }

        .dynamic-project-btn:hover {
            background: var(--project-gold-light);
            transform: translateY(-3px);
        }

        .dynamic-project-breadcrumb {
            background: var(--project-ivory);
            padding: 15px 7%;
            font-size: 12px;
        }

        .dynamic-project-breadcrumb a {
            color: var(--project-navy);
            text-decoration: none;
        }

        .dynamic-project-breadcrumb span {
            color: #8a8f98;
            margin: 0 8px;
        }

        @media(max-width: 800px) {

            .dynamic-project-intro-grid {
                grid-template-columns: 1fr;
                gap: 35px;
            }

            .dynamic-project-image {
                height: 320px;
            }

            .dynamic-project-advantages-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dynamic-project-section {
                padding: 65px 6%;
            }

        }

        @media(max-width: 520px) {

            .dynamic-project-hero {
                min-height: 450px;
            }

            .dynamic-project-advantages-grid {
                grid-template-columns: 1fr;
            }

            .dynamic-project-image {
                height: 260px;
            }

            .dynamic-project-buttons {
                flex-direction: column;
            }

            .dynamic-project-btn {
                width: 100%;
                box-sizing: border-box;
            }

        }

    </style>

</head>

<body class="dynamic-project-page">

<?php include __DIR__ . '/includes/header.php'; ?>


<!-- PROJECT HERO -->
<section class="dynamic-project-hero">

    <div class="dynamic-project-hero-content">

        <span class="dynamic-project-label">
            OLIVERA HOME DEVELOPERS
        </span>

        <h1>
            <?php echo projectDetailEscape($title); ?>
        </h1>

        <p>
            <?php echo projectDetailEscape($heroDescription); ?>
        </p>

    </div>

</section>


<!-- BREADCRUMB -->
<div class="dynamic-project-breadcrumb">

    <a href="index.php">Home</a>

    <span>/</span>

    <a href="ourprojects.php">Our Projects</a>

    <span>/</span>

    <?php echo projectDetailEscape($title); ?>

</div>


<!-- PROJECT INTRODUCTION -->
<section class="dynamic-project-section dynamic-project-intro">

    <div class="dynamic-project-container">

        <div class="dynamic-project-intro-grid">

            <div>

                <img
                    src="<?php echo projectDetailEscape($detailImage); ?>"
                    alt="<?php echo projectDetailEscape($title); ?>"
                    class="dynamic-project-image"
                    loading="lazy">

            </div>

            <div>

                <span class="dynamic-project-label">
                    <?php echo projectDetailEscape($introLabel); ?>
                </span>

                <h2>
                    <?php echo projectDetailEscape($introHeading); ?>
                </h2>

                <?php if ($introContent !== ''): ?>

                    <div>
                        <?php echo nl2br(projectDetailEscape($introContent)); ?>
                    </div>

                <?php else: ?>

                    <p>
                        <?php echo projectDetailEscape($shortDescription); ?>
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </div>

</section>


<!-- LOCATION ADVANTAGES -->
<?php if (!empty($advantages) || $advantagesDescription !== ''): ?>

<section class="dynamic-project-section dynamic-project-advantages">

    <div class="dynamic-project-container">

        <div class="dynamic-project-advantages-header">

            <span class="dynamic-project-label">
                LOCATION HIGHLIGHTS
            </span>

            <h2>
                <?php echo projectDetailEscape($advantagesHeading); ?>
            </h2>

            <?php if ($advantagesDescription !== ''): ?>

                <p>
                    <?php echo projectDetailEscape($advantagesDescription); ?>
                </p>

            <?php endif; ?>

        </div>


        <?php if (!empty($advantages)): ?>

            <div class="dynamic-project-advantages-grid">

                <?php foreach ($advantages as $advantage): ?>

                    <?php

                        if (!is_array($advantage)) {
                            continue;
                        }

                        $advantageTitle = $advantage['title'] ?? '';
                        $advantageDescription = $advantage['description'] ?? '';

                        if ($advantageTitle === '' && $advantageDescription === '') {
                            continue;
                        }

                    ?>

                    <div class="dynamic-project-advantage">

                        <i class="fa-solid fa-location-dot"></i>

                        <h3>
                            <?php echo projectDetailEscape($advantageTitle); ?>
                        </h3>

                        <p>
                            <?php echo projectDetailEscape($advantageDescription); ?>
                        </p>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>

<?php endif; ?>


<!-- CONTACT CTA -->
<section class="dynamic-project-cta">

    <div class="dynamic-project-container">

        <span class="dynamic-project-label">
            TAKE THE NEXT STEP
        </span>

        <h2>
            <?php echo projectDetailEscape($ctaHeading); ?>
        </h2>

        <p>
            <?php echo projectDetailEscape($ctaDescription); ?>
        </p>

        <div class="dynamic-project-buttons">

            <a href="contact.php" class="dynamic-project-btn">

                <i class="fa-solid fa-calendar-check"></i>

                Arrange a Site Visit

            </a>

        </div>

    </div>

</section>


<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
