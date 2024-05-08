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

    if ($rollValue >= 0 && $rollValue < 30) {
        return ExpeditionEvent::NothingHappened;
    }
    if ($rollValue >= 30 && $rollValue < 100) {
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

function getRandomFactor()
{
    return mt_rand(0, 4) / 10;
}

/**
 * @param array $params
 */
function getExpeditionEventPlanetaryResourcesFoundOutcome($params)
{
    // Base amount of resources
    $baseResources = [
        'metal' => 60000,
        'crystal' => 30000,
        'deuterium' => 15000
    ];

    // Total value of ships in the expedition
    $shipsValue = $params['shipsValue'];
    $expeditionHours = $params['$expeditionHours'] ?: 1;

    $resourcesFound = [
        'metal' => floor(($baseResources['metal'] * getRandomFactor() * log($shipsValue, 1.55) * pow(1.1, log($shipsValue, 1.55)))) * $expeditionHours,
        'crystal' => floor(($baseResources['crystal'] * getRandomFactor() * log($shipsValue, 1.55) * pow(1.1, log($shipsValue, 1.55)))) * $expeditionHours,
        'deuterium' => floor(($baseResources['deuterium'] * getRandomFactor() * log($shipsValue, 1.55) * pow(1.1, log($shipsValue, 1.55)))) * $expeditionHours
    ];

    // Calculate the resources found
//    $resourcesFound = [
//        'metal' => floor(($baseResources['metal'] * $shipsValue * getRandomFactor())),
//        'crystal' => floor(($baseResources['crystal'] * $shipsValue * getRandomFactor())),
//        'deuterium' => floor(($baseResources['deuterium'] * $shipsValue * getRandomFactor()))
//    ];

    return [
        'gains' => [
            'planetaryResources' => $resourcesFound
        ]
    ];
}

?>
