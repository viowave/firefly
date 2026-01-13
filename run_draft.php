<?php
/**
 * Firefly Crew Draft System
 *
 * This script handles the draft logic for assigning crew members to players
 * based on various criteria including required roles, leader preferences,
 * and crew member exclusions. It now also supports drafting ships.
 */

// Configuration class to handle form inputs with validation
class DraftConfig
{
    public $numPlayers;
    public $numCrewNeeded;
    public $requiredRoleIds;
    public $targetSourceIds;
    public $draftLeader;
    public $draftShip; // <-- ADDED: Draft Ship flag
    public $playerNames;

    public function __construct()
    {
        // Sanitize and validate inputs
        $this->numPlayers = isset($_POST['numPlayers']) ? intval($_POST['numPlayers']) : 2;
        $this->numCrewNeeded = isset($_POST['numCrewNeeded']) ? intval($_POST['numCrewNeeded']) : 5;

        // Handle comma-separated values for roles
        $roleInput = isset($_POST['requiredRoleIds']) ? $_POST['requiredRoleIds'] : "";
        $this->requiredRoleIds = $roleInput ? array_map('intval', explode(',', $roleInput)) : [];

        // Handle sources (comma-separated string from the form)
        $sourceInput = isset($_POST['targetSourceIds']) ? $_POST['targetSourceIds'] : "";
        if (is_string($sourceInput) && !empty($sourceInput)) {
            $this->targetSourceIds = array_map('intval', explode(',', $sourceInput));
        } else {
            // Default to all sources if none selected, or a specific set of default IDs
            // You might need to adjust these default IDs based on your actual source IDs
            $this->targetSourceIds = [1, 2, 3, 4, 5, 6, 7]; // Example: Default to all sources if empty
        }

        // Boolean for draft leader option
        $this->draftLeader = isset($_POST['draftLeader']) ? (intval($_POST['draftLeader']) === 1) : false;

        // <-- ADDED: Boolean for draft ship option -->
        $this->draftShip = isset($_POST['draftShip']) ? (intval($_POST['draftShip']) === 1) : false;

        // Get Player Names
        $this->playerNames = [];
        for ($i = 1; $i <= $this->numPlayers; $i++) {
            $playerNameKey = "playerName" . $i;
            if (isset($_POST[$playerNameKey])) {
                $this->playerNames[] = trim($_POST[$playerNameKey]);
            } else {
                $this->playerNames[] = "Player " . $i; // Default if not provided.
            }
        }
    }
}

/**
 * ApiClient handles all API interactions
 */
class ApiClient
{
    private $baseUrl;

    public function __construct($baseUrl = null)
    {
        $env = getenv('APPLICATION_ENV');

        if ($env === 'production') {
            $this->baseUrl = 'http://cheatersnever.win/firefly/api';
        } else {
            $this->baseUrl = $baseUrl ?: 'http://firefly.test/api';
        }
    }

