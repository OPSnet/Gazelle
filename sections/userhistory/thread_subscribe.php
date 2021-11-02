<?php
// perform the back end of subscribing to topics

if ($Viewer->disableForums()) {
    error(403);
}
authorize();

$threadId = (int)$_GET['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
if (!$Viewer->readAccess($forum)) {
    error(403);
}

(new Gazelle\Subscription($Viewer))->subscribe($threadId);
