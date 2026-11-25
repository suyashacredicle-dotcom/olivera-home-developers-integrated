
<?php

$projects = [

    [
        'name' => 'Noida',
        'description' => 'Premium plots in Noida with excellent connectivity.',
        'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=80',
        'location' => 'noida'
    ],

    [
        'name' => 'Greater Noida',
        'description' => 'Near Noida International Airport & Yamuna Expressway.',
        'image' => 'https://images.unsplash.com/photo-1511818966892-d7d671e672a2?auto=format&fit=crop&w=900&q=80',
        'location' => 'greater-noida'
    ],

    [
        'name' => 'Vrindavan / Mathura',
        'description' => 'Spiritual & investment hub with high growth potential.',
        'image' => 'https://images.unsplash.com/photo-1605640840605-14ac1855827b?auto=format&fit=crop&w=900&q=80',
        'location' => 'vrindavan'
    ],

    [
        'name' => 'Faridabad',
        'description' => 'Explore residential opportunities in Faridabad.',
        'image' => 'assets/8_G9_A1217_e4035ceb54.jpg',
        'location' => 'faridabad'
    ],

    [
        'name' => 'Delhi',
        'description' => 'Discover property opportunities in the capital.',
        'image' => 'https://images.unsplash.com/photo-1587474260584-136574528ed5?auto=format&fit=crop&w=900&q=80',
        'location' => 'delhi'
    ],

    [
        'name' => 'Gurugram',
        'description' => 'Explore a modern and rapidly developing city.',
        'image' => 'https://images.unsplash.com/photo-1597047084897-51e81819a499?auto=format&fit=crop&w=900&q=80',
        'location' => 'gurugram'
    ],

    [
        'name' => 'Khatu Shyam',
        'description' => 'Explore property opportunities near the spiritual destination.',
        'image' => 'assets/khatushyam5.jpg',
        'location' => 'khatu-shyam'
    ],

    [
        'name' => 'Garhmukteshwar',
        'description' => 'Discover opportunities in a growing destination.',
        'image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=900&q=80',
        'location' => 'garhmukteshwar'
    ],

    [
        'name' => 'Dehradun',
        'description' => 'Explore property opportunities in the scenic city.',
        'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80',
        'location' => 'dehradun'
    ],

    [
        'name' => 'Rishikesh',
        'description' => 'Discover a peaceful destination surrounded by nature.',
        'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=900&q=80',
        'location' => 'rishikesh'
    ],

    [
        'name' => 'Dubai',
        'description' => 'Explore real estate opportunities in Dubai.',
        'image' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=900&q=80',
        'location' => 'dubai'
    ],

    [
        'name' => 'Goa',
        'description' => 'Discover lifestyle and investment opportunities in Goa.',
        'image' => 'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?auto=format&fit=crop&w=900&q=80',
        'location' => 'goa'
    ]

];

// Project page links
$projectPages = [
    'noida' => 'noida.php',
    'greater-noida' => 'greaternoida.php',
    'vrindavan' => 'vrindavan.php',
    'faridabad' => 'faridabad.php',
    'delhi' => 'delhi.php',
    'gurugram' => 'gurugram.php',
    'khatu-shyam' => 'khatushyam.php',
    'garhmukteshwar' => 'Garhmukteshwar.php',
    'dehradun' => 'dehradun.php',
    'rishikesh' => 'rishikesh.php',
    'dubai' => 'dubai.php',
    'goa' => 'goa.php',
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Our Projects | Olivera Home Developers</title>

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/ourprojects.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap"
          rel="stylesheet">

</head>

<body>

<!-- HEADER -->
<?php include __DIR__ . '/includes/header.php'; ?>


<!-- OUR PROJECTS HERO -->
<section class="projects-hero">

    <!-- Background Video -->
    <div class="projects-bg-video" style="background:linear-gradient(135deg,rgba(4,19,44,.82),rgba(7,29,66,.62)),url('assets/noida75.jpg') center/cover no-repeat" aria-hidden="true"></div>

    <!-- Dark Overlay -->
 

    <!-- Hero Content -->
    <div class="projects-hero-content">

        <h1>Our Projects</h1>

        <p>
            Premium plots across NCR's fastest-growing corridors.
            Freehold, RERA compliant, with flexible payment options.
        </p>

    </div>

</section>


<!-- ALL PROJECTS -->
<section class="all-projects-section">

    <div class="projects-grid">

        <?php foreach ($projects as $project): ?>

            <?php
                $location = $project['location'];

                // Dedicated pages for Noida and Greater Noida
                if (isset($projectPages[$location])) {
                    $projectUrl = $projectPages[$location];
                } else {
                    $projectUrl = 'project-details.php?location=' . urlencode($location);
                }
            ?>

            <div class="project-card">

                <!-- PROJECT IMAGE -->
                <a class="project-image-link"
                   href="<?php echo htmlspecialchars($projectUrl, ENT_QUOTES, 'UTF-8'); ?>">

                    <img
                        src="<?php echo htmlspecialchars($project['image'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        loading="lazy">

                </a>

                <!-- PROJECT DETAILS -->
                <div class="project-card-content">

                    <h2>
                        <?php echo htmlspecialchars($project['name']); ?>
                    </h2>

                    <p>
                        <?php echo htmlspecialchars($project['description']); ?>
                    </p>

                    <!-- VIEW PROJECT BUTTON -->
                    <a class="view-project-btn"
                       href="<?php echo htmlspecialchars($projectUrl, ENT_QUOTES, 'UTF-8'); ?>">

                        View Project
                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- FOOTER -->
<?php include __DIR__ . '/includes/footer.php'; ?>


</body>
</html>
