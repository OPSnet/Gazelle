<?php
ini_set('max_execution_time', 600);
set_time_limit(0);

//~~~~~~~~~~~ Main bookmarks page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

function compare($X, $Y) {
    return($Y['count'] - $X['count']);
}

if (empty($_GET['userid'])) {
    $user = $Viewer;
    $ownProfile = true;
} else {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
    $ownProfile = ($user->id() === $Viewer->id());
}

$NumGroups = 0;
$ArtistCount = [];

[$GroupIDs, $CollageDataList, $TorrentList] = $user->bookmarkList();
foreach ($GroupIDs as $Idx => $GroupID) {
    if (!isset($TorrentList[$GroupID])) {
        unset($GroupIDs[$Idx]);
        continue;
    }
    // Handle stats and stuff
    $NumGroups++;

    $Artists = $TorrentList[$GroupID]['Artists'];
    if ($Artists) {
        foreach ($Artists as $Artist) {
            if (!isset($ArtistCount[$Artist['id']])) {
                $ArtistCount[$Artist['id']] = ['name' => $Artist['name'], 'count' => 1];
            } else {
                $ArtistCount[$Artist['id']]['count']++;
            }
        }
    }
}

$GroupIDs = array_values($GroupIDs);
$CollageCovers = isset($LoggedUser['CollageCovers']) ? (int)$LoggedUser['CollageCovers'] : 25;
$title = $user->username() . " &rsaquo; Bookmarked torrent groups";

View::show_header($title, ['js' => 'browse,collage']);
?>
<div class="thin">
    <div class="header">
        <h2><?php if ($ownProfile) { ?><a href="feeds.php?feed=torrents_bookmarks_t_<?=
            $Viewer->auth() ?>&amp;user=<?= $Viewer->id() ?>&amp;auth=<?=
            $Viewer->rssAuth() ?>&amp;passkey=<?= $Viewer->announceKey() ?>&amp;authkey=<?=
            $Viewer->auth()?>&amp;name=<?=urlencode(SITE_NAME . ': Bookmarked Torrents')?>"><img src="<?=
            STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>&nbsp;<?php } ?><?= $title ?></h2>
        <div class="linkbox">
            <a href="bookmarks.php?type=torrents" class="brackets">Torrents</a>
            <a href="bookmarks.php?type=artists" class="brackets">Artists</a>
            <a href="bookmarks.php?type=collages" class="brackets">Collages</a>
            <a href="bookmarks.php?type=requests" class="brackets">Requests</a>
<?php
if (count($TorrentList) > 0) { ?>
            <br /><br />
            <a href="bookmarks.php?action=remove_snatched&amp;auth=<?= $Viewer->auth() ?>" class="brackets" onclick="return confirm('Are you sure you want to remove the bookmarks for all items you\'ve snatched?');">Remove snatched</a>
            <a href="bookmarks.php?action=edit&amp;type=torrents" class="brackets">Manage torrents</a>
<?php
} ?>
        </div>
    </div>
<?php
if (count($TorrentList) === 0) { ?>
    <div class="box pad" align="center">
        <h2>You have not bookmarked any torrents.</h2>
    </div>
</div><!--content-->
<?php
    View::show_footer();
    die();
} ?>
    <div class="sidebar">
        <div class="box box_info box_statistics_bookmarked_torrents">
            <div class="head"><strong>Stats</strong></div>
            <ul class="stats nobullet">
                <li>Torrent groups: <?=$NumGroups?></li>
                <li>Artists: <?=count($ArtistCount)?></li>
            </ul>
        </div>
        <div class="box box_tags">
            <div class="head"><strong>Top Tags</strong></div>
            <div class="pad">
                <ol style="padding-left: 5px;">
<?php Tags::format_top(5) ?>
                </ol>
            </div>
        </div>
        <div class="box box_artists">
            <div class="head"><strong>Top Artists</strong></div>
            <div class="pad">
