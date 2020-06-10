<?php
authorize();

// Make sure they are moderators
if (!check_perms('site_admin_forums')) {
    error(403);
}

$PostID = (int)$_GET['postid'];
if ($PostID < 1) {
    error(0);
}

// Get topic ID, forum ID, number of pages
list($TopicID, $ForumID, $Pages, $Page, $StickyPostID) = $DB->row("
    SELECT
        TopicID,
        ForumID,
        ceil(count(*) / ?) AS Pages,
        ceil(sum(if(p.ID <= ?, 1, 0)) / ?) AS Page,
        StickyPostID
    FROM forums_posts AS p
    INNER JOIN forums_topics AS t ON (t.ID = p.TopicID)
    WHERE p.TopicID = (
        SELECT TopicID
        FROM forums_posts
        WHERE ID = ?
    )
    GROUP BY t.ID
    ", POSTS_PER_PAGE, $PostID, POSTS_PER_PAGE, $PostID
);
if (!$TopicID) {
    // Post is deleted or thread doesn't exist
    error(0); // This is evil, but the ajax call doesn't check the response
}

$forum = new \Gazelle\Forum($ForumID);

// $Pages = number of pages in the thread
// $Page = which page the post is on
// These are set for cache clearing.

$DB->prepared_query("
    DELETE FROM forums_posts
    WHERE ID = ?
    ", $PostID
);

$LastID = $DB->scalar("
    SELECT max(ID)
    FROM forums_posts
    WHERE TopicID = ?
    ", $TopicID
);
$DB->query("
    UPDATE forums AS f, forums_topics AS t SET
        f.NumPosts = f.NumPosts - 1,
        t.NumPosts = t.NumPosts - 1
    WHERE f.ID = ?
        AND t.ID = ?
    ", $ForumID, $TopicID
);

if ($LastID < $PostID) { // Last post in a topic was removed
    list($LastAuthorID, $LastAuthorName, $LastTime) = $DB->row("
        SELECT p.AuthorID, u.Username, p.AddedTime
        FROM forums_posts AS p
            LEFT JOIN users_main AS u ON u.ID = p.AuthorID
        WHERE p.ID = ?
        ", $LastID
    );
    $DB->prepared_query("
        UPDATE forums_topics SET
            LastPostID = ?,
            LastPostAuthorID = ?,
            LastPostTime = ?
        WHERE ID = ?
        ", $LastID, $LastAuthorID, $LastTime, $TopicID
    );

    list($LastTopicID, $LastTopicTitle, $LastTopicPostID, $LastTopicPostTime, $LastTopicAuthorID, $LastTopicAuthorName) = $DB->row("
        SELECT
            t.ID,
            t.Title,
            t.LastPostID,
            t.LastPostTime,
            t.LastPostAuthorID,
            u.Username
        FROM forums_topics AS t
        LEFT JOIN users_main AS u ON (u.ID = t.LastPostAuthorID)
        WHERE ForumID = ?
            AND t.ID != ?
        ORDER BY LastPostID DESC
        LIMIT 1
        ", $ForumID, $TopicID
    );
    if ($LastID < $LastTopicPostID) { // Topic is no longer the most recent in its forum
        $DB->prepared_query("
            UPDATE forums SET
                LastPostTopicID  = ?,
                LastPostID       = ?,
                LastPostAuthorID = ?,
                LastPostTime     = ?
            WHERE ID = ?
                AND LastPostTopicID = ?
            ", $LastTopicID, $LastTopicPostID, $LastTopicAuthorID, $LastTopicPostTime,
                $ForumID, $TopicID
        );
    } else { // Topic is still the most recent in its forum
        $DB->query("
            UPDATE forums SET
                LastPostID       = ?,
                LastPostAuthorID = ?,
                LastPostTime     = ?
            WHERE ID = ?
                AND LastPostTopicID = ?
            ", $LastID, $LastAuthorID, $LastTime,
            $ForumID, $TopicID
        );
    }
}

if ($StickyPostID == $PostID) {
    $DB->prepared_query("
        UPDATE forums_topics SET
            StickyPostID = 0
        WHERE ID = ?
        ", $TopicID
    );
}

$DB->prepared_query("
    DELETE FROM users_notify_quoted
    WHERE Page = 'forums'
        AND PostID = ?
    ", $PostID
);

$subscription = new \Gazelle\Manager\Subscription;
$subscription->flush('forums', $TopicID);
$subscription->flushQuotes('forums', $TopicID);

//We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
$ThisCatalogue = floor((POSTS_PER_PAGE * $Page - POSTS_PER_PAGE) / THREAD_CATALOGUE);
$LastCatalogue = floor((POSTS_PER_PAGE * $Pages - POSTS_PER_PAGE) / THREAD_CATALOGUE);
for ($i = $ThisCatalogue; $i <= $LastCatalogue; $i++) {
    $Cache->delete_value("thread_{$TopicID}_catalogue_{$i}");
}

$Cache->delete_value("thread_{$TopicID}_info");
$Cache->delete_value('forums_list');
$Cache->delete_value("forums_$ForumID");
$forum->flushCache();
