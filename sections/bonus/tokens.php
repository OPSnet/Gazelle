<?php
/** @phpstan-var \Gazelle\User $Viewer */

/**
 * @var string $Label
 */

authorize();

if (!preg_match('/^token-[1-4]$/', $Label, $match)) {
    error(403);
}

$viewerBonus = new \Gazelle\User\Bonus($Viewer);
if (!$viewerBonus->purchaseToken($Label)) {
    error("You aren't able to buy those tokens. Do you have enough bonus points?");
}

header('Location: bonus.php?complete=' . urlencode($Label));
