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

function linkBuild(string $id, string $action): string {
    preg_match('/^(.*?)s?$/', $action, $match);
    return sprintf('<a href="comments.php?action=%s%s" class="brackets">%s comments</a>',
        $action, $id, ucfirst($match[1])
    );
}

function createdBy(int $ownProfile, string $user, string $objects): string {
    return $ownProfile ? "your $objects" : "$objects created by $user";
}

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
$condArgs   = [];
$condition = [];
$Join     = [];
$joinArgs = [];

$ActionLinks = [];
$TypeLinks = [];

switch ($Action) {
    case 'artist':
        $Title = '%s &rsaquo; Artist comments';

        $table       = 'artists_group AS ag';
        $idField     = 'ag.ArtistID';
        $nameField   = 'ag.Name';
        $condition[] = "C.AuthorID = ?";
        $condArgs[]  = $UserID;

        $ActionLinks = [
            linkBuild($linkId, 'collages'),
            linkBuild($linkId, 'requests'),
            linkBuild($linkId, 'torrents'),
        ];
        break;

    case 'collages':
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

        $table       = 'collages AS cl';
        $idField     = 'cl.ID';
        $nameField   = 'cl.Name';
        $condition[] = "cl.Deleted = '0'";

        $ActionLinks = [
            linkBuild($linkId, 'artist'),
            linkBuild($linkId, 'requests'),
            linkBuild($linkId, 'torrents'),
        ];
        break;

    case 'requests':
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
                $TypeLinks = [
                    ["$BaseLink&amp;type=created", "$Username &rsaquo; Created request comments"],
                    ["$BaseLink&amp;type=contributed", "$Username &rsaquo; Voted-on request comments"],
                ];
                break;
        }

        $table      = 'requests AS r';
        $idField    = 'r.ID';
        $nameField  = 'r.Title';

        $ActionLinks = [
            linkBuild($linkId, 'artist'),
            linkBuild($linkId, 'collages'),
            linkBuild($linkId, 'torrents'),
        ];
        break;

    case 'torrents':
        switch($Type) {
            case 'uploaded':
                $Title = "%s &rsaquo; Comments on their uploads";
                $Join[] = 'INNER JOIN torrents t ON (t.GroupID = tg.ID)';
                $condition[] = 'C.AddedTime > t.Time';
                $condition[] = "C.AuthorID != ?";
                $condition[] = "t.UserID = ?";
                $condArgs[] = $UserID;
                $condArgs[] = $UserID;
                $TypeLinks[] = [$BaseLink, "$Username &rsaquo; Torrent comments"];
                break;
            default:
                $Title = "%s &rsaquo; Torrent comments";
                $condition[] = "C.AuthorID = ?";
                $condArgs[] = $UserID;
                $TypeLinks[] = ["$BaseLink&amp;type=uploaded", "$Username &rsaquo; Comments on their uploads"];
                break;
        }

        $table     = 'torrents_group AS tg';
        $idField   = 'tg.ID';
        $nameField = 'tg.Name';

        $ActionLinks = [
            linkBuild($linkId, 'artist'),
            linkBuild($linkId, 'collages'),
            linkBuild($linkId, 'requests'),
        ];
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

array_push($condArgs, $paginator->limit(), $paginator->offset());

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
    ", ...array_merge($joinArgs, $condArgs)
);

if ($Action == 'requests') {
    $RequestIDs = array_flip(array_flip($DB->collect('PageID')));
    $Artists = [];
    foreach ($RequestIDs as $RequestID) {
        $Artists[$RequestID] = Requests::get_artists($RequestID);
    }
} elseif ($Action == 'torrents') {
    $GroupIDs = array_flip(array_flip($DB->collect('PageID')));
    $Artists = Artists::get_artists($GroupIDs);
}

$Links = implode(' ', $ActionLinks)
    . ($TypeLinks
        ? (' <br />' . implode(' ', array_map(
            function ($x) {
                return sprintf('<a href="%s" class="brackets">%s</a>', $x[0], $x[1]);
            }, $TypeLinks
        )))
        : ''
    );

View::show_header(sprintf($Title, $Username), ['js' => 'bbcode,comments']);
?>
<div class="thin">
    <div class="header">
        <h2><?= sprintf($Title, Users::format_username($UserID, false, false, false)) ?></h2>
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
    $isAdmin = $Viewer->permitted('site_admin_forums');
    $commentMan = new Gazelle\Manager\Comment;
    $DB->set_query_id($Comments);
    while ([$AuthorID, $Page, $PageID, $Name, $PostID, $Body, $AddedTime, $EditedTime, $EditedUserID] = $DB->next_record()) {
        $author = new Gazelle\User($AuthorID);
        $ownProfile = $AuthorID == $Viewer->id();
        switch ($Page) {
            case 'artist':
                $heading = " on <a href=\"artist.php?id=$PageID\">$Name</a>";
                break;
            case 'collages':
                $heading = " on <a href=\"collages.php?id=$PageID\">$Name</a>";
                break;
            case 'requests':
                $heading = ' on ' . Artists::display_artists($Artists[$PageID]) . " <a href=\"requests.php?action=view&id=$PageID\">$Name</a>";
                break;
            case 'torrents':
                $heading = ' on ' . Artists::display_artists($Artists[$PageID]) . " <a href=\"torrents.php?id=$PageID\">$Name</a>";
                break;
        }
        echo $Twig->render('comment/comment.twig', [
            'added_time'  => $AddedTime,
            'author'      => $author,
            'avatar'      => $userMan->avatarMarkup($Viewer, $author),
            'body'        => $Body,
            'editor'      => $userMan->findById((int)$EditedUserID),
            'edit_time'   => $EditedTime,
            'id'          => $PostID,
            'is_admin'    => $isAdmin,
            'heading'     => $heading,
            'show_avatar' => $Viewer->showAvatars(),
            'show_delete' => $Viewer->permitted('site_forum_post_delete'),
            'show_edit'   => $Viewer->permitted('site_moderate_forums') || $ownProfile,
            'show_warn'   => $Viewer->permitted('users_warn') && !$ownProfile && $Viewer->classLevel() >= $author->classLevel(),
            'show_unread' => false,
            'url'         => $commentMan->findById($PostID)->url(),
            'page'        => $Action
        ]);
        $DB->set_query_id($Comments);
    }
    echo $paginator->linkbox();
}
?>
</div>
<?php
View::show_footer();
