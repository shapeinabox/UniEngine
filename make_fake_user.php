<?php

define('INSIDE', true);

$_EnginePath = './';

include($_EnginePath . 'common.php');
include_once($_EnginePath . 'modules/session/_includes.php');
include_once($_EnginePath . 'modules/registration/_includes.php');
include_once($_EnginePath . 'includes/functions/CreateOnePlanetRecord.php');
include_once($_EnginePath . 'includes/functions/CreateOneMoonRecord.php');

use UniEngine\Engine\Modules\Session;
use UniEngine\Engine\Modules\Registration;

$isAuthorised = false;

if (
    !empty(AUTOTOOL_STATBUILDER_PASSWORDHASH) &&
    !empty($_GET['pass']) &&
    md5($_GET['pass']) == AUTOTOOL_STATBUILDER_PASSWORDHASH
) {
    $isAuthorised = true;
}

if (!$isAuthorised) {
    AdminMessage("not authorized", "Error");

    die();
}

function makeFakeUser()
{
    $Now = time();

    $NewUserData = createUserWithPlanet($Now);
    $UserID = $NewUserData['userID'];
    $PlanetID = $NewUserData['planetID'];

    setUserTechLevels($UserID);

    // Randomly if user is Miner or Fleeter
    if (rand(0, 1) == 1) {
        makeMiner($UserID, $PlanetID);
    } else {
        makeFleeter($UserID, $PlanetID);
    }

    echo "User created with ID: " . $UserID . " and planet userName: " . $NewUserData['username'];
}

function calculateDefenses($minLasers, $maxLasers)
{
    $lightLasers = rand($minLasers, $maxLasers);


    $defenses = [
        "401" => floor($lightLasers / 100) * 500,
        "402" => floor($lightLasers),
        "403" => floor($lightLasers / 100) * 20,
        "404" => floor($lightLasers / 100) * 4,
        "405" => floor($lightLasers / 100) * 8,
        "406" => floor($lightLasers / 100) * 2,
        "502" => rand(1000000, 5000000)
    ];

    return $defenses;
}

function createUserWithPlanet($time)
{
    // Generate random password for fake user
    $passwordHash = Session\Utils\LocalIdentityV1\hashPassword([
        'password' => bin2hex(random_bytes(16)),
    ]);

    $Username = "Bot_" . bin2hex(random_bytes(4));

    // Generate random username for fake user and infos
    $newUser = Registration\Utils\Queries\insertNewUser([
        'username' => $Username,
        'passwordHash' => $passwordHash,
        'langCode' => "en-GB",
        'email' => "bot_" . bin2hex(random_bytes(4)) . "@example.com",
        'registrationIP' => "0.0.0.0",
        'currentTimestamp' => $time,
    ]);

    $newPlanetCoordinates = Registration\Utils\Galaxy\findNewPlanetPosition([
        'preferredGalaxy' => 1
    ]);
    // $newPlanetCoordinates could return an error code of 'GALAXY_TOO_CROWDED'

    $planets = createPlanetWithMoon($newPlanetCoordinates, $newUser['userId']);

    Registration\Utils\Queries\incrementUsersCounterInGameConfig();
    // Update User with new data
    Registration\Utils\Queries\updateUserFinalDetails([
        'userId' => $newUser['userId'],
        'motherPlanetId' => $planets['planetID'],
        'motherPlanetGalaxy' => $newPlanetCoordinates['galaxy'],
        'motherPlanetSystem' => $newPlanetCoordinates['system'],
        'motherPlanetPlanetPos' => $newPlanetCoordinates['planet'],
        'referrerId' => null,
        'activationCode' => null
    ]);

    // Set user auth level to 50 (GO)
    $QryUpdateUser = "UPDATE {{table}} SET ";
    $QryUpdateUser .= "`authlevel` = 50, NoobProtection_EndTime = " . $time;
    $QryUpdateUser .= " WHERE `id` = '{$newUser['userId']}';";
    doquery($QryUpdateUser, 'users');

    return [
        "newUser" => $newUser,
        "username" => $Username,
        'userID' => $newUser['userId'],
        'planetID' => $planets['planetID'],
        'moonID' => $planets['moonID']
    ];
}

