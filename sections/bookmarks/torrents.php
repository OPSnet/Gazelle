<?php
ini_set('max_execution_time', 600);
set_time_limit(0);

//~~~~~~~~~~~ Main bookmarks page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

if (empty($_GET['userid'])) {
    $user = $Viewer;
    $ownProfile = true;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
    $ownProfile = ($user->id() === $Viewer->id());
}

$bookmark = new Gazelle\Bookmark($user);
$collMan  = new Gazelle\Manager\Collage;
$tgMan    = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$torMan   = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$imgproxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);

$paginator = new Gazelle\Util\Paginator(200, (int)($_GET['page'] ?? 1));
$paginator->setTotal($bookmark->torrentTotal());

$bookmarkList      = $bookmark->torrentList($paginator->limit(), $paginator->offset());
$NumGroups         = count($bookmarkList);
$artistLeaderboard = $bookmark->torrentArtistLeaderboard(new Gazelle\Manager\Artist);
$tagLeaderboard    = $bookmark->torrentTagLeaderboard();
$CollageCovers     = $Viewer->option('CollageCovers') ?? 25;
$title             = $user->username() . " &rsaquo; Bookmarked torrent groups";

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
if (count($bookmarkList) > 0) { ?>
            <br /><br />
            <a href="bookmarks.php?action=remove_snatched&amp;auth=<?= $Viewer->auth() ?>" class="brackets" onclick="return confirm('Are you sure you want to remove the bookmarks for all items you\'ve snatched?');">Remove snatched</a>
            <a href="bookmarks.php?action=edit&amp;type=torrents" class="brackets">Manage torrents</a>
<?php
} ?>
        </div>
    </div>
<?php
if (count($bookmarkList) === 0) { ?>
    <div class="box pad" align="center">
        <h2>You have not bookmarked any torrents.</h2>
    </div>
</div>
<?php
    View::show_footer();
    die();
} ?>
    <div class="sidebar">
        <div class="box box_info box_statistics_bookmarked_torrents">
            <div class="head"><strong>Stats</strong></div>
            <ul class="stats nobullet">
                <li>Torrent groups: <?=$NumGroups?></li>
                <li>Artists: <?= $bookmark->torrentArtistTotal() ?></li>
            </ul>
        </div>
        <div class="box box_artists">
            <div class="head"><strong>Top Artists</strong></div>
            <div class="pad">
<?php if ($artistLeaderboard) { ?>
                <ol>
<?php
        $n = 0;
        foreach ($artistLeaderboard as $artist) {
            if (++$n > 5 && $Viewer->primaryClass() === USER) {
                break;
            }
?>
                    <li><a href="artist.php?id=<?= $artist['id'] ?>"><?= $artist['name'] ?></a> (<?= $artist['total'] ?>)</li>
<?php   } ?>
                </ol>
<?php } else { ?>
                <ul class="nobullet" style="padding-left: 5px;">
                    <li>There are no artists to display.</li>
                </ul>
<?php } ?>
            </div>
        </div>
        <div class="box box_tags">
            <div class="head"><strong>Top Tags</strong></div>
            <div class="pad">
                <ol>
