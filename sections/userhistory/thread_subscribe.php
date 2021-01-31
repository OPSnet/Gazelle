<?php
// perform the back end of subscribing to topics
authorize();

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

$threadId = (int)$_GET['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
if (!Forums::check_forumperm($forum->id())) {
    error(403);
}

(new Gazelle\Manager\Subscription($LoggedUser['ID']))->subscribe($threadId);
