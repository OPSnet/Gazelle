<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

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

$userMan = new Gazelle\Manager\User();
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
        $Title       = '%s › Artist comments';
        $table       = 'artists_group ag INNER JOIN artists_alias aa ON (ag.PrimaryAlias = aa.AliasID)';
        $idField     = 'ag.ArtistID';
        $nameField   = 'aa.Name';
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
                $Title = "%s › Comments on their collages";
                array_push($condition, "cl.UserID = ?", "C.AuthorID != ?");
                array_push($condArgs, $UserID, $UserID);
                $TypeLinks = [
                    [$BaseLink, "$Username › Collage comments"],
                    ["$BaseLink&amp;type=contributed", "$Username › Contributed collage comments"],
                ];
                break;
            case 'contributed':
                $Title = '%s › Contributed collage comments';
                $condition[] = "C.AuthorID != ? AND cl.ID IN (
                    SELECT DISTINCT CollageID FROM collages_torrents ct WHERE ct.UserID = ?
                    UNION ALL
                    SELECT DISTINCT CollageID FROM collages_artists ca WHERE ca.UserID = ?)";
                $condArgs = array_merge($condArgs, [$UserID, $UserID, $UserID]);
                $TypeLinks = [
                    [$BaseLink, "$Username › Collage comments"],
                    ["$BaseLink&amp;type=created", "$Username › Comments on their collages"],
                ];
                break;
            default:
                $Title = "%s › Collage comments";
                $condition[] = "C.AuthorID = ?";
                $condArgs[]  = $UserID;
                $TypeLinks = [
                    ["$BaseLink&amp;type=contributed", "$Username › Contributed collage comments"],
                    ["$BaseLink&amp;type=created", "$Username › Comments on their collages"],
                ];
                break;
        }
        break;

    case 'requests':
        $table      = 'requests AS r';
        $idField    = 'r.ID';
        $nameField  = 'r.Title';

        switch ($Type) {
            case 'created':
                $Title = "%s › Comments on their requests";
                array_push($condition, "r.UserID = ?", "C.AuthorID != ?");
                array_push($condArgs, $UserID, $UserID);
                $TypeLinks = [
                    [$BaseLink, "$Username › Request comments"],
                    ["$BaseLink&amp;type=contributed", "$Username › Voted-on request comments"],
                ];
                break;
            case 'voted':
                $Title = "%s › Comments on voted-on requests";
                $Join[] = 'INNER JOIN requests_votes rv ON (rv.RequestID = r.ID)';
                array_push($condition, "rv.UserID = ?", "C.AuthorID != ?");
                array_push($condArgs, $UserID, $UserID);
                $TypeLinks = [
                    [$BaseLink, "$Username › Request comments"],
                    ["$BaseLink&amp;type=created", "$Username › Created request comments"],
                ];
                break;
            default:
                $Title = "%s › Request comments";
                $condition[] = "C.AuthorID = ?";
                $condArgs[] = $UserID;
                $TypeLinks = $ownProfile
                    ? [
                        ["$BaseLink&amp;type=created", "Comments on your created requests"],
                        ["$BaseLink&amp;type=contributed", "Your comments on requests"],
                    ]
                    : [
                        ["$BaseLink&amp;type=created", "$Username › Created request comments"],
                        ["$BaseLink&amp;type=contributed", "$Username › Request comments"],
                    ];
                break;
        }
        break;

    case 'torrents':
        $table     = 'torrents_group AS tg';
        $idField   = 'tg.ID';
        $nameField = 'tg.Name';

        switch ($Type) {
            case 'uploaded':
                $Title = "%s › Comments on their uploads";
                $Join[] = 'INNER JOIN torrents t ON (t.GroupID = tg.ID)';
                array_push($condition, "C.AuthorID != ?",  "t.UserID = ?", 'C.AddedTime > t.created');
                array_push($condArgs, $UserID, $UserID);
                $TypeLinks[] = [
                    $BaseLink,
                    $ownProfile ? "Your torrent comments" : "$Username › Torrent comments"
                ];
                break;
            default:
                $Title = "%s › Torrent comments";
                $condition[] = "C.AuthorID = ?";
                $condArgs[] = $UserID;
                $TypeLinks[] = [
                    "$BaseLink&amp;type=uploaded",
                    $ownProfile ? "Comments on your uploads" : "$Username › Comments on their uploads"
                ];
                break;
        }
        break;
    default:
        error('What are you trying to comment on?');
}

$Join[] = "INNER JOIN comments C ON (C.Page = ? AND C.PageID = $idField)";
$joinArgs[] = $Action;
$Join = implode("\n", $Join);
$cond = 'WHERE ' . implode(" AND ", $condition);

$db = Gazelle\DB::DB();

// Posts per page limit stuff
$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    (int)$db->scalar("
        SELECT count(DISTINCT C.ID) FROM $table $Join $cond", ...array_merge($joinArgs, $condArgs)
    )
);

$Comments = $db->prepared_query("
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
    $requestMan = new Gazelle\Manager\Request();
    foreach (array_flip(array_flip($db->collect('PageID'))) as $id) {
        $id = (int)$id;
        $requestList[$id] = $requestMan->findById($id);
    }
} elseif ($Action == 'torrents') {
    $tgMan = new Gazelle\Manager\TGroup();
    foreach (array_flip(array_flip($db->collect('PageID'))) as $id) {
        $id = (int)$id;
        $tgroupList[$id] = $tgMan->findById($id);
    }
}

$Links = implode(' ',
    // show links to the other types of pages having comments
    array_map(fn ($a) => "<a href=\"comments.php?action=$a$linkId\" class=\"brackets\">" . ucfirst(rtrim($a, 's')) . ' comments</a>',
        array_filter(['artist', 'collages', 'requests', 'torrents'], fn ($a) => $a != $Action)
    )
);
if ($TypeLinks) {
    // and any extra links for this page type
    $Links .= ' <br />' . implode(' ', array_map(
        fn ($link) => sprintf('<a href="%s" class="brackets">%s</a>', $link[0], $link[1]), $TypeLinks
    ));
}

View::show_header(sprintf($Title, $Username), ['js' => 'bbcode,comments']);
?>
<div class="thin">
    <div class="header">
        <h2><?= sprintf(html_escape($Title), $User->link()) ?></h2>
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
    $commentMan = new Gazelle\Manager\Comment();
    $db->set_query_id($Comments);
    while ([$AuthorID, $Page, $PageID, $Name, $PostID, $Body, $AddedTime, $EditedTime, $EditedUserID] = $db->next_record(escape: false)) {
        $author = new Gazelle\User($AuthorID);
        echo $Twig->render('comment/comment.twig', [
            'added_time'  => $AddedTime,
            'author'      => $author,
            'body'        => $Body,
            'editor'      => $userMan->findById((int)$EditedUserID),
            'edit_time'   => $EditedTime,
            'id'          => $PostID,
            'heading'     => match ($Page) {
                'artist'   => "<a href=\"artist.php?id=$PageID\">" . html_escape($Name) . "</a>",
                'collages' => "<a href=\"collages.php?id=$PageID\">" . html_escape($Name) . "</a>",
                'requests' => $requestList[$PageID]->smartLink(),
                default    => $tgroupList[$PageID]->link(),
            },
            'page'        => $Action,
            'url'         => $commentMan->findById($PostID)->url(),
            'viewer'      => $Viewer,
        ]);
        $db->set_query_id($Comments);
    }
    echo $paginator->linkbox();
}
?>
</div>
<?php
View::show_footer();
