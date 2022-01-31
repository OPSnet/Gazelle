<?php

/** @var \Gazelle\User\Bonus $viewerBonus */

authorize();
if (!$viewerBonus->purchaseInvite()) {
    error("You cannot purchase an invite (either you don't have the privilege or you don't have enough bonus points).");
}
header('Location: bonus.php?complete=invite');
