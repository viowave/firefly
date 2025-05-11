<?php
// Include the database connection (adjust path if necessary)
require_once 'bridge/includes/db.php';

// Get configuration from the form
$numPlayers = isset($_POST['numPlayers']) ? intval($_POST['numPlayers']) : 2;
$numCrewNeeded = isset($_POST['numCrewNeeded']) ? intval($_POST['numCrewNeeded']) : 5;
$requiredRoleIds = isset($_POST['requiredRoleIds']) ? explode(',', $_POST['requiredRoleIds']) : [1, 2, 4, 6, 8]; // Changed to comma-separated
$targetSourceIds = isset($_POST['targetSourceIds']) ? $_POST['targetSourceIds'] : [1, 2, 3, 4, 5];
$draftLeader = isset($_POST['draftLeader']) && $_POST['draftLeader'] == '1' ? true : false;

function getSnakeDraftIndex($pick, $numPlayers) {
    $round = floor($pick / $numPlayers);
    $indexInRound = $pick % $numPlayers;
    return $round % 2 === 0 ? $indexInRound : $numPlayers - 1 - $indexInRound;
}

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
function displayTeam($playerName, $team, $fallbackNote = null) {
    echo "<h2>" . htmlspecialchars($playerName) . "'s Team" . ($fallbackNote ? " *" : "") . "</h2>";
    if ($fallbackNote) {
        echo "<p class='fallback-note'>* Could not completely fill required roles: " . htmlspecialchars($fallbackNote) . "</p>";
    }
    if (!empty($team)) {
        echo "<ul>";
        foreach ($team as $member) {
            echo "<li>";
            echo htmlspecialchars($member['crew_name']) . " (ID: " . htmlspecialchars($member['id']);
            if (isset($member['roles']) && is_array($member['roles'])) {
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
        if (isset($crew['id']) && is_array($excludedIds) && in_array($crew['id'], $excludedIds)) {
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

// Function to get the role IDs of a crew member.
function getRoleIds($crewMember) {
    $roleIds = [];
    if (isset($crewMember['roles']) && is_array($crewMember['roles'])) {
        foreach ($crewMember['roles'] as $role) {
            $roleIds[] = $role['id'];
        }
    }
    return $roleIds;
}

try {
    // 1. Fetch crew from the API
    $crewUrl = 'http://firefly.test/api/crew/?sources=';
    if (is_array($targetSourceIds)) {
        $crewUrl .= implode(',', $targetSourceIds);
    } else {
        $crewUrl .= $targetSourceIds;
    }

    $allCrew = fetchData($crewUrl);

    // Error checking after fetching data
    if ($allCrew === false) {
        throw new Exception("Failed to fetch data from the API.");
    }

    $allCrewArray = json_decode($allCrew, true);

    // Error checking after JSON decoding
    if ($allCrewArray === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON response from the API. Error: " . json_last_error_msg());
    } elseif ($allCrewArray === null) {
        $allCrewArray = []; // Initialize as an empty array
    }

    $mainPool = $allCrewArray;

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
    $teams = array_fill(0, $numPlayers, []);
    $drafted = [];
    $leadersDrafted = array_fill(0, $numPlayers, false);
    $requiredRolesFilled = [];
    for ($i = 0; $i < $numPlayers; $i++) {
        $requiredRolesFilled[$i] = array_fill_keys($requiredRoleIds, false);
    }
    $numPicksPerPlayer = $numCrewNeeded; // Adjust total picks based on leader drafting
    if ($draftLeader) {
        $numPicksPerPlayer++;
    }
    $totalPicks = $numPlayers * $numPicksPerPlayer;

    for ($pick = 0; $pick < $totalPicks; $pick++) {
        $currentPlayerIndex = getSnakeDraftIndex($pick, $numPlayers);

        if ($draftLeader && !$leadersDrafted[$currentPlayerIndex] && !empty($leaderPool)) {
            // Leader Drafting
            $randomIndex = array_rand($leaderPool);
            $pickedLeader = $leaderPool[$randomIndex];
            $teams[$currentPlayerIndex][] = $pickedLeader;
            $drafted[] = $pickedLeader['id'];
            $leadersDrafted[$currentPlayerIndex] = true;
            $excludedIdsArray = isset($pickedLeader['exclusions']) ? explode(',', $pickedLeader['exclusions']) : [];
            if (!is_array($excludedIdsArray)) {
                $excludedIdsArray = [];
            }
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
                        $excludedIdsArray = isset($pickedRequired['exclusions']) ? explode(',', $pickedRequired['exclusions']) : [];
                        if (!is_array($excludedIdsArray)) {
                            $excludedIdsArray = [];
                        }
                        applyExclusionsToPool($mainPool, $excludedIdsArray, $pickedRequired['id']);
                        applyExclusionsToPool($leaderPool, $excludedIdsArray, $pickedRequired['id']);
                        applyExclusionsToPool($requiredRolePool, $excludedIdsArray, $pickedRequired['id']);
                        $requiredRolePool = array_filter($requiredRolePool, function ($crew) use ($pickedRequired) {
                            return $crew['id'] !== $pickedRequired['id'];
                        });
                        $requiredRolePool = array_values($requiredRolePool);
                        break; // Only attempt to draft one required role per pick
                    }
                }
            }
        } else {
            // Remaining Crew Drafting
            $availableCrew = array_filter($mainPool, function ($crew) use ($teams, $currentPlayerIndex, $drafted) {
                $isDuplicateRole = false;
                foreach ($teams[$currentPlayerIndex] as $member) {
                    if (isset($crew['roles']) && isset($member['roles']) && is_array($crew['roles']) && is_array($member['roles'])) {
                        $crewRoleIds = getRoleIds($crew);
                        $memberRoleIds = getRoleIds($member);
                        if (!empty(array_intersect($crewRoleIds, $memberRoleIds))) {
                            $isDuplicateRole = true;
                            break;
                        }
                    }
                }
                return !in_array($crew['id'], array_column($teams[$currentPlayerIndex], 'id')) && !in_array($crew['id'], $drafted) && !$isDuplicateRole;
            });

            if (!empty($availableCrew)) {
                $randomIndex = array_rand($availableCrew);
                $pickedRegularCrew = $availableCrew[$randomIndex];
                $teams[$currentPlayerIndex][] = $pickedRegularCrew;
                $drafted[] = $pickedRegularCrew['id'];
                $excludedIdsArray = isset($pickedRegularCrew['exclusions']) ? explode(',', $pickedRegularCrew['exclusions']) : [];
                if (!is_array($excludedIdsArray)) {
                    $excludedIdsArray = [];
                }
                applyExclusionsToPool($mainPool, $excludedIdsArray, $pickedRegularCrew['id']);
                unset($mainPool[$randomIndex]);
                $mainPool = array_values($mainPool);
            }
        }
    }

    // Fallback for unfilled required roles (before displaying results)
    $fallbackNotes = array_fill(0, $numPlayers, null);
    for ($i = 0; $i < $numPlayers; $i++) {
        $unfilledRoles = [];
        foreach ($requiredRoleIds as $roleId) {
            if (!$requiredRolesFilled[$i][$roleId]) {
                // Try to get the role name
                $roleName = "Role ID " . $roleId;
                foreach ($allCrewArray as $crew) {
                    if (isset($crew['roles']) && is_array($crew['roles'])) {
                        foreach ($crew['roles'] as $role) {
                            if ($role['id'] == $roleId) {
                                $roleName = $role['name'];
                                break 2; // Break out of both loops once found
                            }
                        }
                    }
                }
                $unfilledRoles[] = $roleName;

                // Attempt fallback from main pool
                if (!empty($mainPool)) {
                    $foundFallback = false;
                    shuffle($mainPool);
                    foreach ($mainPool as $key => $fallbackCrew) {
                        $hasDuplicateRole = false;
                        if (isset($fallbackCrew['roles']) && is_array($fallbackCrew['roles'])) {
                            $fallbackCrewRoleIds = array_column($fallbackCrew['roles'], 'id');
                            foreach ($teams[$i] as $member) {
                                if (isset($member['roles']) && is_array($member['roles'])) {
                                    $existingRoleIds = array_column($member['roles'], 'id');
                                    if (!empty(array_intersect($fallbackCrewRoleIds, $existingRoleIds))) {
                                        $hasDuplicateRole = true;
                                        break;
                                    }
                                }
                            }
                        }
                        if (!$hasDuplicateRole) {
                            $teams[$i][] = $fallbackCrew;
                            unset($mainPool[$key]);
                            $mainPool = array_values($mainPool);
                            $foundFallback = true;
                            break;
                        }
                    }
                }
            }
        }
        if (!empty($unfilledRoles)) {
            $fallbackNotes[$i] = implode(", ", $unfilledRoles);
        }
    }

    echo "<h1>Final Draft Results</h1>";
    for ($i = 0; $i < $numPlayers; $i++) {
        displayTeam("Player " . ($i + 1), $teams[$i], $fallbackNotes[$i]);
    }

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
