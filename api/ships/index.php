<?php
require_once '../../bridge/includes/db.php'; // Adjust path as needed to your db connection file

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
        // Create placeholders for the IN clause (e.g., ?,?,?)
        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
        $whereClause = "WHERE s.source_id IN ($placeholders)";
    }
}

try {
    $sql = "SELECT
        s.id,
        s.name AS ship_name,
        s.image_url,
        s.source_id,
        src.name AS source_name
    FROM ships s
    LEFT JOIN sources src ON s.source_id = src.id
    $whereClause
    ORDER BY s.name ASC;";

    $stmt = $pdo->prepare($sql);

    // Execute the statement with source IDs if the whereClause is active
    if ($whereClause) {
        $stmt->execute($sourceIds);
    } else {
        $stmt->execute();
    }

    $ships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format image_url to be a full URL if needed (adjust based on your setup)
    $formattedShips = array_map(function ($ship) {
        if (!empty($ship['image_url'])) {
            // Construct full URL using dynamic host and path
            $ship['image_full_url'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/uploads/ships/" . $ship['image_url'];
        } else {
            $ship['image_full_url'] = null; // Or a placeholder image URL
        }
        return $ship;
    }, $ships);


    echo json_encode($formattedShips);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>