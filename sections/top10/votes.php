<?php

$vote = new Gazelle\Vote($Viewer->id());
$tagMan = new Gazelle\Manager\Tag;
$torMan = (new Gazelle\Manager\Torrent)->setViewer($Viewer);

$all = ($_GET['anyall'] ?? 'all') === 'all';

if (empty($_GET['advanced']) || !$Viewer->permitted('site_advanced_top10')) {
    $limit = (int)($_GET['limit'] ?? 25);
} else {
    $limit = 25;
    $vote->setTopYearInterval((int)$_GET['year1'], (int)$_GET['year2']);
    if (isset($_GET['tags'])) {
        $list = explode(',', trim($_GET['tags']));
        $tags = [];
        foreach ($list as $tag) {
            $t = $tagMan->sanitize($tag);
            if (!empty($t)) {
                $tags[] = $t;
            }
        }
        if ($tags) {
            $vote->setTopTagList($tags, $all);
        }
    }
}
$vote->setTopLimit($limit);
$imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);

View::show_header("Top $limit Voted Groups", ['js' => 'browse,voting']);
?>
<div class="thin">
    <div class="header">
        <h2>Top <?=$limit?> Voted Groups</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'votes']) ?>
    </div>
<?php if ($Viewer->permitted('site_advanced_top10')) { ?>
    <form class="search_form" name="votes" action="" method="get">
        <input type="hidden" name="advanced" value="1" />
        <input type="hidden" name="type" value="votes" />
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr id="tagfilter">
                <td class="label">Tags (comma-separated):</td>
                <td class="ft_taglist">
                    <input type="text" name="tags" size="75" value="<?php if (!empty($_GET['tags'])) { echo display_str($_GET['tags']);} ?>" />&nbsp;
                    <input type="radio" id="rdoAll" name="anyall" value="all"<?=($all ? ' checked="checked"' : '')?> /><label for="rdoAll"> All</label>&nbsp;&nbsp;
                    <input type="radio" id="rdoAny" name="anyall" value="any"<?=(!$all ? ' checked="checked"' : '')?> /><label for="rdoAny"> Any</label>
                </td>
            </tr>
            <tr id="yearfilter">
                <td class="label">Year:</td>
                <td class="ft_year">
                    <input type="text" name="year1" size="4" value="<?php if (!empty($_GET['year1'])) { echo display_str($_GET['year1']);} ?>" />
                    to
                    <input type="text" name="year2" size="4" value="<?php if (!empty($_GET['year2'])) { echo display_str($_GET['year2']);} ?>" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Filter torrents" />
                </td>
            </tr>
        </table>
    </form>
<?php } ?>
    <h3>Top <?=$limit?>
<?php if (empty($_GET['advanced'])) { ?>
        <small class="top10_quantity_links">
<?php
    switch ($limit) {
        case 100: ?>
            - <a href="top10.php?type=votes" class="brackets">Top 25</a>
            - <span class="brackets">Top 100</span>
            - <a href="top10.php?type=votes&amp;limit=250" class="brackets">Top 250</a>
<?php            break;
        case 250: ?>
            - <a href="top10.php?type=votes" class="brackets">Top 25</a>
            - <a href="top10.php?type=votes&amp;limit=100" class="brackets">Top 100</a>
            - <span class="brackets">Top 250</span>
<?php            break;
        default: ?>
            - <span class="brackets">Top 25</span>
            - <a href="top10.php?type=votes&amp;limit=100" class="brackets">Top 100</a>
            - <a href="top10.php?type=votes&amp;limit=250" class="brackets">Top 250</a>
<?php    } ?>
        </small>
<?php } ?>
    </h3>
<?php

