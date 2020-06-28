<?php

use Gazelle\Util\SortableTableHeader;

if (!check_perms('site_torrents_notify')) {
    error(403);
}

define('NOTIFICATIONS_PER_PAGE', 50);
define('NOTIFICATIONS_MAX_SLOWSORT', 10000);

$SortOrderMap = [
    'time'     => ['unt.TorrentID', 'desc'],
    'size'     => ['t.Size', 'desc'],
    'snatched' => ['tls.Snatched', 'desc'],
    'seeders'  => ['tls.Seeders', 'desc'],
    'leechers' => ['tls.Leechers', 'desc'],
    'year'     => ['tnt.Year', 'desc'],
];
$SortOrder = (!empty($_GET['order']) && isset($SortOrderMap[$_GET['order']])) ? $_GET['order'] : 'time';
$OrderBy = $SortOrderMap[$SortOrder][0];
$OrderWay = (empty($_GET['sort']) || $_GET['sort'] == $SortOrderMap[$SortOrder][1])
    ? $SortOrderMap[$SortOrder][1]
    : SortableTableHeader::SORT_DIRS[$SortOrderMap[$SortOrder][1]];

if (!empty($_GET['filterid']) && is_number($_GET['filterid'])) {
    $FilterID = $_GET['filterid'];
} else {
    $FilterID = false;
}

list($Page, $Limit) = Format::page_limit(NOTIFICATIONS_PER_PAGE);

// Perhaps this should be a feature at some point
if (check_perms('users_mod') && !empty($_GET['userid']) && is_number($_GET['userid']) && $_GET['userid'] != $LoggedUser['ID']) {
    $UserID = $_GET['userid'];
    $Sneaky = true;
} else {
    $Sneaky = false;
    $UserID = $LoggedUser['ID'];
}