    /**
     * Fetch data from API endpoint
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array Decoded JSON response
     * @throws Exception If API request fails
     */
    public function fetchData($endpoint, $params = [])
    {
        $url = $this->baseUrl . '/' . $endpoint . '/' . (!empty($params) ? '?' . http_build_query($params) : '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("API Error: " . curl_error($ch) . " for URL " . $url);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error " . $httpCode . " for URL " . $url);
        }

        curl_close($ch);
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Error: " . json_last_error_msg());
        }
        return $decoded;
    }

    /**
     * Fetch crew members based on source IDs and apply source exclusions
     *
     * @param array|string $sourceIds Source IDs to filter by
     * @return array Crew members data
     */
    public function fetchCrew($sourceIds)
    {
        $sourcesParam = is_array($sourceIds) ? implode(',', $sourceIds) : $sourceIds;
        $crewData = $this->fetchData('crew', ['sources' => $sourcesParam]);
        $initialCrewCount = count($crewData);
        error_log("DEBUG: Initial crew count after fetching sources: " . $initialCrewCount);

        $sourcesData = $this->fetchData('sources'); // Fetch all source data

        $globalExclusions = [];
        foreach ($sourcesData as $source) {
            // Check if source_id exists before using it
            if (isset($source['source_id']) && in_array($source['source_id'], $sourceIds) && !empty($source['exclusions'])) {
                $globalExclusions = array_merge($globalExclusions, $source['exclusions']);
            }
        }
        // Remove duplicates from the global exclusions array
        $globalExclusions = array_unique($globalExclusions);
        $exclusionCount = count($globalExclusions);
        error_log("DEBUG: Total number of global exclusions from selected sources: " . $exclusionCount);

        $filteredCrew = array_filter($crewData, function ($crew) use ($globalExclusions) {
            return !in_array($crew['id'], $globalExclusions);
        });

        $finalCrewCount = count($filteredCrew);
        error_log("DEBUG: Final crew count after applying source exclusions: " . $finalCrewCount);

        return array_values($filteredCrew); // Re-index the array after filtering
    }

    /**
     * <-- ADDED: Fetch ships based on source IDs -->
     *
     * @param array|string $sourceIds Source IDs to filter by
     * @return array Ships data
     */
    public function fetchShips($sourceIds)
    {
        $sourcesParam = is_array($sourceIds) ? implode(',', $sourceIds) : $sourceIds;
        $shipsData = $this->fetchData('ships', ['sources' => $sourcesParam]);
        return $shipsData;
    }
}

/**
 * CrewMember class represents a single crew member with helper methods
 */