$number = 0;
$torrentTable = '';
$bookmark = new Gazelle\Bookmark;
$userVotes = $vote->userVotes();
$topVotes = $vote->topVotes();
foreach ($topVotes as $groupID => $group) {
    ++$number;
    $groupName = $group['Name'];
    $groupYear = $group['Year'];
    $groupCategoryID = $group['CategoryID'];
    $torrentTags = new Tags($group['TagList']);
    $wikiImage = $group['WikiImage'];
    $torrents = isset($group['Torrents']) ? $group['Torrents'] : [];
    $artists = $group['Artists'];
    $extendedArtists = $group['ExtendedArtists'];
    $upVotes = $group['Ups'];
    $totalVotes = $group['Total'];
    $score = $group['Score'];
    $downVotes = $totalVotes - $upVotes;

    $isBookmarked = $bookmark->isTorrentBookmarked($Viewer->id(), $groupID);
    $userVote = $userVotes[$groupID] ?? '';

    $displayName = $group['Rank'] . " - ";

    if (!empty($extendedArtists[1]) || !empty($extendedArtists[4]) || !empty($extendedArtists[5])|| !empty($extendedArtists[6])) {
        unset($extendedArtists[2]);
        unset($extendedArtists[3]);
        $displayName .= Artists::display_artists($extendedArtists);
    } elseif (count($artists) > 0) {
        $displayName .= Artists::display_artists(['1' => $artists]);
    }

    $displayName .= '<a href="torrents.php?id='.$groupID.'" class="tooltip" title="View torrent group" dir="ltr">'.$groupName.'</a>';
    if ($groupYear > 0) {
        $displayName .= " [$groupYear]";
    }
    if ($group['VanityHouse']) {
        $displayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
    }
    // Start an output buffer, so we can store this output in $torrentTable
    ob_start();

    if (count($torrents) > 1 || $groupCategoryID == 1) {
        // Grouped torrents
        // TODO: Gazelle\TGroup knows how to do this, so remove this garbage
        $groupSnatched = false;
        foreach ($torrents as &$tinfo) {
            $torrent = $torMan->findById($tinfo['ID']);
            if (is_null($torrent)) {
                continue;
            }
            if (($tinfo['IsSnatched'] = $torrent->isSnatched($Viewer->id())) && !$groupSnatched) {
                $groupSnatched = true;
            }
        }
        unset($tinfo);
        $snatchedGroupClass = $groupSnatched ? ' snatched_group' : '';
?>
                <tr class="group groupid_<?=$groupID?>_header discog<?=$snatchedGroupClass?>" id="group_<?=$groupID?>">
                    <td class="center">
                        <div id="showimg_<?=$groupID?>" class="show_torrents">
                            <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?=$groupID?>, this, event);" title="Expand this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to expand all groups on this page."></a>
                        </div>
                    </td>
                    <td class="center cats_col">
                        <div title="<?=$torrentTags->title()?>" class="tooltip <?=Format::css_category($groupCategoryID)?> <?=$torrentTags->css_name()?>"></div>
                    </td>
                    <td class="big_info">
<?php        if ($Viewer->option('CoverArt')) { ?>
                        <div class="group_image float_left clear">
                            <?= $imgProxy->thumbnail($wikiImage, $groupCategoryID) ?>
                        </div>
<?php        } ?>
                        <div class="group_info clear">

                            <strong><?=$displayName?></strong>
<?php        if ($isBookmarked) { ?>
                            <span class="remove_bookmark float_right">
                                <a href="#" class="bookmarklink_torrent_<?=$groupID?> brackets" onclick="Unbookmark('torrent', <?=$groupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                            </span>
<?php        } else { ?>
                            <span class="add_bookmark float_right">
                                <a href="#" class="bookmarklink_torrent_<?=$groupID?> brackets" onclick="Bookmark('torrent', <?=$groupID?>, 'Remove bookmark'); return false;">Bookmark</a>
                            </span>
<?php        } ?>
                            <div class="tags"><?=$torrentTags->format()?></div>

                        </div>
                    </td>
                    <td colspan="4" class="votes_info_td">
                        <span style="white-space: nowrap;">
                            <span class="favoritecount_small tooltip" title="<?=$upVotes . ($upVotes == 1 ? ' upvote' : ' upvotes')?>"><span id="upvotes"><?=number_format($upVotes)?></span> <span class="vote_album_up">&and;</span></span>
                            &nbsp; &nbsp;
                            <span class="favoritecount_small tooltip" title="<?=$downVotes . ($downVotes == 1 ? ' downvote' : ' downvotes')?>"><span id="downvotes"><?=number_format($downVotes)?></span> <span class="vote_album_down">&or;</span></span>
                            &nbsp;
                            <span style="float: right;"><span class="favoritecount_small" id="totalvotes"><?=number_format($totalVotes)?></span> Total</span>
                        </span>
                        <br />
                        <span style="white-space: nowrap;">
                            <span class="tooltip_interactive" title="&lt;span style=&quot;font-weight: bold;&quot;&gt;Score: <?=number_format($score * 100, 4)?>&lt;/span&gt;&lt;br /&gt;&lt;br /&gt;This is the lower bound of the binomial confidence interval &lt;a href=&quot;wiki.php?action=article&amp;id=108&quot;&gt;described here&lt;/a&gt;, multiplied by 100." data-title-plain="Score: <?=number_format($score * 100, 4)?>. This is the lower bound of the binomial confidence interval described in the Favorite Album Votes wiki article, multiplied by 100.">Score: <span class="favoritecount_small"><?=number_format($score * 100, 1)?></span></span>
                            &nbsp; | &nbsp;
                            <span class="favoritecount_small"><?=number_format($upVotes / $totalVotes * 100, 1)?>%</span> positive
                        </span>
                    </td>
                </tr>
<?php
        $lastRemasterYear = '-';
        $lastRemasterTitle = '';
        $lastRemasterRecordLabel = '';
        $lastRemasterCatalogueNumber = '';
        $lastMedia = '';

        $editionID = 0;
        unset($firstUnknown);

        foreach ($torrents as $torrentID => $tinfo) {
            $torrent = $torMan->findById($torrentID);
            if (is_null($torrent)) {
                continue;
            }
            $reported = $torMan->hasReport($Viewer, $torrentID);
            if ($tinfo['Remastered'] && !$tinfo['RemasterYear']) {
                $firstUnknown = !isset($firstUnknown);
            }
            $snatchedTorrentClass = $tinfo['IsSnatched'] ? ' snatched_torrent' : '';

            if ($tinfo['RemasterTitle'] != $lastRemasterTitle
                || $tinfo['RemasterYear'] != $lastRemasterYear
                || $tinfo['RemasterRecordLabel'] != $lastRemasterRecordLabel
                || $tinfo['RemasterCatalogueNumber'] != $lastRemasterCatalogueNumber
                || (isset($firstUnknown) && $firstUnknown)
                || $tinfo['Media'] != $lastMedia
                ) {
                $editionID++;
?>
        <tr class="group_torrent groupid_<?=$groupID?> edition<?=$snatchedGroupClass?> hidden">
            <td colspan="7" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$groupID?>, <?=$editionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($tinfo, $group)?></strong></td>
        </tr>
<?php
            }
            $lastRemasterTitle = $tinfo['RemasterTitle'];
            $lastRemasterYear = $tinfo['RemasterYear'];
            $lastRemasterRecordLabel = $tinfo['RemasterRecordLabel'];
            $lastRemasterCatalogueNumber = $tinfo['RemasterCatalogueNumber'];
            $lastMedia = $tinfo['Media'];
?>
        <tr class="group_torrent torrent_row groupid_<?=$groupID?> edition_<?=$editionID?><?=$snatchedTorrentClass . $snatchedGroupClass?> hidden">
            <td colspan="3">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $tinfo,
                ]) ?>
                &nbsp;&nbsp;&raquo;&nbsp; <a href="torrents.php?id=<?=$groupID?>&amp;torrentid=<?=$torrentID?>"><?=Torrents::torrent_info($tinfo)?><?php if ($reported) { ?> / <strong class="torrent_label tl_reported">Reported</strong><?php } ?></a>
            </td>
            <td class="number_column nobr"><?=Format::get_size($tinfo['Size'])?></td>
            <td class="number_column"><?=number_format($tinfo['Snatched'])?></td>
            <td class="number_column<?=($tinfo['Seeders'] == 0) ? ' r00' : '' ?>"><?=number_format($tinfo['Seeders'])?></td>
            <td class="number_column"><?=number_format($tinfo['Leechers'])?></td>
        </tr>
