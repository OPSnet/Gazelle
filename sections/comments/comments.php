<?php
/*
 * $_REQUEST['action'] is artist, collages, requests or torrents (default torrents)
 * $_REQUEST['type'] depends on the page:
 *     collages:
 *        created = comments left on one's collages
 *        contributed = comments left on collages one contributed to
 *     requests:
 *        created = comments left on one's requests
 *        voted = comments left on requests one voted on
 *     torrents:
 *        uploaded = comments left on one's uploads
 *     If missing or invalid, this defaults to the comments one made
 */

$userMan = new Gazelle\Manager\User;
if (!isset($_GET['id'])) {
    $User = $Viewer;
} else {
    $User = $userMan->findById((int)($_GET['id'] ?? 0));
    if (is_null($User)) {
        error(404);
    }
    if (!$User->propertyVisible($Viewer, 'torrentcomments')) {
        error(403);
    }
}
$UserID   = $User->id();
$Username = $User->username();

if ($Viewer->id() == $UserID) {
    $ownProfile = true;
    $linkId     = '';
} else {
    $ownProfile = false;
    $linkId     = "&amp;id=$UserID";
}

$Action   = $_REQUEST['action'] ?? 'torrents';
$Type     = $_REQUEST['type'] ?? 'default';
$BaseLink = "comments.php?action=$Action$linkId";

// SQL components
$condArgs  = [];
$condition = [];
$Join      = [];
$joinArgs  = [];

$TypeLinks = [];

switch ($Action) {
    case 'artist':
        $Title       = '%s &rsaquo; Artist comments';
        $table       = 'artists_group AS ag';
        $idField     = 'ag.ArtistID';
        $nameField   = 'ag.Name';
        $condition[] = "C.AuthorID = ?";
        $condArgs[]  = $UserID;
        break;

    case 'collages':
        $table       = 'collages AS cl';
        $idField     = 'cl.ID';
        $nameField   = 'cl.Name';
        $condition[] = "cl.Deleted = '0'";

        switch ($Type) {
            case 'created':
                $Title = "%s &rsaquo; Comments on their collages";
                $condition[] = "cl.UserID = ?";
                $condition[] = "C.AuthorID != ?";
                $condArgs[] = $UserID;
                $condArgs[] = $UserID;
                $TypeLinks = [
                    [$BaseLink, "$Username &rsaquo; Collage comments"],
                    ["$BaseLink&amp;type=contributed", "$Username &rsaquo; Contributed collage comments"],
                ];
                break;
            case 'contributed':
                $Title = '%s &rsaquo; Contributed collage comments';
                $condition[] = "C.AuthorID != ? AND cl.ID IN (
                    SELECT DISTINCT CollageID FROM collages_torrents ct WHERE ct.UserID = ?
                    UNION ALL
                    SELECT DISTINCT CollageID FROM collages_artists ca WHERE ca.UserID = ?)";
                $condArgs = array_merge($condArgs, [$UserID, $UserID, $UserID]);
                $TypeLinks = [
                    [$BaseLink, "$Username &rsaquo; Collage comments"],
                    ["$BaseLink&amp;type=created", "$Username &rsaquo; Comments on their collages"],
                ];
                break;
            default:
                $Title = "%s &rsaquo; Collage comments";
                $condition[] = "C.AuthorID = ?";
                $condArgs[]  = $UserID;
                $TypeLinks = [
                    ["$BaseLink&amp;type=contributed", "$Username &rsaquo; Contributed collage comments"],
                    ["$BaseLink&amp;type=created", "$Username &rsaquo; Comments on their collages"],
                ];
                break;
        }
        break;

    case 'requests':
        $table      = 'requests AS r';
        $idField    = 'r.ID';
        $nameField  = 'r.Title';

        switch($Type) {
            case 'created':
                $Title = "%s &rsaquo; Comments on their requests";
                $condition[] = "r.UserID = ?";
                $condition[] = "C.AuthorID != ?";
                $condArgs[] = $UserID;
                $condArgs[] = $UserID;
                $TypeLinks = [
                    [$BaseLink, "$Username &rsaquo; Request comments"],
                    ["$BaseLink&amp;type=contributed", "$Username &rsaquo; Voted-on request comments"],
                ];
                break;
            case 'voted':
                $Title = "%s &rsaquo; Comments on voted-on requests";
                $Join[] = 'INNER JOIN requests_votes rv ON (rv.RequestID = r.ID)';
                $condition[] = "rv.UserID = ?";
                $condition[] = "C.AuthorID != ?";
                $condArgs[] = $UserID;
                $condArgs[] = $UserID;
                $TypeLinks = [
                    [$BaseLink, "$Username &rsaquo; Request comments"],
                    ["$BaseLink&amp;type=created", "$Username &rsaquo; Created request comments"],
                ];
                break;
            default:
                $Title = "%s &rsaquo; Request comments";
                $condition[] = "C.AuthorID = ?";
                $condArgs[] = $UserID;
                $TypeLinks = $ownProfile
                    ? [
                        ["$BaseLink&amp;type=created", "Comments on your created requests"],
                        ["$BaseLink&amp;type=contributed", "Your comments on requests"],
                    ]
                    : [
                        ["$BaseLink&amp;type=created", "$Username &rsaquo; Created request comments"],
                        ["$BaseLink&amp;type=contributed", "$Username &rsaquo; Request comments"],
                    ];
                break;
        }
        break;

    case 'torrents':
        $table     = 'torrents_group AS tg';
        $idField   = 'tg.ID';
        $nameField = 'tg.Name';

        switch($Type) {
            case 'uploaded':
                $Title = "%s &rsaquo; Comments on their uploads";
                $Join[] = 'INNER JOIN torrents t ON (t.GroupID = tg.ID)';
                $condition[] = 'C.AddedTime > t.Time';
                $condition[] = "C.AuthorID != ?";
                $condition[] = "t.UserID = ?";
                $condArgs[] = $UserID;
                $condArgs[] = $UserID;
                $TypeLinks[] = [
                    $BaseLink,
                    $ownProfile ? "Your torrent comments" : "$Username &rsaquo; Torrent comments"
                ];
                break;
            default:
                $Title = "%s &rsaquo; Torrent comments";
                $condition[] = "C.AuthorID = ?";
                $condArgs[] = $UserID;
                $TypeLinks[] = [
                    "$BaseLink&amp;type=uploaded",
                    $ownProfile ? "Comments on your uploads" : "$Username &rsaquo; Comments on their uploads"
                ];
                break;
        }
        break;
}

