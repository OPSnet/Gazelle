<?php
// perform the back end of subscribing to topics
authorize();

$user = new Gazelle\User($LoggedUser['ID']);
if ($user->disableForums()) {
    error(403);
}

$threadId = (int)$_GET['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
if (!$user->readAccess($forum)) {
    error(403);
}

(new Gazelle\Manager\Subscription($user->id()))->subscribe($threadId);