// Sorting by release year requires joining torrents_group, which is slow. Using a temporary table
// makes it speedy enough as long as there aren't too many records to create
if ($OrderBy == 'tnt.Year') {
    $DB->query("
        SELECT COUNT(*)
        FROM users_notify_torrents AS unt
        INNER JOIN torrents AS t ON (t.ID=unt.TorrentID)
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        WHERE unt.UserID=$UserID".
        ($FilterID
            ? " AND FilterID=$FilterID"
            : ''));
    list($TorrentCount) = $DB->next_record();
    if ($TorrentCount > NOTIFICATIONS_MAX_SLOWSORT) {
        error('Due to performance issues, torrent lists with more than '.number_format(NOTIFICATIONS_MAX_SLOWSORT).' items cannot be ordered by release year.');
    }

    $DB->query("
        CREATE TEMPORARY TABLE temp_notify_torrents
            (TorrentID int, GroupID int, UnRead tinyint, FilterID int, Year smallint, PRIMARY KEY(GroupID, TorrentID), KEY(Year))
        ENGINE=InnoDB");
    $DB->query("
        INSERT IGNORE INTO temp_notify_torrents (TorrentID, GroupID, UnRead, FilterID)
        SELECT t.ID, t.GroupID, unt.UnRead, unt.FilterID
        FROM users_notify_torrents AS unt
        INNER JOIN torrents AS t ON t.ID=unt.TorrentID
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        WHERE unt.UserID=$UserID".
        ($FilterID
            ? " AND unt.FilterID=$FilterID"
            : ''));
    $DB->query("
        UPDATE temp_notify_torrents AS tnt
            JOIN torrents_group AS tg ON tnt.GroupID = tg.ID
        SET tnt.Year = tg.Year");

    $DB->query("
        SELECT TorrentID, GroupID, UnRead, FilterID
        FROM temp_notify_torrents AS tnt
        ORDER BY $OrderBy $OrderWay, GroupID $OrderWay
        LIMIT $Limit");
    $Results = $DB->to_array(false, MYSQLI_ASSOC, false);
} else {
    $DB->query("
        SELECT
            SQL_CALC_FOUND_ROWS
            unt.TorrentID,
            unt.UnRead,
            unt.FilterID,
            t.GroupID
        FROM users_notify_torrents AS unt
        INNER JOIN torrents AS t ON (t.ID = unt.TorrentID)
        INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = unt.TorrentID)
        WHERE unt.UserID = $UserID".
        ($FilterID
            ? " AND unt.FilterID = $FilterID"
            : '')."
        ORDER BY $OrderBy $OrderWay
        LIMIT $Limit");
    $Results = $DB->to_array(false, MYSQLI_ASSOC, false);
    $DB->query('SELECT FOUND_ROWS()');
    list($TorrentCount) = $DB->next_record();
}

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
    $DB->query('
        SELECT ID, Label, Artists
        FROM users_notify_filters
        WHERE ID IN ('.implode(',', $FilterIDs).')');
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
        $DB->query("
            UPDATE users_notify_torrents
            SET UnRead = '0'
            WHERE UserID = ".$LoggedUser['ID'].'
                AND TorrentID IN ('.implode(',', $UnReadIDs).')');
        $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
    }
}
if ($Sneaky) {
    $UserInfo = Users::user_info($UserID);
    View::show_header($UserInfo['Username'].'\'s notifications', 'notifications');
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
    <a href="torrents.php?action=notify_clear&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Clear all old</a>&nbsp;&nbsp;&nbsp;
    <a href="#" onclick="clearSelected(); return false;" class="brackets">Clear selected</a>&nbsp;&nbsp;&nbsp;
    <a href="torrents.php?action=notify_catchup&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;
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
    <a href="torrents.php?action=notify_clear_filter&amp;filterid=<?=$FilterID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Clear all old in filter</a>
    <a href="torrents.php?action=notify_catchup_filter&amp;filterid=<?=$FilterID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Mark all in filter as read</a>
<?php   }
$header = new SortableTableHeader([
    'year' => 'Year',
    'time' => 'Time',
    'size' => 'Size',
], $SortOrder, $OrderWay);

$headerIcons = new SortableTableHeader([
    'snatched' => '<img src="static/styles/' . $LoggedUser['StyleName'] . '/images/snatched.png" class="tooltip" alt="Snatches" title="Snatches" />',
    'seeders'  => '<img src="static/styles/' . $LoggedUser['StyleName'] . '/images/seeders.png" class="tooltip" alt="Seeders" title="Seeders" />',
    'leechers' => '<img src="static/styles/' . $LoggedUser['StyleName'] . '/images/leechers.png" class="tooltip" alt="Leechers" title="Leechers" />',
], $SortOrder, $OrderWay, ['asc' => '', 'desc' => '']);
?>
</div>
<form class="manage_form" name="torrents" id="notificationform_<?=$FilterID?>" action="">
<table class="torrent_table cats checkboxes border m_table">
    <tr class="colhead">
        <td style="text-align: center;"><input type="checkbox" name="toggle" onclick="toggleChecks('notificationform_<?=$FilterID?>', this, '.notify_box')" /></td>
        <td class="small cats_col"></td>
        <td style="width: 100%;" class="nobr">Name<?=$TorrentCount <= NOTIFICATIONS_MAX_SLOWSORT ? ' / ' . $header->emit('year', $SortOrderMap['year'][1]) : ''?></td>
        <td>Files</td>
        <td class="nobr"><?= $header->emit('time', $SortOrderMap['time'][1]) ?></td>
        <td class="nobr"><?= $header->emit('size', $SortOrderMap['size'][1]) ?></td>
        <td class="sign nobr snatches"><?= $headerIcons->emit('snatched', $SortOrderMap['snatched'][1]) ?></td>
        <td class="sign nobr seeders"><?= $headerIcons->emit('seeders', $SortOrderMap['seeders'][1]) ?></td>
        <td class="sign nobr leechers"><?= $headerIcons->emit('leechers', $SortOrderMap['leechers'][1]) ?></td>
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
                    $DisplayName .= " [$GroupInfo[Year]]";
                }
                if ($GroupInfo['ReleaseType'] > 0) {
                    $DisplayName .= ' ['.$ReleaseTypes[$GroupInfo['ReleaseType']].']';
                }
            }

            // append extra info to torrent title
            $ExtraInfo = Torrents::torrent_info($TorrentInfo, true, true);

            $TorrentTags = new Tags($GroupInfo['TagList']);

            if ($GroupInfo['TagList'] == '')
                $TorrentTags->set_primary($Categories[$GroupCategoryID - 1]);

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
<?php   if ($LoggedUser['CoverArt']) { ?>
            <div class="group_image float_left clear">
                <?php ImageTools::cover_thumb($GroupInfo['WikiImage'], $GroupCategoryID) ?>
            </div>
<?php   } ?>
            <div class="group_info clear">
                <span>
                    [ <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="tooltip" title="Download">DL</a>
<?php   if (Torrents::can_use_token($TorrentInfo)) { ?>
                    | <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('<?=FL_confirmation_msg($TorrentInfo['Seeders'], $TorrentInfo['Size'])?>');">FL</a>
<?php
        }
        if (!$Sneaky) { ?>
                    | <a href="#" onclick="clearItem(<?=$TorrentID?>); return false;" class="tooltip" title="Remove from notifications list">CL</a>
<?php   } ?> ]
                </span>
                <strong><?=$DisplayName?></strong>
                <div class="torrent_info">
                    <?=$ExtraInfo?>
<?php   if ($Result['UnRead']) { ?>
                    <strong class="new">New!</strong>
<?php
        }
        if ($bookmark->isTorrentBookmarked($LoggedUser['ID'], $GroupID)) { ?>
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