function createPlanetWithMoon($newPlanetCoordinates, $UserID)
{
    global $_Lang, $_EnginePath;


    $PlanetData = CreateOnePlanetRecord(
        $newPlanetCoordinates['galaxy'],
        $newPlanetCoordinates['system'],
        $newPlanetCoordinates['planet'],
        $UserID,
        $_Lang['MotherPlanet'],
        true,
        null,
        true
    );

    $PlanetID = $PlanetData['ID'];
    $MoonID = null;

    // Randomly also create a moon at the same coordinates
    if (rand(0, 3) == 1) {


        $MoonID = CreateOneMoonRecord([
            'coordinates' => $newPlanetCoordinates,
            'ownerID' => $UserID,
            'moonName' => $_Lang['MotherMoon'],
            'moonCreationChance' => rand(10, 20),
            'fixedDiameter' => null
        ]);
    }

    return [
        "planetID" => $PlanetID,
        "moonID" => $MoonID
    ];
}

function setUserTechLevels($UserID)
{
    global $_Vars_GameElements;

    $researches = [
        "106" => rand(15, 25),
        "108" => rand(12, 22),
        "109" => rand(14, 25),
        "110" => rand(14, 25),
        "111" => rand(14, 25),
        "113" => rand(12, 22),
        "114" => rand(8, 14),
        "115" => rand(14, 24),
        "117" => rand(12, 22),
        "118" => rand(12, 22),
        "120" => rand(12, 22),
        "121" => rand(12, 22),
        "122" => rand(12, 22),
        "123" => rand(1, 6),
        "124" => rand(12, 24),
        "125" => rand(1, 10),
        "126" => rand(2, 5),
    ];

    $QryUpdate = "UPDATE {{table}} SET ";
    foreach ($researches as $researchId => $researchLevel) {
        $QryUpdate .= "`" . $_Vars_GameElements[$researchId] . "` = {$researchLevel}, ";
    }
    $QryUpdate = rtrim($QryUpdate, ", ");
    $QryUpdate .= " WHERE `id` = '{$UserID}';";
    doquery($QryUpdate, 'users');
}

function makeMiner($UserID, $MotherPlanetID)
{
    $planetsCount = rand(1, 8);

    // For each planet count - 1, create a new planet
    $planetsIDs = [$MotherPlanetID];
    for ($i = 0; $i < $planetsCount - 2; $i++) {
        $newPlanetCoordinates = Registration\Utils\Galaxy\findNewPlanetPosition([
            'preferredGalaxy' => 1
        ]);
        $newPlanet = createPlanetWithMoon($newPlanetCoordinates, $UserID);
        $planetsIDs[] = $newPlanet['planetID'];
    }

    // For each planetid, generate buildings fleet and defenses
    foreach ($planetsIDs as $PlanetID) {
        $buildings = [
            "1" => rand(45, 52),
            "2" => rand(42, 50),
            "3" => rand(42, 49),
            "4" => rand(48, 58),
            "14" => rand(10, 20),
            "15" => rand(8, 20),
            "21" => rand(12, 25),
            "22" => rand(21, 25),
            "23" => rand(20, 24),
            "24" => rand(19, 23),
            "31" => rand(10, 20),
            "44" => rand(8, 18),
        ];
        $defenses = calculateDefenses(1000, 1000000);

        updatePlanetInfo($PlanetID, $buildings, [], $defenses);
    }
}

