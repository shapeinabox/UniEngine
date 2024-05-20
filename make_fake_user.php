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
    $powerLevel = rand(1, 10);
    if (rand(0, 1) == 1) {
        makeMiner($UserID, $PlanetID, $powerLevel);
    } else {
        makeFleeter($UserID, $PlanetID, $powerLevel);
    }

    echo "User created with ID: " . $UserID . " and userName: " . $NewUserData['username'] . ". With power level " . $powerLevel;
}

function calculateDefenses($minLasers, $maxLasers, $powerLevel)
{
    $lightLasers = rand($minLasers, $maxLasers);

    $defenses = [
        "401" => floor($lightLasers / 100) * 500,
        "402" => floor($lightLasers),
        "403" => floor($lightLasers / 100) * 20,
        "404" => floor($lightLasers / 100) * 4,
        "405" => floor($lightLasers / 100) * 8,
        "406" => floor($lightLasers / 100) * 2,
        "502" => rand(500 * $powerLevel, 50000 * $powerLevel)
    ];

    return $defenses;
}

function createUserWithPlanet($time)
{
    // Generate random password for fake user
    $passwordHash = Session\Utils\LocalIdentityV1\hashPassword([
        'password' => bin2hex(random_bytes(16)),
    ]);


    $usernameCategory = rand(1, 3);
    if ($usernameCategory == 1) {
        $Username = generateAlienName();
    } else {
        $Username = generateDragonkinName(rand(1, 2));
    }
    $Username .= " (f)";

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

    $planets = createPlanetWithMoon($newPlanetCoordinates, $newUser['userId'], true);

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

    // Disable noob protection and set first login time to make user attackable
    $QryUpdateUser = "UPDATE {{table}} SET ";
    $QryUpdateUser .= "`NoobProtection_EndTime` = " . $time . ", `first_login` = " . $time;
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

function createPlanetWithMoon($newPlanetCoordinates, $UserID, $isMotherPlanet = false)
{
    global $_Lang;


    $PlanetData = CreateOnePlanetRecord(
        $newPlanetCoordinates['galaxy'],
        $newPlanetCoordinates['system'],
        $newPlanetCoordinates['planet'],
        $UserID,
        $isMotherPlanet ? "Mother Planet" : generatePlanetName(),
        true,
        null,
        true
    );

    $PlanetID = $PlanetData['ID'];
    $MoonID = null;

    // Randomly also create a moon at the same coordinates
    if (rand(0, 1) == 1) {


        $MoonID = CreateOneMoonRecord([
            'coordinates' => $newPlanetCoordinates,
            'ownerID' => $UserID,
            'moonName' => generatePlanetName(),
            'moonCreationChance' => rand(5, 15),
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

function makeMiner($UserID, $MotherPlanetID, $powerLevel)
{
    $planetsCount = rand(1, 8);

    if ($powerLevel > 10) {
        $powerLevel = 10;
    }

    // For each planet count - 1, create a new planet
    $planetsIDs = [$MotherPlanetID];
    for ($i = 0; $i < $planetsCount - 2; $i++) {
        $newPlanetCoordinates = Registration\Utils\Galaxy\findNewPlanetPosition([
            'preferredGalaxy' => 1
        ]);
        $newPlanet = createPlanetWithMoon($newPlanetCoordinates, $UserID);
        $planetsIDs[] = $newPlanet['planetID'];
    }

    foreach ($planetsIDs as $PlanetID) {
        $buildings = [
            "1" => rand(34, 42) + $powerLevel,
            "2" => rand(32, 40) + $powerLevel,
            "3" => rand(33, 42) + $powerLevel,
            "4" => rand(34, 44) + $powerLevel,
            "14" => rand(10, 20),
            "15" => rand(8, 20),
            "21" => rand(12, 25),
            "22" => rand(21, 25),
            "23" => rand(20, 24),
            "24" => rand(19, 23),
            "31" => rand(10, 20),
            "44" => rand(8, 18),
        ];
        $defenses = calculateDefenses(10 * $powerLevel, 10000 * $powerLevel, min($powerLevel, 7));

        updatePlanetInfo($PlanetID, $buildings, [], $defenses);
    }
}

function makeFleeter($UserID, $MotherPlanetID, $powerLevel)
{
    $planetsCount = rand(1, 5);
    if ($powerLevel > 10) {
        $powerLevel = 10;
    }

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
            "1" => rand(30, 38) + $powerLevel,
            "2" => rand(28, 36) + $powerLevel,
            "3" => rand(27, 33) + $powerLevel,
            "4" => rand(30, 38) + $powerLevel,
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
            "204" => rand(0, 3) > 1 ? rand(0, 1000000 * $powerLevel) : 0,
            "205" => rand(0, 3) > 1 ? rand(0, 600000 * $powerLevel) : 0,
            "206" => rand(0, 3) > 1 ? rand(0, 400000 * $powerLevel) : 0,
            "207" => rand(0, 3) > 1 ? rand(0, 200000 * $powerLevel) : 0,
            "210" => rand(0, 3) > 1 ? rand(0, 100000 * $powerLevel) : 0,
            "213" => rand(0, 3) > 1 ? rand(0, 50000 * $powerLevel) : 0,
            "214" => rand(0, 3) > 1 ? rand(0, 100 * $powerLevel) : 0,
            "215" => rand(0, 3) > 1 ? rand(0, 200000 * $powerLevel) : 0,
            "216" => rand(0, 3) > 1 ? rand(0, 10 * $powerLevel) : 0,
            "218" => rand(0, 3) > 1 ? rand(0, 1000 * $powerLevel) : 0,
            "220" => rand(0, 3) > 1 ? rand(0, 1000 * $powerLevel) : 0,
            "221" => rand(0, 3) > 1 ? rand(0, 1000 * $powerLevel) : 0,
            "222" => rand(0, 3) > 1 ? rand(0, 1000 * $powerLevel) : 0,
            "223" => rand(0, 3) > 1 ? rand(0, 1000 * $powerLevel) : 0,
            "224" => rand(0, 3) > 1 ? rand(0, 500 * $powerLevel) : 0,
        ];
        $defenses = calculateDefenses(1 * $powerLevel, 1000 * $powerLevel, min($powerLevel, 5));

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

function generatePlanetName()
{
    $nm1 = ["b", "c", "ch", "d", "g", "h", "k", "l", "m", "n", "p", "r", "s", "t", "th", "v", "x", "y", "z", "", "", "", "", ""];
    $nm2 = ["a", "e", "i", "o", "u"];
    $nm3 = ["b", "bb", "br", "c", "cc", "ch", "cr", "d", "dr", "g", "gn", "gr", "l", "ll", "lr", "lm", "ln", "lv", "m", "n", "nd", "ng", "nk", "nn", "nr", "nv", "nz", "ph", "s", "str", "th", "tr", "v", "z"];
    $nm3b = ["b", "br", "c", "ch", "cr", "d", "dr", "g", "gn", "gr", "l", "ll", "m", "n", "ph", "s", "str", "th", "tr", "v", "z"];
    $nm4 = ["a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "ae", "ai", "ao", "au", "a", "ea", "ei", "eo", "eu", "e", "ua", "ue", "ui", "u", "ia", "ie", "iu", "io", "oa", "ou", "oi", "o"];
    $nm5 = ["turn", "ter", "nus", "rus", "tania", "hiri", "hines", "gawa", "nides", "carro", "rilia", "stea", "lia", "lea", "ria", "nov", "phus", "mia", "nerth", "wei", "ruta", "tov", "zuno", "vis", "lara", "nia", "liv", "tera", "gantu", "yama", "tune", "ter", "nus", "cury", "bos", "pra", "thea", "nope", "tis", "clite"];
    $nm6 = ["una", "ion", "iea", "iri", "illes", "ides", "agua", "olla", "inda", "eshan", "oria", "ilia", "erth", "arth", "orth", "oth", "illon", "ichi", "ov", "arvis", "ara", "ars", "yke", "yria", "onoe", "ippe", "osie", "one", "ore", "ade", "adus", "urn", "ypso", "ora", "iuq", "orix", "apus", "ion", "eon", "eron", "ao", "omia"];
    $nm7 = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];

    $i = rand(0, 9);

    if ($i < 2) {
        do {
            $rnd = array_rand($nm1);
            $rnd2 = array_rand($nm2);
            $rnd3 = array_rand($nm3);
        } while ($nm1[$rnd] === $nm3[$rnd3]);

        $rnd4 = array_rand($nm4);
        $rnd5 = array_rand($nm5);
        $name = $nm1[$rnd] . $nm2[$rnd2] . $nm3[$rnd3] . $nm4[$rnd4] . $nm5[$rnd5];
    } elseif ($i < 4) {
        do {
            $rnd = array_rand($nm1);
            $rnd2 = array_rand($nm2);
            $rnd3 = array_rand($nm3);
        } while ($nm1[$rnd] === $nm3[$rnd3]);

        $rnd4 = array_rand($nm6);
        $name = $nm1[$rnd] . $nm2[$rnd2] . $nm3[$rnd3] . $nm6[$rnd4];
    } elseif ($i < 6) {
        $rnd = array_rand($nm1);
        $rnd4 = array_rand($nm4);
        $rnd5 = array_rand($nm5);
        $name = $nm1[$rnd] . $nm4[$rnd4] . $nm5[$rnd5];
    } elseif ($i < 8) {
        do {
            $rnd = array_rand($nm1);
            $rnd2 = array_rand($nm2);
            $rnd3 = array_rand($nm3b);
        } while ($nm1[$rnd] === $nm3b[$rnd3]);

        $rnd4 = array_rand($nm2);
        $rnd5 = array_rand($nm5);
        $name = $nm3b[$rnd3] . $nm2[$rnd2] . $nm1[$rnd] . $nm2[$rnd4] . $nm5[$rnd5];
    } else {
        $rnd = array_rand($nm3b);
        $rnd2 = array_rand($nm6);
        $rnd3 = array_rand($nm7);
        $rnd4 = array_rand($nm7);
        $rnd5 = array_rand($nm7);
        $rnd6 = array_rand($nm7);
        $name = $nm3b[$rnd] . $nm6[$rnd2] . " " . $nm7[$rnd3] . $nm7[$rnd4] . $nm7[$rnd5] . $nm7[$rnd6];
    }

    return $name;
}

function generateAlienName()
{
    $nm1 = ["br", "c", "cr", "dr", "g", "gh", "gr", "k", "kh", "kr", "n", "q", "qh", "sc", "scr", "str", "st", "t", "tr", "thr", "v", "vr", "x", "z", "", "", "", "", ""];
    $nm2 = ["ae", "aa", "ai", "au", "uu", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u"];
    $nm3 = ["c", "k", "n", "q", "t", "v", "x", "z", "c", "cc", "cr", "cz", "dr", "gr", "gn", "gm", "gv", "gz", "k", "kk", "kn", "kr", "kt", "kv", "kz", "lg", "lk", "lq", "lx", "lz", "nc", "ndr", "nkr", "ngr", "nk", "nq", "nqr", "nz", "q", "qr", "qn", "rc", "rg", "rk", "rkr", "rq", "rqr", "sc", "sq", "str", "t", "v", "vr", "x", "z", "q'", "k'", "rr", "r'", "t'", "tt", "vv", "v'", "x'", "z'", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
    $nm4 = ["", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "oi", "ie", "ai", "ei", "eo", "ui"];
    $nm5 = ["d", "ds", "k", "ks", "l", "ls", "n", "ns", "ts", "x"];
    $nm6 = ["b", "bh", "ch", "d", "dh", "f", "h", "l", "m", "n", "ph", "r", "s", "sh", "th", "v", "y", "z", "", "", "", "", "", "", "", "", ""];
    $nm7 = ["ae", "ai", "ee", "ei", "ie", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u"];
    $nm8 = ["c", "d", "g", "h", "l", "m", "n", "r", "s", "v", "z", "c", "ch", "d", "dd", "dh", "g", "gn", "h", "hl", "hm", "hn", "hr", "l", "ld", "ldr", "lg", "lgr", "lk", "ll", "lm", "ln", "lph", "lt", "lv", "lz", "m", "mm", "mn", "mh", "mph", "n", "nd", "nn", "ng", "nk", "nph", "nz", "ph", "phr", "r", "rn", "rl", "rz", "s", "ss", "sl", "sn", "st", "v", "z", "s'", "l'", "n'", "m'", "f'", "h'"];
    $nm10 = ["a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "oi", "ie", "ai", "ea", "ae"];
    $nm11 = ["", "", "", "", "d", "ds", "h", "l", "ll", "n", "ns", "r", "rs", "s", "t", "th"];
    $nm12 = ["b", "bh", "br", "c", "ch", "cr", "d", "dh", "dr", "f", "g", "gh", "gr", "h", "k", "kh", "kr", "l", "m", "n", "q", "qh", "ph", "r", "s", "sc", "scr", "sh", "st", "str", "t", "th", "thr", "tr", "v", "vr", "y", "x", "z", "", "", "", "", "", "", ""];
    $nm13 = ["ae", "aa", "ai", "au", "ee", "ei", "ie", "uu", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u"];
    $nm14 = ["c", "d", "g", "h", "k", "l", "m", "n", "q", "r", "s", "t", "v", "z", "c", "d", "g", "h", "k", "l", "m", "n", "q", "r", "s", "t", "v", "z", "c", "cc", "ch", "cr", "cz", "d", "dd", "dh", "dr", "g", "gm", "gn", "gr", "gv", "gz", "h", "hl", "hm", "hn", "hr", "k", "k'", "kk", "kn", "kr", "kt", "kv", "kz", "l", "ld", "ldr", "lg", "lgr", "lk", "ll", "lm", "ln", "lph", "lq", "lt", "lv", "lx", "lz", "m", "mh", "mm", "mn", "mph", "n", "nc", "nd", "ndr", "ng", "ngr", "nk", "nkr", "nn", "nph", "nq", "nqr", "nz", "ph", "phr", "q", "q'", "qn", "qr", "r", "r'", "rc", "rg", "rk", "rkr", "rl", "rn", "rq", "rqr", "rr", "rz", "s", "sc", "sl", "sn", "sq", "ss", "st", "str", "t", "t'", "tt", "v", "v'", "vr", "vv", "x", "x'", "z", "z'", "", "", "", "", "", "", "", "", "", "", ""];
    $nm15 = ["", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "oi", "ie", "ai", "ea", "ae"];
    $nm16 = ["d", "ds", "k", "ks", "l", "ll", "ls", "n", "ns", "r", "rs", "s", "t", "ts", "th", "x", "", "", "", ""];

    $name = "";

    function generateNamePart($arrays)
    {
        return $arrays[rand(0, count($arrays) - 1)];
    }

    $i = rand(0, 9);
    if ($i < 4) {
        do {
            $part1 = generateNamePart($nm1);
            $part2 = generateNamePart($nm2);
            $part3 = generateNamePart($nm3);
            $part4 = generateNamePart($nm4);
            $part5 = generateNamePart($nm5);
        } while ($part1 == $part3 || $part3 == $part5);
        if ($part3 == "") {
            $part4 = "";
        } else {
            do {
                $part4 = generateNamePart($nm4);
            } while ($part4 == "");
        }
        $name = $part1 . $part2 . $part3 . $part4 . $part5;
    } elseif ($i < 7) {
        do {
            $part1 = generateNamePart($nm6);
            $part2 = generateNamePart($nm7);
            $part3 = generateNamePart($nm8);
            $part4 = generateNamePart($nm10);
            $part5 = generateNamePart($nm11);
        } while ($part1 == $part3 || $part3 == $part5);
        $name = $part1 . $part2 . $part3 . $part4 . $part5;
    } else {
        do {
            $part1 = generateNamePart($nm12);
            $part2 = generateNamePart($nm13);
            $part3 = generateNamePart($nm14);
            $part4 = generateNamePart($nm15);
            $part5 = generateNamePart($nm16);
        } while ($part1 == $part3 || $part3 == $part5);
        if ($part3 == "") {
            $part4 = "";
        } else {
            do {
                $part4 = generateNamePart($nm15);
            } while ($part4 == "");
        }
        $name = $part1 . $part2 . $part3 . $part4 . $part5;
    }

    return ucfirst($name);
}

function generateDragonkinName($type = 1)
{
    $nm1 = ["", "", "", "", "", "b", "br", "dr", "g", "gr", "h", "k", "kr", "m", "n", "r", "s", "sr", "str", "t", "tr", "v", "z"];
    $nm2 = ["a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "ai", "ae", "ia", "iu", "io", "eo"];
    $nm3 = ["cr", "cg", "cn", "csh", "cd", "cdr", "dr", "dg", "dgr", "dk", "dkr", "k", "kr", "kt", "kth", "ksh", "l", "lk", "lt", "ldr", "lg", "lgr", "lsh", "lz", "n", "nd", "ndr", "nsh", "nsk", "r", "rc", "rph", "rsh", "rth", "rd", "rdr", "rgr", "rg", "rz", "rzr", "rsh", "s", "sth", "shk", "sk", "sg", "skr", "th", "tr", "tr", "tg", "z", "zz", "zg", "zk"];
    $nm4 = ["b", "d", "g", "j", "k", "l", "n", "r", "s", "sh", "z"];
    $nm5 = ["", "", "", "c", "d", "g", "gg", "k", "ks", "n", "nd", "ph", "s", "th", "x", "z"];
    $nm6 = ["", "", "", "", "", "", "", "", "", "", "b", "bh", "c", "ch", "d", "g", "h", "kh", "l", "m", "n", "ph", "phr", "r", "s", "shr", "str", "sth", "t", "th", "tr", "z", "zh"];
    $nm7 = ["a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "ai", "ae", "ia", "ea", "ie", "ei"];
    $nm8 = ["dr", "dh", "dn", "dhr", "gn", "gr", "ghr", "gtr", "gt", "k", "kk", "kh", "kt", "kth", "l", "lk", "ll", "lg", "ld", "ldr", "lgr", "ln", "lm", "lkh", "ls", "lz", "n", "nd", "ndh", "ndr", "ns", "nsh", "nz", "nh", "nhr", "ng", "ngh", "r", "rc", "rph", "rsh", "rz", "rl", "s", "sh", "ss", "sth", "sht", "shl", "sn", "sg", "sk", "th", "thr", "thn", "tr", "z", "zh"];
    $nm9 = ["l", "m", "n", "r", "s", "sh", "t", "th", "x", "z"];
    $nm10 = ["", "", "", "", "", "", "", "", "", "", "h", "s", "sh", "th", "x", "z"];
    $nm11 = ["", "", "", "", "", "", "", "b", "ch", "d", "g", "h", "k", "kr", "l", "m", "n", "r", "s", "sr", "str", "sth", "t", "tr", "th", "z"];
    $nm12 = ["a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "a", "e", "i", "o", "u", "ai", "ae", "ia", "ea", "io", "ie"];
    $nm13 = ["cr", "cn", "cd", "dr", "dh", "dg", "dhr", "gn", "gr", "ghr", "k", "kk", "kt", "kth", "l", "lk", "ll", "lg", "ld", "ldr", "lgr", "lz", "n", "nd", "ndr", "ns", "nsh", "nz", "ng", "r", "rc", "rph", "rsh", "rz", "rd", "rdr", "rgr", "rg", "s", "sh", "ss", "sth", "sht", "sth", "sn", "sg", "sk", "th", "thr", "tr", "z", "zg", "zh"];
    $nm14 = ["b", "d", "g", "l", "n", "r", "s", "sh", "t", "th", "z"];
    $nm15 = ["", "", "", "h", "n", "s", "sh", "t", "th", "x", "z"];

    $names = [];

    for ($i = 0; $i < 10; $i++) {
        $name = "";

        if ($type == 1) {
            do {
                $rnd = rand(0, count($nm6) - 1);
                $rnd2 = rand(0, count($nm7) - 1);
                $rnd3 = rand(0, count($nm8) - 1);
                $rnd4 = rand(0, count($nm7) - 1);
                $rnd5 = rand(0, count($nm10) - 1);
            } while ($nm8[$rnd3] == $nm6[$rnd] || $nm8[$rnd3] == $nm10[$rnd5]);

            if ($i < 6) {
                $name = $nm6[$rnd] . $nm7[$rnd2] . $nm8[$rnd3] . $nm7[$rnd4] . $nm10[$rnd5];
            } else {
                $rnd6 = rand(0, count($nm9) - 1);
                $rnd7 = rand(0, count($nm7) - 1);

                while ($nm8[$rnd3] == $nm9[$rnd6] || $nm9[$rnd6] == $nm10[$rnd5]) {
                    $rnd6 = rand(0, count($nm9) - 1);
                }

                if ($i < 8) {
                    $name = $nm6[$rnd] . $nm7[$rnd2] . $nm8[$rnd3] . $nm7[$rnd4] . $nm9[$rnd6] . $nm7[$rnd7] . $nm10[$rnd5];
                } else {
                    $name = $nm6[$rnd] . $nm7[$rnd2] . $nm9[$rnd6] . $nm7[$rnd7] . $nm8[$rnd3] . $nm7[$rnd4] . $nm10[$rnd5];
                }
            }
        } elseif ($type == 2) {
            do {
                $rnd = rand(0, count($nm11) - 1);
                $rnd2 = rand(0, count($nm12) - 1);
                $rnd3 = rand(0, count($nm13) - 1);
                $rnd4 = rand(0, count($nm12) - 1);
                $rnd5 = rand(0, count($nm15) - 1);
            } while ($nm13[$rnd3] == $nm11[$rnd] || $nm13[$rnd3] == $nm15[$rnd5]);

            if ($i < 6) {
                $name = $nm11[$rnd] . $nm12[$rnd2] . $nm13[$rnd3] . $nm12[$rnd4] . $nm15[$rnd5];
            } else {
                $rnd6 = rand(0, count($nm14) - 1);
                $rnd7 = rand(0, count($nm12) - 1);

                while ($nm13[$rnd3] == $nm14[$rnd6] || $nm14[$rnd6] == $nm15[$rnd5]) {
                    $rnd6 = rand(0, count($nm14) - 1);
                }

                if ($i < 8) {
                    $name = $nm11[$rnd] . $nm12[$rnd2] . $nm13[$rnd3] . $nm12[$rnd4] . $nm14[$rnd6] . $nm12[$rnd7] . $nm15[$rnd5];
                } else {
                    $name = $nm11[$rnd] . $nm12[$rnd2] . $nm14[$rnd6] . $nm12[$rnd7] . $nm13[$rnd3] . $nm12[$rnd4] . $nm15[$rnd5];
                }
            }
        } else {
            do {
                $rnd = rand(0, count($nm1) - 1);
                $rnd2 = rand(0, count($nm2) - 1);
                $rnd3 = rand(0, count($nm3) - 1);
                $rnd4 = rand(0, count($nm2) - 1);
                $rnd5 = rand(0, count($nm5) - 1);
            } while ($nm3[$rnd3] == $nm1[$rnd] || $nm3[$rnd3] == $nm5[$rnd5]);

            if ($i < 6) {
                $name = $nm1[$rnd] . $nm2[$rnd2] . $nm3[$rnd3] . $nm2[$rnd4] . $nm5[$rnd5];
            } else {
                $rnd6 = rand(0, count($nm4) - 1);
                $rnd7 = rand(0, count($nm2) - 1);

                while ($nm3[$rnd3] == $nm4[$rnd6] || $nm4[$rnd6] == $nm5[$rnd5]) {
                    $rnd6 = rand(0, count($nm4) - 1);
                }

                if ($i < 8) {
                    $name = $nm1[$rnd] . $nm2[$rnd2] . $nm3[$rnd3] . $nm2[$rnd4] . $nm4[$rnd6] . $nm2[$rnd7] . $nm5[$rnd5];
                } else {
                    $name = $nm1[$rnd] . $nm2[$rnd2] . $nm4[$rnd6] . $nm2[$rnd7] . $nm3[$rnd3] . $nm2[$rnd4] . $nm5[$rnd5];
                }
            }
        }

        $names[] = ucfirst($name);
    }

    return $names[0];
}

makeFakeUser();
