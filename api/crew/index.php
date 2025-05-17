<?php
require_once '../../bridge/includes/db.php'; // Database connection

// Allow all domains to access this API
header("Access-Control-Allow-Origin: *");
// Allow certain HTTP methods
header("Access-Control-Allow-Methods: GET, OPTIONS");
// Allow certain headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// Allow credentials (if needed)
header("Access-Control-Allow-Credentials: true");

// Handle preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// Get the 'sources' parameter from the GET request
$sources = $_GET['sources'] ?? null;

$whereClause = '';
$sourceIds = [];
if ($sources) {
    // Sanitize the input to prevent SQL injection
    $sourceIds = array_map('intval', explode(',', $sources));
    if (!empty($sourceIds)) {
        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
        $whereClause = "WHERE c.source_id IN ($placeholders)";
    }
}

try {
    $sql = "SELECT
        c.id,
        c.name AS crew_name,
        c.description,
        c.fight_points,
        c.tech_points,
        c.talk_points,
        c.moral,
        c.leader,
        c.wanted,
        c.is_custom,
        c.cost,
        c.planet_id,
        p.name as planet_name,
        c.source_id,
        s.name AS source_name,
        c.image_url,
        GROUP_CONCAT(DISTINCT r.id SEPARATOR ',') AS role_ids_str,
        GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS roles_str,
        GROUP_CONCAT(DISTINCT ce.excluded_crew_id SEPARATOR ',') AS exclusions
    FROM crew c
    LEFT JOIN crew_roles cr ON c.id = cr.crew_id
    LEFT JOIN roles r ON cr.role_id = r.id
    LEFT JOIN sources s ON c.source_id = s.id
    LEFT JOIN crew_exclusions ce ON c.id = ce.crew_id
    LEFT JOIN planets p ON c.planet_id = p.id
    $whereClause
    GROUP BY c.id;";

    $stmt = $pdo->prepare($sql);

    if ($whereClause) {
        $stmt->execute($sourceIds);
    } else {
        $stmt->execute();
    }

    $crew = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Structure the roles as an array of objects with id and name
    $formattedCrew = array_map(function ($member) {
        $roles = [];
        if (!empty($member['role_ids_str']) && !empty($member['roles_str'])) {
            $roleIds = explode(',', $member['role_ids_str']);
            $roleNames = explode(',', $member['roles_str']);
            foreach ($roleIds as $index => $roleId) {
                $roles[] = [
                    'id' => (int) trim($roleId),
                    'name' => trim($roleNames[$index] ?? ''), // Handle cases where names might be missing
                ];
            }
        }
        $member['roles'] = $roles;
        unset($member['role_ids_str']); // Remove the temporary string fields
        unset($member['roles_str']);
        return $member;
    }, $crew);

    echo json_encode($formattedCrew);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>