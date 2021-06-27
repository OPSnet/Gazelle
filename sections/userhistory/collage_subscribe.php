<?php
// perform the back end of subscribing to collages
authorize();

$collageId = (int)$_GET['collageid'];
if (!$collageId) {
    error(404);
}
$collage = new Gazelle\Collage($collageId);
$collage->toggleSubscription($Viewer->id());
