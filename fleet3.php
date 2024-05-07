<?php

define('INSIDE', true);

$_EnginePath = './';

include($_EnginePath.'common.php');

include($_EnginePath . 'modules/flightControl/_includes.php');

use UniEngine\Engine\Modules\FlightControl;
use UniEngine\Engine\Modules\Flights;

loggedCheck();

if($_POST['sending_fleet'] != '1')
{
    header('Location: fleet.php'.($_GET['quickres'] == 1 ? '?quickres=1' : ''));
    safeDie();
}

function messageRed($Text, $Title)
{
    global $_POST, $_Lang;
    $_POST = base64_encode(json_encode($_POST));
    $GoBackForm = '';
    $GoBackForm .= '<form action="fleet2.php" method="post"><input type="hidden" name="fromEnd" value="1"/>';
    $GoBackForm .= '<input type="hidden" name="gobackVars" value="'.$_POST.'"/>';
    $GoBackForm .= '<input class="orange pad5" style="font-weight: bold;" type="submit" value="&laquo; '.$_Lang['fl_goback'].'"/>';
    $GoBackForm .= '</form>';
    message("<br/><b class=\"red\">{$Text}</b><br/>{$GoBackForm}", $Title);
}

includeLang('fleet');

$Now = time();
$ErrorTitle = &$_Lang['fl_error'];

if(MORALE_ENABLED)
{
    Morale_ReCalculate($_User, $Now);
}

// --- Initialize Vars
$Target['galaxy'] = intval($_POST['galaxy']);
$Target['system'] = intval($_POST['system']);
$Target['planet'] = intval($_POST['planet']);
$Target['type'] = intval($_POST['planettype']);
$Fleet['Speed'] = floatval($_POST['speed']);
$Fleet['UseQuantum'] = (isset($_POST['usequantumgate']) && $_POST['usequantumgate'] == 'on' ? true : false);
$Fleet['resources'] = [
    'metal' => $_POST['resource1'],
    'crystal' => $_POST['resource2'],
    'deuterium' => $_POST['resource3'],
];
$Fleet['ExpeTime'] = floatval($_POST['expeditiontime']);
$Fleet['HoldTime'] = intval($_POST['holdingtime']);
$Fleet['ACS_ID'] = isset($_POST['acs_id']) ? floor(floatval($_POST['acs_id'])) : 0;
$Fleet['Mission'] = isset($_POST['mission']) ? intval($_POST['mission']) : 0;

if (!isUserAccountActivated($_User)) {
    messageRed($_Lang['fl3_BlockAccNotActivated'], $ErrorTitle);
}

// --- Check if Mission is selected
if($Fleet['Mission'] <= 0)
{
    messageRed($_Lang['fl3_NoMissionSelected'], $ErrorTitle);
}

$fleetsInFlightCounters = FlightControl\Utils\Helpers\getFleetsInFlightCounters([
    'userId' => $_User['id'],
]);

$flightSlotsValidationResult = FlightControl\Utils\Validators\validateFlightSlots([
    'user' => &$_User,
    'fleetEntry' => $Fleet,
    'fleetsInFlightCounters' => $fleetsInFlightCounters,
    'currentTimestamp' => $Now,
]);

if (!$flightSlotsValidationResult['isSuccess']) {
    $errorMessage = FlightControl\Utils\Errors\mapFlightSlotsValidationErrorToReadableMessage(
        $flightSlotsValidationResult['error'],
        []
    );

    messageRed($errorMessage, $ErrorTitle);
}

FlightControl\Utils\Inputs\normalizeFleetResourcesInputs([ 'fleetEntry' => &$Fleet ]);

$fleetResourcesValidationResult = FlightControl\Utils\Validators\validateFleetResources([
    'fleetEntry' => $Fleet,
    'fleetOriginPlanet' => &$_Planet,
]);

if (!$fleetResourcesValidationResult['isSuccess']) {
    $errorMessage = FlightControl\Utils\Errors\mapFleetResourcesValidationErrorToReadableMessage(
        $fleetResourcesValidationResult['error'],
        []
    );

    messageRed($errorMessage, $ErrorTitle);
}

