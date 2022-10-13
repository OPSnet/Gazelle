<?php

$bookmark = new Gazelle\Bookmark($Viewer);
$snatcher = new Gazelle\User\Snatch($Viewer);
$top10    = new Gazelle\Top10\Torrent(FORMAT, $Viewer);
$torMan   = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$urlStem  = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
$imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);

if (!empty($_GET['advanced']) && $Viewer->permitted('site_advanced_top10')) {
    $details = 'all';
    $limit   = 10;
} else {
    $details = in_array($_GET['details'] ?? '', ['day', 'week', 'overall', 'snatched', 'data', 'seeded', 'month', 'year'])
        ? $_GET['details'] : 'all';

    $limit = (int)($_GET['limit'] ?? 10);
    $limit = in_array($limit, [10, 100, 250]) && $details !== 'all' ? $limit : 10;
}

$anyAll = $_GET['anyall'] ?? 'all';
$excludedArtists = $_GET['excluded_artists'] ?? '';
$format          = $_GET['format'] ?? '';
$tags            = $_GET['tags'] ?? '';

$hideFreeleechTorrentTop10 = (int)$Viewer->option('DisableFreeTorrentTop10');
if (isset($_GET['freeleech'])) {
    $newPreference = (int)($_GET['freeleech'] == 'hide');
    if ($newPreference != $hideFreeleechTorrentTop10) {
        $hideFreeleechTorrentTop10 = $newPreference;
        $Viewer->modifyOption('DisableFreeTorrentTop10', $hideFreeleechTorrentTop10);
    }
}

$freeleechToggleQuery = Format::get_url(['freeleech', 'groups']);
if (!empty($freeleechToggleQuery)) {
    $freeleechToggleQuery .= '&amp;';
}
$freeleechToggleName = $top10->showFreeleechTorrents($hideFreeleechTorrentTop10) ? 'show' : 'hide';
$freeleechToggleQuery .= 'freeleech=' . $freeleechToggleName;

$groupByToggleQuery = Format::get_url(['freeleech', 'groups']);
if (!empty($groupByToggleQuery)) {
  $groupByToggleQuery .= '&amp;';
}
$groupByToggleName = ($_GET['groups'] ?? '') == 'show' ? 'hide' : 'show';
$groupByToggleQuery .= 'groups=' . $groupByToggleName;

$context = [];
if (in_array($details, ['all', 'day'])) {
    $context[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Day',
        'tag'     => 'day',
        'list'    => $top10->getTopTorrents($_GET, 'day', $limit),
    ];
}
if (in_array($details, ['all', 'week'])) {
    $context[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Week',
        'tag'     => 'week',
        'list'    => $top10->getTopTorrents($_GET, 'week', $limit),
    ];
}
if (in_array($details, ['all', 'month'])) {
    $context[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Month',
        'tag'     => 'month',
        'list'    => $top10->getTopTorrents($_GET, 'month', $limit),
    ];
}
if (in_array($details, ['all', 'year'])) {
    $context[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Year',
        'tag'     => 'year',
        'list'    => $top10->getTopTorrents($_GET, 'year', $limit),
    ];
}
if (in_array($details, ['all', 'overall'])) {
    $context[] = [
        'caption' => 'Most Active Torrents of All Time',
        'tag'     => 'overall',
        'list'    => $top10->getTopTorrents($_GET, 'overall', $limit),
    ];
}
if (in_array($details, ['all', 'snatched'])) {
    $context[] = [
        'caption' => 'Most Snatched Torrents',
        'tag'     => 'snatched',
        'list'    => $top10->getTopTorrents($_GET, 'snatched', $limit),
    ];
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
                    <input type="text" name="tags" id="tags" size="75" value="<?= display_str($tags) ?>"<?=
                        $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />&nbsp;
                    <input type="radio" id="rdoAll" name="anyall" value="all"<?= $anyAll == 'all' ? ' checked="checked"' : '' ?> /><label for="rdoAll"> All</label>&nbsp;&nbsp;
                    <input type="radio" id="rdoAny" name="anyall" value="any"<?= $anyAll != 'all' ? ' checked="checked"' : '' ?> /><label for="rdoAny"> Any</label>
                </td>
            </tr>
            <tr id="artistfilter">
                <td class="label">Exclude Artists (one on each line):</td>
                <td>
                    <textarea name="excluded_artists" rows="3" cols="25" style="width: 95%"><?= display_str($excludedArtists) ?></textarea>&nbsp;
                </td>
            </tr>
            <tr>
                <td class="label">Format:</td>
                <td>
                    <select name="format" style="width: auto;" class="ft_format">
                        <option value="">Any</option>
<?php foreach (FORMAT as $formatName) { ?>
                        <option value="<?=display_str($formatName)?>"<?php if ($format) { ?> selected="selected"<?php } ?>><?=display_str($formatName)?></option>
<?php } ?>
                    </select>
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
?>
    <div style="text-align: right;" class="linkbox">
        <a href="top10.php?<?=$freeleechToggleQuery?>" class="brackets"><?=ucfirst($freeleechToggleName)?> freeleech in Top 10</a>
<?php if ($Viewer->permitted('users_mod')) { ?>
        <a href="top10.php?<?=$groupByToggleQuery?>" class="brackets"><?=ucfirst($groupByToggleName)?> top groups</a>
<?php } ?>
    </div>
<?php
foreach ($context as $c) {
    $tag     = $c['tag'];
    $details = $c['list'];
?>
        <h3>Top <?= $limit ?> <?= $c['caption'] ?>
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
        continue;
    } elseif (empty($details)) {
?>
        <tr class="rowb">
            <td colspan="9" class="center">
                Found no torrents matching the criteria.
            </td>
        </tr>
        </table><br />
<?php
        continue;
    }

    $groupIds = array_column($details, 1);
    foreach ($details as $index => $detail) {
        [$torrentId, $groupId, $data] = $detail;
        $torrent = $torMan->findById($torrentId);
        if (is_null($torrent)) {
            continue;
        }
        $tgroup       = $torrent->group();
        $isBookmarked = $bookmark->isTorrentBookmarked($groupId);
?>
    <tr class="torrent row <?=$index % 2 ? 'a' : 'b'?> <?=($isBookmarked ? ' bookmarked' : '')
        . ($snatcher->showSnatch($torrent->id()) ? ' snatched_torrent' : '')?>">
        <td style="padding: 8px; text-align: center;" class="td_rank m_td_left"><strong><?=$index + 1?></strong></td>
        <td class="center cats_col m_hidden"><div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?=
            $tgroup->categoryCss() ?> tags_<?= str_replace('.', '_', $tgroup->primaryTag()) ?>"></div></td>
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
                <strong><?= $tgroup->link() ?></strong><br />[<?= $torrent->edition() ?>] [<?= $torrent->label() ?>]<?php if ($torMan->hasReport($Viewer, $torrentId)) { ?> - <strong class="torrent_label tl_reported">Reported</strong><?php } ?>
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
<?php } ?>
</div>
<?php
View::show_footer();
