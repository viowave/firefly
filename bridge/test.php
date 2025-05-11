<?php
require 'includes/db.php';

$csvFile = 'Book2.csv'; // Change this to the actual file path

if (!file_exists($csvFile)) {
    die("CSV file not found.");
}

$handle = fopen($csvFile, 'r');
if ($handle === false) {
    die("Error opening the CSV file.");
}

// Skip the header row
fgetcsv($handle);

while (($row = fgetcsv($handle)) !== false) {
    list($source, $name, $type, $cost, $description, $fight, $tech, $talk, $moral, $wanted,
        $companion, $lawman, $merc, $grifter, $hill_folk, $mechanic, $medic, $mudder, $pilot, $soldier,
        $corp, $synthetic, $tong, $fake_id, $fancy_duds, $hacking_rig, $transport, $shuttle, $firearm, 
        $sniper_rifle, $explosives, $space_bazaar, $persephone, $silverhold, $regina, $osiris, $meridian, 
        $beaumonde, $yctts, $count) = $row;

    // Assign a planet (ensure only one is selected per crew member)
    $planetId = NULL;
    $planetColumns = ['Space Bazaar' => $space_bazaar, 'Persephone' => $persephone, 'Silverhold' => $silverhold,
                      'Regina' => $regina, 'Osiris' => $osiris, 'Meridian' => $meridian, 'Beaumonde' => $beaumonde];
    
    foreach ($planetColumns as $planet => $value) {
        if (trim($value) === '1') {
            $stmt = $pdo->prepare("SELECT id FROM planets WHERE name = ?");
            $stmt->execute([$planet]);
            $planetId = $stmt->fetchColumn();
            if ($planetId) {
                break; // Assign the first found planet
            }
        }
    }

    // Default fallback if no planet is found
    if (!$planetId) {
        $planetId = 8;
    }

    // Debugging output
    echo "Name: $name, Planet: " . ($planetId ? $planetId : 'Not Found') . "<br>";
}

fclose($handle);
echo "✅ Debugging complete!";
?>
