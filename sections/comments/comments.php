<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

declare(strict_types=1);

namespace Gazelle;

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

$userMan = new Manager\User();
if (!isset($_GET['id'])) {
    $User = $Viewer;
} else {
    $User = $userMan->findById((int)($_GET['id'] ?? 0));
    if (is_null($User)) {
        Error404::error();
    }
    if (!$User->propertyVisible($Viewer, 'torrentcomments')) {
        Error403::error();
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
$idField = match ($Action) {
    'artist'   => 'ag."ArtistID"',
    'collages' => 'cl."ID"',
    'requests' => 'r.id_request',
    'torrents' => 'tg."ID"',
    default    => Error400::error('bad comment type: ' . html_escape($Action)),
};
$Join      = ["inner join relay.comments C ON (C.\"Page\" = $1 AND C.\"PageID\" = $idField)"];
$args      = [$Action];
$condition = [];
$TypeLinks = [];

switch ($Action) {
    case 'artist':
        $Title       = '%s › Artist comments';
        $table       = 'relay.artists_group ag inner join relay.artists_alias aa on (ag."PrimaryAlias" = aa."AliasID")';
        $nameField   = 'aa."Name"';
        $condition[] = 'C."AuthorID" = $' . (1 + count($args));
        $args[]      = $UserID;
        break;

    case 'collages':
        $table       = 'relay.collages cl';
        $nameField   = 'cl."Name"';
        $condition[] = "cl.\"Deleted\" = '0'";

        switch ($Type) {
            case 'created':
                $Title       = "%s › Comments on their collages";
                $placeholder = '$' . (1 + count($args));
                $condition[] = "cl.\"UserID\" = $placeholder and C.\"AuthorID\" != $placeholder";
                $args[]      = $UserID;
                $TypeLinks   = [
                    [$BaseLink, "$Username › Collage comments"],
                    ["$BaseLink&amp;type=contributed", "$Username › Contributed collage comments"],
                ];
                break;
            case 'contributed':
                $Title       = '%s › Contributed collage comments';
                $placeholder = '$' . (1 + count($args));
                $condition[] = <<<END_SQL
                    C."AuthorID" != $placeholder and cl."ID" in (
                    select distinct "CollageID" from relay.collages_torrents ct where ct."UserID" = $placeholder
                    union all
                    select distinct "CollageID" from relay.collages_artists ca where ca."UserID" = $placeholder)
                    END_SQL;
                $args[]     = $UserID;
                $TypeLinks  = [
                    [$BaseLink, "$Username › Collage comments"],
                    ["$BaseLink&amp;type=created", "$Username › Comments on their collages"],
                ];
                break;
            default:
                $Title       = "%s › Collage comments";
                $condition[] = 'C."AuthorID" = $' . (1 + count($args));
                $args[]      = $UserID;
                $TypeLinks   = [
                    ["$BaseLink&amp;type=contributed", "$Username › Contributed collage comments"],
                    ["$BaseLink&amp;type=created", "$Username › Comments on their collages"],
                ];
                break;
        }
        break;

    case 'requests':
        $table      = 'request AS r';
        $nameField  = 'r.title';

        switch ($Type) {
            case 'created':
                $Title       = "%s › Comments on their requests";
                $placeholder = '$' . (1 + count($args));
                $condition[] = "r.id_user = $placeholder and C.\"AuthorID\" != $placeholder";
                $args[]      = $UserID;
                $TypeLinks   = [
                    [$BaseLink, "$Username › Request comments"],
                    ["$BaseLink&amp;type=contributed", "$Username › Voted-on request comments"],
                ];
                break;
            case 'voted':
                $Title       = "%s › Comments on voted-on requests";
                $Join[]      = 'inner join request_vote rv using (id_request)';
                $placeholder = '$' . (1 + count($args));
                $condition[] = "rv.id_user = $placeholder and C.\"AuthorID\" != $placeholder";
                $args[]      = $UserID;
                $TypeLinks   = [
                    [$BaseLink, "$Username › Request comments"],
                    ["$BaseLink&amp;type=created", "$Username › Created request comments"],
                ];
                break;
            default:
                $Title       = "%s › Request comments";
                $condition[] = 'C."AuthorID" = $' . (1 + count($args));
                $args[]      = $UserID;
                $TypeLinks   = $ownProfile
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
        $table     = 'relay.torrents_group tg';
        $nameField = 'tg."Name"';

        switch ($Type) {
            case 'uploaded':
                $Title       = "%s › Comments on their uploads";
                $Join[]      = 'inner join relay.torrents t on (t."GroupID" = tg."ID")';
                $placeholder = '$' . (1 + count($args));
                $condition[] = "t.\"UserID\" = $placeholder and C.\"AuthorID\" != $placeholder";
                $condition[] = 'C."AddedTime" > t.created';
                $args[]      = $UserID;
                $TypeLinks[] = [
                    $BaseLink,
                    $ownProfile ? "Your torrent comments" : "$Username › Torrent comments"
                ];
                break;
            default:
                $Title       = "%s › Torrent comments";
                $condition[] = 'C."AuthorID" = $' . (1 + count($args));
                $args[]      = $UserID;
                $TypeLinks[] = [
                    "$BaseLink&amp;type=uploaded",
                    $ownProfile ? "Comments on your uploads" : "$Username › Comments on their uploads"
                ];
                break;
        }
        break;
    default:
        Error400::error('What are you trying to comment on?');
}

$Join = implode("\n", $Join);
$cond = 'WHERE ' . implode(" and ", $condition);

$pg = new DB\Pg(PG_RO_DSN);

// Posts per page limit stuff
$paginator = new Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    (int)$pg->scalarex(<<<END_SQL
        select count(distinct C."ID") from
        $table
        $Join
        $cond
        END_SQL,
            ...$args
    )
);

$limit  = '$' . (1 + count($args));
$args[] = $paginator->limit();
$offset = '$' . (1 + count($args));
$args[] = $paginator->offset();

$commentList = $pg->allex(<<<END_SQL
    select C."AuthorID",
        C."Page",
        C."PageID",
        $nameField AS name,
        C."ID",
        C."Body",
        C."AddedTime",
        C."EditedTime",
        C."EditedUserID"
    from $table
    $Join
    $cond
    order by C."ID" desc
    limit $limit offset $offset
    END_SQL,
        ...$args
);

$requestList = [];
$tgroupList = [];
if ($Action == 'requests') {
    $requestMan = new Manager\Request();
    foreach (array_unique(array_map(fn ($c) => $c['PageID'], $commentList)) as $id) {
        $requestList[$id] = $requestMan->findById((int)$id);
    }
} elseif ($Action == 'torrents') {
    $tgMan = new Manager\TGroup();
    foreach (array_unique(array_map(fn ($c) => $c['PageID'], $commentList)) as $id) {
        $tgroupList[$id] = $tgMan->findById((int)$id);
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
    $commentMan = new Manager\Comment();
    foreach ($commentList as $c) {
        $author = new User($c['AuthorID']);
        echo $Twig->render('comment/comment.twig', [
            'created'     => $c['AddedTime'],
            'author'      => $author,
            'body'        => $c['Body'],
            'editor'      => $userMan->findById((int)$c['EditedUserID']),
            'edit_time'   => $c['EditedTime'],
            'id'          => $c['ID'],
            'heading'     => match ($Action) {
                'artist'   => "<a href=\"artist.php?id={$c['ID']}\">" . html_escape($c['name']) . "</a>",
                'collages' => "<a href=\"collages.php?id={$c['ID']}\">" . html_escape($c['name']) . "</a>",
                'requests' => $requestList[$c['PageID']]->smartLink(),
                default    => $tgroupList[$c['PageID']]->link(),
            },
            'page'        => $Action,
            'url'         => $commentMan->findById($c['ID'])->url(),
            'viewer'      => $Viewer,
        ]);
    }
    echo $paginator->linkbox();
}
?>
</div>
<?php
View::show_footer();
