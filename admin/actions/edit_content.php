
<?php

require_once "../../includes/permissions.php";
require_once "../../config/database.php";

requirePermission("manage_content");

$message = "";

// Get content ID
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die("Invalid content ID.");
}

// Get existing content
$stmt = $pdo->prepare(
    "SELECT * FROM content WHERE id = ?"
);

$stmt->execute([$id]);

$content = $stmt->fetch();

if (!$content) {
    die("Content not found.");
}

// Update content
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"] ?? ""
        )
    ) {
        die("Invalid request. Please reload the page.");
    }

    $title = trim($_POST["title"] ?? "");
    $body = trim($_POST["body"] ?? "");
    $status = $_POST["status"] ?? "";

    if (
        $title === "" ||
        $body === "" ||
        !in_array($status, ["draft", "published"], true)
    ) {
        $message = "Please enter valid content details.";

    } else {

        $stmt = $pdo->prepare(
            "UPDATE content
             SET title = ?, body = ?, status = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $title,
            $body,
            $status,
            $id
        ]);

        header("Location: ../manage_content.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Content</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>

<body>

<div class="main-content">

    <h1>Edit Website Content</h1>

    <p style="color:red;">
        <?php echo htmlspecialchars($message); ?>
    </p>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
        >

        <label>Content Title</label><br>

        <input
            type="text"
            name="title"
            maxlength="255"
            value="<?php echo htmlspecialchars($content["title"]); ?>"
            required
        >

        <br><br>

        <label>Content Body</label><br>

        <textarea name="body" rows="10" required><?php
            echo htmlspecialchars($content["body"]);
        ?></textarea>

        <br><br>

        <label>Content Status</label><br>

        <select name="status" required>

            <option value="draft"
                <?php echo $content["status"] === "draft" ? "selected" : ""; ?>>
                Draft
            </option>

            <option value="published"
                <?php echo $content["status"] === "published" ? "selected" : ""; ?>>
                Published
            </option>

        </select>

        <br><br>

        <button type="submit">Update Content</button>

    </form>

    <br>

    <a href="../manage_content.php">Back to Manage Content</a>

</div>

</body>
</html>