// --- Check, if Target Data are correct
if($Target['galaxy'] == $_Planet['galaxy'] AND $Target['system'] == $_Planet['system'] AND $Target['planet'] == $_Planet['planet'] AND $Target['type'] == $_Planet['planet_type'])
{
    messageRed($_Lang['fl2_cantsendsamecoords'], $ErrorTitle);
}

$isValidCoordinate = Flights\Utils\Checks\isValidCoordinate([ 'coordinate' => $Target ]);

if (!$isValidCoordinate['isValid']) {
    messageRed($_Lang['fl2_targeterror'], $ErrorTitle);
}

$availableSpeeds = FlightControl\Utils\Helpers\getAvailableSpeeds([
    'user' => &$_User,
    'timestamp' => $Now,
]);

if (!in_array($Fleet['Speed'], $availableSpeeds)) {
    messageRed($_Lang['fl_bad_fleet_speed'], $ErrorTitle);
}

// --- Check PlanetOwner
$targetInfo = FlightControl\Utils\Helpers\getTargetInfo([
    'targetCoords' => $Target,
    'fleetEntry' => $Fleet,
    'fleetOwnerUser' => &$_User,
    'isExtendedTargetOwnerDetailsEnabled' => true,
]);

$smartFleetsBlockadeStateValidationResult = FlightControl\Utils\Validators\validateSmartFleetsBlockadeState([
    'timestamp' => $Now,
    'fleetData' => $Fleet,
    'fleetOwnerDetails' => [
        'userId' => $_User['id'],
        'planetId' => $_Planet['id'],
    ],
    'targetOwnerDetails' => (
        $targetInfo['targetOwnerDetails']['id'] > 0 ?
        [
            'userId' => $targetInfo['targetOwnerDetails']['id'],
            'planetId' => $targetInfo['targetPlanetDetails']['id'],
            'onlinetime' => $targetInfo['targetOwnerDetails']['onlinetime'],
        ] :
        null
    ),
    'settings' => [
        'idleTime' => getIdleProtectionTimeLimit(),
    ],
]);

if (!$smartFleetsBlockadeStateValidationResult['isValid']) {
    $firstValidationError = $smartFleetsBlockadeStateValidationResult['errors'];

    $errorMessage = FlightControl\Utils\Errors\mapSmartFleetsBlockadeValidationErrorToReadableMessage(
        $firstValidationError,
        [
            'user' => $_User,
            'originPlanet' => $_Planet,
            'targetPlanet' => $Target,
        ]
    );

    messageRed(
        $errorMessage . $_Lang['SFB_Stop_LearnMore'],
        $_Lang['SFB_BoxTitle']
    );
}

// --- Parse Fleet Array
$Fleet['array'] = [];
$Fleet['count'] = 0;
$Fleet['storage'] = 0;
$Fleet['FuelStorage'] = 0;
$Fleet['TotalResStorage'] = 0;

$inputFleetArray = String2Array($_POST['FleetArray']);

if (
    empty($inputFleetArray) ||
    !is_array($inputFleetArray)
) {
    messageRed($_Lang['fl1_NoShipsGiven'], $ErrorTitle);
}

$fleetArrayParsingResult = FlightControl\Utils\Validators\parseFleetArray([
    'fleet' => $inputFleetArray,
    'planet' => &$_Planet,
    'isFromDirectUserInput' => false,
]);

if (!$fleetArrayParsingResult['isValid']) {
    $firstValidationError = $fleetArrayParsingResult['errors'][0];
    $errorMessage = FlightControl\Utils\Errors\mapFleetArrayValidationErrorToReadableMessage($firstValidationError);

    messageRed($errorMessage, $ErrorTitle);
}

$Fleet['array'] = $fleetArrayParsingResult['payload']['parsedFleet'];

$shipsTotalStorage = FlightControl\Utils\Helpers\FleetArray\getShipsTotalStorage($Fleet['array']);

$Fleet['count'] = FlightControl\Utils\Helpers\FleetArray\getAllShipsCount($Fleet['array']);
$Fleet['storage'] = $shipsTotalStorage['allPurpose'];
$Fleet['FuelStorage'] = $shipsTotalStorage['fuelOnly'];

