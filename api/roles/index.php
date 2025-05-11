<?php
require_once '../../bridge/includes/db.php'; // Database connection
header('Content-Type: application/json');

try {
    // Fetch all roles
    $stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($roles);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
