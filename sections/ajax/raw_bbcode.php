<?php

$PostID = (int)$_POST['postid'];

if (empty($PostID)) {
    json_die("failure", "empty postid");
}

$DB->prepared_query("
    SELECT t.ForumID, p.Body
    FROM forums_posts AS p
    INNER JOIN forums_topics AS t ON (p.TopicID = t.ID)
    WHERE p.ID = ?
    ", $PostID
);

if (!$DB->has_results()) {
    json_die("failure", "no results");
}

list($ForumID, $Body) = $DB->next_record();
if (!Forums::check_forumperm($ForumID)) {
    json_die("failure", "assholes");
}

json_die("success", ["body" => nl2br($Body)]);
