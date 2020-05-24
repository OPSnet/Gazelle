<?php
if (empty($_GET['id']) || !is_number($_GET['id'])) {
    json_die("failure");
}

list($NumComments, $Page, $Thread) = Comments::load('torrents', (int)$_GET['id'], false);

//---------- Begin printing
$JsonComments = [];
foreach ($Thread as $Key => $Post) {
    list($PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername) = array_values($Post);
    list($AuthorID, $Username, $PermissionID, $Paranoia, $Artist, $Donor, $Warned, $Avatar, $Enabled, $UserTitle) = array_values(Users::user_info($AuthorID));
    $JsonComments[] = [
        'postId' => (int)$PostID,
        'addedTime' => $AddedTime,
        'bbBody' => $Body,
        'body' => Text::full_format($Body),
        'editedUserId' => (int)$EditedUserID,
        'editedTime' => $EditedTime,
        'editedUsername' => $EditedUsername,
        'userinfo' => [
            'authorId' => (int)$AuthorID,
            'authorName' => $Username,
            'artist' => $Artist == 1,
            'donor' => $Donor == 1,
            'warned' => !is_null($Warned),
            'avatar' => $Avatar,
            'enabled' => ($Enabled == '1' ? true : false),
            'userTitle' => $UserTitle
        ]
    ];
}

json_print("success", [
    'page' => (int)$Page,
    'pages' => ceil($NumComments / TORRENT_COMMENTS_PER_PAGE),
    'comments' => $JsonComments
]);
