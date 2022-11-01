<?php

use Gazelle\Util\SortableTableHeader;

if (!$Viewer->permitted('site_torrents_notify')) {
    error(403);
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
$headerIcons = new SortableTableHeader('time', $headerMap, ['asc' => '', 'desc' => '']);

$torMan   = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$notifier = new Gazelle\User\NotificationSearch($user, $header->getOrderBy(), $header->getOrderDir(), $torMan);
if (isset($_GET['filterid'])) {
    $notifier->setFilter((int)$_GET['filterid']);
}

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($notifier->total());

$page = $notifier->page($paginator->limit(), $paginator->offset());
$filterList = [];
foreach (array_unique(array_map(fn($n) => $n['filter_id'], $page)) as $filterId) {
    $filterList[$filterId] = new Gazelle\NotificationFilter($filterId);
}

$unread = array_map(
    fn ($n) => $n['TorrentID'],
    array_filter(
        $page,
        fn ($n) => $n['unread'] == 1
    )
);
if ($unread) {
    $notifier->clearUnread($unread);
}

$imgProxy = new Gazelle\Util\ImageProxy($Viewer);
$snatcher = new Gazelle\User\Snatch($Viewer);

View::show_header(($ownProfile ? 'My' : $user->username() . "'s") . ' notifications', ['js' => 'notifications']);
?>
<div class="thin widethin">
<div class="header">
    <h2>Latest notifications</h2>
</div>
<div class="linkbox">
<?php if ($notifier->filterId()) { ?>
    <a href="torrents.php?action=notify<?= $ownProfile ? '' : "&amp;userid=$UserID" ?>" class="brackets">View all</a>&nbsp;&nbsp;&nbsp;
<?php } elseif ($ownProfile) { ?>
    <a href="torrents.php?action=notify_clear&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Clear all old</a>&nbsp;&nbsp;&nbsp;
    <a href="#" onclick="clearSelected(); return false;" class="brackets">Clear selected</a>&nbsp;&nbsp;&nbsp;
    <a href="torrents.php?action=notify_catchup&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;
<?php } ?>
    <a href="user.php?action=notify" class="brackets">Edit filters</a>&nbsp;&nbsp;&nbsp;
</div>
<?php if (empty($page)) { ?>
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
    foreach ($page as $Result) {
        if (!isset($FilterGroups[$Result['filter_id']])) {
            $FilterGroups[$Result['filter_id']] = [
                'FilterLabel' => $filterList[$Result['filter_id']]->label(),
            ];
        }
        $FilterGroups[$Result['filter_id']][] = $Result;
    }

    $bookmark = new Gazelle\User\Bookmark($Viewer);
    foreach ($FilterGroups as $FilterID => $FilterResults) {
        $filter = $filterList[$FilterID];
?>
<div class="header">
    <h3>
        Matches for <a href="torrents.php?action=notify&amp;filterid=<?=$FilterID . ($ownProfile ? "" : "&amp;userid=$UserID") ?>"><?=$FilterResults['FilterLabel']?></a>
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
            $torrent = $Result['torrent'];
            $tgroup = $torrent->group();
            $match = $tgroup->artistRole()->matchName($filter->artistList());
?>
    <tr id="torrent<?= $TorrentID ?>" class="torrent torrent_row<?=
        ($snatcher->showSnatch($TorrentID) ? ' snatched_torrent' : '')
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
                    'torrent' => $torrent,
                    'viewer'  => $Viewer,
                    'extra'   => [
                        $ownProfile ? "<a href=\"#\" onclick=\"clearItem({$TorrentID}); return false;\" class=\"tooltip\" title=\"Remove from notifications list\">CL</a>" : ''
                    ],
                ]) ?>
                <strong><?= $torrent->fullLink() ?></strong>
                <div class="torrent_info">
<?php       if ($Result['unread']) { ?>
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
                <?= display_str($match ? 'Caught by filter for ' . implode(', ', $match) : '') ?>
            </div>
        </td>
        <td class="td_file_count"><?= $torrent->fileTotal() ?></td>
        <td class="td_time nobr"><?= time_diff($torrent->created(), 1) ?></td>
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