$Join[] = "INNER JOIN comments C ON (C.Page = ? AND C.PageID = $idField)";
$joinArgs[] = $Action;
$Join = implode("\n", $Join);
$cond = $condition ? 'WHERE ' . implode(" AND ", $condition) : '';

// Posts per page limit stuff
$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    $DB->scalar("
        SELECT count(DISTINCT(C.ID)) FROM $table $Join $cond", ...array_merge($joinArgs, $condArgs)
    )
);

$Comments = $DB->prepared_query("
    SELECT C.AuthorID,
        C.Page,
        C.PageID,
        $nameField,
        C.ID,
        C.Body,
        C.AddedTime,
        C.EditedTime,
        C.EditedUserID
    FROM $table
    $Join
    $cond
    GROUP BY C.ID
    ORDER BY C.ID DESC
    LIMIT ? OFFSET ?
    ", ...[...$joinArgs, ...$condArgs, $paginator->limit(), $paginator->offset()]
);

$requestList = [];
$tgroupList = [];
if ($Action == 'requests') {
    $requestMan = new Gazelle\Manager\Request;
    foreach (array_flip(array_flip($DB->collect('PageID'))) as $id) {
        $requestList[$id] = $requestMan->findById($id);
    }
} elseif ($Action == 'torrents') {
    $tgtMan = new Gazelle\Manager\TGroup;
    foreach (array_flip(array_flip($DB->collect('PageID'))) as $id) {
        $tgroupList[$id] = $tgMan->findById($id);
    }
}

$Links = implode(' ',
    // show links to the other types of pages having comments
    array_map(fn($a) => "<a href=\"comments.php?action=$a$linkId\" class=\"brackets\">" . ucfirst(rtrim($a, 's')) . ' comments</a>',
        array_filter(['artist', 'collages', 'requests', 'torrents'], fn($a) => $a != $Action)
    )
);
if ($TypeLinks) {
    // and any extra links for this page type
    $Links .= ' <br />' . implode(' ', array_map(
        fn($link) => sprintf('<a href="%s" class="brackets">%s</a>', $link[0], $link[1]), $TypeLinks
    ));
}

View::show_header(sprintf($Title, $Username), ['js' => 'bbcode,comments']);
?>
<div class="thin">
    <div class="header">
        <h2><?= sprintf($Title, $User->link()) ?></h2>
<?php if ($Links != '') { ?>
        <div class="linkbox">
            <?= $Links ?>
        </div>
<?php } ?>
    </div>
<?php if (!$paginator->total()) { ?>
    <div class="center">No results.</div>
<?php
} else {
    echo $paginator->linkbox();
    $commentMan = new Gazelle\Manager\Comment;
    $DB->set_query_id($Comments);
    while ([$AuthorID, $Page, $PageID, $Name, $PostID, $Body, $AddedTime, $EditedTime, $EditedUserID] = $DB->next_record()) {
        $author = new Gazelle\User($AuthorID);
        echo $Twig->render('comment/comment.twig', [
            'added_time'  => $AddedTime,
            'author'      => $author,
            'avatar'      => $userMan->avatarMarkup($Viewer, $author),
            'body'        => $Body,
            'editor'      => $userMan->findById((int)$EditedUserID),
            'edit_time'   => $EditedTime,
            'id'          => $PostID,
            'heading'     => match($Page) {
                'artist'   => "<a href=\"artist.php?id=$PageID\">$Name</a>",
                'collages' => "<a href=\"collages.php?id=$PageID\">$Name</a>",
                'requests' => $requestList[$PageID]->smartLink(),
                'torrents' => $tgroupList[$PageID]->link(),
            },
            'page'        => $Action,
            'url'         => $commentMan->findById($PostID)->url(),
            'viewer'      => $Viewer,
        ]);
        $DB->set_query_id($Comments);
    }
    echo $paginator->linkbox();
}
?>
</div>
<?php
View::show_footer();
