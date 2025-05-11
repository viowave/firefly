<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/header.php';

// Handle adding a new source
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_source'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO sources (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);
    }
}

// Handle deleting a source
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM sources WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: sources.php");
    exit;
}

// Fetch all sources
$sourcesStmt = $pdo->query("SELECT * FROM sources ORDER BY name ASC");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="grid-container">
    <h3>Manage Sources</h3>
    <form method="POST">
        <div class="grid-x grid-margin-x">
            <div class="cell small-12 medium-8">
                <label>New Source:
                    <input type="text" name="name" required>
                </label>
            </div>
            <div class="cell small-12 medium-4 text-right">
                <button type="submit" name="add_source" class="button success">Add Source</button>
            </div>
        </div>
    </form>

    <table class="stack">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sources as $source): ?>
                <tr>
                    <td><?= htmlspecialchars($source['id']); ?></td>
                    <td><?= htmlspecialchars($source['name']); ?></td>
                    <td>
                        <a href="sources.php?delete=<?= $source['id']; ?>" class="button alert small" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php" class="button secondary">Back to Dashboard</a>
</div>

<?php require 'includes/footer.php'; ?>
