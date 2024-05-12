<?php

namespace UniEngine\Engine\Modules\Flights\Utils\Helpers;

abstract class ExpeditionEvent
{
    const NothingHappened = 0;
    const PlanetaryResourcesFound = 1;
}

/**
 * @param array $params
 */
function getRandomExpeditionEvent($params)
{
    // TODO: Add more events
    $rollValue = mt_rand(0, 99);

    $expeditionHours = $params['expeditionHours'] ?: 1;

    $maxNothingHappenedValue = 30;
    if ($expeditionHours == 2) {
        $maxNothingHappenedValue = 10;
    } else if ($expeditionHours == 3) {
        $maxNothingHappenedValue = 5;
    } else if ($expeditionHours >= 4) {
        $maxNothingHappenedValue = 1;
    }

    if ($rollValue >= 0 && $rollValue < $maxNothingHappenedValue) {
        return ExpeditionEvent::NothingHappened;
    }
    if ($rollValue >= $maxNothingHappenedValue && $rollValue < 100) {
        return ExpeditionEvent::PlanetaryResourcesFound;
    }
}

/**
 * @param array $params
 * @param ExpeditionEvent $params ['event']
 */
function getExpeditionEventOutcome($params)
{
    $event = $params['event'];

    if ($event == ExpeditionEvent::PlanetaryResourcesFound) {
        return getExpeditionEventPlanetaryResourcesFoundOutcome($params);
    }

    return [];
}

function getRandomFactor($min, $max)
{
    return mt_rand($min, $max) / 10;
}

/**
 * @param array $params
 */
function getExpeditionEventPlanetaryResourcesFoundOutcome($params)
{
    $baseResources = [
        'metal' => 60000,
        'crystal' => 30000,
        'deuterium' => 15000
    ];

    $shipsValue = $params['shipsValue'];
    $expeditionHours = $params['expeditionHours'] ?: 1;

    $minFactor = 0;
    $maxFactor = 4;

    if ($expeditionHours == 2) {
        $maxFactor = 5;
    } else if ($expeditionHours == 3) {
        $maxFactor = 6;
    } else if ($expeditionHours == 4) {
        $maxFactor = 7;
    } else if ($expeditionHours >= 5 && $expeditionHours < 8) {
        $minFactor = 1;
        $maxFactor = 7;
    } else if ($expeditionHours >= 8) {
        $minFactor = 1;
        $maxFactor = 9;
    }

//    echo "Ships value: $shipsValue\n";
//    echo "Expedition hours: $expeditionHours\n";
//    echo "Min factor: $minFactor\n";
//    echo "Max factor: $maxFactor\n";
//    trigger_error('calculateFleetValue', E_USER_ERROR);

    $resourcesFound = [
        'metal' => floor(($baseResources['metal'] * getRandomFactor($minFactor, $maxFactor) * log($shipsValue / 15, 1.55) * pow(1.1, log($shipsValue / 15, 1.55)))) * $expeditionHours,
        'crystal' => floor(($baseResources['crystal'] * getRandomFactor($minFactor, $maxFactor) * log($shipsValue / 15, 1.55) * pow(1.1, log($shipsValue / 15, 1.55)))) * $expeditionHours,
        'deuterium' => floor(($baseResources['deuterium'] * getRandomFactor($minFactor, $maxFactor) * log($shipsValue / 15, 1.55) * pow(1.1, log($shipsValue / 15, 1.55)))) * $expeditionHours
    ];

    return [
        'gains' => [
            'planetaryResources' => $resourcesFound
        ]
    ];
}

?>