function makeFleeter($UserID, $MotherPlanetID)
{

    $planetsCount = rand(1, 4);

    // For each planet count - 1, create a new planet
    $planetsIDs = [$MotherPlanetID];
    for ($i = 0; $i < $planetsCount - 2; $i++) {
        $newPlanetCoordinates = Registration\Utils\Galaxy\findNewPlanetPosition([
            'preferredGalaxy' => 1
        ]);
        $newPlanet = createPlanetWithMoon($newPlanetCoordinates, $UserID);
        $planetsIDs[] = $newPlanet['planetID'];
    }

    // For each planetid, generate buildings fleet and defenses
    foreach ($planetsIDs as $PlanetID) {
        $buildings = [
            "1" => rand(38, 45),
            "2" => rand(36, 42),
            "3" => rand(38, 44),
            "4" => rand(42, 45),
            "14" => rand(10, 20),
            "15" => rand(8, 20),
            "21" => rand(12, 25),
            "22" => rand(21, 25),
            "23" => rand(20, 24),
            "24" => rand(19, 23),
            "31" => rand(10, 20),
            "44" => rand(8, 18),
        ];
        $fleet = [
            "204" => rand(0, 10000000),
            "205" => rand(0, 6000000),
            "206" => rand(0, 4000000),
            "207" => rand(0, 2000000),
            "210" => rand(0, 1000000),
            "213" => rand(0, 500000),
            "214" => rand(0, 1000),
            "215" => rand(0, 2000000),
            "216" => rand(0, 100),
            "218" => rand(0, 10000),
            "220" => rand(0, 10000),
            "221" => rand(0, 10000),
            "222" => rand(0, 10000),
            "223" => rand(0, 10000),
            "224" => rand(0, 5000),
        ];
        $defenses = calculateDefenses(1000, 100000);

        updatePlanetInfo($PlanetID, $buildings, $fleet, $defenses);
    }
}

function updatePlanetInfo($PlanetID, $buildings, $fleet, $defenses)
{
    global $_Vars_GameElements;

    $QryUpdatePlanet = "UPDATE {{table}} SET ";
    foreach ($buildings as $buildingId => $buildingLevel) {
        $QryUpdatePlanet .= "`" . $_Vars_GameElements[$buildingId] . "` = {$buildingLevel}, ";
    }
    foreach ($fleet as $fleetId => $fleetCount) {
        $QryUpdatePlanet .= "`" . $_Vars_GameElements[$fleetId] . "` = {$fleetCount}, ";
    }
    foreach ($defenses as $defenseId => $defenseCount) {
        $QryUpdatePlanet .= "`" . $_Vars_GameElements[$defenseId] . "` = {$defenseCount}, ";
    }
    $QryUpdatePlanet = rtrim($QryUpdatePlanet, ", ");
    $QryUpdatePlanet .= " WHERE `id` = '{$PlanetID}';";
    doquery($QryUpdatePlanet, 'planets');
    $currentPlanet = _fetchPlanetData($PlanetID);
    $workPercents = array(
        "metal_mine_workpercent" => array(
            "old" => "9",
            "new" => 10
        ),
        "crystal_mine_workpercent" => array(
            "old" => "9",
            "new" => 10
        ),
        "deuterium_synthesizer_workpercent" => array(
            "old" => "9",
            "new" => 10
        ),
        "solar_plant_workpercent" => array(
            "old" => "9",
            "new" => 10
        ),
        "fusion_reactor_workpercent" => array(
            "old" => "9",
            "new" => 10
        ),
        "solar_satellite_workpercent" => array(
            "old" => "9",
            "new" => 10
        )
    );
    _recalculateHourlyProductionLevels(
        $workPercents,
        $currentPlanet,
        $insertNewUserResult,
        [
            'start' => $currentPlanet['last_update'],
            'end' => time()
        ]
    );
    $HPQ_PlanetUpdatedFields = array_unique(["metal_perhour", "crystal_perhour", "deuterium_perhour", "energy_max", "energy_used", "last_update"]);
    foreach ($HPQ_PlanetUpdatedFields as $Value) {
        $Query_Update_Arr[] = "`{$Value}` = '{$currentPlanet[$Value]}'";
    }
    $Query_Update = "UPDATE {{table}} SET " . implode(', ', $Query_Update_Arr) . " WHERE `id` = {$currentPlanet['id']} LIMIT 1;";
    doquery($Query_Update, 'planets');
}


makeFakeUser();
