<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$collage = (new Gazelle\Manager\Collage())->findById((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}
$collage->toggleSubscription($Viewer);