<?php
        }
    } else { //if (count($torrents) > 1 || $groupCategoryID == 1)
        // Viewing a type that does not require grouping
        $torrentID = key($torrents);
        $torrent = $torMan->findById($torrentID);
        if (is_null($torrent)) {
            continue;
        }
        $tinfo = current($torrents);
        $tinfo['IsSnatched'] = $torrent->isSnatched($Viewer->id());

        $displayName = $number .' - <a href="torrents.php?id='.$groupID.'" class="tooltip" title="View torrent group" dir="ltr">'.$groupName.'</a>';
        if ($tinfo['IsSnatched']) {
            $displayName .= ' ' . Format::torrent_label('Snatched!');
        }
        if ($tinfo['FreeTorrent'] == '1') {
            $displayName .= ' ' . Format::torrent_label('Freeleech!');
        } elseif ($tinfo['FreeTorrent'] == '2') {
            $displayName .= ' ' . Format::torrent_label('Neutral leech!');
        } elseif ($torrent->hasToken($Viewer->id())) {
            $displayName .= ' ' . Format::torrent_label('Personal freeleech!');
        }
        $snatchedTorrentClass = $tinfo['IsSnatched'] ? ' snatched_torrent' : '';
?>
        <tr class="torrent torrent_row<?=$snatchedTorrentClass . $snatchedGroupClass?>" id="group_<?=$groupID?>">
            <td></td>
            <td class="center cats_col">
                <div title="<?=$torrentTags->title()?>" class="tooltip <?=Format::css_category($groupCategoryID)?> <?=$torrentTags->css_name()?>">
                </div>
            </td>
            <td class="nobr big_info">
<?php        if ($Viewer->option('CoverArt')) { ?>
                <div class="group_image float_left clear">
                    <?= $imgProxy->thumbnail($wikiImage, $groupCategoryID) ?>
                </div>
<?php        } ?>
                <div class="group_info clear">
                    <?= $Twig->render('torrent/action.twig', [
                        'can_fl' => $Viewer->canSpendFLToken($torrent),
                        'key'    => $Viewer->announceKey(),
                        't'      => $tinfo,
                        'extra'  => [
                            "<a href=\"#\" id=\"bookmarklink_torrent_<?=$groupID?>\" " . $isBookmarked
                                ? "class=\"remove_bookmark\" onclick=\"Unbookmark('torrent', <?=$groupID?>, 'Bookmark'); return false;\">Remove bookmark</a>"
                                : "class=\"add_bookmark\" onclick=\"Bookmark('torrent', <?=$groupID?>, 'Remove bookmark'); return false;\">Bookmark</a>"
                        ],
                    ]) ?>
                    <strong><?=$displayName?></strong>
                    <div class="tags"><?=$torrentTags->format()?></div>
                </div>
            </td>
            <td class="number_column nobr"><?=Format::get_size($tinfo['Size'])?></td>
            <td class="number_column"><?=number_format($tinfo['Snatched'])?></td>
            <td class="number_column<?=($tinfo['Seeders'] == 0) ? ' r00' : '' ?>"><?=number_format($tinfo['Seeders'])?></td>
            <td class="number_column"><?=number_format($tinfo['Leechers'])?></td>
        </tr>
<?php
    } //if (count($torrents) > 1 || $groupCategoryID == 1)
    $torrentTable .= ob_get_clean();
}
$urlStem = STATIC_SERVER . '/styles/' . $Viewer->stylesheetName() . '/images/';
?>
<table class="torrent_table grouping cats m_table" id="discog_table">
    <tr class="colhead_dark">
        <td><!-- expand/collapse --></td>
        <td class="cats_col"><!-- category --></td>
        <td class="m_th_left" width="70%">Torrents</td>
        <td>Size</td>
        <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" alt="Snatches" title="Snatches" class="tooltip" /></td>
        <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" alt="Seeders" title="Seeders" class="tooltip" /></td>
        <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" alt="Leechers" title="Leechers" class="tooltip" /></td>
    </tr>
<?php
if ($topVotes === false) { ?>
    <tr>
        <td colspan="7" class="center">Server is busy processing another top list request. Please try again in a minute.</td>
    </tr>
<?php
} elseif (count($topVotes) === 0) { ?>
    <tr>
        <td colspan="7" class="center">No torrents were found that meet your criteria.</td>
    </tr>
<?php
} else {
    echo $torrentTable;
}
?>
</table>
</div>
<?php
View::show_footer();
