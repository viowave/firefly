<?php
require_once '../../bridge/includes/db.php'; // Database connection
header('Content-Type: application/json');

try {
    // Fetch all crew members with planet, source, and source exclusions
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.leader, c.is_custom, c.cost, 
               p.name AS planet, s.id AS source_id
        FROM crew c
        LEFT JOIN planets p ON c.planet_id = p.id
        JOIN sources s ON c.source_id = s.id
        ORDER BY c.name
    ");

    $crew = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch roles, keywords, crew exclusions, and source exclusions for each crew member
    foreach ($crew as &$member) {
        $crewId = $member['id'];

        // Get roles
        $stmt = $pdo->prepare("
            SELECT r.name FROM roles r
            JOIN crew_roles cr ON r.id = cr.role_id
            WHERE cr.crew_id = ?
        ");
        $stmt->execute([$crewId]);
        $member['roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get keywords
        $stmt = $pdo->prepare("
            SELECT k.name FROM keywords k
            JOIN crew_keywords ck ON k.id = ck.keyword_id
            WHERE ck.crew_id = ?
        ");
        $stmt->execute([$crewId]);
        $member['keywords'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get crew exclusions
        $stmt = $pdo->prepare("
            SELECT excluded_crew_id FROM crew_exclusions WHERE crew_id = ?
        ");
        $stmt->execute([$crewId]);
        $excludedCrewIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Store exclusions as an array
        $member['crew_exclusions'] = array_map('intval', $excludedCrewIds);

        // Get source exclusions
        $stmt = $pdo->prepare("
            SELECT source_id FROM source_exclusions WHERE excluded_crew_id = ?
        ");
        $stmt->execute([$crewId]);
        $excludedSourceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Store source exclusions as an array
        $member['source_exclusions'] = array_map('intval', $excludedSourceIds);
    }

    echo json_encode($crew);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
}
?>
