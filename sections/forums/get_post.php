<?php

/*********************************************************************\
//--------------Get Post--------------------------------------------//

This gets the raw BBCode of a post. It's used for editing and
quoting posts.

It gets called if $_GET['action'] == 'get_post'. It requires
$_GET['post'], which is the ID of the post.

\*********************************************************************/

// Quick SQL injection check
$postId = (int)$_GET['post'];
if ($postId < 1) {
    error(0);
}

list($body, $forumId) = $DB->row("
    SELECT
        p.Body,
        t.ForumID
    FROM forums_posts AS p
    INNER JOIN forums_topics AS t ON (p.TopicID = t.ID)
    WHERE p.ID = ?
    ", $postId
);

// Is the user allowed to view the post?
if (!Forums::check_forumperm($forumId)) {
    error(0);
}

// This gets sent to the browser, which echoes it wherever
echo trim($body);
