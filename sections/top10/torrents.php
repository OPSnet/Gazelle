<?php

$torrent = new \Gazelle\Top10\Torrent(FORMAT, $Viewer);
$torMan = new Gazelle\Manager\Torrent;

if (!empty($_GET['advanced']) && $Viewer->permitted('site_advanced_top10')) {
    $details = 'all';
    $limit = 10;
} else {
    $details = isset($_GET['details']) && in_array($_GET['details'], ['day', 'week', 'overall', 'snatched', 'data', 'seeded', 'month', 'year']) ? $_GET['details'] : 'all';

    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $limit = in_array($limit, [10, 100, 250]) ? $limit : 10;
}

View::show_header("Top $limit Torrents");
?>
<div class="thin">
    <div class="header">
        <h2>Top <?=$limit?> Torrents</h2>
        <?php \Gazelle\Top10::renderLinkbox("torrents"); ?>
    </div>
<?php

if ($Viewer->permitted('site_advanced_top10')) {
?>
    <form class="search_form" name="torrents" action="" method="get">
        <input type="hidden" name="advanced" value="1" />
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr id="tagfilter">
                <td class="label">Tags (comma-separated):</td>
                <td class="ft_taglist">
                    <input type="text" name="tags" id="tags" size="75" value="<?php if (!empty($_GET['tags'])) { echo display_str($_GET['tags']);} ?>"<?=
                        $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />&nbsp;
                    <input type="radio" id="rdoAll" name="anyall" value="all"<?=(empty($_GET['anyall']) || $_GET['anyall'] != 'any' ? ' checked="checked"' : '')?> /><label for="rdoAll"> All</label>&nbsp;&nbsp;
                    <input type="radio" id="rdoAny" name="anyall" value="any"<?=(!empty($_GET['anyall']) && $_GET['anyall'] == 'any' ? ' checked="checked"' : '')?> /><label for="rdoAny"> Any</label>
                </td>
            </tr>
            <tr id="artistfilter">
                <td class="label">Exclude Artists (one on each line):</td>
                <td>
                    <textarea name="excluded_artists" rows="3" cols="25" style="width: 95%"><?php if (!empty($_GET['excluded_artists'])) echo display_str($_GET['excluded_artists']) ?></textarea>&nbsp;
                </td>
            </tr>
            <tr>
                <td class="label">Format:</td>
                <td>
                    <select name="format" style="width: auto;" class="ft_format">
                        <option value="">Any</option>
<?php
    foreach (FORMAT as $formatName) { ?>
                        <option value="<?=display_str($formatName)?>"<?php if (isset($_GET['format']) && $formatName==$_GET['format']) { ?> selected="selected"<?php } ?>><?=display_str($formatName)?></option>
<?php
    } ?>                </select>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Filter torrents" />
                </td>
            </tr>
        </table>
    </form>
<?php
}

$disableFreeleechTorrentTop10 = $Viewer->option('DisableFreeTorrentTop10') ?? false;

if (isset($_GET['freeleech'])) {
    $newPreference = (($_GET['freeleech'] == 'hide') ? 1 : 0);
    if ($newPreference != $disableFreeleechTorrentTop10) {
        $disableFreeleechTorrentTop10 = $newPreference;
        $Viewer->modifyOption('DisableFreeTorrentTop10', $disableFreeleechTorrentTop10);
    }
}

$freeleechToggleName = isset($_GET['freeleech']) && $torrent->showFreeleechTorrents($_GET['freeleech']) ? 'show' : 'hide';
$freeleechToggleQuery = Format::get_url(['freeleech', 'groups']);

if (!empty($freeleechToggleQuery))
    $freeleechToggleQuery .= '&amp;';

$freeleechToggleQuery .= 'freeleech=' . $freeleechToggleName;

$groupByToggleName = (!empty($_GET['groups']) && $_GET['groups'] == 'show' ? 'hide' : 'show');
$groupByToggleQuery = Format::get_url(['freeleech', 'groups']);
if (!empty($groupByToggleQuery)) {
  $groupByToggleQuery .= '&amp;';
}

$groupByToggleQuery .= 'groups=' . $groupByToggleName;
?>
    <div style="text-align: right;" class="linkbox">
        <a href="top10.php?<?=$freeleechToggleQuery?>" class="brackets"><?=ucfirst($freeleechToggleName)?> freeleech in Top 10</a>
<?php if ($Viewer->permitted('users_mod')) { ?>
        <a href="top10.php?<?=$groupByToggleQuery?>" class="brackets"><?=ucfirst($groupByToggleName)?> top groups</a>
<?php } ?>
    </div>
