<?php

namespace UniEngine\Engine\Modules\Flights\Utils\Helpers;

/**
 * @param array $params
 * The parametere is a key-value array
 */
function calculateShipsValue($ships)
{
    global $_Vars_Prices;

    $totalValue = 0;

    foreach ($ships as $shipId => $shipsAmount) {
        $totalValue += $shipsAmount * ($_Vars_Prices[$shipId]['metal'] + $_Vars_Prices[$shipId]['crystal'] + $_Vars_Prices[$shipId]['deuterium']);
    }

    return $totalValue;
}

?>
