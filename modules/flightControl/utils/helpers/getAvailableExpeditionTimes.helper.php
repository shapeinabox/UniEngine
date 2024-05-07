<?php

namespace UniEngine\Engine\Modules\FlightControl\Utils\Helpers;

function getAvailableExpeditionTimes()
{
//    $availableOptions = range(0.1, 1, 0.1);
    $availableOptions = [0.6, 1, 2, 3, 4];

    return $availableOptions;
}

?>
