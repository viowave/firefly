<?php
require 'includes/db.php';
require 'includes/header.php';

// Fetch all sources
$sourcesStmt = $pdo->query("SELECT * FROM sources ORDER BY name ASC");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected source (preserve after reload)
$selectedSourceId = $_POST['source_id'] ?? null;

// Fetch excluded crew IDs for the selected source
$excludedCrewIds = [];
if ($selectedSourceId) {
    $stmt = $pdo->prepare("SELECT excluded_crew_id FROM source_exclusions WHERE source_id = :source_id");
    $stmt->execute(['source_id' => $selectedSourceId]);
    $excludedCrewIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch available crew members (exclude those who match the selected source)
// Fetch available crew members (exclude those who match the selected source)
$availableCrew = [];
if ($selectedSourceId) {
    $query = "
        SELECT c.id, c.name, s.name AS source_name 
        FROM crew c
        LEFT JOIN sources s ON c.source_id = s.id
        WHERE 
            (c.source_id != ? OR c.id IN (" . implode(',', array_fill(0, count($excludedCrewIds), '?')) . "))
            ORDER BY c.name ASC
    ";

    $executeParams = array_merge([$selectedSourceId], $excludedCrewIds);
    $stmt = $pdo->prepare($query);
    $stmt->execute($executeParams);

    $availableCrew = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch full details of excluded crew members
$excludedCrew = [];
if (!empty($excludedCrewIds)) {
    $query = "
        SELECT c.id, c.name, s.name AS source_name 
        FROM crew c
        LEFT JOIN sources s ON c.source_id = s.id
        WHERE c.id IN (" . implode(',', array_fill(0, count($excludedCrewIds), '?')) . ")
        ORDER BY c.name ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($excludedCrewIds);
    $excludedCrew = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission to update exclusions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluded_crew'])) {
    $excludedIds = $_POST['excluded_crew'] ?? [];

    // Delete old exclusions
    $stmt = $pdo->prepare("DELETE FROM source_exclusions WHERE source_id = ?");
    $stmt->execute([$selectedSourceId]);

    // Insert new exclusions
    foreach ($excludedIds as $excludedId) {
        $stmt = $pdo->prepare("INSERT INTO source_exclusions (source_id, excluded_crew_id) VALUES (?, ?)");
        $stmt->execute([$selectedSourceId, $excludedId]);
    }

    echo "<p>✅ Exclusions updated successfully!</p>";

    // Refresh the exclusion list (fetch updated exclusions)
    $stmt = $pdo->prepare("SELECT excluded_crew_id FROM source_exclusions WHERE source_id = :source_id");
    $stmt->execute(['source_id' => $selectedSourceId]);
    $excludedCrewIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Re-fetch the updated list of excluded crew members
    $query = "
        SELECT c.id, c.name, s.name AS source_name 
        FROM crew c
        LEFT JOIN sources s ON c.source_id = s.id
        WHERE c.id IN (" . implode(',', array_fill(0, count($excludedCrewIds), '?')) . ")
        ORDER BY c.name ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($excludedCrewIds);
    $excludedCrew = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h3>Manage Exclusions for Sources</h3>

<!-- Source Selection Dropdown -->
<form method="POST">
    <label for="source">Select Source:</label>
    <select name="source_id" id="source" onchange="this.form.submit()">
        <option value="">Select a Source</option>
        <?php foreach ($sources as $source): ?>
            <option value="<?= $source['id'] ?>" <?= ($selectedSourceId == $source['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($source['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selectedSourceId): ?>
    <h4>Available Crew Members to Exclude</h4>
    <form method="POST">
        <input type="hidden" name="source_id" value="<?= htmlspecialchars($selectedSourceId) ?>">

        <label for="excluded_crew">Exclude Crew Members:</label>
        <select name="excluded_crew[]" multiple>
            <?php foreach ($availableCrew as $crew): ?>
                <option value="<?= $crew['id'] ?>" <?= in_array($crew['id'], $excludedCrewIds) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($crew['name']) ?> 
                    (<?= htmlspecialchars($crew['source_name'] ?? 'Unknown Source') ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button primary">Save Exclusions</button>
    </form>

    <h4>Currently Excluded Crew Members</h4>
    <?php if (!empty($excludedCrew)): ?>
        <ul>
            <?php foreach ($excludedCrew as $crew): ?>
                <li>
                    <strong>#<?= htmlspecialchars($crew['id']) ?> - <?= htmlspecialchars($crew['name']) ?></strong> 
                    (<?= htmlspecialchars($crew['source_name'] ?? 'Unknown Source') ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No crew members are excluded for this source.</p>
    <?php endif; ?>
<?php endif; ?>

<a href="index.php" class="button secondary">Back to Dashboard</a>

<?php require 'includes/footer.php'; ?>
