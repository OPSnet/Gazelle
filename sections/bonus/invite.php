<?php

/** @var \Gazelle\Bonus $viewerBonus */

authorize();
if (!$viewerBonus->purchaseInvite()) {
    error(403);
}
header('Location: bonus.php?complete=invite');
