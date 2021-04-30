<?php
authorize();

if ($Bonus->purchaseInvite($LoggedUser['ID'])) {
    header('Location: bonus.php?complete=invite');
}
else {
    error(403);
}
