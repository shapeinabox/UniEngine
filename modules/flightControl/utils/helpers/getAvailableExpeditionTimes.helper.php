<?php

namespace UniEngine\Engine\Modules\FlightControl\Utils\Helpers;

function getAvailableExpeditionTimes()
{
    $availableOptions = range(0.25, 1, 0.25);

    return $availableOptions;
}

?>