$validMissionTypes = FlightControl\Utils\Helpers\getValidMissionTypes([
    'targetCoordinates' => $Target,
    'fleetShips' => $Fleet['array'],
    'fleetShipsCount' => $Fleet['count'],
    'isPlanetOccupied' => $targetInfo['isPlanetOccupied'],
    'isPlanetOwnedByUser' => $targetInfo['isPlanetOwnedByFleetOwner'],
    'isPlanetOwnedByUsersFriend' => $targetInfo['isPlanetOwnerFriendly'],
    // TODO: additional pre-validation might be needed
    'isUnionMissionAllowed' => true,
]);

// --- Check if everything is OK with ACS
$isJoiningUnion = false;

if (
    $Fleet['Mission'] == Flights\Enums\FleetMission::UnitedAttack &&
    in_array(Flights\Enums\FleetMission::UnitedAttack, $validMissionTypes)
) {
    $joinUnionValidationResult = FlightControl\Utils\Validators\validateJoinUnion([
        'newFleet' => $Fleet,
        'timestamp' => $Now,
        'user' => &$_User,
        'destinationEntry' => [
            'id' => $targetInfo['targetPlanetDetails']['id'],
        ],
    ]);

    if (!$joinUnionValidationResult['isValid']) {
        $firstValidationError = $joinUnionValidationResult['errors'][0];
        $errorMessage = FlightControl\Utils\Errors\mapJoinUnionValidationErrorToReadableMessage($firstValidationError);

        messageRed($errorMessage, $ErrorTitle);
    }

    // TODO: Optimize by not fetching this again
    $CheckACS = FlightControl\Utils\Helpers\getFleetUnionJoinData([
        'newFleet' => $Fleet,
    ]);

    $isJoiningUnion = true;
}

