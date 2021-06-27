<?php

use Gazelle\Util\SortableTableHeader;

if (!check_perms('site_torrents_notify')) {
    error(403);
}

define('NOTIFICATIONS_PER_PAGE', 50);
define('NOTIFICATIONS_MAX_SLOWSORT', 10000);

$iconUri = STATIC_SERVER . '/styles/' . $LoggedUser['StyleName'] . '/images';
$imgTag = '<img src="' . $iconUri . '/%s.png" class="tooltip" alt="%s" title="%s"/>';
$headerMap = [
    'year'     => ['dbColumn' => 'tnt.Year',      'defaultSort' => 'desc', 'text' => 'Year'],
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

if (!empty($_GET['filterid']) && is_number($_GET['filterid'])) {
    $FilterID = $_GET['filterid'];
} else {
    $FilterID = false;
}

list($Page, $Limit) = Format::page_limit(NOTIFICATIONS_PER_PAGE);

// Perhaps this should be a feature at some point
if (check_perms('users_mod') && !empty($_GET['userid']) && is_number($_GET['userid']) && $_GET['userid'] != $Viewer->id()) {
    $UserID = $_GET['userid'];
    $Sneaky = true;
} else {
    $Sneaky = false;
    $UserID = $Viewer->id();
}

$cond = ['unt.UserID = ?'];
$args = [$UserID];
if ($FilterID) {
    $cond[] = 'FilterID = ?';
    $args[] = $FilterID;
}
$clause = implode(' AND ', $cond);

// Sorting by release year requires joining torrents_group, which is slow. Using a temporary table
// makes it speedy enough as long as there aren't too many records to create
if ($OrderBy == 'tnt.Year') {
    $TorrentCount = $DB->scalar("
        SELECT count(*)
        FROM users_notify_torrents AS unt
        INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        WHERE $clause
        ", ...$args
    );
    if ($TorrentCount > NOTIFICATIONS_MAX_SLOWSORT) {
        error('Due to performance issues, torrent lists with more than '.number_format(NOTIFICATIONS_MAX_SLOWSORT).' items cannot be ordered by release year.');
    }

    $DB->prepared_query("
        CREATE TEMPORARY TABLE temp_notify_torrents
            (TorrentID int, GroupID int, UnRead tinyint, FilterID int, Year smallint, PRIMARY KEY(GroupID, TorrentID), KEY(Year))
        ENGINE=InnoDB");
    $DB->prepared_query("
        INSERT IGNORE INTO temp_notify_torrents (TorrentID, GroupID, UnRead, FilterID)
        SELECT t.ID, t.GroupID, unt.UnRead, unt.FilterID
        FROM users_notify_torrents AS unt
        INNER JOIN torrents AS t ON t.ID=unt.TorrentID
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        WHERE $clause
        ", ...$args
    );
    $DB->prepared_query("
        UPDATE temp_notify_torrents AS tnt
        INNER JOIN torrents_group AS tg ON (tnt.GroupID = tg.ID)
        SET tnt.Year = tg.Year
    ");
    $DB->prepared_query("
        SELECT TorrentID, GroupID, UnRead, FilterID
        FROM temp_notify_torrents AS tnt
        ORDER BY $OrderBy $OrderDir, GroupID $OrderDir
        LIMIT $Limit
    ");
} else {
    $from = "
        FROM users_notify_torrents AS unt
        INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
        INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = unt.TorrentID)
        WHERE $clause
    ";
    $TorrentCount = $DB->scalar("
        SELECT count(*) $from
        ", ...$args
    );
    $DB->prepared_query("
        SELECT
            unt.TorrentID,
            unt.UnRead,
            unt.FilterID,
            t.GroupID
        $from
        ORDER BY $OrderBy $OrderDir
        LIMIT $Limit
        ", ...$args
    );
}
$Results = $DB->to_array(false, MYSQLI_ASSOC, false);

$GroupIDs = $FilterIDs = $UnReadIDs = [];
foreach ($Results as $Torrent) {
    $GroupIDs[$Torrent['GroupID']] = 1;
    $FilterIDs[$Torrent['FilterID']] = 1;
    if ($Torrent['UnRead']) {
        $UnReadIDs[] = $Torrent['TorrentID'];
    }
}
$Pages = Format::get_pages($Page, $TorrentCount, NOTIFICATIONS_PER_PAGE, 9);

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
    $Filters = $DB->to_array('ID', MYSQLI_ASSOC, ['Artists']);
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
if ($Sneaky) {
    View::show_header((new Gazelle\User($UserID))->username() . '\'s notifications', 'notifications');
} else {
    View::show_header('My notifications', 'notifications');
}
?>
<div class="thin widethin">
<div class="header">
    <h2>Latest notifications</h2>
</div>
<div class="linkbox">
<?php
if ($FilterID) { ?>
    <a href="torrents.php?action=notify<?=($Sneaky ? "&amp;userid=$UserID" : '')?>" class="brackets">View all</a>&nbsp;&nbsp;&nbsp;
<?php
} elseif (!$Sneaky) { ?>
    <a href="torrents.php?action=notify_clear&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Clear all old</a>&nbsp;&nbsp;&nbsp;
    <a href="#" onclick="clearSelected(); return false;" class="brackets">Clear selected</a>&nbsp;&nbsp;&nbsp;
    <a href="torrents.php?action=notify_catchup&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;
<?php
} ?>
    <a href="user.php?action=notify" class="brackets">Edit filters</a>&nbsp;&nbsp;&nbsp;
</div>
<?php
if ($TorrentCount > NOTIFICATIONS_PER_PAGE) { ?>
<div class="linkbox">
    <?=$Pages?>
</div>
<?php
}
if (empty($Results)) {
?>
<table class="layout border">
    <tr class="rowb">
        <td colspan="8" class="center">
            No new notifications found! <a href="user.php?action=notify" class="brackets">Edit notification filters</a>
        </td>
    </tr>
</table>
<?php
} else {
    $FilterGroups = [];
    foreach ($Results as $Result) {
        if (!isset($FilterGroups[$Result['FilterID']])) {
            $FilterGroups[$Result['FilterID']] = [];
            $FilterGroups[$Result['FilterID']]['FilterLabel'] = isset($Filters[$Result['FilterID']])
                ? $Filters[$Result['FilterID']]['Label']
                : false;
        }
        $FilterGroups[$Result['FilterID']][] = $Result;
    }

    foreach ($FilterGroups as $FilterID => $FilterResults) {
?>
<div class="header">
    <h3>
<?php
        if ($FilterResults['FilterLabel'] !== false) { ?>
        Matches for <a href="torrents.php?action=notify&amp;filterid=<?=$FilterID.($Sneaky ? "&amp;userid=$UserID" : '')?>"><?=$FilterResults['FilterLabel']?></a>
<?php   } else { ?>
        Matches for unknown filter[<?=$FilterID?>]
<?php   } ?>
    </h3>
</div>
<div class="linkbox notify_filter_links">
<?php   if (!$Sneaky) { ?>
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
        <td style="width: 100%;" class="nobr">Name<?=$TorrentCount <= NOTIFICATIONS_MAX_SLOWSORT ? ' / ' . $header->emit('year') : ''?></td>
        <td>Files</td>
        <td class="nobr"><?= $header->emit('time') ?></td>
        <td class="nobr"><?= $header->emit('size') ?></td>
        <td class="sign nobr snatches"><?= $headerIcons->emit('snatched') ?></td>
        <td class="sign nobr seeders"><?= $headerIcons->emit('seeders') ?></td>
        <td class="sign nobr leechers"><?= $headerIcons->emit('leechers') ?></td>
    </tr>
<?php
        unset($FilterResults['FilterLabel']);
        $bookmark = new \Gazelle\Bookmark;
        foreach ($FilterResults as $Result) {
            $TorrentID = $Result['TorrentID'];
            $GroupID = $Result['GroupID'];
            $GroupInfo = $TorrentGroups[$Result['GroupID']];
            if (!isset($GroupInfo['Torrents'][$TorrentID]) || !isset($GroupInfo['ID'])) {
                // If $GroupInfo['ID'] is unset, the torrent group associated with the torrent doesn't exist
                continue;
            }
            $TorrentInfo = $GroupInfo['Torrents'][$TorrentID];
            // generate torrent's title
            $DisplayName = '';
            if (!empty($GroupInfo['ExtendedArtists'])) {
                $MatchingArtists = [];
                foreach ($GroupInfo['ExtendedArtists'] as $GroupArtists) {
                    foreach ($GroupArtists as $GroupArtist) {
                        if (isset($Filters[$FilterID]['Artists'][mb_strtolower($GroupArtist['name'], 'UTF-8')])) {
                            $MatchingArtists[] = $GroupArtist['name'];
                        }
                    }
                }
                $MatchingArtistsText = (!empty($MatchingArtists) ? 'Caught by filter for '.implode(', ', $MatchingArtists) : '');
                $DisplayName = Artists::display_artists($GroupInfo['ExtendedArtists'], true, true);
            }
            $DisplayName .= "<a href=\"torrents.php?id=$GroupID&amp;torrentid=$TorrentID#torrent$TorrentID\" class=\"tooltip\" title=\"View torrent\" dir=\"ltr\">" . $GroupInfo['Name'] . '</a>';

            $GroupCategoryID = $GroupInfo['CategoryID'];
            if ($GroupCategoryID == 1) {
                if ($GroupInfo['Year'] > 0) {
                    $DisplayName .= " [" . $GroupInfo['Year'] . "]";
                }
                if ($GroupInfo['ReleaseType'] > 0) {
                    $DisplayName .= ' [' . (new Gazelle\ReleaseType)->findNameById($GroupInfo['ReleaseType']) . ']';
                }
            }

            // append extra info to torrent title
            $ExtraInfo = Torrents::torrent_info($TorrentInfo, true, true);

            $TorrentTags = new Tags($GroupInfo['TagList']);
            if ($GroupInfo['TagList'] == '') {
                $TorrentTags->set_primary($Categories[$GroupCategoryID - 1]);
            }

        // print row
?>
    <tr class="torrent torrent_row<?=($TorrentInfo['IsSnatched'] ? ' snatched_torrent' : '') . ($GroupInfo['Flags']['IsSnatched'] ? ' snatched_group' : '') . ($MatchingArtistsText ? ' tooltip" title="'.display_str($MatchingArtistsText) : '')?>" id="torrent<?=$TorrentID?>">
        <td class="m_td_left td_checkbox" style="text-align: center;">
            <input type="checkbox" class="notify_box notify_box_<?=$FilterID?>" value="<?=$TorrentID?>" id="clear_<?=$TorrentID?>" tabindex="1" />
        </td>
        <td class="center cats_col">
            <div title="<?=$TorrentTags->title()?>" class="tooltip <?=Format::css_category($GroupCategoryID)?> <?=$TorrentTags->css_name()?>"></div>
        </td>
        <td class="td_info big_info">
<?php       if ($LoggedUser['CoverArt']) { ?>
            <div class="group_image float_left clear">
                <?php ImageTools::cover_thumb($GroupInfo['WikiImage'], $GroupCategoryID) ?>
            </div>
<?php       } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => Torrents::can_use_token($TorrentInfo),
                    'key'    => $Viewer->announceKey(),
                    't'      => $TorrentInfo,
                    'extra'  => [
                        !$Sneaky ? "<a href=\"#\" onclick=\"clearItem({$TorrentID}); return false;\" class=\"tooltip\" title=\"Remove from notifications list\">CL</a>" : ''
                    ],
                ]) ?>
                <strong><?=$DisplayName?></strong>
                <div class="torrent_info">
                    <?=$ExtraInfo?>
<?php   if ($Result['UnRead']) { ?>
                    <strong class="new">New!</strong>
<?php
        }
        if ($bookmark->isTorrentBookmarked($Viewer->id(), $GroupID)) {
?>
                    <span class="remove_bookmark float_right">
                        <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                    </span>
<?php               } else { ?>
                    <span class="add_bookmark float_right">
                        <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
<?php               } ?>
                    </span>
                </div>
                <div class="tags"><?=$TorrentTags->format()?></div>
            </div>
        </td>
        <td class="td_file_count"><?=$TorrentInfo['FileCount']?></td>
        <td class="td_time number_column nobr"><?=time_diff($TorrentInfo['Time'])?></td>
        <td class="td_size number_column nobr"><?=Format::get_size($TorrentInfo['Size'])?></td>
        <td class="td_snatched m_td_right number_column"><?=number_format($TorrentInfo['Snatched'])?></td>
        <td class="td_seeders m_td_right number_column"><?=number_format($TorrentInfo['Seeders'])?></td>
        <td class="td_leechers m_td_right number_column"><?=number_format($TorrentInfo['Leechers'])?></td>
    </tr>
<?php
        }
?>
</table>
</form>
<?php
    }
}
if ($Pages) { ?>
    <div class="linkbox"><?=$Pages?></div>
<?php } ?>
</div>
<?php
View::show_footer();
