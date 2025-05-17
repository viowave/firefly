<?php
require 'includes/db.php';
require 'includes/header.php';

// Get filter values
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$planetFilter = $_GET['planet'] ?? '';
$minCost = $_GET['min_cost'] ?? '';
$maxCost = $_GET['max_cost'] ?? '';

// Build the base SQL query
$sql = "SELECT 
            crew.id, crew.name, crew.description, crew.fight_points, crew.tech_points, crew.talk_points, 
            crew.moral, crew.leader, crew.wanted, crew.is_custom, crew.cost, crew.image_url,
            planets.name AS planet_name, sources.name AS source_name
        FROM crew
        LEFT JOIN planets ON crew.planet_id = planets.id
        LEFT JOIN sources ON crew.source_id = sources.id
        WHERE 1 ";

$params = [];

// Apply search filter
if (!empty($search)) {
    $sql .= "AND crew.name LIKE ? ";
    $params[] = "%$search%";
}

// Apply role filter
if (!empty($roleFilter)) {
    $sql .= "AND crew.id IN (SELECT crew_id FROM crew_roles JOIN roles ON crew_roles.role_id = roles.id WHERE roles.name = ?) ";
    $params[] = $roleFilter;
}

// Apply planet filter
if (!empty($planetFilter)) {
    $sql .= "AND planets.name = ? ";
    $params[] = $planetFilter;
}

// Apply cost filters
if (!empty($minCost)) {
    $sql .= "AND crew.cost >= ? ";
    $params[] = $minCost;
}
if (!empty($maxCost)) {
    $sql .= "AND crew.cost <= ? ";
    $params[] = $maxCost;
}

$sql .= "ORDER BY crew.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

    <h1>Crew List</h1>
    
    <h3>Filter Crew Members</h3>
    <form method="GET" class="grid-container fluid">
        <div class="grid-x grid-padding-x">
            <div class="cell small-12 medium-4">
                <input type="text" name="search" placeholder="Search by name" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="cell small-12 medium-2">
                <select name="role">
                    <option value="">Filter by Role</option>
                    <?php
                    $rolesStmt = $pdo->query("SELECT name FROM roles ORDER BY name ASC");
                    while ($role = $rolesStmt->fetchColumn()) {
                        $selected = ($_GET['role'] ?? '') === $role ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($role) . "' $selected>" . htmlspecialchars($role) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="cell small-12 medium-2">
                <select name="planet">
                    <option value="">Filter by Planet</option>
                    <?php
                    $planetsStmt = $pdo->query("SELECT name FROM planets ORDER BY name ASC");
                    while ($planet = $planetsStmt->fetchColumn()) {
                        $selected = ($_GET['planet'] ?? '') === $planet ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($planet) . "' $selected>" . htmlspecialchars($planet) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="cell small-6 medium-2">
                <input type="number" name="min_cost" placeholder="Min Cost" value="<?= htmlspecialchars($_GET['min_cost'] ?? '') ?>">
            </div>
            <div class="cell small-6 medium-2">
                <input type="number" name="max_cost" placeholder="Max Cost" value="<?= htmlspecialchars($_GET['max_cost'] ?? '') ?>">
            </div>
        </div>
    </form>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const filterForm = document.querySelector("form");
            filterForm.addEventListener("change", function () {
                filterForm.submit();
            });
        });
    </script>
    <br>
    <table border="1">
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th><img src="images/fight.webp" /></th>
            <th><img src="images/tech.webp" /></th>
            <th><img src="images/talk.webp" /></th>
            <th>Moral</th>
            <th>Leader</th>
            <th>Wanted</th>
            <th>Custom</th>
            <th>Cost</th>
            <th>Planet</th>
            <th>Source</th>
            <th>Roles</th>
            <th>Keywords</th>
            <th>Image</th>
            <th></th>
        </tr>
        <?php foreach ($result as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']); ?></td>
                <td><?= htmlspecialchars($row['description']); ?></td>
                <td><?= $row['fight_points']; ?></td>
                <td><?= $row['tech_points']; ?></td>
                <td><?= $row['talk_points']; ?></td>
                <td><?= $row['moral'] ? 'Yes' : 'No'; ?></td>
                <td><?= $row['leader'] ? 'Yes' : 'No'; ?></td>
                <td><?= $row['wanted'] ? 'Yes' : 'No'; ?></td>
                <td><?= $row['is_custom'] ? 'Yes' : 'No'; ?></td>
                <td><?= $row['cost']; ?></td>
                <td><?= htmlspecialchars($row['planet_name'] ?? ''); ?></td>
                <td><?= htmlspecialchars($row['source_name']); ?></td>
                <td>
                    <?= implode(', ', $pdo->query("SELECT roles.name FROM roles JOIN crew_roles ON roles.id = crew_roles.role_id WHERE crew_roles.crew_id = " . $row['id'])->fetchAll(PDO::FETCH_COLUMN)); ?>
                </td>
                <td>
                    <?= implode(', ', $pdo->query("SELECT keywords.name FROM keywords JOIN crew_keywords ON keywords.id = crew_keywords.keyword_id WHERE crew_keywords.crew_id = " . $row['id'])->fetchAll(PDO::FETCH_COLUMN)); ?>
                </td>
                <td>
                    <img src="../uploads/crew/<?= htmlspecialchars($row['image_url']) ?>" alt="Crew image" style="max-width: 50px;">
                </td>
                <td>
                    <div class="button-group tiny">
                        <a href="edit.php?id=<?= $row['id']; ?>" class="button primary">Edit</a>
                        <a href="delete.php?id=<?= $row['id']; ?>" class="button alert" onclick="return confirm('Are you sure?')">Delete</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php require 'includes/footer.php'; ?>
</body>
</html>
