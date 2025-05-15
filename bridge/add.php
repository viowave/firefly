<?php
require 'includes/db.php';
require 'includes/header.php';

// Fetch roles
$rolesStmt = $pdo->query("SELECT id, name FROM roles ORDER BY name ASC");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch planets
$planetsStmt = $pdo->query("SELECT id, name FROM planets ORDER BY name ASC");
$planets = $planetsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch sources
$sourcesStmt = $pdo->query("SELECT id, name FROM sources ORDER BY name ASC");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch keywords
$keywordsStmt = $pdo->query("SELECT id, name FROM keywords ORDER BY name ASC");
$keywords = $keywordsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all crew members (for exclusion selection)
$crewStmt = $pdo->query("
    SELECT c.id, c.name, p.name AS planet, s.name AS source
    FROM crew c
    LEFT JOIN planets p ON c.planet_id = p.id
    LEFT JOIN sources s ON c.source_id = s.id
    ORDER BY c.name ASC
");
$crewList = $crewStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $fight_points = $_POST['fight_points'];
    $tech_points = $_POST['tech_points'];
    $talk_points = $_POST['talk_points'];
    $moral = isset($_POST['moral']) ? 1 : 0;
    $leader = isset($_POST['leader']) ? 1 : 0;
    $wanted = isset($_POST['wanted']) ? 1 : 0;
    $is_custom = isset($_POST['is_custom']) ? 1 : 0;
    $cost = $_POST['cost'];
    $planet_id = $_POST['planet'] ?: null;
    $source_id = $_POST['source'];
    $imageFilename = null;

    try {
        // Start transaction to ensure data consistency
        $pdo->beginTransaction();

        // Insert crew member (without image path initially)
        $sql = "INSERT INTO crew (name, description, fight_points, tech_points, talk_points, moral, leader, wanted, is_custom, cost, planet_id, source_id, image_url)
                VALUES (:name, :description, :fight_points, :tech_points, :talk_points, :moral, :leader, :wanted, :is_custom, :cost, :planet_id, :source_id, :image_url)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'fight_points' => $fight_points,
            'tech_points' => $tech_points,
            'talk_points' => $talk_points,
            'moral' => $moral,
            'leader' => $leader,
            'wanted' => $wanted,
            'is_custom' => $is_custom,
            'cost' => $cost,
            'planet_id' => $planet_id,
            'source_id' => $source_id,
            'image_url' => null // Initial null for image path
        ]);

        $crewId = $pdo->lastInsertId();

        // Insert roles
        if (!empty($_POST['roles'])) {
            $stmt = $pdo->prepare("INSERT INTO crew_roles (crew_id, role_id) VALUES (:crew_id, :role_id)");
            foreach ($_POST['roles'] as $roleId) {
                $stmt->execute(['crew_id' => $crewId, 'role_id' => $roleId]);
            }
        }

        // Insert keywords
        if (!empty($_POST['keywords'])) {
            $stmt = $pdo->prepare("INSERT INTO crew_keywords (crew_id, keyword_id) VALUES (:crew_id, :keyword_id)");
            foreach ($_POST['keywords'] as $keywordId) {
                $stmt->execute(['crew_id' => $crewId, 'keyword_id' => $keywordId]);
            }
        }

        // Insert exclusions
        if (!empty($_POST['excluded_crew'])) {
            foreach ($_POST['excluded_crew'] as $excludedId) {
                $stmt = $pdo->prepare("INSERT INTO crew_exclusions (crew_id, excluded_crew_id)
                                       VALUES (?, ?) ON DUPLICATE KEY UPDATE crew_id = crew_id");
                $stmt->execute([$crewId, $excludedId]);
                $stmt->execute([$excludedId, $crewId]); // Bidirectional
            }
        }

        // Handle image upload AFTER getting the crew ID
        if (!empty($_FILES['image']['name'])) {
            $targetDir = __DIR__ . '/../uploads/crew/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $originalFilename = basename($_FILES["image"]["name"]);
            $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            $newFilename = $crewId . "_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $name) . ".webp";
            $imagePath = $targetDir . $newFilename;
            $imageFilename = $newFilename; // Store just the filename for the database

            // Check if GD library with WebP support is available
            if (!function_exists('imagewebp')) {
                throw new Exception("❌ WebP support is not enabled on the server.");
            }

            // Load and convert the image to WebP
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
                // Save as WebP with quality 80 (adjust as needed)
                imagewebp($sourceImage, $imagePath, 80);
                imagedestroy($sourceImage);
            } else {
                throw new Exception("❌ Could not create image resource.");
            }

            // Update the crew member's record with the filename
            $updateSql = "UPDATE crew SET image_url = :image_url WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute(['image_url' => $imageFilename, 'id' => $crewId]);
        }

        // Commit the transaction
        $pdo->commit();
        echo "✅ Crew member added successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Error adding crew member: " . $e->getMessage());
    }
}
?>

<h3>Add Crew Member</h3>
<form method="POST" enctype="multipart/form-data" class="grid-container fluid">
    <div class="grid-x grid-padding-x">
        <div class="cell small-12">
            <label>Name:<input type="text" name="name" required></label>
            <label>Description:<textarea name="description" required></textarea></label>
            <label>Fight Points:<input type="number" name="fight_points" min="0" max="3" required></label>
            <label>Tech Points:<input type="number" name="tech_points" min="0" max="3" required></label>
            <label>Talk Points:<input type="number" name="talk_points" min="0" max="3" required></label>
            <label>Roles:
                <select name="roles[]" multiple required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['id']) ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Keywords:
                <select name="keywords[]" multiple>
                    <?php foreach ($keywords as $keyword): ?>
                        <option value="<?= htmlspecialchars($keyword['id']) ?>"><?= htmlspecialchars($keyword['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Moral:<input type="checkbox" name="moral"></label>
            <label>Leader:<input type="checkbox" name="leader"></label>
            <label>Wanted:<input type="checkbox" name="wanted"></label>
            <label>Custom Card:<input type="checkbox" name="is_custom"></label>
            <label>Cost:<input type="number" name="cost" required></label>
            <label>Planet:
                <select name="planet">
                    <option value="">No Planet</option>
                    <?php foreach ($planets as $planet): ?>
                        <option value="<?= htmlspecialchars($planet['id']) ?>"><?= htmlspecialchars($planet['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Source:
                <select name="source" required>
                    <?php foreach ($sources as $source): ?>
                        <option value="<?= htmlspecialchars($source['id']) ?>"><?= htmlspecialchars($source['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Upload Image:<input type="file" name="image" accept="image/jpeg, image/png, image/gif, image/webp"></label>
            <label>Excluded Crew Members:</label>
            <select name="excluded_crew[]" multiple>
                <?php foreach ($crewList as $crewMember): ?>
                    <option value="<?= htmlspecialchars($crewMember['id']) ?>">
                        #<?= htmlspecialchars($crewMember['id']) ?> - <?= htmlspecialchars($crewMember['name']) ?>
                        (<?= htmlspecialchars($crewMember['source'] ?? 'No Source') ?>,
                        <?= htmlspecialchars($crewMember['planet'] ?? 'No Planet') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="submit" class="button success">Add Crew Member</button>
</form>
<a href="index.php" class="button secondary">Back to Dashboard</a>
<?php require 'includes/footer.php'; ?>