<?php

if ($details == 'all' || $details == 'day') {
    $topTorrentsActiveLastDay = $torrent->getTopTorrents($_GET, 'day', $limit);
    generate_torrent_table('Most Active Torrents Uploaded in the Past Day', 'day', $topTorrentsActiveLastDay, $limit);
}

if ($details == 'all' || $details == 'week') {
    $topTorrentsActiveLastWeek = $torrent->getTopTorrents($_GET, 'week', $limit);
    generate_torrent_table('Most Active Torrents Uploaded in the Past Week', 'week', $topTorrentsActiveLastWeek, $limit);
}

if ($details == 'all' || $details == 'month') {
    $topTorrentsActiveLastMonth = $torrent->getTopTorrents($_GET, 'month', $limit);
    generate_torrent_table('Most Active Torrents Uploaded in the Past Month', 'month', $topTorrentsActiveLastMonth, $limit);
}

if ($details == 'all' || $details == 'year') {
    $topTorrentsActiveLastYear = $torrent->getTopTorrents($_GET, 'year', $limit);
    generate_torrent_table('Most Active Torrents Uploaded in the Past Year', 'year', $topTorrentsActiveLastYear, $limit);
}

if ($details == 'all' || $details == 'overall') {
    $topTorrentsActiveAllTime = $torrent->getTopTorrents($_GET, 'overall', $limit);
    generate_torrent_table('Most Active Torrents of All Time', 'overall', $topTorrentsActiveAllTime, $limit);
}

if (($details == 'all' || $details == 'snatched')) {
    $topTorrentsSnatched = $torrent->getTopTorrents($_GET, 'snatched', $limit);
    generate_torrent_table('Most Snatched Torrents', 'snatched', $topTorrentsSnatched, $limit);
}

if (($details == 'all' || $details == 'data')) {
    $topTorrentsTransferred = $torrent->getTopTorrents($_GET, 'data', $limit);
    generate_torrent_table('Most Data Transferred Torrents', 'data', $topTorrentsTransferred, $limit);
}

if (($details == 'all' || $details == 'seeded')) {
    $topTorrentsSeeded = $torrent->getTopTorrents($_GET, 'seeded', $limit);
    generate_torrent_table('Best Seeded Torrents', 'seeded', $topTorrentsSeeded, $limit);
}

?>
</div>
<?php
View::show_footer();

