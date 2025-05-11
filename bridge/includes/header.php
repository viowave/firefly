<?php
require 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/foundation-sites/dist/css/foundation.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/foundation-sites/dist/js/foundation.min.js"></script>
</head>
<body>
    <div class="grid-container fluid">
        <div class="grid-x">
            <div class="cell small-12 medium-12">
                <?php include 'includes/menu.php'; ?>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="grid-x">
            <div class="cell auto" id="main-content">
