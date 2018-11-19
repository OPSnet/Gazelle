<?php
authorize();

if ($Bonus->purchaseInvite(G::$LoggedUser['ID'])) {
    header('Location: bonus.php?complete=invite');
} else {
    error(403);
}
