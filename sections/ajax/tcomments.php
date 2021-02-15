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

$JsonComments = [];
foreach ($thread as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername] = array_values($Post);
    [$AuthorID, $Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $UserTitle] = array_values(Users::user_info($AuthorID));
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
            'authorName' => $Username,
            'donor'      => $Donor == 1,
            'warned'     => !is_null($Warned),
            'avatar'     => $Avatar,
            'enabled'    => $Enabled == '1',
            'userTitle'  => $UserTitle
        ]
    ];
}

json_print("success", [
    'page'     => $commentPage->pageNum(),
    'pages'    => (int)ceil($commentPage->total() / TORRENT_COMMENTS_PER_PAGE),
    'comments' => $JsonComments
]);
