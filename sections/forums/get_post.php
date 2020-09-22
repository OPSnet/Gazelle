<?php

// Quick SQL injection check
$postId = (int)$_GET['post'];
if (!$postId) {
    error(0);
}

[$body, $forumId] = (new Gazelle\Forum)->postBody($postId);
if (!Forums::check_forumperm($forumId)) {
    error(0);
}
echo trim(display_str($body));
