
<?php

// --------------------------------------------------
// EMPLOYEE GALLERY
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

// --------------------------------------------------
// ALLOWED ROLES
// --------------------------------------------------

$allowedRoles = [
    "super_admin",
    "admin",
    "editor",
    "sales_employee"
];

$currentRole = $_SESSION["admin_role"] ?? "";

if (!in_array($currentRole, $allowedRoles, true)) {
    http_response_code(403);
    exit("Access Denied!");
}

// --------------------------------------------------
// ESCAPE OUTPUT
// --------------------------------------------------

function employeeGalleryEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// FETCH EMPLOYEE PHOTOS ONLY
// --------------------------------------------------

$message = "";
$photos = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            description,
            created_at
        FROM gallery_photos
        WHERE visibility = ?
        ORDER BY created_at DESC, id DESC
    ");

    $stmt->execute(["employees"]);

    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Employee Gallery Error: " . $e->getMessage()
    );

    $message = "Unable to load gallery photos. Please try again.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Employee Gallery | Admin Panel</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.6;
        }

        /* NAVIGATION */

        .navbar {
            background: #ffffff;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 12px rgba(15,23,42,0.05);
        }

        .brand {
            color: #172554;
            font-size: 22px;
            font-weight: 800;
        }

        .navigation {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .navigation a,
        .logout-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 7px;
            background: #1e40af;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }

        .navigation a:hover,
        .logout-button:hover {
            background: #172554;
        }

        .logout-form {
            display: inline;
            margin: 0;
        }

        .logout-button {
            background: #dc2626;
        }

        .logout-button:hover {
            background: #991b1b;
        }

        /* HERO */

        .hero {
            background: linear-gradient(
                135deg,
                #172554,
                #1e40af
            );
            color: #ffffff;
            padding: 65px 20px;
            text-align: center;
        }

        .hero-label {
            color: #bfdbfe;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
            display: inline-block;
        }

        .hero h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .hero p {
            color: #dbeafe;
            font-size: 15px;
        }

        /* MAIN CONTENT */

        .container {
            max-width: 1250px;
            margin: 0 auto;
            padding: 45px 22px 65px;
        }

        .section-heading {
            margin-bottom: 28px;
        }

        .section-heading h2 {
            color: #172554;
            font-size: 25px;
            margin-bottom: 5px;
        }

        .section-heading p {
            color: #64748b;
            font-size: 14px;
        }

        /* GALLERY GRID */

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(280px, 1fr)
            );
            gap: 24px;
        }

        .gallery-card {
            background: #ffffff;
            border-radius: 13px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 16px rgba(15,23,42,0.05);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 26px rgba(15,23,42,0.10);
        }

        .gallery-image-wrapper {
            height: 240px;
            width: 100%;
            overflow: hidden;
            background: #e2e8f0;
        }

        .gallery-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.35s ease;
        }

        .gallery-card:hover .gallery-image {
            transform: scale(1.04);
        }

        .gallery-content {
            padding: 20px;
        }

        .gallery-content h3 {
            color: #172554;
            font-size: 18px;
            margin-bottom: 9px;
            overflow-wrap: anywhere;
        }

        .gallery-description {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .gallery-date {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 15px;
            display: block;
        }

        /* EMPTY / ERROR */

        .empty-message,
        .error-message {
            background: #ffffff;
            border-radius: 12px;
            padding: 45px 20px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .empty-message h2 {
            color: #172554;
            font-size: 21px;
            margin-bottom: 8px;
        }

        .empty-message p {
            color: #64748b;
            font-size: 14px;
        }

        .error-message {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fecaca;
        }

        /* FOOTER */

        .footer {
            background: #172554;
            color: #cbd5e1;
            padding: 24px 20px;
            text-align: center;
            font-size: 13px;
        }

        /* RESPONSIVE */

        @media (max-width: 650px) {

            .navbar {
                flex-direction: column;
                padding: 17px 15px;
            }

            .navigation {
                justify-content: center;
            }

            .hero {
                padding: 48px 18px;
            }

            .hero h1 {
                font-size: 29px;
            }

            .container {
                padding: 30px 15px 45px;
            }

            .gallery-grid {
                grid-template-columns: 1fr;
            }

            .gallery-image-wrapper {
                height: 220px;
            }

        }

    </style>

</head>

<body>

    <!-- NAVIGATION -->

    <header class="navbar">

        <div class="brand">
            Employee Gallery
        </div>

        <nav class="navigation">

            <a href="dashboard.php">
                Dashboard
            </a>

            <?php if ($currentRole === "super_admin"): ?>

                <a href="manage_gallery.php">
                    Manage Gallery
                </a>

            <?php endif; ?>

            <form
                method="POST"
                action="logout.php"
                class="logout-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo employeeGalleryEscape($csrfToken); ?>"
                >

                <button
                    type="submit"
                    class="logout-button"
                >
                    Logout
                </button>

            </form>

        </nav>

    </header>

    <!-- HERO -->

    <section class="hero">

        <span class="hero-label">
            Private Collection
        </span>

        <h1>Employee Gallery</h1>

        <p>
            Photos shared privately with authorized employees.
        </p>

    </section>

    <!-- GALLERY -->

    <main class="container">

        <div class="section-heading">

            <h2>Shared Photos</h2>

            <p>
                View photos shared by Super Admin with employees.
            </p>

        </div>

        <?php if ($message !== ""): ?>

            <div class="error-message">

                <?php echo employeeGalleryEscape($message); ?>

            </div>

        <?php elseif (empty($photos)): ?>

            <div class="empty-message">

                <h2>No Employee Photos Yet</h2>

                <p>
                    Photos shared by Super Admin with employees
                    will appear here.
                </p>

            </div>

        <?php else: ?>

            <div class="gallery-grid">

                <?php foreach ($photos as $photo): ?>

                    <article class="gallery-card">

                        <div class="gallery-image-wrapper">

                            <img
                                class="gallery-image"
                                src="gallery_image.php?id=<?php echo (int) $photo["id"]; ?>"
                                alt="<?php
                                    echo employeeGalleryEscape(
                                        $photo["title"] ?: "Employee Gallery Photo"
                                    );
                                ?>"
                                loading="lazy"
                            >

                        </div>

                        <div class="gallery-content">

                            <h3>
                                <?php
                                    echo employeeGalleryEscape(
                                        $photo["title"] ?: "Untitled Photo"
                                    );
                                ?>
                            </h3>

                            <?php if (!empty($photo["description"])): ?>

                                <div class="gallery-description">
                                    <?php
                                        echo employeeGalleryEscape(
                                            $photo["description"]
                                        );
                                    ?>
                                </div>

                            <?php endif; ?>

                            <span class="gallery-date">

                                Uploaded:

                                <?php

                                $timestamp = strtotime(
                                    $photo["created_at"]
                                );

                                echo $timestamp !== false
                                    ? date("d M Y", $timestamp)
                                    : "";

                                ?>

                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>

    <!-- FOOTER -->

    <footer class="footer">

        <p>
            Authorized employee access only.
            &copy; <?php echo date("Y"); ?> Our Website.
        </p>

    </footer>

</body>

</html>