// --- If Mission is not correct, show Error
if(!in_array($Fleet['Mission'], $validMissionTypes))
{
    if ($Fleet['Mission'] == 1) {
        if ($Target['type'] == 2) {
            messageRed($_Lang['fl3_CantAttackDebris'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantAttackNonUsed'], $ErrorTitle);
        }
        if ($targetInfo['isPlanetOwnedByFleetOwner']) {
            messageRed($_Lang['fl3_CantAttackYourself'], $ErrorTitle);
        }
    }
    if ($Fleet['Mission'] == 2) {
        if ($Target['type'] == 2) {
            messageRed($_Lang['fl3_CantACSDebris'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantACSNonUsed'], $ErrorTitle);
        }
        if ($targetInfo['isPlanetOwnedByFleetOwner']) {
            messageRed($_Lang['fl3_CantACSYourself'], $ErrorTitle);
        }
    }
    if ($Fleet['Mission'] == 3) {
        if ($Target['type'] == 2) {
            messageRed($_Lang['fl3_CantTransportDebris'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantTransportNonUsed'], $ErrorTitle);
        }
        // TODO: This should be checked,
        // to prevent from other error states from being caught by this
        messageRed($_Lang['fl3_CantTransportSpyProbes'], $ErrorTitle);
    }
    if ($Fleet['Mission'] == 4) {
        if ($Target['type'] == 2) {
            messageRed($_Lang['fl3_CantStayDebris'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantStayNonUsed'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOwnedByFleetOwner']) {
            messageRed($_Lang['fl3_CantStayNonYourself'], $ErrorTitle);
        }
    }
    if ($Fleet['Mission'] == 5) {
        if ($Target['type'] == 2) {
            messageRed($_Lang['fl3_CantProtectDebris'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantProtectNonUsed'], $ErrorTitle);
        }
        if ($targetInfo['isPlanetOwnedByFleetOwner']) {
            messageRed($_Lang['fl3_CantProtectYourself'], $ErrorTitle);
        }
        // TODO: This should be checked,
        // to prevent from other error states from being caught by this
        messageRed($_Lang['fl3_CantProtectNonFriend'], $ErrorTitle);
    }
    if ($Fleet['Mission'] == 6) {
        if ($Target['type'] == 2) {
            messageRed($_Lang['fl3_CantSpyDebris'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantSpyNonUsed'], $ErrorTitle);
        }
        if ($targetInfo['isPlanetOwnedByFleetOwner']) {
            messageRed($_Lang['fl3_CantSpyYourself'], $ErrorTitle);
        }
        // TODO: This should be checked,
        // to prevent from other error states from being caught by this
        messageRed($_Lang['fl3_CantSpyProbesCount'], $ErrorTitle);
    }
    if ($Fleet['Mission'] == 7) {
        if ($Target['type'] != 1) {
            messageRed($_Lang['fl3_CantSettleNonPlanet'], $ErrorTitle);
        }
        if ($targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantSettleOnUsed'], $ErrorTitle);
        }
        // TODO: This should be checked,
        // to prevent from other error states from being caught by this
        messageRed($_Lang['fl3_CantSettleNoShips'], $ErrorTitle);
    }
    if ($Fleet['Mission'] == 8) {
        if ($Target['type'] != 2) {
            messageRed($_Lang['fl3_CantRecycleNonDebris'], $ErrorTitle);
        }
        // TODO: This should be checked,
        // to prevent from other error states from being caught by this
        messageRed($_Lang['fl3_CantRecycleNoShip'], $ErrorTitle);
    }
    if ($Fleet['Mission'] == 9) {
        if ($Target['type'] != 3) {
            messageRed($_Lang['fl3_CantDestroyNonMoon'], $ErrorTitle);
        }
        if (!$targetInfo['isPlanetOccupied']) {
            messageRed($_Lang['fl3_CantDestroyNonUsed'], $ErrorTitle);
        }
        if ($targetInfo['isPlanetOwnedByFleetOwner']) {
            messageRed($_Lang['fl3_CantDestroyYourself'], $ErrorTitle);
        }
        // TODO: This should be checked,
        // to prevent from other error states from being caught by this
        messageRed($_Lang['fl3_CantDestroyNoShip'], $ErrorTitle);
    }
    if ($Fleet['Mission'] == 15) {
        if (!isFeatureEnabled(\FeatureType::Expeditions)) {
            messageRed($_Lang['fl3_ExpeditionsAreOff'], $ErrorTitle);
        }
    }

    messageRed($_Lang['fl3_BadMissionSelected'], $ErrorTitle);
}

// --- If Mission is Recycling and there is no Debris Field, show Error
if ($Fleet['Mission'] == Flights\Enums\FleetMission::Harvest) {
    if (
        $targetInfo['galaxyEntry']['metal'] <= 0 &&
        $targetInfo['galaxyEntry']['crystal'] <= 0
    ) {
        messageRed($_Lang['fl3_NoDebrisFieldHere'], $ErrorTitle);
    }
}

if ($Fleet['Mission'] == Flights\Enums\FleetMission::Hold) {
    $missionHoldValidationResult = FlightControl\Utils\Validators\validateMissionHold([
        'newFleet' => $Fleet,
    ]);

    if (!$missionHoldValidationResult['isValid']) {
        $firstValidationError = $missionHoldValidationResult['errors'][0];

        $errorMessage = null;
        switch ($firstValidationError['errorCode']) {
            case 'INVALID_HOLD_TIME':
                $errorMessage = $_Lang['fl3_Holding_BadTime'];
                break;
            default:
                $errorMessage = $_Lang['fleet_generic_errors_unknown'];
                break;
        }

        messageRed($errorMessage, $ErrorTitle);
    }
}
if ($Fleet['Mission'] == Flights\Enums\FleetMission::Expedition) {
    $missionExpeditionValidationResult = FlightControl\Utils\Validators\validateMissionExpedition([
        'fleetEntry' => $Fleet,
    ]);

    if (!$missionExpeditionValidationResult['isSuccess']) {
        $error = $missionExpeditionValidationResult['error'];

        $errorMessage = null;
        switch ($error['code']) {
            case 'INVALID_EXPEDITION_TIME':
                $errorMessage = $_Lang['fl3_MissionExpedition_BadTime'];
                break;
            default:
                $errorMessage = $_Lang['fleet_generic_errors_unknown'];
                break;
        }

        messageRed($errorMessage, $ErrorTitle);
    }
}

// --- Translate expedition or hold time into stay time (values already validated)
$Fleet['StayTime'] = 0;
if ($Fleet['Mission'] == Flights\Enums\FleetMission::Expedition) {
    $Fleet['StayTime'] = $Fleet['ExpeTime'] * TIME_HOUR;
}
if ($Fleet['Mission'] == Flights\Enums\FleetMission::Hold) {
    $Fleet['StayTime'] = $Fleet['HoldTime'] * TIME_HOUR;
}

// --- Set Variables to better usage
if (!empty($targetInfo['galaxyEntry'])) {
    $TargetData = &$targetInfo['galaxyEntry'];
} else {
    $TargetData = &$targetInfo['targetOwnerDetails'];
}

$hasTargetOwner = (
    $targetInfo['isPlanetOccupied'] &&
    !$targetInfo['isPlanetOwnedByFleetOwner'] &&
    !$targetInfo['isPlanetAbandoned']
);
$usersStats = (
    $hasTargetOwner ?
        FlightControl\Utils\Factories\createFleetUsersStatsData([
            'fleetOwner' => $_User,
            'targetOwner' => $TargetData,
        ]) :
        FlightControl\Utils\Factories\createFleetUsersStatsData([])
);

if ($hasTargetOwner) {
    $targetOwnerValidation = FlightControl\Utils\Validators\validateTargetOwner([
        'fleetEntry' => $Fleet,
        'fleetOwner' => $_User,
        'targetOwner' => $TargetData,
        'targetInfo' => $targetInfo,
        'usersStats' => $usersStats,
        'fleetsInFlightCounters' => $fleetsInFlightCounters,
        'currentTimestamp' => $Now,
    ]);

    if (!$targetOwnerValidation['isSuccess']) {
        $errorMessage = FlightControl\Utils\Errors\mapTargetOwnerValidationErrorToReadableMessage(
            $targetOwnerValidation['error']
        );

        messageRed($errorMessage, $ErrorTitle);
    }
}

// --- Calculate Speed and Distance
$GenFleetSpeed = $Fleet['Speed'];
$SpeedFactor = getUniFleetsSpeedFactor();

$slowestShipSpeed = FlightControl\Utils\Helpers\getSlowestShipSpeed([
    'shipsDetails' => getFleetShipsSpeeds($Fleet['array'], $_User),
    'user' => &$_User,
]);

$Distance = getFlightDistanceBetween($_Planet, $Target);

$isUsingQuantumGate = false;
$quantumGateUseType = 0;

if ($Fleet['UseQuantum']) {
    $quantumGateValidationResult = FlightControl\Utils\Validators\validateQuantumGate([
        'fleet' => $Fleet,
        'originPlanet' => $_Planet,
        'targetPlanet' => [
            'quantumgate' => $targetInfo['targetPlanetDetails']['quantumgate'],
        ],
        'targetData' => $Target,
        'isTargetOccupied' => $targetInfo['isPlanetOccupied'],
        'isTargetOwnPlanet' => $targetInfo['isPlanetOwnedByFleetOwner'],
        'isTargetOwnedByFriend' => $targetInfo['isPlanetOwnerFriendly'],
        'isTargetOwnedByFriendlyMerchant' => $targetInfo['isPlanetOwnerFriendlyMerchant'],
        'currentTimestamp' => $Now,
    ]);

    if (!$quantumGateValidationResult['isSuccess']) {
        $errorMessage = FlightControl\Utils\Errors\mapQuantumGateValidationErrorToReadableMessage(
            $quantumGateValidationResult['error']
        );

        messageRed($errorMessage, $ErrorTitle);
    } else {
        $isUsingQuantumGate = true;
        $quantumGateUseType = $quantumGateValidationResult['payload']['useType'];
    }
}

$flightParams = FlightControl\Utils\Helpers\getFleetParams([
    'user' => $_User,
    'fleet' => $Fleet,
    'fleetSpeed' => $GenFleetSpeed,
    'distance' => $Distance,
    'maxFleetSpeed' => $slowestShipSpeed,
    'isUsingQuantumGate' => $isUsingQuantumGate,
    'quantumGateUseType' => $quantumGateUseType,
]);

$DurationTarget = $flightParams['duration']['toDestination'];
$DurationBack = $flightParams['duration']['backToOrigin'];
$Consumption = $flightParams['consumption'];

if($_Planet['deuterium'] < $Consumption)
{
    messageRed($_Lang['fl3_NoEnoughFuel'], $ErrorTitle);
}
if($Consumption > ($Fleet['storage'] + $Fleet['FuelStorage']))
{
    messageRed($_Lang['fl3_NoEnoughtStorage4Fuel'], $ErrorTitle);
}
if($_Planet['deuterium'] < ($Consumption + $Fleet['resources']['deuterium']))
{
    messageRed($_Lang['fl3_PlanetNoEnoughdeuterium'], $ErrorTitle);
}
if($Fleet['FuelStorage'] >= $Consumption)
{
    $Fleet['storage'] += $Consumption;
}
else
{
    $Fleet['storage'] += $Fleet['FuelStorage'];
}
$Fleet['storage'] -= $Consumption;
foreach($Fleet['resources'] as $Value)
{
    $Fleet['TotalResStorage'] += $Value;
}
if($Fleet['TotalResStorage'] > $Fleet['storage'])
{
    messageRed($_Lang['fl3_NoEnoughStorage4Res'], $ErrorTitle);
}

$Fleet['SetCalcTime'] = $Now + $DurationTarget;
$Fleet['SetStayTime'] = ($Fleet['StayTime'] > 0 ? $Fleet['SetCalcTime'] + $Fleet['StayTime'] : '0');
$Fleet['SetBackTime'] = $Fleet['SetCalcTime'] + $Fleet['StayTime'] + $DurationBack;

$unionFlightsAnySlowdown = 0;
$unionInFlightFleetsSlowdown = 0;

if ($isJoiningUnion) {
    $unionFlightTimeDiff = Flights\Utils\Calculations\calculateUnionFlightTimeDiff([
        'fleetAtDestinationTimestamp' => $Fleet['SetCalcTime'],
        'union' => $CheckACS,
    ]);

    if (!$unionFlightTimeDiff['isSuccess']) {
        messageRed($_Lang['fl3_ACSFleet2Slow'], $ErrorTitle);
    }

    if (isset($unionFlightTimeDiff['payload']['newFleetSlowDownBy'])) {
        $slowdown = $unionFlightTimeDiff['payload']['newFleetSlowDownBy'];
        $unionFlightsAnySlowdown = $slowdown;

        $Fleet['SetCalcTime'] += $slowdown;
        $Fleet['SetBackTime'] += $slowdown;
    }
    if (isset($unionFlightTimeDiff['payload']['unionSlowDownBy'])) {
        $slowdown = $unionFlightTimeDiff['payload']['unionSlowDownBy'];
        $unionFlightsAnySlowdown = $slowdown;
        $unionInFlightFleetsSlowdown = $slowdown;
    }
}

// MultiAlert System
$SendAlert = false;
$IPIntersectionFound = false;
$IPIntersectionFiltered = false;
$IPIntersectionNow = false;
$DeclarationID = null;
if($targetInfo['isPlanetOccupied'] AND !$targetInfo['isPlanetOwnedByFleetOwner'] AND !$targetInfo['isPlanetAbandoned'])
{
    include($_EnginePath.'includes/functions/AlertSystemUtilities.php');
    $CheckIntersection = AlertUtils_IPIntersect($_User['id'], $TargetData['id'], [
        'LastTimeDiff' => (TIME_DAY * 30),
        'ThisTimeDiff' => (TIME_DAY * 30),
        'ThisTimeStamp' => ($Now - SERVER_MAINOPEN_TSTAMP)
    ]);
    if($CheckIntersection !== false)
    {
        $IPIntersectionFound = true;
        if($_User['user_lastip'] == $TargetData['lastip'])
        {
            $IPIntersectionNow = true;
        }

        if (
            $_User['multiIP_DeclarationID'] > 0 &&
            $_User['multiIP_DeclarationID'] == $TargetData['multiIP_DeclarationID']
        ) {
            $multiDeclarationDetails = FlightControl\Utils\Fetchers\fetchMultiDeclaration([ 'declarationId' => $_User['multiIP_DeclarationID'] ]);

            if ($multiDeclarationDetails['status'] == 1) {
                $DeclarationID = $multiDeclarationDetails['id'];
            }
        }

        $alertFiltersSearchParams = FlightControl\Utils\Factories\createAlertFiltersSearchParams([
            'fleetOwner' => &$_User,
            'targetOwner' => [
                'id' => $TargetData['id'],
            ],
            'ipsIntersectionsCheckResult' => $CheckIntersection,
        ]);
        $FilterResult = AlertUtils_CheckFilters($alertFiltersSearchParams);

        if($FilterResult['FilterUsed'])
        {
            $IPIntersectionFiltered = true;
        }
        if(!$FilterResult['SendAlert'])
        {
            $DontSendAlert = true;
        }
        if(!$FilterResult['ShowAlert'])
        {
            $DontShowAlert = true;
        }

        if(!isset($DontSendAlert))
        {
            $SendAlert = true;
        }
        if(1 == 0 AND $DontShowAlert !== true)
        {
            // Currently there is no indicator that user wants to get MultiAlert Messages (do disable this code)
            $LockFleetSending = true;
            $ShowMultiAlert = true;
        }
    }
}

if (!isset($LockFleetSending)) {
    $LastFleetID = FlightControl\Utils\Updaters\insertFleetEntry([
        'ownerUser' => $_User,
        'ownerPlanet' => $_Planet,
        'fleetEntry' => $Fleet,
        'targetPlanet' => [
            'id' => $targetInfo['targetPlanetDetails']['id'],
            'ownerId' => $TargetData['id'],
            'galaxy_id' => $targetInfo['galaxyId'],
        ],
        'targetCoords' => $Target,
        'currentTime' => $Now,
    ]);

    if (empty($TargetData['id'])) {
        $TargetData['id'] = '0';
    }

    // PushAlert handling
    $hasMetPushAlertConditions = FlightControl\Utils\Checks\hasMetPushAlertConditions([
        'fleetData' => $Fleet,
        'fleetOwner' => $_User,
        'targetOwner' => $TargetData,
        'targetInfo' => $targetInfo,
        'statsData' => $usersStats,
    ]);

    if ($hasMetPushAlertConditions) {
        $alertData = FlightControl\Utils\Factories\createPushAlert([
            'fleetId' => $LastFleetID,
            'fleetData' => $Fleet,
            'fleetOwner' => $_User,
            'targetOwner' => $TargetData,
            'targetInfo' => $targetInfo,
            'statsData' => $usersStats,
        ]);

        Alerts_Add(1, $Now, 5, 4, 5, $_User['id'], $alertData);
    }
}

if ($SendAlert) {
    $targetOwnerId = $TargetData['id'];

    $alertType = (
        !empty($DeclarationID) ?
            2 :
            1
    );

    $alertData = FlightControl\Utils\Factories\createMultiAlert([
        'fleetId' => $LastFleetID,
        'fleetData' => $Fleet,
        'fleetOwner' => $_User,
        'targetOwner' => $TargetData,
        'foundIpIntersections' => $CheckIntersection,
        'multiIpDeclarationId' => $DeclarationID,
        'hasBlockedFleet' => isset($LockFleetSending),
    ]);

    Alerts_Add(1, $Now, $alertType, 1, 10, $_User['id'], $alertData);
}

if(isset($ShowMultiAlert))
{
    messageRed($_Lang['MultiAlert'], $_Lang['fl_error']);
}

if ($isJoiningUnion) {
    FlightControl\Utils\Updaters\updateUnionEntry([
        'union' => $CheckACS,
        'updates' => [
            'joiningFleetId' => $LastFleetID,
            'joiningUserId' => $_User['id'],
            'slowdown' => $unionInFlightFleetsSlowdown,
        ],
    ]);

    FlightControl\Utils\Updaters\updateUnionFleets([
        'union' => $CheckACS,
        'updates' => [
            'slowdown' => $unionInFlightFleetsSlowdown,
        ],
    ]);

    FlightControl\Utils\Updaters\updateFleetArchiveACSEntries([
        'union' => $CheckACS,
        'updates' => [
            'slowdown' => $unionInFlightFleetsSlowdown,
        ],
    ]);
}

FlightControl\Utils\Updaters\insertFleetArchiveEntry([
    'fleetEntryId' => $LastFleetID,
    'ownerUser' => $_User,
    'ownerPlanet' => $_Planet,
    'fleetEntry' => $Fleet,
    'targetPlanet' => [
        'id' => $targetInfo['targetPlanetDetails']['id'],
        'ownerId' => $TargetData['id'],
        'galaxy_id' => $targetInfo['galaxyId'],
    ],
    'targetCoords' => $Target,
    'flags' => [
        'hasIpIntersection' => $IPIntersectionFound,
        'hasIpIntersectionFiltered' => $IPIntersectionFiltered,
        'hasIpIntersectionOnSend' => $IPIntersectionNow,
        'hasUsedTeleportation' => $isUsingQuantumGate,
    ],
    'currentTime' => $Now,
]);

FlightControl\Utils\Updaters\updateFleetOriginPlanet([
    'originPlanet' => &$_Planet,
    'fleetEntry' => $Fleet,
    'fuelConsumption' => $Consumption,
    'quantumGateUsage' => [
        'isUsing' => $isUsingQuantumGate,
        'usageType' => $quantumGateUseType,
    ],
    'currentTimestamp' => $Now,
]);

$UserDev_Log[] = FlightControl\Utils\Factories\createFleetDevLogEntry([
    'currentPlanet' => &$_Planet,
    'newFleetId' => $LastFleetID,
    'timestamp' => $Now,
    'fleetData' => $Fleet,
    'fuelUsage' => $Consumption,
]);

// ---

$_Lang['FleetMission'] = $_Lang['type_mission'][$Fleet['Mission']];
$_Lang['FleetDistance'] = prettyNumber($Distance);
$_Lang['FleetSpeed'] = prettyNumber($slowestShipSpeed);
$_Lang['FleetFuel'] = prettyNumber($Consumption);
$_Lang['StartGalaxy'] = $_Planet['galaxy'];
$_Lang['StartSystem'] = $_Planet['system'];
$_Lang['StartPlanet'] = $_Planet['planet'];
$_Lang['StartType'] = ($_Planet['planet_type'] == 1 ? 'planet' : ($_Planet['planet_type'] == 3 ? 'moon' : 'debris'));
$_Lang['TargetGalaxy'] = $Target['galaxy'];
$_Lang['TargetSystem'] = $Target['system'];
$_Lang['TargetPlanet'] = $Target['planet'];
$_Lang['TargetType'] = ($Target['type'] == 1 ? 'planet' : ($Target['type'] == 3 ? 'moon' : 'debris'));
$_Lang['FleetStartTime'] = prettyDate('d m Y H:i:s', $Fleet['SetCalcTime'], 1);
$_Lang['FleetEndTime'] = prettyDate('d m Y H:i:s', $Fleet['SetBackTime'], 1);
$_Lang['useQuickRes'] = ($_POST['useQuickRes'] == '1' ? 'true' : 'false');

$_Lang['ShipsRows'] = array_map_withkeys($Fleet['array'], function ($shipCount, $shipId) use (&$_Lang) {
    $shipName = $_Lang['tech'][$shipId];
    $shipCountDisplay = prettyNumber($shipCount);

    return "<tr><th class=\"pad\">{$shipName}</th><th class=\"pad\">{$shipCountDisplay}</th></tr>";
});
$_Lang['ShipsRows'] = implode('', $_Lang['ShipsRows']);

display(parsetemplate(gettemplate('fleet3_body'), $_Lang), $_Lang['fl_title']);

?>