<?php
    $Indent = "\t\t\t\t";
    if (count($ArtistCount) > 0) {
        echo "$Indent<ol style=\"padding-left: 5px;\">\n";
        uasort($ArtistCount, 'compare');
        $i = 0;
        foreach ($ArtistCount as $ID => $Artist) {
            $i++;
            if ($i > 10) {
                break;
            }
?>
                    <li><a href="artist.php?id=<?=$ID?>"><?=display_str($Artist['name'])?></a> (<?=$Artist['count']?>)</li>
<?php
        }
        echo "$Indent</ol>\n";
    } else {
        echo "$Indent<ul class=\"nobullet\" style=\"padding-left: 5px;\">\n";
        echo "$Indent\t<li>There are no artists to display.</li>\n";
        echo "$Indent</ul>\n";
    }
?>
            </div>
        </div>
    </div>
    <div class="main_column">
<?php

if ($CollageCovers !== 0) { ?>
        <div id="coverart" class="box">
            <div class="head" id="coverhead"><strong>Cover art</strong></div>
            <ul class="collage_images" id="collage_page0">
<?php
    $collMan = new Gazelle\Manager\Collage;
    for ($Idx = 0; $Idx < min($NumGroups, $CollageCovers); $Idx++) {
        echo $collMan->coverRow($TorrentList[$GroupIDs[$Idx]]);
    }
?>
            </ul>
        </div>
<?php
    if ($NumGroups > $CollageCovers) { ?>
        <div class="linkbox pager" style="clear: left;" id="pageslinksdiv">
            <span id="firstpage" class="invisible"><a href="#" class="pageslink" onclick="collageShow.page(0); return false;">&laquo; First</a> | </span>
            <span id="prevpage" class="invisible"><a href="#" id="prevpage" class="pageslink" onclick="collageShow.prevPage(); return false;">&lsaquo; Prev</a> | </span>
<?php   for ($i = 0; $i < $NumGroups / $CollageCovers; $i++) { ?>
            <span id="pagelink<?=$i?>" class="<?=(($i > 4) ? 'hidden' : '')?><?=(($i === 0) ? ' selected' : '')?>"><a href="#" class="pageslink" onclick="collageShow.page(<?=$i?>, this); return false;"><?=($CollageCovers * $i + 1)?>-<?=min($NumGroups, $CollageCovers * ($i + 1))?></a><?=(($i !== ceil($NumGroups / $CollageCovers) - 1) ? ' | ' : '')?></span>
<?php   } ?>
            <!--<span id="nextbar" class="<?=(($NumGroups / $CollageCovers > 5) ? 'hidden' : '')?>"> | </span>-->
            <span id="nextpage"><a href="#" class="pageslink" onclick="collageShow.nextPage(); return false;">Next &rsaquo;</a></span>
            <span id="lastpage" class="<?=(ceil($NumGroups / $CollageCovers) === 2 ? 'invisible' : '')?>"> | <a href="#" id="lastpage" class="pageslink" onclick="collageShow.page(<?=(ceil($NumGroups / $CollageCovers) - 1)?>); return false;">Last &raquo;</a></span>
        </div>
        <script type="text/javascript">
<?php
        $CollagePages = [];
        for ($i = 0; $i < $NumGroups / $CollageCovers; $i++) {
            $Groups = array_slice($GroupIDs, $i * $CollageCovers, $CollageCovers);
            $CollagePages[] = implode('',
                array_map(
                    function($id) use ($collMan, $TorrentList) {
                        return $collMan->coverRow($TorrentList[$id]);
                    },
                    $Groups
                )
            );
        }
?>
            collageShow.init(<?=json_encode($CollagePages)?>);
        </script>
<?php
        unset($CollagePages);
    }
}
$urlStem = STATIC_SERVER . '/styles/' . $Viewer->stylesheetName() . '/images/';
?>
        <table class="torrent_table grouping cats m_table" id="torrent_table">
            <tr class="colhead_dark">
                <td><!-- expand/collapse --></td>
                <td><!-- Category --></td>
                <td class="m_th_left m_th_left_collapsable" width="70%"><strong>Torrents</strong></td>
                <td>Size</td>
                <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php