// generate a table based on data from most recent query to $DB
function generate_torrent_table($caption, $tag, $details, $limit) {
    global $groupBy, $torMan, $Viewer;
?>
        <h3>Top <?="$limit $caption"?>
<?php
    if (empty($_GET['advanced'])) { ?>
        <small class="top10_quantity_links">
<?php
        switch ($limit) {
            case 100: ?>
                - <a href="top10.php?details=<?=$tag?>" class="brackets">Top 10</a>
                - <span class="brackets">Top 100</span>
                - <a href="top10.php?type=torrents&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
<?php           break;
            case 250: ?>
                - <a href="top10.php?details=<?=$tag?>" class="brackets">Top 10</a>
                - <a href="top10.php?type=torrents&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
                - <span class="brackets">Top 250</span>
<?php           break;
            default: ?>
                - <span class="brackets">Top 10</span>
                - <a href="top10.php?type=torrents&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
                - <a href="top10.php?type=torrents&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
<?php   } ?>
        </small>
<?php
    }
    $urlStem = STATIC_SERVER . '/styles/' . $Viewer->stylesheetName() . '/images/';
?>
        </h3>
    <table class="torrent_table cats numbering border m_table">
    <tr class="colhead">
        <td class="center" style="width: 15px;"></td>
        <td class="cats_col"></td>
        <td class="m_th_left m_th_left_collapsable">Name</td>
        <td style="text-align: right;">Size</td>
        <td style="text-align: right;">Data</td>
        <td style="text-align: right;" class="sign snatches"><img src="<?= $urlStem ?>snatched.png" alt="Snatches" title="Snatches" class="tooltip" /></td>
        <td style="text-align: right;" class="sign seeders"><img src="<?= $urlStem ?>seeders.png" alt="Seeders" title="Seeders" class="tooltip" /></td>
        <td style="text-align: right;" class="sign leechers"><img src="<?= $urlStem ?>leechers.png" alt="Leechers" title="Leechers" class="tooltip" /></td>
        <td style="text-align: right;">Peers</td>
    </tr>
<?php
    // Server is already processing a top10 query. Starting another one will make things slow
    if ($details === false) {
?>
        <tr class="rowb">
            <td colspan="9" class="center">
                Server is busy processing another top list request. Please try again in a minute.
            </td>
        </tr>
        </table><br />
<?php
        return;
    }

    if (empty($details)) {
?>
        <tr class="rowb">
            <td colspan="9" class="center">
                Found no torrents matching the criteria.
            </td>
        </tr>
        </table><br />
<?php
        return;
    }

    $groupIds = array_column($details, 1);
    // exclude artists because it's retarded
    $groups = Torrents::get_groups($groupIds, true, false);
    $artists = Artists::get_artists($groupIds);

    $bookmark = new \Gazelle\Bookmark;
    foreach ($details as $index => $detail) {
        [$torrentID, $groupID, $data] = $detail;
        $group = $groups[$groupID];
        global $Debug;
        $Debug->log_var($group, $groupID);

        $isBookmarked = $bookmark->isTorrentBookmarked($Viewer->id(), $groupID);
        $isSnatched = Torrents::has_snatched($torrentID);

        // generate torrent's title
        $displayName = '';

        if (!empty($artists[$groupID])) {
            $displayName = Artists::display_artists($artists[$groupID], true, true);
        }

        $displayName .= "<a href=\"torrents.php?id=$groupID&amp;torrentid=$torrentID\" class=\"tooltip\" title=\"View torrent\" dir=\"ltr\">${group['Name']}</a>";

        if ($group['CategoryID'] == 1 && $group['Year'] > 0) {
            $displayName .= " [${group['Year']}]";
        }

        if ($group['CategoryID'] == 1 && $group['ReleaseType'] > 0) {
            $displayName .= ' [' . (new Gazelle\ReleaseType)->findNameById($group['ReleaseType']) . ']';
        }

        $torrentDetails     = $group['Torrents'][$torrentID];
        $torrentInformation = Torrents::torrent_info($torrentDetails);
        $torrentTags        = new Tags($group['TagList']);
        $reported           = $torMan->hasReport($Viewer, $torrentID);

        global $Twig;
?>
    <tr class="torrent row <?=$index % 2 ? 'a' : 'b'?> <?=($isBookmarked ? ' bookmarked' : '') . ($isSnatched ? ' snatched_torrent' : '')?>">
        <td style="padding: 8px; text-align: center;" class="td_rank m_td_left"><strong><?=$index + 1?></strong></td>
        <td class="center cats_col m_hidden"><div title="<?=$torrentTags->title()?>" class="tooltip <?=Format::css_category($group['CategoryID'])?> <?=$torrentTags->css_name()?>"></div></td>
        <td class="td_info big_info">
<?php   if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?php ImageTools::cover_thumb($group['WikiImage'], $group['CategoryID']) ?>
            </div>
<?php   } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => Torrents::can_use_token($torrentDetails),
                    'key'    => $Viewer->announceKey(),
                    't'      => $torrentDetails,
                ]) ?>
                <strong><?=$displayName?></strong> <?=$torrentInformation?><?php if ($reported) { ?> - <strong class="torrent_label tl_reported">Reported</strong><?php } ?>
<?php
        if ($isBookmarked) {
?>
                <span class="remove_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$groupID?>" class="bookmarklink_torrent_<?=$groupID?> brackets" onclick="Unbookmark('torrent', <?=$groupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                </span>
<?php   } else { ?>
                <span class="add_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$groupID?>" class="bookmarklink_torrent_<?=$groupID?> brackets" onclick="Bookmark('torrent', <?=$groupID?>, 'Remove bookmark'); return false;">Bookmark</a>
                </span>
<?php   } ?>
                <div class="tags"><?=$torrentTags->format()?></div>
            </div>
        </td>
        <td class="td_size number_column nobr"><?=Format::get_size($torrentDetails['Size'])?></td>
        <td class="td_data number_column nobr"><?=Format::get_size($data)?></td>
        <td class="td_snatched number_column m_td_right"><?=number_format((double)$torrentDetails['Snatched'])?></td>
        <td class="td_seeders number_column m_td_right"><?=number_format((double)$torrentDetails['Seeders'])?></td>
        <td class="td_leechers number_column m_td_right"><?=number_format((double)$torrentDetails['Leechers'])?></td>
        <td class="td_seeders_leechers number_column m_hidden"><?=number_format($torrentDetails['Seeders'] + $torrentDetails['Leechers'])?></td>
    </tr>
<?php
    } //foreach ($details as $detail)
?>
    </table><br />
<?php
}
