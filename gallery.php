<?php
require_once __DIR__ . '/config/database.php';
function oliveraGalleryEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$photos = [];
$galleryError = false;
try {
    $stmt = $pdo->query("SELECT id, title, description, created_at FROM gallery_photos WHERE visibility = 'public' ORDER BY created_at DESC, id DESC");
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Public gallery load failed: ' . $e->getMessage());
    $galleryError = true;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gallery | Olivera Home Developers</title><meta name="description" content="Explore public project photographs and moments from Olivera Home Developers.">
<link rel="stylesheet" href="CSS/style.css"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>.public-gallery-hero{padding:155px 20px 75px;text-align:center;background:linear-gradient(135deg,rgba(4,19,44,.96),rgba(7,29,66,.88));color:#fff}.public-gallery-hero span{color:#f8d365;letter-spacing:2px;font-size:12px;font-weight:700}.public-gallery-hero h1{font-size:clamp(38px,5vw,58px);margin:12px 0}.public-gallery-hero p{max-width:680px;margin:auto;color:#dce4f0}.public-gallery-wrap{max-width:1240px;margin:0 auto;padding:65px 22px 85px}.public-gallery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px}.public-gallery-card{overflow:hidden;background:#fff;border:1px solid #e7e2d7;border-radius:14px;box-shadow:0 10px 28px rgba(4,19,44,.08)}.public-gallery-card img{width:100%;height:250px;object-fit:cover;display:block}.public-gallery-copy{padding:18px 20px}.public-gallery-copy h2{color:#071d42;font-size:23px;margin:0 0 7px}.public-gallery-copy p{color:#64748b;font-size:14px;line-height:1.7}.public-gallery-empty{text-align:center;padding:50px 20px;color:#64748b;background:#fff;border-radius:14px}.public-gallery-cta{text-align:center;margin-top:38px}.public-gallery-cta a{display:inline-block;padding:13px 22px;border-radius:6px;background:linear-gradient(135deg,#f8d365,#d49b27);color:#08152b;text-decoration:none;font-weight:700}@media(max-width:600px){.public-gallery-hero{padding-top:125px}.public-gallery-card img{height:215px}}</style>
</head><body>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="public-gallery-hero"><span>OLIVERA HOME DEVELOPERS</span><h1>Our Gallery</h1><p>A glimpse of our projects, places and experiences. Photographs selected for public viewing are displayed here.</p></section>
<main class="public-gallery-wrap">
<?php if ($galleryError): ?><div class="public-gallery-empty">The gallery is temporarily unavailable. Please visit again shortly.</div>
<?php elseif (!$photos): ?><div class="public-gallery-empty"><h2>Gallery Coming Soon</h2><p>Our latest public project photographs will appear here.</p></div>
<?php else: ?><div class="public-gallery-grid">
<?php foreach ($photos as $photo): ?><article class="public-gallery-card"><img src="gallery_public_image.php?id=<?php echo (int)$photo['id']; ?>" alt="<?php echo oliveraGalleryEscape($photo['title'] ?: 'Olivera Home Developers project photo'); ?>" loading="lazy"><div class="public-gallery-copy"><h2><?php echo oliveraGalleryEscape($photo['title'] ?: 'Olivera Home Developers'); ?></h2><?php if (!empty($photo['description'])): ?><p><?php echo nl2br(oliveraGalleryEscape($photo['description'])); ?></p><?php endif; ?></div></article><?php endforeach; ?>
</div><?php endif; ?>
<div class="public-gallery-cta"><a href="booksitevisit.php">Book a Site Visit</a></div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body></html>