$groupsClosed = (bool)$Viewer->option('TorrentGrouping');
foreach ($GroupIDs as $Idx => $GroupID) {
    $Group = $TorrentList[$GroupID];
    $GroupName = $Group['Name'];
    $GroupYear = $Group['Year'];
    $GroupCategoryID = $Group['CategoryID'];
    $GroupFlags = isset($Group['Flags']) ? $Group['Flags'] : ['IsSnatched' => false];
    $TorrentTags = new Tags($Group['TagList'], false);
    $Torrents = isset($Group['Torrents']) ? $Group['Torrents'] : [];
    $Artists = $Group['Artists'];
    $ExtendedArtists = $Group['ExtendedArtists'];

    [, $Sort, $AddedTime] = array_values($CollageDataList[$GroupID]);

    if ($Artists) {
        foreach ($Artists as $Artist) {
            if (!isset($ArtistCount[$Artist['id']])) {
                $ArtistCount[$Artist['id']] = ['name' => $Artist['name'], 'count' => 1];
            }
            else {
                $ArtistCount[$Artist['id']]['count']++;
            }
        }
    }

    if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5]) || !empty($ExtendedArtists[6])) {
        unset($ExtendedArtists[2]);
        unset($ExtendedArtists[3]);
        $DisplayName = Artists::display_artists($ExtendedArtists);
    }
    elseif (count($Artists) > 0) {
        $DisplayName = Artists::display_artists(['1' => $Artists]);
    }
    else {
        $DisplayName = '';
    }
    $DisplayName .= '<a href="torrents.php?id=' . $GroupID . '" class="tooltip" title="View torrent group" dir="ltr">' . $GroupName . '</a>';
    if ($GroupYear > 0) {
        $DisplayName = "$DisplayName [$GroupYear]";
    }
    if ($Group['VanityHouse']) {
        $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
    }
    $SnatchedGroupClass = $GroupFlags['IsSnatched'] ? ' snatched_group' : '';

    // Start an output buffer, so we can store this output in $TorrentTable
    ob_start();
    if (count($Torrents) > 1 || $GroupCategoryID == 1) {
        // Grouped torrents
?>
        <tr class="group groupid_<?=$GroupID?>_header discog" id="group_<?= $GroupID ?>">
            <td class="td_collapse m_td_left center">
                <div id="showimg_<?= $GroupID ?>" class="<?= $groupsClosed ? 'show' : 'hide' ?>_torrents">
                    <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?= $GroupID ?>, this, event);"
                       title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collape all groups on this page."></a>
                </div>
            </td>
            <td class="m_hidden center">
                <div title="<?= $TorrentTags->title() ?>"
                     class="tooltip <?= Format::css_category($GroupCategoryID) ?> <?= $TorrentTags->css_name() ?>"></div>
            </td>
            <td class="td_info" colspan="5">
                <strong><?= $DisplayName ?></strong>
                <span style="text-align: right;" class="float_right">
 <?php if ($ownProfile) { ?>
        <a href="#group_<?= $GroupID ?>" class="brackets remove_bookmark"
           onclick="Unbookmark('torrent', <?= $GroupID ?>, ''); return false;">Remove bookmark</a>
        <br/>
<?php } ?>
                    <?= time_diff($AddedTime); ?>
                        </span>
                <div class="tags"><?= $TorrentTags->format() ?></div>
            </td>
        </tr>
