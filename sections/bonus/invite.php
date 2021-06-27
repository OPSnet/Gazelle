<?php
authorize();

if (!$Bonus->purchaseInvite($Viewer->id())) {
    error(403);
}
header('Location: bonus.php?complete=invite');
