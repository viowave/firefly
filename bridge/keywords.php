<?php
require 'includes/db.php';
require 'includes/header.php';

// Handle adding a new keyword
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_keyword'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO keywords (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);
    }
}

// Handle deleting a keyword
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM keywords WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: keywords.php");
    exit;
}

// Fetch all keywords
$keywordsStmt = $pdo->query("SELECT * FROM keywords ORDER BY name ASC");
$keywords = $keywordsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="grid-container">
    <h3>Manage Keywords</h3>
    <form method="POST">
        <div class="grid-x grid-margin-x">
            <div class="cell small-12 medium-8">
                <label>New Keyword:
                    <input type="text" name="name" required>
                </label>
            </div>
            <div class="cell small-12 medium-4 text-right">
                <button type="submit" name="add_keyword" class="button success">Add Keyword</button>
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
            <?php foreach ($keywords as $keyword): ?>
                <tr>
                    <td><?= htmlspecialchars($keyword['id']); ?></td>
                    <td><?= htmlspecialchars($keyword['name']); ?></td>
                    <td>
                        <a href="keywords.php?delete=<?= $keyword['id']; ?>" class="button alert small" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php" class="button secondary">Back to Dashboard</a>
</div>

<?php require 'includes/footer.php'; ?>
