<?php

//------------- Lock old threads ----------------------------------------//
sleep(10);
$DB->query("
		SELECT t.ID, t.ForumID
		FROM forums_topics AS t
			JOIN forums AS f ON t.ForumID = f.ID
		WHERE t.IsLocked = '0'
			AND t.IsSticky = '0'
			AND DATEDIFF(CURDATE(), DATE(t.LastPostTime)) / 7 > f.AutoLockWeeks
			AND f.AutoLock = '1'");
$IDs = $DB->collect('ID');
$ForumIDs = $DB->collect('ForumID');

if (count($IDs) > 0) {
    $LockIDs = implode(',', $IDs);
    $DB->query("
			UPDATE forums_topics
			SET IsLocked = '1'
			WHERE ID IN($LockIDs)");
    sleep(2);
    $DB->query("
			DELETE FROM forums_last_read_topics
			WHERE TopicID IN($LockIDs)");

    foreach ($IDs as $ID) {
        $Cache->begin_transaction("thread_$ID".'_info');
        $Cache->update_row(false, ['IsLocked' => '1']);
        $Cache->commit_transaction(3600 * 24 * 30);
        $Cache->expire_value("thread_$ID".'_catalogue_0', 3600 * 24 * 30);
        $Cache->expire_value("thread_$ID".'_info', 3600 * 24 * 30);
        Forums::add_topic_note($ID, 'Locked automatically by schedule', 0);
    }

    $ForumIDs = array_flip(array_flip($ForumIDs));
    foreach ($ForumIDs as $ForumID) {
        $Cache->delete_value("forums_$ForumID");
    }
}