<?php
        $n = 0;
        foreach ($tagLeaderboard as $tag) {
            if (++$n > 5 && $Viewer->primaryClass() === USER) {
                break;
            }
?>
                    <li><a href="torrents.php?taglist=<?= $tag['name'] ?>"><?= $tag['name'] ?></a> (<?= $tag['total'] ?>)</li>
<?php   } ?>
                </ol>
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
    $idx = 0;
    while ($idx < min($CollageCovers, $NumGroups)) {
        $tgroup = $tgMan->findById($bookmarkList[$idx]['tgroup_id']);
        if ($tgroup) {
            echo $collMan->tgroupCover($tgroup, $imgproxy);
            ++$idx;
        }
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
            $Groups = array_slice($tgroupList, $i * $CollageCovers, $CollageCovers);
            $CollagePages[] = implode('',
                array_map(
                    function($id) use ($collMan, $imgproxy, $tgMan) {
                        $tgroup = $tgMan->findById($id);
                        return $tgroup ? $collMan->tgroupCover($tgroup, $imgproxy) : '';
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
        <?= $paginator->linkbox() ?>
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
foreach ($bookmarkList as $bm) {
    $tgroupId = $bm['tgroup_id'];
    $tgroup = $tgMan->findById($tgroupId);
    if (is_null($tgroup)) {
        continue;
    }
    $torrentIdList = explode(',', $bm['torrent_list']);
    $SnatchedGroupClass = $tgroup->isSnatched($Viewer->id()) ? ' snatched_group' : '';

    if (count($torrentIdList) > 1 || $tgroup->categoryId() == 1) {
        $tagList = $tgroup->tagList();
        $primaryTag = current($tagList)['name'];
        // Grouped torrents
?>
        <tr class="group groupid_<?=$tgroupId?>_header discog" id="group_<?= $tgroupId ?>">
            <td class="td_collapse m_td_left center">
                <div id="showimg_<?= $tgroupId ?>" class="<?= $groupsClosed ? 'show' : 'hide' ?>_torrents">
                    <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?= $tgroupId ?>, this, event);"
                       title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collape all groups on this page."></a>
                </div>
            </td>
            <td class="m_hidden center">
                <div title="<?= ucfirst($primaryTag) ?>"
                     class="tooltip cats_<?= strtolower($tgroup->categoryName()) ?> tags_<?=  str_replace('.', '_', $primaryTag) ?>"></div>
            </td>
            <td class="td_info" colspan="5">
                <strong><?= $tgroup->displayNameHtml() ?></strong>
                <span style="text-align: right;" class="float_right">
 <?php if ($ownProfile) { ?>
        <a href="#group_<?= $tgroupId ?>" class="brackets remove_bookmark"
           onclick="Unbookmark('torrent', <?= $tgroupId ?>, ''); return false;">Remove bookmark</a>
        <br/>
<?php } ?>
                    <?= time_diff($bm['added']); ?>
                        </span>
                <div class="tags"><?= implode(', ',
                    array_map(fn($name) => "<a href=\"torrents.php?taglist=$name\">$name</a>", $tgroup->tagNameList())
                    ) ?></div>
            </td>
        </tr>
<?php
        $prev = '';
        $EditionID = 0;
        unset($FirstUnknown);

        foreach ($torrentIdList as $torrentId) {
            $torrent = $torMan->findById($torrentId);
            if (!$torrent) {
                continue;
            }
            if ($torrent->isRemasteredUnknown()) {
                $FirstUnknown = !isset($FirstUnknown);
            }
            $SnatchedTorrentClass = $torrent->isSnatched($Viewer->id()) ? ' snatched_torrent' : '';

            $current = $torrent->remasterTuple();
            if ($prev != $current || (isset($FirstUnknown) && $FirstUnknown)) {
                $EditionID++;
?>
                <tr class="group_torrent groupid_<?= $tgroupId ?> edition<?= $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                    <td colspan="7" class="edition_info"><strong><a href="#"
                        onclick="toggle_edition(<?= $tgroupId ?>, <?= $EditionID ?>, this, event)"
                        class="tooltip"
                        title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a>
                            <?= $torrent->edition() ?>
                        </strong></td>
                </tr>
<?php
            }
            $prev = $current;
?>
            <tr class="group_torrent torrent_row groupid_<?= $tgroupId ?> edition_<?= $EditionID ?><?= $SnatchedTorrentClass . $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                <td class="td_info" colspan="3">
                <?= $Twig->render('torrent/action-v2.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $torrent,
                ]) ?>
                    &nbsp;&nbsp;&raquo;&nbsp; <a
                            href="torrents.php?id=<?= $tgroupId ?>&amp;torrentid=<?= $torrentId ?>"><?= $torrent->label() ?></a>
                </td>
                <td class="td_size number_column nobr"><?= Format::get_size($torrent->size()) ?></td>
                <td class="td_snatched m_td_right number_column"><?= number_format($torrent->snatchTotal()) ?></td>
                <td class="td_seeders m_td_right number_column<?= $torrent->seederTotal() ? '' : ' r00' ?>"><?= number_format($torrent->seederTotal()) ?></td>
                <td class="td_leechers m_td_right number_column"><?= number_format($torrent->leecherTotal()) ?></td>
            </tr>
<?php
        }
    } else {
        // Viewing a type that does not require grouping
        $torrent = $torMan->findById(current($torrentIdList));
        if (is_null($torrent)) {
            continue;
        }
        $SnatchedTorrentClass = $torrent->isSnatched($Viewer->id()) ? ' snatched_torrent' : '';
?>
        <tr class="torrent torrent_row<?= $SnatchedTorrentClass . $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
            <td></td>
            <td class="center">
                <div title="<?= '$TorrentTags->title()' ?>"
                     class="tooltip <?= 'Format::css_category($GroupCategoryID)' ?> <?= '$TorrentTags->css_name()' ?>">
                </div>
            </td>
            <td>
                <?= $Twig->render('torrent/action-v2.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $torrent,
                ]) ?>
                <strong><?= $tgroup->displayNameText() ?></strong>
                <div class="tags"><?= '$TorrentTags->format()' ?></div>
<?php if ($ownProfile) { ?>
                    <span class="float_right float_clear"><a href="#group_<?= $tgroupId
                        ?>" class="brackets remove_bookmark" onclick="Unbookmark('torrent', <?= $tgroupId ?>, ''); return false;">Remove bookmark</a></span>
<?php } ?>
                <span class="float_right float_clear"><?= time_diff($bm['added']); ?></span>

            </td>
            <td class="number_column nobr"><?= Format::get_size($torrent->size()) ?></td>
            <td class="number_column"><?= number_format($torrent->snatchTotal()) ?></td>
            <td class="number_column<?= $torrent->seederTotal() ? '' : ' r00' ?>"><?= number_format($torrent->seederTotal()) ?></td>
            <td class="number_column"><?= number_format($torrent->leecherTotal()) ?></td>
        </tr>
<?php
    }
    unset($tgroup);
}
?>
        </table>
    </div>
        <?= $paginator->linkbox() ?>
</div>

<?php
View::show_footer();
