<?php

namespace UniEngine\Engine\Modules\Development\Components\GridViewElementCard\UpgradeRequirements\ResourcesList;


use UniEngine\Engine\Includes\Helpers\World\Elements;
use UniEngine\Engine\Includes\Helpers\World\Resources;

//  Arguments
//      - $props (Object)
//          - elementID (String)
//          - user (Object)
//          - planet (Object)
//          - isQueueActive (Boolean)
//
//  Returns: Object
//      - componentHTML (String)
//
function render($props)
{
    global $_SkinPath, $_Vars_Prices;

    $localTemplateLoader = createLocalTemplateLoader(__DIR__);

    $tplBodyCache = [
        'resource_box' => $localTemplateLoader('resource_box'),
        'transport_needed' => $localTemplateLoader('transport_needed'),
    ];

    $elementID = $props['elementID'];
    $planet = $props['planet'];
    $user = $props['user'];
    $isQueueActive = $props['isQueueActive'];


    $resourceIcons = [
        'metal' => 'metall',
        'crystal' => 'kristall',
        'deuterium' => 'deuterium',
        'energy' => 'energie',
        'energy_max' => 'energie',
        'darkEnergy' => 'darkenergy'
    ];

    $upgradeCost = Elements\calculatePurchaseCost(
        $elementID,
        Elements\getElementState($elementID, $planet, $user),
        [
            'purchaseMode' => Elements\PurchaseMode::Upgrade
        ]
    );

    $subcomponentsResourceBoxesHTML = [];

    $totalCost = 0;
    $totalDeficitCost = 0;

    foreach ($upgradeCost as $costResourceKey => $costValue) {
        $currentResourceState = Resources\getResourceState(
            $costResourceKey,
            $user,
            $planet
        );
        $resourceLeft = ($currentResourceState - $costValue);
        $hasResourceDeficit = ($resourceLeft < 0);

        $resourceCostColor = classNames([
            'orange' => ($hasResourceDeficit && $isQueueActive),
            'red' => ($hasResourceDeficit && !$isQueueActive),
        ]);
        $resourceDeficitColor = classNames([
            'red' => $hasResourceDeficit,
        ]);
        $resourceDeficitValue = (
        $hasResourceDeficit ?
            '(' . prettyNumber($resourceLeft) . ')' :
            '&nbsp;'
        );

        $totalCost += $costValue;
        if ($hasResourceDeficit) {
            $totalDeficitCost += abs($resourceLeft);
        }

        $resourceCostTPLData = [
            'SkinPath' => $_SkinPath,
            'ResKey' => $costResourceKey,
            'ResImg' => $resourceIcons[$costResourceKey],
            'ResColor' => $resourceCostColor,
            'Value' => prettyNumber($costValue),
            'ResMinusColor' => $resourceDeficitColor,
            'MinusValue' => $resourceDeficitValue,
        ];

        $subcomponentsResourceBoxesHTML[] = parsetemplate(
            $tplBodyCache['resource_box'],
            $resourceCostTPLData
        );
    }

    $totalMegaCargoNeeded = floor($totalCost / $_Vars_Prices[217]['capacity']) + 1;
    $deficitMegaCargoNeeded = ($totalDeficitCost > 0) ? floor($totalDeficitCost / $_Vars_Prices[217]['capacity']) + 1 : 0;

    $subcomponentsResourceBoxesHTML[] = parsetemplate(
        $tplBodyCache['transport_needed'],
        [
            'TotalNeeded' => $totalMegaCargoNeeded > 0 ? $totalMegaCargoNeeded : '&nbsp;',
            'MinusNeeded' => $deficitMegaCargoNeeded > 0 ? $deficitMegaCargoNeeded : '&nbsp;',
        ]
    );

    $componentHTML = implode('', $subcomponentsResourceBoxesHTML);

    return [
        'componentHTML' => $componentHTML
    ];
}

?>
