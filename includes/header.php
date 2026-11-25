<?php
require_once __DIR__ . '/site_bootstrap.php';
?>
<header class="navbar">
    <div class="logo">
        <a href="<?php echo htmlspecialchars(site_url('index.php#home'), ENT_QUOTES, 'UTF-8'); ?>">
            <img src="<?php echo htmlspecialchars(site_url('assets/WhatsApp Image 2026-11-16 at 3.29.00 PM.jpeg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Olivera Home Developers Logo" class="company-logo">
        </a>
    </div>
    <nav class="nav-links" aria-label="Main navigation">
        <a href="<?php echo htmlspecialchars(site_url('index.php#home'), ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        <div class="projects-menu">
            <div class="projects-nav">
                <a href="<?php echo htmlspecialchars(site_url('ourprojects.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-dropdown-link">Our Projects</a>
                <button type="button" class="nav-dropdown-btn" id="projectsBtn" aria-expanded="false" aria-label="Toggle project locations"><i class="fa-solid fa-caret-down"></i></button>
            </div>
            <div class="projects-dropdown" id="projectsDropdown">
                <?php foreach ([
                    'Noida'=>'noida.php','Greater Noida'=>'greaternoida.php','Vrindavan'=>'vrindavan.php','Faridabad'=>'faridabad.php','Delhi'=>'delhi.php','Gurugram'=>'gurugram.php','Khatu Shyam'=>'khatushyam.php','Garhmukteshwar'=>'Garhmukteshwar.php','Dehradun'=>'dehradun.php','Rishikesh'=>'rishikesh.php','Dubai'=>'dubai.php','Goa'=>'goa.php'
                ] as $label=>$page): ?>
                    <a href="<?php echo htmlspecialchars(site_url($page), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="nav-dropdown brochure-menu">
            <button type="button" class="nav-dropdown-btn brochure-nav-btn" id="brochureBtn" aria-expanded="false">Brochure <i class="fa-solid fa-caret-down"></i></button>
            <div class="projects-dropdown brochure-dropdown" id="brochureDropdown">
                <a href="<?php echo htmlspecialchars(site_url('vrindavan-shri-ji-dham.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-book-open"></i> Vrindavan Shri Ji Dham</a>
            </div>
        </div>
        <a href="<?php echo htmlspecialchars(site_url('gallery.php'), ENT_QUOTES, 'UTF-8'); ?>">Gallery</a>
        <a href="<?php echo htmlspecialchars(site_url('director.php'), ENT_QUOTES, 'UTF-8'); ?>">Director</a>
        <a href="<?php echo htmlspecialchars(site_url('about.php'), ENT_QUOTES, 'UTF-8'); ?>">About Us</a>
        <a href="<?php echo htmlspecialchars(site_url('contactus.php'), ENT_QUOTES, 'UTF-8'); ?>">Contact Us</a>
    </nav>
    <div class="nav-actions">
        <a href="<?php echo htmlspecialchars(site_url('becomeourpartner.php'), ENT_QUOTES, 'UTF-8'); ?>" class="partner-button">Become Our Partner</a>
        <a href="<?php echo htmlspecialchars(site_url('booksitevisit.php'), ENT_QUOTES, 'UTF-8'); ?>" class="visit-button">Book Site Visit</a>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pairs = [
        [document.getElementById('projectsBtn'), document.getElementById('projectsDropdown')],
        [document.getElementById('brochureBtn'), document.getElementById('brochureDropdown')]
    ];
    pairs.forEach(([button, menu]) => {
        if (!button || !menu) return;
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            const open = menu.classList.toggle('show');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', event => event.stopPropagation());
    });
    document.addEventListener('click', function () {
        pairs.forEach(([button, menu]) => {
            if (button && menu) {
                menu.classList.remove('show');
                button.setAttribute('aria-expanded', 'false');
            }
        });
    });
});
</script>
