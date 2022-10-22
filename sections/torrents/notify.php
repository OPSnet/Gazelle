<?php

use Gazelle\Util\SortableTableHeader;

if (!$Viewer->permitted('site_torrents_notify')) {
    error(403);
}

$imgTag = '<img src="' . (new Gazelle\User\Stylesheet($Viewer))->imagePath()
    . '%s.png" class="tooltip" alt="%s" title="%s"/>';
$headerMap = [
    'year'     => ['dbColumn' => 'tg.Year',       'defaultSort' => 'desc', 'text' => 'Year'],
    'time'     => ['dbColumn' => 'unt.TorrentID', 'defaultSort' => 'desc', 'text' => 'Time'],
    'size'     => ['dbColumn' => 't.Size',        'defaultSort' => 'desc', 'text' => 'Size'],
    'snatched' => ['dbColumn' => 'tls.Snatched',  'defaultSort' => 'desc', 'text' => sprintf($imgTag, 'snatched', 'Snatches', 'Snatches')],
    'seeders'  => ['dbColumn' => 'tls.Seeders',   'defaultSort' => 'desc', 'text' => sprintf($imgTag, 'seeders', 'Seeders', 'Seeders')],
    'leechers' => ['dbColumn' => 'tls.Leechers',  'defaultSort' => 'desc', 'text' => sprintf($imgTag, 'leechers', 'Leechers', 'Leechers')],
];
$header = new SortableTableHeader('time', $headerMap);
$OrderBy = $header->getOrderBy();
$OrderDir = $header->getOrderDir();
$headerIcons = new SortableTableHeader('time', $headerMap, ['asc' => '', 'desc' => '']);

$from = "FROM users_notify_torrents AS unt
    INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
    INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = unt.TorrentID)";
if ($OrderBy == 'tg.Year') {
    $from .= " INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)";
}

if ($Viewer->permitted('users_mod') && (int)($_GET['userid'] ?? 0)) {
    $user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
} else {
    $user = $Viewer;
}
$UserID = $user->id();
$ownProfile = $UserID === $Viewer->id();

$cond = ['unt.UserID = ?'];
$args = [$UserID];
$FilterID = (int)($_GET['filterid'] ?? 0);
if ($FilterID) {
    $cond[] = 'FilterID = ?';
    $args[] = $FilterID;
}
$where = implode(' AND ', $cond);

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($DB->scalar("
    SELECT count(*) $from WHERE $where
    ", ...$args
));
array_push($args, $paginator->limit(), $paginator->offset());
$DB->prepared_query("
    SELECT unt.TorrentID,
        unt.UnRead,
        unt.FilterID,
        t.GroupID
    $from
    WHERE $where
    ORDER BY $OrderBy $OrderDir
    LIMIT ? OFFSET ?
    ", ...$args
);
$Results = $DB->to_array(false, MYSQLI_ASSOC, false);

$GroupIDs = $FilterIDs = $UnReadIDs = [];
foreach ($Results as $Torrent) {
    $GroupIDs[$Torrent['GroupID']] = 1;
    $FilterIDs[$Torrent['FilterID']] = 1;
    if ($Torrent['UnRead']) {
        $UnReadIDs[] = $Torrent['TorrentID'];
    }
}

if (!empty($GroupIDs)) {
    $GroupIDs = array_keys($GroupIDs);
    $FilterIDs = array_keys($FilterIDs);
    $TorrentGroups = Torrents::get_groups($GroupIDs);

    // Get the relevant filter labels
    $DB->prepared_query("
        SELECT ID, Label, Artists
        FROM users_notify_filters
        WHERE ID IN (" . placeholders($FilterIDs) . ")
        ", ...$FilterIDs
    );
    $Filters = $DB->to_array('ID', MYSQLI_ASSOC, false);
    foreach ($Filters as &$Filter) {
        $Filter['Artists'] = explode('|', trim($Filter['Artists'], '|'));
        foreach ($Filter['Artists'] as &$FilterArtist) {
            $FilterArtist = mb_strtolower($FilterArtist, 'UTF-8');
        }
        $Filter['Artists'] = array_flip($Filter['Artists']);
    }
    unset($Filter);

    if (!empty($UnReadIDs)) {
        //Clear before header but after query so as to not have the alert bar on this page load
        $DB->prepared_query("
            UPDATE users_notify_torrents SET
                UnRead = 0
            WHERE UserID = ?
                AND TorrentID IN (" . placeholders($UnReadIDs) . ")
            ", $Viewer->id(), ...$UnReadIDs
        );
        $Cache->delete_value('user_notify_upload_'.$Viewer->id());
    }
}

$imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);
$torMan   = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$snatcher = new Gazelle\User\Snatch($Viewer);

View::show_header(($ownProfile ? 'My' : $user->username() . "'s") . ' notifications', ['js' => 'notifications']);
?>
<div class="thin widethin">
<div class="header">
    <h2>Latest notifications</h2>
</div>
<div class="linkbox">
<?php if ($FilterID) { ?>
    <a href="torrents.php?action=notify<?= $ownProfile ? '' : "&amp;userid=$UserID" ?>" class="brackets">View all</a>&nbsp;&nbsp;&nbsp;
<?php } elseif ($ownProfile) { ?>
    <a href="torrents.php?action=notify_clear&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Clear all old</a>&nbsp;&nbsp;&nbsp;
    <a href="#" onclick="clearSelected(); return false;" class="brackets">Clear selected</a>&nbsp;&nbsp;&nbsp;
    <a href="torrents.php?action=notify_catchup&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;
<?php } ?>
    <a href="user.php?action=notify" class="brackets">Edit filters</a>&nbsp;&nbsp;&nbsp;
