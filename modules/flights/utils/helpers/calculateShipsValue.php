<?php

namespace UniEngine\Engine\Modules\Flights\Utils\Helpers;

/**
 * @param array $params
 *
 * Calculates the value of a fleet based on the ships it contains
 *
 * The value of a single ship is calculated by the sum of its cost divided by its capacity and multiplied by 0.1
 *
 */
function calculateFleetValue($ships)
{
    global $_Vars_Prices;

    $totalValue = 0;

    foreach ($ships as $shipId => $shipsAmount) {
        $shipData = $_Vars_Prices[$shipId];
        if ($shipData === null) {
            continue;
        }
        $totalValue += $shipsAmount * ((($shipData['metal'] + $shipData['crystal'] + $shipData['deuterium']) / $shipData['capacity']) * 0.1);
    }

    return $totalValue;
}

?>
