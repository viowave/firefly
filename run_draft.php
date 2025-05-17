<?php
/**
 * Firefly Crew Draft System
 *
 * This script handles the draft logic for assigning crew members to players
 * based on various criteria including required roles, leader preferences,
 * and crew member exclusions.
 */

// Include the database connection (if needed - check if your draft logic requires it)
// require_once 'bridge/includes/db.php';  <--  Uncomment this if your original script uses it

// Configuration class to handle form inputs with validation
class DraftConfig
{
    public $numPlayers;
    public $numCrewNeeded;
    public $requiredRoleIds;
    public $targetSourceIds;
    public $draftLeader;
    public $playerNames; // Added playerNames

    public function __construct()
    {
        // Sanitize and validate inputs
        $this->numPlayers = isset($_POST['numPlayers']) ? intval($_POST['numPlayers']) : 2;
        $this->numCrewNeeded = isset($_POST['numCrewNeeded']) ? intval($_POST['numCrewNeeded']) : 5;
    
        // Handle comma-separated values for roles
        $roleInput = isset($_POST['requiredRoleIds']) ? $_POST['requiredRoleIds'] : ""; // Declare and assign $roleInput
        $this->requiredRoleIds = $roleInput ? array_map('intval', explode(',', $roleInput)) : [];
    
        // Handle sources (comma-separated string from the form)
        $sourceInput = isset($_POST['targetSourceIds']) ? $_POST['targetSourceIds'] : "";
        if (is_string($sourceInput) && !empty($sourceInput)) {
            $this->targetSourceIds = array_map('intval', explode(',', $sourceInput));
        } else {
            $this->targetSourceIds = [1, 2, 3, 4, 5]; // Default
        }
    
        // Boolean for draft leader option
        $this->draftLeader = isset($_POST['draftLeader']) ? (intval($_POST['draftLeader']) === 1) : false;
    
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

    public function __construct($baseUrl = null) // Make the default null
    {
        $env = getenv('APPLICATION_ENV');

        if ($env === 'production') {
            $this->baseUrl = 'http://cheatersnever.win/firefly/api'; // Replace with your production API URL
        } else {
            $this->baseUrl = $baseUrl ?: 'http://firefly.test/api'; // Use provided $baseUrl or default to dev
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
     * Fetch crew members based on source IDs
     *
     * @param array|string $sourceIds Source IDs to filter by
     * @return array Crew members data
     */
    public function fetchCrew($sourceIds)
    {
        $sources = is_array($sourceIds) ? implode(',', $sourceIds) : $sourceIds;
        return $this->fetchData('crew', ['sources' => $sources]);
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

    /**
     * Get the crew member's name
     *
     * @return string Crew Name
     */
    public function getName()
    {
        return isset($this->data['crew_name']) ? $this->data['crew_name'] : '';
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
    private $teams = [];
    private $drafted = [];
    private $leadersDrafted = [];
    private $requiredRolesFilled = [];
    private $allCrew = [];
    private $fallbackNotes = [];

    public function __construct(DraftConfig $config, array $allCrew)
    {
        $this->config = $config;
        $this->allCrew = array_map(function ($crew) {
            return new CrewMember($crew);
        }, $allCrew);

        // Initialize team arrays
        $this->teams = [];
        $this->leadersDrafted = array_fill(0, $config->numPlayers, false);

        // Initialize role tracking
        for ($i = 0; $i < $config->numPlayers; $i++) {
            $this->requiredRolesFilled[$i] = array_fill_keys($config->requiredRoleIds, false);
        }

        $this->fallbackNotes = array_fill(0, $config->numPlayers, null);

        // Initialize teams with player names
        if (is_array($this->config->playerNames) && count($this->config->playerNames) > 0) {
            foreach ($this->config->playerNames as $playerName) {
                $this->teams[] = ['playerName' => $playerName, 'members' => []];
            }
        } else {
            for ($i = 0; $i < $this->config->numPlayers; $i++) {
                $this->teams[] = ['playerName' => 'Player ' . ($i + 1), 'members' => []];
            }
        }
        $this->initializePools();
    }

    /**
     * Split crew into different pools based on properties
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
        $this->drafted[] = $draftedId;
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
                    return $crew->hasRole($roleId) && !in_array($crew->getId(), $this->drafted) && !$isAlreadyOnTeam;
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
            if (in_array($crew->getId(), $this->drafted)) {
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
        if ($this->config->draftLeader) {
            $numPicksPerPlayer++;
        }
    
        $totalPicks = $this->config->numPlayers * $numPicksPerPlayer;
    
        // Main draft loop
        for ($pick = 0; $pick < $totalPicks; $pick++) {
            $currentPlayerIndex = $this->getSnakeDraftIndex($pick, $this->config->numPlayers);
            $draftSuccess = false;
    
            // Try to draft a leader if needed
            if ($this->config->draftLeader && !$this->leadersDrafted[$currentPlayerIndex]) {
                $draftSuccess = $this->draftLeader($currentPlayerIndex);
            }
    
            // Try to draft a required role if needed
            if (!$draftSuccess && in_array(false, $this->requiredRolesFilled[$currentPlayerIndex])) {
                $draftSuccess = $this->draftRequiredRole($currentPlayerIndex);
            }
    
            // Otherwise draft a regular crew member
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
        if (empty($this->mainPool)) {
            return false;
        }

        // Create a copy and shuffle it for randomness
        $fallbackPool = $this->mainPool;
        shuffle($fallbackPool);

        foreach ($fallbackPool as $key => $fallbackCrew) {
            // Check if crew would duplicate any roles
            $hasDuplicateRole = false;
            $fallbackRoleIds = $fallbackCrew->getRoleIds();
            $existingRoleIds = [];
            if (isset($this->teams[$playerIndex]['members'])) {
                foreach ($this->teams[$playerIndex]['members'] as $member) {
                    $existingRoleIds = array_merge($existingRoleIds, $member->getRoleIds());
                }
            }


            if (!$hasDuplicateRole) {
                // Add to team
                $this->teams[$playerIndex]['members'][] = $fallbackCrew;

                // Handle exclusions
                $this->applyExclusions($fallbackCrew);

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
     * @return array Teams with crew assignments
     */
    public function getTeams()
    {
        $results = [];

        for ($i = 0; $i < $this->config->numPlayers; $i++) {
            $results[$i] = [
                'playerName' => $this->teams[$i]['playerName'], //get player name
                'members' => array_map(function ($crew) {
                    return $crew->data;
                }, $this->teams[$i]['members']),
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
        foreach ($teams as $team) { // Changed from $index => $team
            $resultsHTML .= self::displayTeam($team['playerName'], $team['members'], $team['fallbackNote']); //append team html to the results
        }
        echo $resultsHTML; // Echo out the whole thing.
    }

    /**
     * Display a single team
     *
     * @param string $playerName Player name
     * @param array $team Team members
     * @param string|null $fallbackNote Note about unfilled roles
     */
    private static function displayTeam($playerName, $team, $fallbackNote = null)
    {
        $teamHTML =  "<h2 class='team-title'>" . htmlspecialchars($playerName) . "'s Team" . ($fallbackNote ? " *" : "") . "</h2>"; //start building team html
        if ($fallbackNote) {
            $teamHTML .= "<p class='fallback-note'>* Could not completely fill required roles: " .
                htmlspecialchars($fallbackNote) . "</p>";
        }
        
        if (!empty($team)) {
            $crewCount = count($team);
            $crewClass = '';
    
            if ($crewCount >= 1 && $crewCount <= 3) {
                $crewClass = ' crew-' . $crewCount;
            } else {
                $crewClass = ' crew-4';
            }
    
            $teamHTML .= '<ul class="team-list' . $crewClass . '">';
            foreach ($team as $member) {
                $teamHTML .= '<li class="team-member">';
                
                // Card
                $teamHTML .= '<div class="member-image-container">';
                $imagePath = !empty($member['image_url']) ? "uploads/crew/" . htmlspecialchars($member['image_url']) : "uploads/crew/4_Bridgit.webp";
                $teamHTML .= '<img src="' . $imagePath . '" alt="' . htmlspecialchars($member['crew_name']) . '" class="member-image">';
                $teamHTML .= '</div>';

                if (isset($member['planet_name'])) {
                    $teamHTML .= "<span>Planet: " . htmlspecialchars($member['planet_name']) . "</span>";
                }

                if (isset($member['source_name'])) {
                    $teamHTML .= "<span>Source: " . htmlspecialchars($member['source_name']) . "</span>";
                }

                $teamHTML .= '</li>';
            }
            $teamHTML .= '</ul>';
        } else {
            $teamHTML .= "<p>No team members selected yet.</p>";
        }
        return $teamHTML; //return the team HTML
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

    // Run the draft
    $draftManager = new DraftManager($config, $allCrew);
    $draftManager->runDraft();

    // Get and display results
    $teams = $draftManager->getTeams();
    DraftView::displayResults($teams);  //changed to class method call
} catch (Exception $e) {
    DraftView::displayError($e->getMessage()); //changed to class method call
}
?>