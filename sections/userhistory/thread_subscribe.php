<?php
// perform the back end of subscribing to topics
authorize();

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

$TopicID = (int)$_GET['topicid'];
if (!$TopicID) {
    error(404);
}

$ForumID = $DB->scalar("
    SELECT f.ID
    FROM forums_topics AS t
    INNER JOIN forums AS f ON (f.ID = t.ForumID)
    WHERE t.ID = ?
    ", $TopicID
);
if (!Forums::check_forumperm($ForumID)) {
    error(403);
}

$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
$subscription->subscribe($TopicID);
