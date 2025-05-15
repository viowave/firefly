<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firefly Crew Picker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php
    $manifestPath = __DIR__ . '/dist/manifest.json'; // Full path to manifest.json
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (isset($manifest['main.css']['file'])) {
            echo '<link rel="stylesheet" href="../dist/' . htmlspecialchars($manifest['main.css']['file']) . '">';
        }
    } else {
        // Handle the case where manifest.json is not found (e.g., development mode)
        echo '<link rel="stylesheet" href="../dist/assets/main.css">'; // Or some default
    }
    ?>
</head>
<body>
    <div class="wrapper">
        <h1 class="papyrus-font">Firefly Crew Picker</h1>
        <form action="run_draft.php" method="post">
            <div class="number-button-group">
                <label>Number of Players</label>
                <div id="playerCountButtons">
                </div>
                <input type="hidden" id="numPlayersInput" name="numPlayers" value="2">
            </div>
            <div class="form-group" id="playerNames"></div>
            <div class="number-button-group">
                <label>Number of Crew Needed</label>
                <div id="crewNeededButtons">
                </div>
                <input type="hidden" id="numCrewNeededInput" name="numCrewNeeded" value="1">
            </div>
            <div id="draftLeaderContainer">
                <button type="button" id="draftLeaderButton" class="leader-toggle-button">Draft a Leader</button>
                <input type="hidden" id="draftLeaderInput" name="draftLeader" value="0">
            </div>
            <div class="role-button-group">
                <label>Required Roles</label>
                <div id="requiredRoles">
                    </div>
            </div>
            <div class="source-button-group">
                <label>Sources</label>
                <div>
                    <button type="button" id="selectAllSources" class="select-all-button">Select All</button>
                </div>
                <div id="targetSources">
                    </div>
            </div>
            <div class="submit">
                <button type="submit">Run Draft</button>
            </div>
        </form>
        <?php
        if (file_exists($manifestPath)) {
            if (isset($manifest['main.js']['file'])) {
                echo '<script src="../dist/' . htmlspecialchars($manifest['main.js']['file']) . '"></script>';
            }
        } else {
            echo '<script  src="../dist/assets/main.js"></script>';
        }
        ?>
    </div>
    <div class="resultsWrapper">
    </div>
</body>
</html>