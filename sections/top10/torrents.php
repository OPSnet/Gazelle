<?php

$torrent = new \Gazelle\Top10\Torrent(FORMAT, $Viewer);
$torMan = new Gazelle\Manager\Torrent;
$torMan->setViewer($Viewer);
$snatcher = new Gazelle\User\Snatch($Viewer);

if (!empty($_GET['advanced']) && $Viewer->permitted('site_advanced_top10')) {
    $details = 'all';
    $limit = 10;
} else {
    $details = $_GET['details'] ?? 'all';
    $details = in_array($_GET['details'] ?? '', ['day', 'week', 'overall', 'snatched', 'data', 'seeded', 'month', 'year'])
        ? $details : 'all';

    $limit = $_GET['limit'] ?? 10;
    $limit = in_array($limit, [10, 100, 250]) ? $limit : 10;
}

View::show_header("Top $limit Torrents");
?>
<div class="thin">
    <div class="header">
        <h2>Top <?=$limit?> Torrents</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'torrents']) ?>
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
    global $groupBy, $torMan, $Twig, $Viewer;
?>
        <h3>Top <?="$limit $caption"?>
<?php if (empty($_GET['advanced'])) { ?>
        <small class="top10_quantity_links">
<?php   if ($limit == 100) { ?>
                - <a href="top10.php?details=<?=$tag?>" class="brackets">Top 10</a>
                - <span class="brackets">Top 100</span>
                - <a href="top10.php?type=torrents&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
<?php   } elseif ($limit == 250) { ?>
            case 250: ?>
                - <a href="top10.php?details=<?=$tag?>" class="brackets">Top 10</a>
                - <a href="top10.php?type=torrents&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
                - <span class="brackets">Top 250</span>
<?php   } else { ?>
                - <span class="brackets">Top 10</span>
                - <a href="top10.php?type=torrents&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
                - <a href="top10.php?type=torrents&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
<?php   } ?>
        </small>
<?php
    }
    $urlStem = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
?>
        </h3>
    <table class="torrent_table cats numbering border m_table">
    <tr class="colhead">
        <td class="center" style="width: 15px;"></td>
        <td class="cats_col"></td>
        <td class="m_th_left m_th_left_collapsable">Name</td>
        <td style="text-align: right;">Size</td>
        <td style="text-align: right;">Transferred</td>
        <td style="text-align: right;" class="sign snatches"><img src="<?= $urlStem ?>snatched.png" alt="Snatches" title="Snatches" class="tooltip" /></td>
        <td style="text-align: right;" class="sign seeders"><img src="<?= $urlStem ?>seeders.png" alt="Seeders" title="Seeders" class="tooltip" /></td>
        <td style="text-align: right;" class="sign leechers"><img src="<?= $urlStem ?>leechers.png" alt="Leechers" title="Leechers" class="tooltip" /></td>
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
    $bookmark = new \Gazelle\Bookmark($Viewer);
    $imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);
    foreach ($details as $index => $detail) {
        [$torrentId, $groupId, $data] = $detail;
        $torrent = $torMan->findById($torrentId);
        if (is_null($torrent)) {
            continue;
        }
        $tgroup       = $torrent->group();
        $isBookmarked = $bookmark->isTorrentBookmarked($groupId);
        $isSnatched   = $snatcher->showSnatch($torrent->id());
        $reported     = $torMan->hasReport($Viewer, $torrentId);

?>
    <tr class="torrent row <?=$index % 2 ? 'a' : 'b'?> <?=($isBookmarked ? ' bookmarked' : '') . ($isSnatched ? ' snatched_torrent' : '')?>">
        <td style="padding: 8px; text-align: center;" class="td_rank m_td_left"><strong><?=$index + 1?></strong></td>
        <td class="center cats_col m_hidden"><div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?=
            Format::css_category($tgroup->categoryId()) ?> tags_<?= str_replace('.', '_', $tgroup->primaryTag()) ?>"></div></td>
        <td class="td_info big_info">
<?php   if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->thumbnail($tgroup->image() ?? '', $tgroup->categoryId()) ?>
            </div>
<?php   } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action-v2.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $torrent,
                ]) ?>
                <strong><?= $tgroup->link() ?></strong><br />[<?= $torrent->edition() ?>] [<?= $torrent->label() ?>]<?php if ($reported) { ?> - <strong class="torrent_label tl_reported">Reported</strong><?php } ?>
<?php   if ($isBookmarked) { ?>
                <span class="remove_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$groupId?>" class="bookmarklink_torrent_<?=$groupId?> brackets" onclick="Unbookmark('torrent', <?=$groupId?>, 'Bookmark'); return false;">Remove bookmark</a>
                </span>
<?php   } else { ?>
                <span class="add_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$groupId?>" class="bookmarklink_torrent_<?=$groupId?> brackets" onclick="Bookmark('torrent', <?=$groupId?>, 'Remove bookmark'); return false;">Bookmark</a>
                </span>
<?php   } ?>
                <div class="tags"><?= implode(', ', $tgroup->tagNameList()) ?></div>
            </div>
        </td>
        <td class="td_size number_column nobr"><?= Format::get_size($torrent->size()) ?></td>
        <td class="td_data number_column nobr"><?=Format::get_size($data)?></td>
        <td class="td_snatched number_column m_td_right"><?= number_format($torrent->snatchTotal()) ?></td>
        <td class="td_seeders number_column m_td_right"><?= number_format($torrent->seederTotal()) ?></td>
        <td class="td_leechers number_column m_td_right"><?= number_format($torrent->leecherTotal()) ?></td>
    </tr>
<?php } ?>
    </table><br />
<?php
}
