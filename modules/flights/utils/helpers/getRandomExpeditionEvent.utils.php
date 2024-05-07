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

/**
 * @param array $params
 */
function getExpeditionEventPlanetaryResourcesFoundOutcome($params)
{
    // Base amount of resources
    $baseResources = [
        'metal' => 30000,
        'crystal' => 20000,
        'deuterium' => 10000
    ];

    // Total value of ships in the expedition
    $shipsValue = $params['shipsValue'];

    // Random factor for resources found
    $randomFactor = mt_rand(0.002, 0.01);

    // Calculate the resources found
    $resourcesFound = [
        'metal' => floor(($baseResources['metal'] * $shipsValue * $randomFactor) / 1000),
        'crystal' => floor(($baseResources['crystal'] * $shipsValue * $randomFactor) / 1000),
        'deuterium' => floor(($baseResources['deuterium'] * $shipsValue * $randomFactor) / 1000)
    ];

    return [
        'gains' => [
            'planetaryResources' => $resourcesFound
        ]
    ];
}

?>
