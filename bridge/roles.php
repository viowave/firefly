<?php
require 'includes/db.php';
require 'includes/header.php';

// Handle adding a new role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);
    }
}

// Handle deleting a role
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: roles.php");
    exit;
}

// Fetch all roles
$rolesStmt = $pdo->query("SELECT * FROM roles ORDER BY name ASC");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="grid-container">
    <h3>Manage Roles</h3>
    <form method="POST">
        <div class="grid-x grid-margin-x">
            <div class="cell small-12 medium-8">
                <label>New Role:
                    <input type="text" name="name" required>
                </label>
            </div>
            <div class="cell small-12 medium-4 text-right">
                <button type="submit" name="add_role" class="button success">Add Role</button>
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
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= htmlspecialchars($role['id']); ?></td>
                    <td><?= htmlspecialchars($role['name']); ?></td>
                    <td>
                        <a href="roles.php?delete=<?= $role['id']; ?>" class="button alert small" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php" class="button secondary">Back to Dashboard</a>
</div>

<?php require 'includes/footer.php'; ?>