</div>
<?php if (empty($Results)) { ?>
<table class="layout border">
    <tr class="rowb">
        <td colspan="8" class="center">
            No new notifications found! <a href="user.php?action=notify" class="brackets">Edit notification filters</a>
        </td>
    </tr>
</table>
<?php
} else {
    echo $paginator->linkbox();
    $FilterGroups = [];
    foreach ($Results as $Result) {
        if (!isset($FilterGroups[$Result['FilterID']])) {
            $FilterGroups[$Result['FilterID']] = [
                'FilterLabel' => $Filters[$Result['FilterID']]['Label'] ?? false,
            ];
        }
        $FilterGroups[$Result['FilterID']][] = $Result;
    }

    $bookmark = new Gazelle\User\Bookmark($Viewer);
    foreach ($FilterGroups as $FilterID => $FilterResults) {
?>
<div class="header">
    <h3>
<?php
        if ($FilterResults['FilterLabel'] !== false) { ?>
        Matches for <a href="torrents.php?action=notify&amp;filterid=<?=$FilterID . ($ownProfile ? "" : "&amp;userid=$UserID") ?>"><?=$FilterResults['FilterLabel']?></a>
<?php   } else { ?>
        Matches for unknown filter[<?=$FilterID?>]
<?php   } ?>
    </h3>
</div>
<div class="linkbox notify_filter_links">
<?php   if ($ownProfile) { ?>
    <a href="#" onclick="clearSelected(<?=$FilterID?>); return false;" class="brackets">Clear selected in filter</a>
    <a href="torrents.php?action=notify_clear_filter&amp;filterid=<?=$FilterID?>&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Clear all old in filter</a>
    <a href="torrents.php?action=notify_catchup_filter&amp;filterid=<?=$FilterID?>&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Mark all in filter as read</a>
<?php   } ?>
</div>
<form class="manage_form" name="torrents" id="notificationform_<?=$FilterID?>" action="">
<table class="torrent_table cats checkboxes border m_table">
    <tr class="colhead">
        <td style="text-align: center;"><input type="checkbox" name="toggle" onclick="toggleChecks('notificationform_<?=$FilterID?>', this, '.notify_box')" /></td>
        <td class="small cats_col"></td>
        <td style="width: 100%;" class="nobr">Name<?= ' / ' . $header->emit('year') ?></td>
        <td>Files</td>
        <td class="nobr"><?= $header->emit('time') ?></td>
        <td class="nobr"><?= $header->emit('size') ?></td>
        <td class="sign nobr snatches"><?= $headerIcons->emit('snatched') ?></td>
        <td class="sign nobr seeders"><?= $headerIcons->emit('seeders') ?></td>
        <td class="sign nobr leechers"><?= $headerIcons->emit('leechers') ?></td>
    </tr>
<?php
        unset($FilterResults['FilterLabel']);
        foreach ($FilterResults as $Result) {
            $TorrentID = $Result['TorrentID'];
            $torrent = $torMan->findById($TorrentID);
            if (is_null($torrent)) {
                continue;
            }

            $tgroup = $torrent->group();
            $tagList = $tgroup->tagList();
            $roleIdList = (new Gazelle\ArtistRole\TGroup($tgroup->id()))->idList();
            $match = [];
            foreach ($roleIdList as $role) {
                foreach ($role as $artist) {
                    if (isset($Filters[$FilterID]['Artists'][mb_strtolower($artist['name'])])) {
                        $match[] = $artist['name'];
                    }
                }
            }
            $MatchingArtistsText = ($match ? 'Caught by filter for '.implode(', ', $match) : '');
?>
    <tr id="torrent<?= $TorrentID ?>" class="torrent torrent_row<?=
        ($snatcher->showSnatch($torrent->id()) ? ' snatched_torrent' : '')
        . ($tgroup->isSnatched($Viewer->id()) ? ' snatched_group' : '')
        ?>">
        <td class="m_td_left td_checkbox" style="text-align: center;">
            <input type="checkbox" class="notify_box notify_box_<?=$FilterID?>" value="<?=$TorrentID?>" id="clear_<?=$TorrentID?>" tabindex="1" />
        </td>
        <td class="center cats_col">
            <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>"></div>
        </td>
        <td class="td_info big_info">
<?php       if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->tgroupThumbnail($tgroup) ?>
            </div>
<?php       } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action-v2.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $torrent,
                    'extra'  => [
                        $ownProfile ? "<a href=\"#\" onclick=\"clearItem({$TorrentID}); return false;\" class=\"tooltip\" title=\"Remove from notifications list\">CL</a>" : ''
                    ],
                ]) ?>
                <strong><?= $torrent->fullLink() ?></strong>
                <div class="torrent_info">
<?php       if ($Result['UnRead']) { ?>
                    <strong class="new">New!</strong>
<?php
            }
            echo $Twig->render('bookmark/action.twig', [
                'class'         => 'torrent',
                'id'            => $tgroup->id(),
                'is_bookmarked' => $bookmark->isTorrentBookmarked($tgroup->id()),
            ]);
?>
                </div>
                <div class="tags"><?= implode(', ', array_map(
                    fn($name) => "<a href=\"torrents.php?taglists={$name}\">$name</a>",
                    $tgroup->tagNameList()))
                    ?></div>
                <?= display_str($MatchingArtistsText) ?>
            </div>
        </td>
        <td class="td_file_count"><?= $torrent->fileTotal() ?></td>
        <td class="td_time nobr"><?= time_diff($torrent->uploadDate(), 1) ?></td>
        <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
    </tr>
<?php   } ?>
</table>
</form>
<?php
    }
    echo $paginator->linkbox();
}
View::show_footer();
