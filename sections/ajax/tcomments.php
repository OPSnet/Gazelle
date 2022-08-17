<?php
$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) {
    json_die("failure");
}

$commentPage = new Gazelle\Comment\Torrent($groupId);
if (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$thread = $commentPage->load()->thread();

$userCache = [];
$userMan = new Gazelle\Manager\User;

$JsonComments = [];
foreach ($thread as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername] = array_values($Post);
    if (!isset($userCache[$AuthorID])) {
        $userCache[$AuthorID] = $userMan->findById((int)$AuthorID);
    }
    $author = $userCache[$AuthorID];
    $JsonComments[] = [
        'postId'         => $PostID,
        'addedTime'      => $AddedTime,
        'bbBody'         => $Body,
        'body'           => Text::full_format($Body),
        'editedUserId'   => $EditedUserID,
        'editedTime'     => $EditedTime,
        'editedUsername' => $EditedUsername,
        'userinfo' => [
            'authorId'   => $AuthorID,
            'authorName' => $author->username(),
            'donor'      => (new Gazelle\User\Privilege($author))->isDonor(),
            'warned'     => $author->isWarned(),
            'avatar'     => $author->avatar(),
            'enabled'    => $author->isEnabled(),
            'userTitle'  => $author->title(),
        ]
    ];
}

json_print("success", [
    'page'     => $commentPage->pageNum(),
    'pages'    => (int)ceil($commentPage->total() / TORRENT_COMMENTS_PER_PAGE),
    'comments' => $JsonComments
]);
