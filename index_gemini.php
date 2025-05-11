<?php
// Include the database connection
require_once 'bridge/includes/db.php';

// Configuration
$numCrewNeeded = 5;
$numPlayers = 2;
$requiredRoleIds = [1, 2, 4, 6, 8];
$targetSourceIds = [1, 2, 3, 4, 5];

// Function to fetch data from the API
function fetchData($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception("cURL error: " . curl_error($ch) . " for URL " . $url);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode >= 400) {
        throw new Exception("HTTP error: " . $httpCode . " (" . $httpCode . ") for URL " . $url . ". Response: " . $response);
    }

    curl_close($ch);
    return $response;
}

// Function to display a team
function displayTeam($playerName, $team) {
    echo "<h2>" . htmlspecialchars($playerName) . "'s Team</h2>";
    if (!empty($team)) {
        echo "<ul>";
        foreach ($team as $member) {
            echo "<li>";
            echo htmlspecialchars($member['crew_name']) . " (ID: " . htmlspecialchars($member['id']);
            if (isset($member['roles']) && !empty($member['roles'])) {
                echo ", Roles: ";
                $roleNames = array_column($member['roles'], 'name');
                echo htmlspecialchars(implode(', ', $roleNames));
            }
            if (isset($member['source_name'])) {
                echo ", Source: " . htmlspecialchars($member['source_name']);
            }
            echo ", Status: " . (isset($member['leader']) && $member['leader'] == 1 ? "Leader" : "Crew");
            if (isset($member['exclusions']) && !empty($member['exclusions'])) {
                echo ", Exclusions: " . htmlspecialchars($member['exclusions']);
            }
            echo ")";
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No crew drafted.</p>";
    }
}

// Function to apply exclusions to a pool
function applyExclusionsToPool(&$pool, $excludedIds, $draftedId = null) {
    $pool = array_filter($pool, function ($crew) use ($excludedIds, $draftedId) {
        $isExcluded = false;
        if (isset($crew['id']) && in_array($crew['id'], $excludedIds)) {
            $isExcluded = true;
        }
        return !$isExcluded && ($draftedId === null || $crew['id'] !== $draftedId);
    });
    $pool = array_values($pool); // Re-index
}

// Function to check if a crew member has a specific role
function hasRole($crewMember, $roleId) {
    if (isset($crewMember['roles']) && is_array($crewMember['roles'])) {
        foreach ($crewMember['roles'] as $role) {
            if ($role['id'] == $roleId) {
                return true;
            }
        }
    }
    return false;
}

try {
    // 1. Fetch crew from the API
    $crewUrl = 'http://firefly.test/api/crew/?sources=' . implode(',', $targetSourceIds);
    $allCrew = fetchData($crewUrl);

    // Error checking after fetching data
    if ($allCrew === false) {
        throw new Exception("Failed to fetch data from the API.");
    }

    $allCrewArray = json_decode($allCrew, true);
    $mainPool = $allCrewArray;

    // Error checking after JSON decoding
    if ($mainPool === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON response from the API. Error: " . json_last_error_msg());
    } elseif ($mainPool === null) {
        $mainPool = []; // Initialize as an empty array
    }

    // 2. Separate leaders into Pool A and remove from mainPool
    $leaderPool = [];
    $mainPool = array_filter($mainPool, function ($crew) use (&$leaderPool) {
        if (isset($crew['leader']) && $crew['leader'] == 1) {
            $leaderPool[] = $crew;
            return false; // Remove from mainPool
        }
        return true;
    });
    $leaderPool = array_values($leaderPool); // Re-index

    // 3. Create Pool B with crew having required roles (remaining in mainPool)
    $requiredRolePool = array_filter($mainPool, function ($crew) use ($requiredRoleIds) {
        foreach ($requiredRoleIds as $roleId) {
            if (hasRole($crew, $roleId)) {
                return true;
            }
        }
        return false;
    });
    $requiredRolePool = array_values($requiredRolePool); // Re-index

    // Initialize teams and draft tracking
    $teams = [[], []];
    $drafted = [];
    $leadersDrafted = [false, false];
    $requiredRolesFilled = [
        0 => array_fill_keys($requiredRoleIds, false),
        1 => array_fill_keys($requiredRoleIds, false),
    ];
    $numPicksPerPlayer = 1 + $numCrewNeeded;
    $totalPicks = $numPlayers * $numPicksPerPlayer;
    $teams = [[], []];
    $drafted = [];
    $leadersDrafted = [false, false];
    $requiredRolesFilled = [
        0 => array_fill_keys($requiredRoleIds, false),
        1 => array_fill_keys($requiredRoleIds, false),
    ];

    for ($pick = 0; $pick < $totalPicks; $pick++) {
        $round = floor($pick / $numPlayers) + 1;
        $currentPlayerIndex = ($round % 2 == 1) ? ($pick % $numPlayers) : (($numPlayers - 1) - ($pick % $numPlayers));

        if (!$leadersDrafted[$currentPlayerIndex] && !empty($leaderPool)) {
            // Leader Drafting
            $randomIndex = array_rand($leaderPool);
            $pickedLeader = $leaderPool[$randomIndex];
            $teams[$currentPlayerIndex][] = $pickedLeader;
            $drafted[] = $pickedLeader['id'];
            $leadersDrafted[$currentPlayerIndex] = true;
            $excludedIdsArray = array_map('intval', explode(',', ($pickedLeader['exclusions'] ?? '')));
            applyExclusionsToPool($mainPool, $excludedIdsArray, $pickedLeader['id']);
            applyExclusionsToPool($requiredRolePool, $excludedIdsArray, $pickedLeader['id']);
            unset($leaderPool[$randomIndex]);
            $leaderPool = array_values($leaderPool);
        } elseif (array_search(false, $requiredRolesFilled[$currentPlayerIndex]) !== false) {
            // Required Role Drafting
            foreach ($requiredRoleIds as $roleId) {
                if (!$requiredRolesFilled[$currentPlayerIndex][$roleId]) {
                    $availableRequired = array_filter($requiredRolePool, function ($crew) use ($roleId, $teams, $currentPlayerIndex, $drafted) {
                        return hasRole($crew, $roleId) && !in_array($crew['id'], array_column($teams[$currentPlayerIndex], 'id')) && !in_array($crew['id'], $drafted);
                    });

                    if (!empty($availableRequired)) {
                        $randomIndex = array_rand($availableRequired);
                        $pickedRequired = $availableRequired[$randomIndex];
                        $teams[$currentPlayerIndex][] = $pickedRequired;
                        $drafted[] = $pickedRequired['id'];
                        $requiredRolesFilled[$currentPlayerIndex][$roleId] = true;
                        $excludedIdsArray = array_map('intval', explode(',', ($pickedRequired['exclusions'] ?? '')));
                        applyExclusionsToPool($mainPool, $excludedIdsArray, $pickedRequired['id']);
                        applyExclusionsToPool($leaderPool, $excludedIdsArray, $pickedRequired['id']);
                        applyExclusionsToPool($requiredRolePool, $excludedIdsArray, $pickedRequired['id']);
                        $requiredRolePool = array_filter($requiredRolePool, function ($crew) use ($pickedRequired) {
                            return $crew['id'] !== $pickedRequired['id'];
                        });
                        $requiredRolePool = array_values($requiredRolePool);
                        $roleName = 'Role ID ' . $roleId; // Default if role name not found
                        foreach ($pickedRequired['roles'] as $role) {
                            if ($role['id'] == $roleId) {
                                $roleName = $role['name'];
                                break;
                            }
                        }
                        break; // Only attempt to draft one required role per pick
                    } else {
                        echo "Warning: No available crew with required role ID " . $roleId . " for Player " . ($currentPlayerIndex + 1) . ".<br>";
                    }
                }
            }
        } else {
            // Remaining Crew Drafting
            $availableCrew = array_filter($mainPool, function ($crew) use ($teams, $currentPlayerIndex, $drafted) {
                $isDuplicateRole = false;
                foreach ($teams[$currentPlayerIndex] as $member) {
                    if (isset($crew['roles']) && isset($member['roles']) && !empty(array_intersect(array_column($crew['roles'], 'id'), array_column($member['roles'], 'id')))) {
                        $isDuplicateRole = true;
                        break;
                    }
                }
                return !in_array($crew['id'], array_column($teams[$currentPlayerIndex], 'id')) && !in_array($crew['id'], $drafted) && !$isDuplicateRole;
            });

            if (!empty($availableCrew)) {
                $randomIndex = array_rand($availableCrew);
                $pickedRegularCrew = $availableCrew[$randomIndex];
                $teams[$currentPlayerIndex][] = $pickedRegularCrew;
                $drafted[] = $pickedRegularCrew['id'];
                $excludedIdsArray = array_map('intval', explode(',', ($pickedRegularCrew['exclusions'] ?? '')));
                applyExclusionsToPool($mainPool, $excludedIdsArray, $pickedRegularCrew['id']);
                unset($mainPool[$randomIndex]);
                $mainPool = array_values($mainPool);
            } else {
                echo "Warning: No available crew left in the Main Pool for Player " . ($currentPlayerIndex + 1) . ".<br>";
            }
        }
    }

    echo "<h1>Final Draft Results</h1>";
    displayTeam("Player 1", $teams[0]);
    displayTeam("Player 2", $teams[1]);

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
