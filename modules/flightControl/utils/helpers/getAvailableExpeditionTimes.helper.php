<?php

namespace UniEngine\Engine\Modules\FlightControl\Utils\Helpers;

function getAvailableExpeditionTimes()
{
//    $availableOptions = range(0.1, 1, 0.1);
    $availableOptions = [0.30, 0, 5, 0.7, 1, 2, 3, 4, 5];

    return $availableOptions;
}

?>
