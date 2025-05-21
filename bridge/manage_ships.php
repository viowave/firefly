<?php
require 'includes/db.php'; // Database connection
require 'includes/header.php'; // Header

// Fetch sources for the dropdown
$sourcesStmt = $pdo->query("SELECT id, name FROM sources ORDER BY name ASC");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle adding a new ship
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ship'])) {
    $name = trim($_POST['name']);
    $source_id = $_POST['source'] ?: null; // Get source ID, set to null if empty
    $imageFilename = null; // Initialize to null

    // Validate inputs
    if (empty($name)) {
        $error = "Ship name is required.";
    } elseif (empty($_FILES['image']['name'])) {
        $error = "Ship image is required.";
    } elseif ($source_id === null && !isset($error)) { // Check if source is required and not selected
        // Only if you want to make source mandatory:
        // $error = "Ship source is required.";
    }
    // No else here, let the image handling logic continue

    if (!isset($error)) { // Proceed only if no validation errors so far
        $targetDir = __DIR__ . '/../uploads/ships/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $originalFilename = basename($_FILES["image"]["name"]);
        $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        try {
            // Start transaction
            $pdo->beginTransaction();

            // Insert ship data (without image initially)
            // IMPORTANT: Add source_id to the INSERT statement
            $stmt = $pdo->prepare("INSERT INTO ships (name, source_id, image_url) VALUES (:name, :source_id, :image_url)");
            $stmt->execute([
                'name' => $name,
                'source_id' => $source_id,
                'image_url' => null
            ]);
            $shipId = $pdo->lastInsertId();

            $sanitizedName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($name));
            $newFilenameBase = $shipId . "_" . $sanitizedName;
            $webpFilename = $newFilenameBase . ".webp";
            $imagePath = $targetDir . $webpFilename;

            // Check for WebP support
            if (!function_exists('imagewebp')) {
                throw new Exception("❌ WebP support is not enabled on the server.");
            }

            // Load and convert image to WebP
            $sourcePath = $_FILES["image"]["tmp_name"];
            $sourceImage = null;
            switch ($fileExtension) {
                case 'jpg':
                case 'jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                case 'webp':
                    $sourceImage = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    throw new Exception("❌ Unsupported image format. Only JPG, JPEG, PNG, GIF, and WebP are allowed.");
            }

            if ($sourceImage) {
                imagewebp($sourceImage, $imagePath, 80);
                imagedestroy($sourceImage);
                $imageFilename = $webpFilename; // Store WebP filename
            } else {
                throw new Exception("❌ Could not create image resource.");
            }

            // Update database with the WebP filename
            $updateStmt = $pdo->prepare("UPDATE ships SET image_url = :image_url WHERE id = :ship_id");
            $updateStmt->execute(['image_url' => $imageFilename, 'ship_id' => $shipId]);

            // Commit transaction
            $pdo->commit();
            $success = "Ship added successfully!";
            $_POST['name'] = '';
            $_POST['source'] = ''; // Clear source selection
            $_FILES['image']['name'] = '';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error adding ship: " . $e->getMessage();
        }
    }
}

// Handle deleting a ship (no changes needed here, it already handles image deletion)
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT image_url FROM ships WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $ship = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ship && !empty($ship['image_url'])) {
            $imagePath = __DIR__ . '/../uploads/ships/' . $ship['image_url'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM ships WHERE id = :id");
        $stmt->execute(['id' => $id]);
        header("Location: manage_ships.php");
        exit;
    } catch (PDOException $e) {
        die("Error deleting ship: " . $e->getMessage());
    }
}

// Fetch all ships for display, now also joining with sources table
$shipsStmt = $pdo->query("
    SELECT s.*, src.name AS source_name
    FROM ships s
    LEFT JOIN sources src ON s.source_id = src.id
    ORDER BY s.name ASC
");
$ships = $shipsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="grid-container">
    <h3>Manage Ships</h3>
    <?php if (isset($error)): ?>
        <div class="callout alert"><?= $error; ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="callout success"><?= $success; ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="grid-x grid-margin-x">
            <div class="cell small-12 medium-3">
                <label>New Ship Name:
                    <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </label>
            </div>
            <div class="cell small-12 medium-3">
                <label>Source:
                    <select name="source">
                        <option value="">Select a Source</option>
                        <?php foreach ($sources as $source): ?>
                            <option value="<?= htmlspecialchars($source['id']) ?>"
                                <?= (isset($_POST['source']) && $_POST['source'] == $source['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($source['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
             <div class="cell small-12 medium-3">
                <label>Ship Image:
                    <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/gif, image/webp" required>
                </label>
            </div>
            <div class="cell small-12  medium-3 text-right">
                <button type="submit" name="add_ship" class="button success">Add Ship</button>
            </div>
        </div>
    </form>
    <table class="stack">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Source</th> <th>Image</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ships as $ship): ?>
                <tr>
                    <td><?= htmlspecialchars($ship['id']); ?></td>
                    <td><?= htmlspecialchars($ship['name']); ?></td>
                    <td><?= htmlspecialchars($ship['source_name'] ?? 'N/A'); ?></td> <td>
                        <?php if (!empty($ship['image_url'])): ?>
                            <img src="../uploads/ships/<?= htmlspecialchars($ship['image_url']); ?>" alt="Ship Image" style="height: 50px; width: auto;">
                        <?php else: ?>
                            <span class="text-gray-500">No Image</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="manage_ships.php?delete=<?= $ship['id']; ?>" class="button alert" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php" class="button secondary">Back to Dashboard</a>
</div>

<?php require 'includes/footer.php'; ?>