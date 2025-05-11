<?php
require_once '../../bridge/includes/db.php'; // Database connection

// Allow all domains to access this API
header("Access-Control-Allow-Origin: *");
// Allow certain HTTP methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

try {
    // Fetch all sources along with their exclusions using the provided query
    $stmt = $pdo->query("
        SELECT 
            s.id AS source_id, 
            s.name AS source_name, 
            COALESCE(GROUP_CONCAT(e.excluded_crew_id), '') AS exclusions
        FROM sources s
        LEFT JOIN source_exclusions e ON s.id = e.source_id
        GROUP BY s.id
        ORDER BY s.name
    ");
    
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert exclusions string to an array of integers if necessary
    foreach ($sources as &$source) {
        if ($source['exclusions']) {
            // Convert the comma-separated exclusions into an array of integers
            $source['exclusions'] = array_map('intval', explode(',', $source['exclusions']));
        } else {
            // If no exclusions, make it an empty array
            $source['exclusions'] = [];
        }
    }

    echo json_encode($sources);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
