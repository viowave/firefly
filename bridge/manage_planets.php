<?php
require 'includes/db.php';
require 'includes/header.php';

// Handle adding a new planet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_planet'])) {
    $name = trim($_POST['name']);
    $is_supply_planet = isset($_POST['is_supply_planet']) ? 1 : 0;
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO planets (name, is_supply_planet) VALUES (:name, :is_supply_planet)");
        $stmt->execute(['name' => $name, 'is_supply_planet' => $is_supply_planet]);
    }
}

// Handle deleting a planet
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM planets WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: planets.php");
    exit;
}

// Fetch all planets
$planetsStmt = $pdo->query("SELECT * FROM planets ORDER BY name ASC");
$planets = $planetsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="grid-container">
    <h3>Manage Planets</h3>
    <form method="POST">
        <div class="grid-x grid-margin-x">
            <div class="cell small-12 medium-8">
                <label>New Planet:
                    <input type="text" name="name" required>
                </label>
            </div>
            <div class="cell small-12 medium-4 text-right">
                <button type="submit" name="add_planet" class="button success">Add Planet</button>
            </div>
        </div>
    </form>
    <table class="stack">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Supply Planet</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($planets as $planet): ?>
                <tr>
                    <td><?= htmlspecialchars($planet['id']); ?></td>
                    <td><?= htmlspecialchars($planet['name']); ?></td>
                    <td><?= $planet['is_supply_planet'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="planets.php?delete=<?= $planet['id']; ?>" class="button alert small" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php" class="button secondary">Back to Dashboard</a>
</div>

<?php require 'includes/footer.php'; ?>