class CrewMember
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the crew member's ID
     *
     * @return int Crew ID
     */
    public function getId()
    {
        return isset($this->data['id']) ? (int)$this->data['id'] : 0;
    }

    /**
     * Check if crew member has a specific role
     *
     * @param int $roleId Role ID to check
     * @return bool True if crew has the role
     */
    public function hasRole($roleId)
    {
        if (!isset($this->data['roles']) || !is_array($this->data['roles'])) {
            return false;
        }

        foreach ($this->data['roles'] as $role) {
            if ($role['id'] == $roleId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all role IDs for this crew member
     *
     * @return array Role IDs
     */
    public function getRoleIds()
    {
        $roleIds = [];

        if (isset($this->data['roles']) && is_array($this->data['roles'])) {
            foreach ($this->data['roles'] as $role) {
                $roleIds[] = (int)$role['id'];
            }
        }

        return $roleIds;
    }

    /**
     * Check if crew member is a leader
     *
     * @return bool True if leader
     */
    public function isLeader()
    {
        return isset($this->data['leader']) && $this->data['leader'] == 1;
    }

    /**
     * Get crew member's exclusions as array of IDs
     *
     * @return array Excluded crew IDs
     */
    public function getExclusions()
    {
        $exclusions = [];

        if (isset($this->data['exclusions']) && !empty($this->data['exclusions'])) {
            $exclusionString = trim($this->data['exclusions']);
            if (!empty($exclusionString)) {
                $exclusions = array_map('intval', explode(',', $exclusionString));
            }
        }

        return $exclusions;
    }

}

class Ship
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the ship's ID
     *
     * @return int Ship ID
     */
    public function getId()
    {
        return isset($this->data['id']) ? (int)$this->data['id'] : 0;
    }

}

/**
 * DraftManager handles the draft logic
 */
class DraftManager
{
    private $config;
    private $mainPool = [];
    private $leaderPool = [];
    private $requiredRolePool = [];
    private $shipPool = []; // <-- ADDED: Ship Pool
    private $teams = [];
    private $draftedCrew = []; // Renamed from $drafted to be specific
    private $draftedShips = []; // <-- ADDED: Track drafted ships
    private $leadersDrafted = [];
    private $shipsDrafted = []; // <-- ADDED: Track if a ship has been drafted by a player
    private $requiredRolesFilled = [];
    private $allCrew = [];
    private $allShips = []; // <-- ADDED: Store all ships
    private $fallbackNotes = [];

    public function __construct(DraftConfig $config, array $allCrew, array $allShips) // <-- MODIFIED: Add allShips
    {
        $this->config = $config;
        $this->allCrew = array_map(function ($crew) {
            return new CrewMember($crew);
        }, $allCrew);
        $this->allShips = array_map(function ($ship) { // <-- ADDED: Map raw ship data to Ship objects
            return new Ship($ship);
        }, $allShips);

        // Initialize team arrays
        $this->teams = [];
        $this->leadersDrafted = array_fill(0, $config->numPlayers, false);
        $this->shipsDrafted = array_fill(0, $config->numPlayers, false); // <-- ADDED: Initialize ship drafted tracking

        // Initialize role tracking
        for ($i = 0; $i < $config->numPlayers; $i++) {
            $this->requiredRolesFilled[$i] = array_fill_keys($config->requiredRoleIds, false);
        }

        $this->fallbackNotes = array_fill(0, $config->numPlayers, null);

        // Initialize teams with player names
        if (is_array($this->config->playerNames) && count($this->config->playerNames) > 0) {
            foreach ($this->config->playerNames as $playerName) {
                $this->teams[] = ['playerName' => $playerName, 'members' => [], 'ship' => null]; // <-- MODIFIED: Add ship slot
            }
        } else {
            for ($i = 0; i < $this->config->numPlayers; $i++) {
                $this->teams[] = ['playerName' => 'Player ' . ($i + 1), 'members' => [], 'ship' => null]; // <-- MODIFIED: Add ship slot
            }
        }
        $this->initializePools();
    }

    /**
     * Split crew and ships into different pools based on properties
     */
    private function initializePools()
    {
        foreach ($this->allCrew as $crew) {
            if ($crew->isLeader()) {
                $this->leaderPool[] = $crew;
            } else {
                $this->mainPool[] = $crew;

                // Check if crew has any required roles
                $hasRequiredRole = false;
                foreach ($this->config->requiredRoleIds as $roleId) {
                    if ($crew->hasRole($roleId)) {
                        $hasRequiredRole = true;
                        break;
                    }
                }

                if ($hasRequiredRole) {
                    $this->requiredRolePool[] = $crew;
                }
            }
        }

        // <-- ADDED: Initialize ship pool -->
        $this->shipPool = $this->allShips;
        shuffle($this->shipPool); // Shuffle ships initially
    }

    /**
     * Get the player index for snake draft
     *
     * @param int $pick Current pick number
     * @param int $numPlayers Number of players
     * @return int Player index
     */
    private function getSnakeDraftIndex($pick, $numPlayers)
    {
        $round = floor($pick / $numPlayers);
        $indexInRound = $pick % $numPlayers;
        return $round % 2 === 0 ? $indexInRound : $numPlayers - 1 - $indexInRound;
    }

    /**
     * Apply exclusions to remove excluded crew from pools
     *
     * @param CrewMember $draftedCrew The crew that was just drafted
     */
    private function applyExclusions(CrewMember $draftedCrew)
    {
        $exclusions = $draftedCrew->getExclusions();
        $draftedId = $draftedCrew->getId();

        // Helper function to filter pools
        $filterPool = function ($pool) use ($exclusions, $draftedId) {
            return array_filter($pool, function ($crew) use ($exclusions, $draftedId) {
                return !in_array($crew->getId(), $exclusions) && $crew->getId() !== $draftedId;
            });
        };

        $this->mainPool = $filterPool($this->mainPool);
        $this->leaderPool = $filterPool($this->leaderPool);
        $this->requiredRolePool = $filterPool($this->requiredRolePool);

        // Track drafted crew
        $this->draftedCrew[] = $draftedId;
    }

    /**
     * Find and draft a crew member with a required role
     *
     * @param int $playerIndex Current player index
     * @return bool True if successful
     */
    private function draftRequiredRole($playerIndex)
    {
        // Check which roles still need to be filled
        foreach ($this->config->requiredRoleIds as $roleId) {
            if (!$this->requiredRolesFilled[$playerIndex][$roleId]) {
                // Find available crew with this role
                $candidates = array_filter($this->requiredRolePool, function ($crew) use ($roleId, $playerIndex) {
                    $isAlreadyOnTeam = false;
                    if (isset($this->teams[$playerIndex]['members'])) { //check if members is set
                        $isAlreadyOnTeam = in_array($crew->getId(), array_map(function ($c) {
                            return $c->getId();
                        }, $this->teams[$playerIndex]['members']));
                    }
                    return $crew->hasRole($roleId) && !in_array($crew->getId(), $this->draftedCrew) && !$isAlreadyOnTeam;
                });

                if (!empty($candidates)) {
                    // Pick random crew from candidates
                    $randomIndex = array_rand($candidates);
                    $pickedCrew = $candidates[$randomIndex];

                    // Add to team
                    $this->teams[$playerIndex]['members'][] = $pickedCrew;
                    $this->requiredRolesFilled[$playerIndex][$roleId] = true;

                    // Handle exclusions
                    $this->applyExclusions($pickedCrew);

                    // Remove from required role pool
                    $this->requiredRolePool = array_values(array_filter($this->requiredRolePool, function ($crew) use ($pickedCrew) {
                        return $crew->getId() !== $pickedCrew->getId();
                    }));

                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Find and draft a leader
     *
     * @param int $playerIndex Current player index
     * @return bool True if successful
     */
    private function draftLeader($playerIndex)
    {
        if (!empty($this->leaderPool)) {
            $randomIndex = array_rand($this->leaderPool);
            $pickedLeader = $this->leaderPool[$randomIndex];

            // Add to team
            $this->teams[$playerIndex]['members'][] = $pickedLeader;
            $this->leadersDrafted[$playerIndex] = true;

            // Handle exclusions
            $this->applyExclusions($pickedLeader);

            // Remove from leader pool
            unset($this->leaderPool[$randomIndex]);
            $this->leaderPool = array_values($this->leaderPool);

            return true;
        }

        return false;
    }

    /**
     * <-- ADDED: Find and draft a ship -->
     *
     * @param int $playerIndex Current player index
     * @return bool True if successful
     */
    private function draftShip($playerIndex)
    {
        if (!empty($this->shipPool)) {
            $randomIndex = array_rand($this->shipPool);
            $pickedShip = $this->shipPool[$randomIndex];

            // Assign ship to team
            $this->teams[$playerIndex]['ship'] = $pickedShip;
            $this->shipsDrafted[$playerIndex] = true;

            // Remove from ship pool (so it can't be drafted again)
            unset($this->shipPool[$randomIndex]);
            $this->shipPool = array_values($this->shipPool);

            return true;
        }
        return false;
    }

    /**
     * Find and draft a regular crew member
     *
     * @param int $playerIndex Current player index
     * @return bool True if successful
     */
    private function draftRegularCrew($playerIndex)
    {
        // Filter available crew that don't duplicate roles
        $availableCrew = array_filter($this->mainPool, function ($crew) use ($playerIndex) {
            // Check if crew is already drafted
            if (in_array($crew->getId(), $this->draftedCrew)) { // Changed from $this->drafted
                return false;
            }

            // Check if crew would duplicate a role on the team
            $crewRoleIds = $crew->getRoleIds();
            $existingRoleIds = [];
            if (isset($this->teams[$playerIndex]['members'])) {
                foreach ($this->teams[$playerIndex]['members'] as $member) {
                    $existingRoleIds = array_merge($existingRoleIds, $member->getRoleIds());
                }
            }

            if (!empty(array_intersect($crewRoleIds, $existingRoleIds))) {
                return false;
            }

            return true;
        });

        if (!empty($availableCrew)) {
            $randomIndex = array_rand($availableCrew);
            $pickedCrew = $availableCrew[$randomIndex];

            // Add to team
            $this->teams[$playerIndex]['members'][] = $pickedCrew;

            // Handle exclusions
            $this->applyExclusions($pickedCrew);

            return true;
        }

        return false;
    }

    /**
     * Run the full draft
     */
    public function runDraft()
    {
        $numPicksPerPlayer = $this->config->numCrewNeeded;

        // Adjust total picks if leaders or ships are to be drafted
        if ($this->config->draftLeader) {
            $numPicksPerPlayer++;
        }
        if ($this->config->draftShip) { // <-- ADDED: Account for ship pick
            $numPicksPerPlayer++;
        }

        $totalPicks = $this->config->numPlayers * $numPicksPerPlayer;

        // Main draft loop
        for ($pick = 0; $pick < $totalPicks; $pick++) {
            $currentPlayerIndex = $this->getSnakeDraftIndex($pick, $this->config->numPlayers);
            $draftSuccess = false;

            // Priority 1: Draft a ship if enabled and not yet drafted by this player
            if ($this->config->draftShip && !$this->shipsDrafted[$currentPlayerIndex]) {
                $draftSuccess = $this->draftShip($currentPlayerIndex);
                if ($draftSuccess) {
                    continue; // Ship drafted, move to next pick
                }
            }

            // Priority 2: Draft a leader if enabled and not yet drafted by this player
            if ($this->config->draftLeader && !$this->leadersDrafted[$currentPlayerIndex]) {
                $draftSuccess = $this->draftLeader($currentPlayerIndex);
                if ($draftSuccess) {
                    continue; // Leader drafted, move to next pick
                }
            }

            // Priority 3: Draft a required role if needed
            if (!$draftSuccess && count($this->config->requiredRoleIds) > 0 && in_array(false, $this->requiredRolesFilled[$currentPlayerIndex])) {
                $draftSuccess = $this->draftRequiredRole($currentPlayerIndex);
                if ($draftSuccess) {
                    continue; // Required role drafted, move to next pick
                }
            }

            // Priority 4: Otherwise, draft a regular crew member
            if (!$draftSuccess) {
                $draftSuccess = $this->draftRegularCrew($currentPlayerIndex);
            }
            // If we couldn't draft anything, that's ok - we'll fill in the gaps later
        }

        // Fill in missing required roles as best as possible
        $this->fillMissingRequiredRoles();
    }

    /**
     * Try to fill missing required roles and record fallback notes
     */
    private function fillMissingRequiredRoles()
    {
        $roleNames = $this->getRoleNames();

        for ($i = 0; $i < $this->config->numPlayers; $i++) {
            $unfilledRoles = [];

            foreach ($this->config->requiredRoleIds as $roleId) {
                if (!$this->requiredRolesFilled[$i][$roleId]) {
                    // Record unfilled role
                    $roleName = isset($roleNames[$roleId]) ? $roleNames[$roleId] : "Role ID " . $roleId;
                    $unfilledRoles[] = $roleName;

                    // Try to find fallback crew
                    $this->tryFallbackForRole($i, $roleId);
                }
            }

            if (!empty($unfilledRoles)) {
                $this->fallbackNotes[$i] = implode(", ", $unfilledRoles);
            }
        }
    }

    /**
     * Attempt to find a fallback crew member for a missing role
     *
     * @param int $playerIndex Current player index
     * @param int $roleId Role ID to fill
     * @return bool True if successful
     */
    private function tryFallbackForRole($playerIndex, $roleId)
    {
        // Filter the main pool to avoid already drafted crew
        $availableFallbackPool = array_filter($this->mainPool, function($crew) {
            return !in_array($crew->getId(), $this->draftedCrew);
        });

        if (empty($availableFallbackPool)) {
            return false;
        }

        // Create a copy and shuffle it for randomness
        shuffle($availableFallbackPool);

        foreach ($availableFallbackPool as $fallbackCrew) {
            // Check if crew would duplicate any roles
            $hasDuplicateRole = false;
            $fallbackRoleIds = $fallbackCrew->getRoleIds();
            $existingRoleIds = [];
            if (isset($this->teams[$playerIndex]['members'])) {
                foreach ($this->teams[$playerIndex]['members'] as $member) {
                    $existingRoleIds = array_merge($existingRoleIds, $member->getRoleIds());
                }
            }


            if (!empty(array_intersect($fallbackRoleIds, $existingRoleIds))) {
                $hasDuplicateRole = true;
            }

            if (!$hasDuplicateRole) {
                // Add to team
                $this->teams[$playerIndex]['members'][] = $fallbackCrew;

                // Handle exclusions
                $this->applyExclusions($fallbackCrew);

                // Mark the role as filled for this team (if it matches the fallback role)
                if (in_array($roleId, $fallbackRoleIds)) {
                    $this->requiredRolesFilled[$playerIndex][$roleId] = true;
                }

                return true;
            }
        }
        return false;
    }


    /**
     * Create a mapping of role IDs to role names
     *
     * @return array Role ID => Role Name mapping
     */
    private function getRoleNames()
    {
        $roleNames = [];
        // To get all role names, we should ideally fetch them from an API or database
        // For simplicity, we'll try to extract them from the available crew.
        // A more robust solution would be to add a fetchRoles method to ApiClient.
        foreach ($this->allCrew as $crew) {
            if (isset($crew->data['roles']) && is_array($crew->data['roles'])) {
                foreach ($crew->data['roles'] as $role) {
                    if (isset($role['id']) && isset($role['name'])) {
                        $roleNames[$role['id']] = $role['name'];
                    }
                }
            }
        }
        return $roleNames;
    }

    /**
     * Get the final teams after draft
     *
     * @return array Teams with crew and ship assignments
     */
    public function getTeams()
    {
        $results = [];

        for ($i = 0; $i < $this->config->numPlayers; $i++) {
            $teamMembers = $this->teams[$i]['members'];
            $leader = null;
            $regularCrew = [];

            // Separate leader from regular crew
            foreach ($teamMembers as $member) {
                $crewMember = new CrewMember($member->data); // Re-instantiate to use isLeader()
                if ($crewMember->isLeader()) {
                    $leader = $member->data;
                } else {
                    $regularCrew[] = $member->data;
                }
            }

            $results[$i] = [
                'playerName' => $this->teams[$i]['playerName'],
                'ship' => $this->teams[$i]['ship'] ? $this->teams[$i]['ship']->data : null,
                'leader' => $leader,       // <-- ADDED: Leader separated
                'regularCrew' => $regularCrew, // <-- ADDED: Regular crew separated
                'fallbackNote' => $this->fallbackNotes[$i]
            ];
        }
        return $results;
    }
}

/**
 * View class handles all HTML output
 */
class DraftView
{
    /**
     * Output draft results as HTML
     *
     * @param array $teams Final team assignments
     */
    public static function displayResults($teams)
    {
        $resultsHTML = '<h1 class="papyrus-font">Final Draft Results</h1>';
        foreach ($teams as $team) {
            $resultsHTML .= '<div class="team-wrapper">';
            $resultsHTML .= self::displayTeam($team);
            $resultsHTML .= '</div>';
            $resultsHTML .= '<div class="results-actions"><button type="button" id="rerunDraftButton" class="action-button">Re-run Draft</button><button type="button" id="newDraftButton" class="action-button" onclick="window.location.reload();">New Draft</button></div>';
        }
        echo $resultsHTML;
    }

    /**
     * Display a single team
     *
     * @param array $teamData Team data including player name, leader, regular crew, and ship
     */
    private static function displayTeam($teamData)
    {
        $playerName = $teamData['playerName'];
        $ship = $teamData['ship'];
        $leader = $teamData['leader'];           
        $regularCrew = $teamData['regularCrew']; 
        $fallbackNote = $teamData['fallbackNote'];

        $teamHTML =  "<h2 class='team-title'>" . htmlspecialchars($playerName) . "'s Team" . ($fallbackNote ? " *" : "") . "</h2>";
        if ($fallbackNote) {
            $teamHTML .= "<p class='fallback-note'>* Could not completely fill required roles: " .
                htmlspecialchars($fallbackNote) . "</p>";
        }

        // --- Display Ship Information ---
        if ($ship) {
            $shipName = htmlspecialchars($ship['ship_name'] ?? 'Unknown Ship');
            $shipImage = !empty($ship['image_full_url']) ? htmlspecialchars($ship['image_full_url']) : "uploads/ships/default_ship.webp";
            $sourceName = htmlspecialchars($ship['source_name'] ?? 'N/A');

            $teamHTML .= '<h3 class="section-title papyrus-font">Ship</h3>';
            $teamHTML .= '<ul class="team-list ship-single">';
            $teamHTML .= '<li class="ship-card card-item">';
            $teamHTML .= '<div class="ship-image-container image-container">';
            $teamHTML .= '<img src="' . $shipImage . '" alt="' . $shipName . '" class="ship-image">';
            $teamHTML .= '</div>';
            $teamHTML .= '<div class="ship-details item-details">';
            $teamHTML .= '<p class="item-source">Source: ' . $sourceName . '</p>';
            $teamHTML .= '</div>';
            $teamHTML .= '</li>';
            $teamHTML .= '</ul>';
        }

        // --- Display Leader Information ---
        if ($leader) {
            $teamHTML .= '<h3 class="section-title papyrus-font">Leader</h3>';
            $teamHTML .= '<ul class="team-list crew-1">';
            $teamHTML .= self::displayCrewMember($leader);
            $teamHTML .= '</ul>';
        }

        // --- Display Regular Crew Information ---
        if (!empty($regularCrew)) {
            $teamHTML .= '<h3 class="section-title papyrus-font">Crew</h3>';
            $crewCount = count($regularCrew);
            $crewClass = '';

            if ($crewCount >= 1 && $crewCount <= 3) {
                $crewClass = ' crew-' . $crewCount;
            } else {
                $crewClass = ' crew-4';
            }

            $teamHTML .= '<ul class="team-list' . $crewClass . '">';
            foreach ($regularCrew as $member) {
                $teamHTML .= self::displayCrewMember($member); // Re-use method to display
            }
            $teamHTML .= '</ul>';
        } else {
            $teamHTML .= "<p>No other crew members drafted.</p>";
        }
        return $teamHTML;
    }

    /**
     * Helper to display a single crew member card (for reuse)
     *
     * @param array $member Crew member data
     * @return string HTML for crew member card
     */
    private static function displayCrewMember($member) {
        $memberHTML = '<li class="team-member card-item">';

        $memberHTML .= '<div class="member-image-container image-container">';
        $imageSrc = !empty($member['image_full_url']) ? htmlspecialchars($member['image_full_url']) : "uploads/crew/" . htmlspecialchars($member['image_url'] ?? 'default_crew.webp');
        $memberHTML .= '<img src="' . $imageSrc . '" alt="' . htmlspecialchars($member['crew_name']) . '" class="member-image">';
        $memberHTML .= '</div>';

        $memberHTML .= '<div class="member-details item-details">';

        if (isset($member['planet_name']) && !empty($member['planet_name'])) {
            $memberHTML .= "<p class='item-detail'>Planet: " . htmlspecialchars($member['planet_name']) . "</p>";
        }

        if (isset($member['source_name']) && !empty($member['source_name'])) {
            $memberHTML .= "<p class='item-detail'>Source: " . htmlspecialchars($member['source_name']) . "</p>";
        }

        $memberHTML .= '</div>';
        $memberHTML .= '</li>';
        return $memberHTML;
    }


    /**
     * Display an error message
     *
     * @param string $message Error message
     */
    public static function displayError($message)
    {
        echo "<div class='error'>";
        echo "<h2>Error</h2>";
        echo "<p>" . htmlspecialchars($message) . "</p>";
        echo "</div>";
    }
}

// Main execution
try {
    // Initialize configuration from form data
    $config = new DraftConfig();

    // Initialize API client
    $apiClient = new ApiClient();

    // Fetch crew data
    $allCrew = $apiClient->fetchCrew($config->targetSourceIds);

    // <-- ADDED: Fetch ship data -->
    $allShips = [];
    if ($config->draftShip) { // Only fetch ships if the option is enabled
        $allShips = $apiClient->fetchShips($config->targetSourceIds);
    }

    // Run the draft
    $draftManager = new DraftManager($config, $allCrew, $allShips); // <-- MODIFIED: Pass allShips
    $draftManager->runDraft();

    // Get and display results
    $teams = $draftManager->getTeams();
    DraftView::displayResults($teams);
} catch (Exception $e) {
    DraftView::displayError($e->getMessage());
}
?>