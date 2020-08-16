<?php
authorize();

if ($Bonus->purchaseCollage($LoggedUser['ID'], $Label)) {
    header("Location: bonus.php?complete=$Label");
}
else {
    error(403);
}
