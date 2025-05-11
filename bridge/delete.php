<?php
require 'includes/auth.php'; // Protect the page
require 'includes/db.php'; // Database connection

// Check if the ID is set in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ Invalid crew member ID.");
}

$id = (int) $_GET['id']; // Ensure ID is an integer to prevent SQL injection

// Start transaction
$pdo->beginTransaction();

try {
    // Delete related roles
    $stmt = $pdo->prepare("DELETE FROM crew_roles WHERE crew_id = :id");
    $stmt->execute(['id' => $id]);

    // Delete related keywords
    $stmt = $pdo->prepare("DELETE FROM crew_keywords WHERE crew_id = :id");
    $stmt->execute(['id' => $id]);

    // Delete the crew member
    $stmt = $pdo->prepare("DELETE FROM crew WHERE id = :id");
    $stmt->execute(['id' => $id]);

    // Commit transaction
    $pdo->commit();

    // Redirect back to dashboard
    header("Location: index.php?message=success");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Error deleting crew member: " . $e->getMessage());
}
?>
