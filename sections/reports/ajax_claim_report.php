<?php

$postId = (int)$_POST['id'];
if (!($postId && check_perms('site_moderate_forums'))) {
    json_error('no post id');
}

$claimerId = $DB->scalar("
    SELECT ClaimerID FROM reports WHERE ID = ?
    ", $postId
);
if ($ClaimerID) {
    print json_encode([
        'status' => 'dupe'
    ]);
} else {
    $DB->prepared_query("
        UPDATE reports SET
            ClaimerID = ?
        WHERE ID = ?
        ", $Viewer->id(), $postId
    );
    print json_encode([
        'status' => 'success',
        'username' => $Viewer->username()
    ]);
}
