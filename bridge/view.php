<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'includes/db.php';
require 'includes/header.php';

// Get the crew member's ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ Invalid crew member ID.");
}

$id = $_GET['id'];

// Fetch existing crew data
$stmt = $pdo->prepare("SELECT * FROM crew WHERE id = :id");
$stmt->execute(['id' => $id]);
$crew = $stmt->fetch();

if (!$crew) {
    die("❌ Crew member not found.");
}

// Fetch roles
$rolesStmt = $pdo->prepare("SELECT r.name FROM crew_roles cr
                            JOIN roles r ON cr.role_id = r.id
                            WHERE cr.crew_id = :crew_id");
$rolesStmt->execute(['crew_id' => $id]);
$roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch keywords
$keywordsStmt = $pdo->prepare("SELECT k.name FROM crew_keywords ck
                               JOIN keywords k ON ck.keyword_id = k.id
                               WHERE ck.crew_id = :crew_id");
$keywordsStmt->execute(['crew_id' => $id]);
$keywords = $keywordsStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch exclusions
$excludedStmt = $pdo->prepare("SELECT c.name FROM crew_exclusions ce
                               JOIN crew c ON ce.excluded_crew_id = c.id
                               WHERE ce.crew_id = :crew_id");
$excludedStmt->execute(['crew_id' => $id]);
$excludedCrew = $excludedStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<h3>View Crew Member</h3>

<div class="crew-details">
    <h4>Name: <?= htmlspecialchars($crew['name']) ?></h4>
    <p><strong>Description:</strong> <?= htmlspecialchars($crew['description']) ?></p>
    <p><strong>Fight Points:</strong> <?= htmlspecialchars($crew['fight_points']) ?></p>
    <p><strong>Tech Points:</strong> <?= htmlspecialchars($crew['tech_points']) ?></p>
    <p><strong>Talk Points:</strong> <?= htmlspecialchars($crew['talk_points']) ?></p>
    <p><strong>Moral:</strong> <?= $crew['moral'] ? 'Yes' : 'No' ?></p>
    <p><strong>Leader:</strong> <?= $crew['leader'] ? 'Yes' : 'No' ?></p>
    <p><strong>Wanted:</strong> <?= $crew['wanted'] ? 'Yes' : 'No' ?></p>
    <p><strong>Custom Card:</strong> <?= $crew['is_custom'] ? 'Yes' : 'No' ?></p>
    <p><strong>Cost:</strong> <?= htmlspecialchars($crew['cost']) ?></p>
    <p><strong>Planet:</strong> <?= htmlspecialchars($crew['planet_id']) ?></p> <!-- You can link this to planet name if needed -->
    <p><strong>Source:</strong> <?= htmlspecialchars($crew['source_id']) ?></p> <!-- You can link this to source name if needed -->

    <?php if (!empty($crew['image_url'])): ?>
        <div class="crew-image">
            <p><strong>Image:</strong></p>
            <img src="/uploads/crew/<?= htmlspecialchars($crew['image_url']) ?>" alt="Crew Image" style="max-width: 150px;">
        </div>
    <?php endif; ?>

    <h5>Roles:</h5>
    <ul>
        <?php foreach ($roles as $role): ?>
            <li><?= htmlspecialchars($role) ?></li>
        <?php endforeach; ?>
    </ul>

    <h5>Keywords:</h5>
    <ul>
        <?php foreach ($keywords as $keyword): ?>
            <li><?= htmlspecialchars($keyword) ?></li>
        <?php endforeach; ?>
    </ul>

    <h5>Exclusions:</h5>
    <ul>
        <?php if (empty($excludedCrew)): ?>
            <li>No exclusions</li>
        <?php else: ?>
            <?php foreach ($excludedCrew as $excluded): ?>
                <li><?= htmlspecialchars($excluded) ?></li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<a href="index.php" class="button secondary">Back to Dashboard</a>
<?php require 'includes/footer.php'; ?>