<?php
        $LastRemasterYear = '-';
        $LastRemasterTitle = '';
        $LastRemasterRecordLabel = '';
        $LastRemasterCatalogueNumber = '';
        $LastMedia = '';

        $EditionID = 0;
        unset($FirstUnknown);

        foreach ($Torrents as $TorrentID => $Torrent) {

            if ($Torrent['Remastered'] && !$Torrent['RemasterYear']) {
                $FirstUnknown = !isset($FirstUnknown);
            }
            $SnatchedTorrentClass = $Torrent['IsSnatched'] ? ' snatched_torrent' : '';

            if (
                $Torrent['RemasterTitle'] != $LastRemasterTitle
                || $Torrent['RemasterYear'] != $LastRemasterYear
                || $Torrent['RemasterRecordLabel'] != $LastRemasterRecordLabel
                || $Torrent['RemasterCatalogueNumber'] != $LastRemasterCatalogueNumber
                || (isset($FirstUnknown) && $FirstUnknown)
                || $Torrent['Media'] != $LastMedia
            ) {
                $EditionID++;
?>
                <tr class="group_torrent groupid_<?= $GroupID ?> edition<?= $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                    <td colspan="7" class="edition_info"><strong><a href="#"
                        onclick="toggle_edition(<?= $GroupID ?>, <?= $EditionID ?>, this, event)"
                        class="tooltip"
                        title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a>
                            <?= Torrents::edition_string($Torrent, $Group) ?>
                        </strong></td>
                </tr>
<?php
            }
            $LastRemasterTitle = $Torrent['RemasterTitle'];
            $LastRemasterYear = $Torrent['RemasterYear'];
            $LastRemasterRecordLabel = $Torrent['RemasterRecordLabel'];
            $LastRemasterCatalogueNumber = $Torrent['RemasterCatalogueNumber'];
            $LastMedia = $Torrent['Media'];
?>
            <tr class="group_torrent torrent_row groupid_<?= $GroupID ?> edition_<?= $EditionID ?><?= $SnatchedTorrentClass . $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                <td class="td_info" colspan="3">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => Torrents::can_use_token($Torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $Torrent,
                ]) ?>
                    &nbsp;&nbsp;&raquo;&nbsp; <a
                            href="torrents.php?id=<?= $GroupID ?>&amp;torrentid=<?= $TorrentID ?>"><?= Torrents::torrent_info($Torrent) ?></a>
                </td>
                <td class="td_size number_column nobr"><?= Format::get_size($Torrent['Size']) ?></td>
                <td class="td_snatched m_td_right number_column"><?= number_format($Torrent['Snatched']) ?></td>
                <td class="td_seeders m_td_right number_column<?= (($Torrent['Seeders'] == 0) ? ' r00' : '') ?>"><?= number_format($Torrent['Seeders']) ?></td>
                <td class="td_leechers m_td_right number_column"><?= number_format($Torrent['Leechers']) ?></td>
            </tr>
<?php
        }
    }
    else {
        // Viewing a type that does not require grouping
        $TorrentID = key($Torrents);
        $Torrent = current($Torrents);

        $DisplayName = '<a href="torrents.php?id=' . $GroupID . '" class="tooltip" title="View torrent group" dir="ltr">' . $GroupName . '</a>';

        if ($Torrent['IsSnatched']) {
            $DisplayName .= ' ' . Format::torrent_label('Snatched!');
        }
        if ($Torrent['FreeTorrent'] === '1') {
            $DisplayName .= ' ' . Format::torrent_label('Freeleech!');
        }
        elseif ($Torrent['FreeTorrent'] === '2') {
            $DisplayName .= ' ' . Format::torrent_label('Neutral leech!');
        }
        elseif ($Torrent['PersonalFL']) {
            $DisplayName .= ' ' . Format::torrent_label('Personal Freeleech!');
        }
        $SnatchedTorrentClass = $Torrent['IsSnatched'] ? ' snatched_torrent' : '';
?>
        <tr class="torrent torrent_row<?= $SnatchedTorrentClass . $SnatchedGroupClass ?>" id="group_<?= $GroupID ?>">
            <td></td>
            <td class="center">
                <div title="<?= $TorrentTags->title() ?>"
                     class="tooltip <?= Format::css_category($GroupCategoryID) ?> <?= $TorrentTags->css_name() ?>">
                </div>
            </td>
            <td>
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => Torrents::can_use_token($Torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $Torrent,
                ]) ?>
                <strong><?= $DisplayName ?></strong>
                <div class="tags"><?= $TorrentTags->format() ?></div>
<?php if ($ownProfile) { ?>
                    <span class="float_right float_clear"><a href="#group_<?= $GroupID
                        ?>" class="brackets remove_bookmark" onclick="Unbookmark('torrent', <?= $GroupID ?>, ''); return false;">Remove bookmark</a></span>
<?php } ?>
                <span class="float_right float_clear"><?= time_diff($AddedTime); ?></span>

            </td>
            <td class="number_column nobr"><?= Format::get_size($Torrent['Size']) ?></td>
            <td class="number_column"><?= number_format($Torrent['Snatched']) ?></td>
            <td class="number_column<?= (($Torrent['Seeders'] == 0) ? ' r00' : '') ?>"><?= number_format($Torrent['Seeders']) ?></td>
            <td class="number_column"><?= number_format($Torrent['Leechers']) ?></td>
        </tr>
<?php
    }
    echo ob_get_clean();
}
?>
        </table>
    </div>
</div>

<?php
View::show_footer();
