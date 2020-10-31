<?php
authorize();

$label = $_REQUEST['label'];
switch($label) {
    case 'collage-1':
        if ($Bonus->purchaseCollage($LoggedUser['ID'], $label)) {
            header("Location: bonus.php?complete=$label");
            exit;
        }
        break;
    case 'seedbox':
        if ($Bonus->unlockSeedbox($LoggedUser['ID'])) {
            header("Location: bonus.php?complete=$label");
            exit;
        }
}
error(403);
