<?php

if ($Viewer->disableForums()) {
    json_die('failure');
}

$PerPage = $Viewer->postPerPage();
[$Page, $Limit] = Format::page_limit($PerPage);

$ShowCollapsed = (bool)($_GET['collapse'] ?? true);
$ShowUnread = (bool)($_GET['showunread'] ?? true);

$cond = [
    "p.ID <= IFNULL(l.PostID, t.LastPostID)",
    Forums::user_forums_sql(),
    "s.UserID = ?",
];
$args = [
    $Viewer->id()
];
if ($ShowUnread) {
    $cond[] = "IF(l.PostID IS NULL OR (t.IsLocked = '1' && t.IsSticky = '0'), t.LastPostID, l.PostID) < t.LastPostID";
}

$from = "FROM forums_posts AS p
    INNER JOIN forums_topics AS t ON (t.ID = p.TopicID)
    INNER JOIN users_subscriptions AS s ON (s.TopicID = t.ID)
    INNER JOIN forums AS f ON (f.ID = t.ForumID)
    INNER JOIN forums_last_read_topics AS l ON (l.TopicID = p.TopicID AND l.UserID = s.UserID)
    WHERE " . implode( ' AND ', $cond) . "
    GROUP BY t.ID";

$NumResults = $DB->scalar("
    SELECT count(DISTINCT t.ID) $from
    ", ...$args
);

$DB->prepared_query("
    SELECT MAX(p.ID) AS ID $from ORDER BY t.LastPostID DESC LIMIT $Limit
    ", ...$args
);
$postIds = $DB->collect('ID');

if ($NumResults > $PerPage * ($Page - 1)) {
    $DB->prepared_query("
        SELECT f.ID AS ForumID,
            f.Name AS ForumName,
            p.TopicID,
            t.Title,
            t.LastPostID,
            t.IsLocked,
            p.ID
        FROM forums_posts AS p
        INNER JOIN forums_topics AS t ON (t.ID = p.TopicID)
        INNER JOIN forums AS f ON (f.ID = t.ForumID)
        WHERE p.ID IN (" . placeholders($postIds) . ")
        ORDER BY f.Name ASC, t.LastPostID DESC
        ", ...$postIds
    );
}

$JsonPosts = [];
while ([$ForumID, $ForumName, $TopicID, $ThreadTitle, $LastPostID, $Locked, $PostID] = $DB->next_record(MYSQLI_NUM, false)) {
    $JsonPosts[] = [
        'forumId'     => $ForumID,
        'forumName'   => $ForumName,
        'threadId'    => $TopicID,
        'threadTitle' => $ThreadTitle,
        'postId'      => $PostID,
        'lastPostId'  => $LastPostID,
        'locked'      => $Locked == 1,
        'new'         => ($PostID < $LastPostID && !$Locked)
    ];
}

json_print('success', [
    'threads' => $JsonPosts,
]);
