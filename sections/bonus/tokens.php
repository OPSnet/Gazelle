<?php

/** @var \Gazelle\User\Bonus $viewerBonus */

authorize();

if (!preg_match('/^token-[1-4]$/', $Label, $match)) {
    error(403);
}

if (!$viewerBonus->purchaseToken($Label)) {
    error("You aren't able to buy those tokens. Do you have enough bonus points?");
}

header('Location: bonus.php?complete=' . urlencode($Label));
