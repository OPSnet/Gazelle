<?php

$postId = (int)$_POST['id'];
if (!($postId && $Viewer->permitted('site_moderate_forums'))) {
    json_error('no post id');
}

Gazelle\DB::DB()->prepared_query("
    UPDATE reports SET
        Notes = ?
    WHERE ID = ?
    ", str_replace("<br />", "\n", trim($_POST['notes'])), $postId
);
print json_encode(['status' => 'success']);
