<?php
require_once '../../bridge/includes/db.php'; // Database connection
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid crew ID']);
    exit;
}

$crew_id = $_GET['id'];

try {
    // Fetch crew details with planet and source names
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.description, c.fight_points, c.tech_points, c.talk_points, 
               c.moral, c.leader, c.wanted, c.is_custom, c.cost, 
               p.name AS planet, s.name AS source
        FROM crew c
        JOIN planets p ON c.planet_id = p.id
        JOIN sources s ON c.source_id = s.id
        WHERE c.id = ?
    ");
    $stmt->execute([$crew_id]);
    $crew = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$crew) {
        http_response_code(404);
        echo json_encode(['error' => 'Crew member not found']);
        exit;
    }

    // Fetch roles
    $stmt = $pdo->prepare("
        SELECT r.name FROM roles r
        JOIN crew_roles cr ON r.id = cr.role_id
        WHERE cr.crew_id = ?
    ");
    $stmt->execute([$crew_id]);
    $crew['roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch keywords
    $stmt = $pdo->prepare("
        SELECT k.name FROM keywords k
        JOIN crew_keywords ck ON k.id = ck.keyword_id
        WHERE ck.crew_id = ?
    ");
    $stmt->execute([$crew_id]);
    $crew['keywords'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($crew);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
