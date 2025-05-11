<?php
require 'includes/db.php';
require 'includes/header.php';

// Get the crew member's ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ Invalid crew member ID.");
}

$id = $_GET['id'];

// Fetch existing crew data FIRST, before processing form
$stmt = $pdo->prepare("SELECT * FROM crew WHERE id = :id");
$stmt->execute(['id' => $id]);
$crew = $stmt->fetch();

if (!$crew) {
    die("❌ Crew member not found.");
}

// Process form submission for crew member update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Set default values for checkboxes
        $moral = isset($_POST['moral']) ? 1 : 0;
        $leader = isset($_POST['leader']) ? 1 : 0;
        $wanted = isset($_POST['wanted']) ? 1 : 0;
        $is_custom = isset($_POST['is_custom']) ? 1 : 0;
        
        // Handle image upload if a new file was provided
        $imagePath = $crew['image_url']; // Now $crew is defined before being used
        
        if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
            $uploadDir = 'uploads/crew/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate a unique filename
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            
            // Check file type
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($imageFileType, $allowedExtensions)) {
                throw new Exception("Only JPG, JPEG, PNG, and GIF files are allowed.");
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                // Delete old image if exists and is different
                if (!empty($crew['image_url']) && file_exists($crew['image_url']) && $crew['image_url'] !== $targetFile) {
                    unlink($crew['image_url']);
                }                
                $imagePath = $targetFile;
            } else {
                throw new Exception("Failed to upload image.");
            }
        }
        
        // Update crew member information
        $stmt = $pdo->prepare("
            UPDATE crew SET 
                name = :name,
                description = :description,
                fight_points = :fight_points,
                tech_points = :tech_points,
                talk_points = :talk_points,
                moral = :moral,
                leader = :leader,
                wanted = :wanted,
                is_custom = :is_custom,
                cost = :cost,
                planet_id = :planet_id,
                source_id = :source_id,
                image_url = :image_url
            WHERE id = :id
        ");
        
        $stmt->execute([
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'fight_points' => $_POST['fight_points'],
            'tech_points' => $_POST['tech_points'],
            'talk_points' => $_POST['talk_points'],
            'moral' => $moral,
            'leader' => $leader,
            'wanted' => $wanted,
            'is_custom' => $is_custom,
            'cost' => $_POST['cost'],
            'planet_id' => $_POST['planet'],
            'source_id' => $_POST['source'],
            'image_url' => $imagePath,
            'id' => $id
        ]);
        
        // Update roles - first delete existing roles
        $stmt = $pdo->prepare("DELETE FROM crew_roles WHERE crew_id = :crew_id");
        $stmt->execute(['crew_id' => $id]);
        
        // Insert new roles
        if (isset($_POST['roles']) && is_array($_POST['roles'])) {
            $roleStmt = $pdo->prepare("INSERT INTO crew_roles (crew_id, role_id) VALUES (:crew_id, :role_id)");
            foreach ($_POST['roles'] as $roleId) {
                $roleStmt->execute([
                    'crew_id' => $id,
                    'role_id' => $roleId
                ]);
            }
        }
        
        // Update keywords - first delete existing keywords
        $stmt = $pdo->prepare("DELETE FROM crew_keywords WHERE crew_id = :crew_id");
        $stmt->execute(['crew_id' => $id]);
        
        // Insert new keywords
        if (isset($_POST['keywords']) && is_array($_POST['keywords'])) {
            $keywordStmt = $pdo->prepare("INSERT INTO crew_keywords (crew_id, keyword_id) VALUES (:crew_id, :keyword_id)");
            foreach ($_POST['keywords'] as $keywordId) {
                $keywordStmt->execute([
                    'crew_id' => $id,
                    'keyword_id' => $keywordId
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        

        // Handle exclusions
        if (isset($_POST['excluded_crew'])) {
            try {
                $excludedIds = $_POST['excluded_crew'];

                // Clear existing exclusions for this crew
                $stmt = $pdo->prepare("DELETE FROM crew_exclusions WHERE crew_id = ?");
                $stmt->execute([$id]);

                // Prepare statements outside the loop for efficiency
                $insertStmt = $pdo->prepare("INSERT IGNORE INTO crew_exclusions (crew_id, excluded_crew_id) VALUES (?, ?)");
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM crew_exclusions WHERE crew_id = ? AND excluded_crew_id = ?");

                foreach ($excludedIds as $excludedId) {
                    // Insert primary and reverse exclusions
                    $insertStmt->execute([$id, $excludedId]);
                    $insertStmt->execute([$excludedId, $id]);

                    // Create mutual exclusions between all selected crew members
                    foreach ($excludedIds as $otherExcludedId) {
                        if ($excludedId !== $otherExcludedId) {
                            $insertStmt->execute([$excludedId, $otherExcludedId]);
                        }
                    }
                }

                error_log("Exclusions processed successfully for crew ID: $id");
            } catch (PDOException $e) {
                error_log("Exclusion error: " . $e->getMessage());
                // Optionally, handle the error (show message, rollback, etc.)
            }
        }
        // Redirect to prevent form resubmission
        header("Location: view.php?id=$id&updated=1");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $errorMessage = "Error updating crew member: " . $e->getMessage();
    }
}

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

// Fetch currently assigned roles
$crewRolesStmt = $pdo->prepare("SELECT role_id FROM crew_roles WHERE crew_id = :crew_id");
$crewRolesStmt->execute(['crew_id' => $id]);
$crewRoles = $crewRolesStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch currently assigned keywords
$crewKeywordsStmt = $pdo->prepare("SELECT keyword_id FROM crew_keywords WHERE crew_id = :crew_id");
$crewKeywordsStmt->execute(['crew_id' => $id]);
$crewKeywords = $crewKeywordsStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all crew members (excluding the one being edited if applicable)
$crewStmt = $pdo->prepare("
    SELECT c.id, c.name, p.name AS planet, s.name AS source
    FROM crew c
    LEFT JOIN planets p ON c.planet_id = p.id
    LEFT JOIN sources s ON c.source_id = s.id
    WHERE c.id != :id
    ORDER BY c.name ASC
");
$crewStmt->execute(['id' => $id ?? 0]);
$crewList = $crewStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing exclusions
$excludedStmt = $pdo->prepare("SELECT excluded_crew_id FROM crew_exclusions WHERE crew_id = ?");
$excludedStmt->execute([$id]);
$excludedCrew = $excludedStmt->fetchAll(PDO::FETCH_COLUMN);


?>

<h3>Edit Crew Member</h3>
<?php if (isset($errorMessage)): ?>
    <div class="callout alert"><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
    <div class="callout success">✅ Crew member updated successfully!</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="grid-container fluid">
    <div class="grid-x grid-padding-x">
        <div class="cell small-12">
            <label>Name:<input type="text" name="name" value="<?= htmlspecialchars($crew['name']) ?>" required></label>
            <label>Description:<textarea name="description" required><?= htmlspecialchars($crew['description']) ?></textarea></label>
            <label>Fight Points:<input type="number" name="fight_points" min="0" max="3" value="<?= $crew['fight_points'] ?>" required></label>
            <label>Tech Points:<input type="number" name="tech_points" min="0" max="3" value="<?= $crew['tech_points'] ?>" required></label>
            <label>Talk Points:<input type="number" name="talk_points" min="0" max="3" value="<?= $crew['talk_points'] ?>" required></label>
            <label>Roles:
                <select name="roles[]" multiple>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['id']) ?>" <?= in_array($role['id'], $crewRoles) ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Keywords:
                <select name="keywords[]" multiple>
                    <?php foreach ($keywords as $keyword): ?>
                        <option value="<?= htmlspecialchars($keyword['id']) ?>" <?= in_array($keyword['id'], $crewKeywords) ? 'selected' : '' ?>><?= htmlspecialchars($keyword['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Moral:<input type="checkbox" name="moral" <?= $crew['moral'] ? 'checked' : '' ?>></label>
            <label>Leader:<input type="checkbox" name="leader" <?= $crew['leader'] ? 'checked' : '' ?>></label>
            <label>Wanted:<input type="checkbox" name="wanted" <?= $crew['wanted'] ? 'checked' : '' ?>></label>
            <label>Custom Card:<input type="checkbox" name="is_custom" <?= $crew['is_custom'] ? 'checked' : '' ?>></label>
            <label>Cost:<input type="number" name="cost" value="<?= $crew['cost'] ?>" required></label>
            <label>Planet:
                <select name="planet">
                    <?php foreach ($planets as $planet): ?>
                        <option value="<?= htmlspecialchars($planet['id']) ?>" <?= ($crew['planet_id'] == $planet['id']) ? 'selected' : '' ?>><?= htmlspecialchars($planet['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Source:
                <select name="source" required>
                    <?php foreach ($sources as $source): ?>
                        <option value="<?= htmlspecialchars($source['id']) ?>" <?= ($crew['source_id'] == $source['id']) ? 'selected' : '' ?>><?= htmlspecialchars($source['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Upload Image:<input type="file" name="image" accept="image/*"></label>
            <?php if (!empty($crew['image'])): ?>
                <div class="thumbnail-preview">
                    <p>Current image:</p>
                    <img src="<?= htmlspecialchars($crew['image']) ?>" alt="Crew image" style="max-width: 150px;">
                </div>
            <?php endif; ?>
            <label>Excluded Crew Members:</label>
            <select name="excluded_crew[]" multiple>
            <?php foreach ($crewList as $crewMember): ?>
                <option value="<?= htmlspecialchars($crewMember['id']) ?>"
                    <?= in_array($crewMember['id'], $excludedCrew ?? []) ? 'selected' : '' ?>>
                    #<?= htmlspecialchars($crewMember['id']) ?> - <?= htmlspecialchars($crewMember['name']) ?>
                    (<?= htmlspecialchars($crewMember['source'] ?? 'No Source') ?>, 
                    <?= htmlspecialchars($crewMember['planet'] ?? 'No Planet') ?>)
                </option>
            <?php endforeach; ?>
            </select>
            <small>Hold Ctrl (Windows) or Command (Mac) to select multiple.</small>
        </div>
    </div>
    <button type="submit" class="button primary">Update Crew Member</button>
</form>
<a href="index.php" class="button secondary">Back to Dashboard</a>
<?php require 'includes/footer.php'; ?>