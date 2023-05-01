<?php
// perform the back end of subscribing to collages
authorize();

$collageId = (int)($_GET['collageid'] ?? 0);
if (!$collageId) {
    error(404);
}
(new Gazelle\Collage($collageId))->toggleSubscription($Viewer);
