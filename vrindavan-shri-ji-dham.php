
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vrindavan Shri Ji Dham | Olivera Home Developers</title>

    <link rel="stylesheet" href="CSS/style.css">

    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            margin: 0;
            background: #F8F6F1;
            font-family: Arial, sans-serif;
        }

   /* VIDEO HERO SECTION */

.brochure-header {
    position: relative;
    width: 100%;
    min-height: 480px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 60px 20px;
    box-sizing: border-box;
    overflow: hidden;
    background: #071A38;
}

.brochure-bg-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
}

.brochure-video-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        rgba(7, 26, 56, 0.72),
        rgba(7, 26, 56, 0.78)
    );
    z-index: 1;
}

.brochure-header h2,
.brochure-header h1,
.brochure-header p {
    position: relative;
    z-index: 2;
}

.brochure-header h2 {
    color: #D4AF65;
    font-size: 15px;
    letter-spacing: 3px;
    margin-bottom: 15px;
}

.brochure-header h1 {
    color: #FFFFFF;
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(38px, 6vw, 65px);
    margin: 0 0 12px;
}

.brochure-header p {
    color: #FFFFFF;
    font-size: 16px;
    margin: 0;
}

@media(max-width: 600px) {
    .brochure-header {
        min-height: 350px;
    }

    .brochure-header h2 {
        font-size: 11px;
    }

    .brochure-header p {
        font-size: 13px;
    }
}

       .brochure-container {
    max-width: 1100px;
    margin: 35px auto;
    padding: 0 15px;
    color: #ffffff;
}

        .brochure-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

       
        
        .brochure-toolbar h3 {
    color: #ffffff;
    margin: 0;
}

        .brochure-link {
    color: #ffffff !important;
}

.brochure-link:hover {
    color: #D4AF65 !important;
}
        .download-btn {
            background: #D4AF65;
            color: #071A38;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .pdf-viewer {
            width: 100%;
            height: 850px;
            border: 2px solid #D4AF65;
            border-radius: 8px;
            background: white;
        }

        @media(max-width: 600px) {
            .brochure-header h1 {
                font-size: 25px;
            }

            .pdf-viewer {
                height: 650px;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

   <!-- SHRI JI DHAM VIDEO HERO -->

<section class="brochure-header">

    <video class="brochure-bg-video"
           autoplay
           muted
           loop
           playsinline
           preload="metadata" poster="assets/vrindavanimage1.jpg">

        <source src="assets/shrijidham.mp4">

    </video>

    

    <h2>OLIVERA HOME DEVELOPERS PVT. LTD.</h2>

    <h1>Vrindavan Shri Ji Dham</h1>

    <p>Explore Our Project Brochure</p>

</section>

    <section class="brochure-container">

        <div class="brochure-toolbar">
            <h3>Project Brochure</h3>

            <a href="assets/shijidham.pdf"
               class="download-btn"
               download>
                <i class="fa-solid fa-download"></i>
                Download Brochure
            </a>
        </div>

        <iframe
            class="pdf-viewer"
            src="assets/shijidham.pdf"
            title="Vrindavan Shri Ji Dham Brochure">
        </iframe>

    </section>
   <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
