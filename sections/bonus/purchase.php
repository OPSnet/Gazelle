<?php

authorize();

$label = $_REQUEST['label'];
$bonus = new Gazelle\User\Bonus($Viewer);

if ($label === 'collage-1') {
    if (!$bonus->purchaseCollage($label)) {
        error('Could not purchase a personal collage slot due to lack of funds.');
    }
    header("Location: bonus.php?complete=$label");
} elseif ($label === 'seedbox') {
    if (!$bonus->unlockSeedbox()) {
        error('Could not unlock the seedbox viewer. Either you have already unlocked it, or you lack the required bonus points.');
    }
    header("Location: bonus.php?complete=$label");
} else {
    error(403);
}
