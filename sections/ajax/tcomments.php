<?php

declare(strict_types=1);

namespace Gazelle;

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) {
    json_die("failure");
}

$commentPage = new Comment\Torrent($groupId, (int)($_GET['page'] ?? 1), (int)($_GET['postid'] ?? 0));
$thread = $commentPage->load()->thread();

$userCache = [];
$userMan = new Manager\User();

$JsonComments = [];
foreach ($thread as $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername] = array_values($Post);
    $userCache[$AuthorID] ??= $userMan->findById((int)$AuthorID);
    $author = $userCache[$AuthorID];
    $JsonComments[] = [
        'postId'         => $PostID,
        'addedTime'      => $AddedTime,
        'bbBody'         => $Body,
        'body'           => \Text::full_format($Body),
        'editedUserId'   => $EditedUserID,
        'editedTime'     => $EditedTime,
        'editedUsername' => $EditedUsername,
        'userinfo' => [
            'authorId'   => $AuthorID,
            'authorName' => $author->username(),
            'donor'      => new User\Donor($author)->isDonor(),
            'warned'     => $author->warning()->isWarned(),
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
