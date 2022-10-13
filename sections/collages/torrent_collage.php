<?php

$tgMan    = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$torMan   = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$bookmark = new Gazelle\Bookmark($Viewer);
$collMan  = (new Gazelle\Manager\Collage)->setImageProxy((new \Gazelle\Util\ImageProxy)->setViewer($Viewer));
$urlStem  = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
$vote     = new Gazelle\User\Vote($Viewer);
$snatcher = new Gazelle\User\Snatch($Viewer);

$entryList    = $Collage->entryList();
$groupsClosed = (bool)$Viewer->option('TorrentGrouping');

echo $Twig->render('collage/header.twig', [
    'bookmarked' => $bookmark->isCollageBookmarked($CollageID),
    'collage'    => $Collage,
    'object'     => 'torrent',
    'viewer'     => $Viewer,
]);

echo$Twig->render('collage/sidebar.twig', [
    'artists'      => $Collage->numArtists(),
    'collage'      => $Collage,
    'comments'     => (new Gazelle\Manager\Comment)->collageSummary($CollageID),
    'contributors' => array_slice($Collage->contributors(), 0, 5, true),
    'entries'      => $Collage->numEntries(),
    'object'       => 'torrent',
    'object_name'  => 'torrent group',
    'top_artists'  => $Collage->topArtists(10),
    'top_tags'     => $Collage->topTags(5),
    'viewer'       => $Viewer,
]);

if ($Viewer->permitted('zip_downloader')) {
    if ($Viewer->option('Collector')) {
        [$ZIPList, $ZIPPrefs] = $Viewer->option('Collector');
        if (is_null($ZIPList)) {
            $ZIPList = ['00', '11'];
            $ZIPPrefs = 1;
        } else {
            $ZIPList = explode(':', $ZIPList);
        }
    } else {
        $ZIPList = ['00', '11'];
        $ZIPPrefs = 1;
    }
?>
        <div class="box box_zipdownload">
            <div class="head colhead_dark"><strong>Collector</strong></div>
            <div class="pad">
                <form class="download_form" name="zip" action="collages.php" method="post">
                <input type="hidden" name="action" value="download" />
                <input type="hidden" name="auth" value="<?=$Viewer->auth()?>" />
                <input type="hidden" name="collageid" value="<?=$CollageID?>" />
                <ul id="list" class="nobullet">
<?php foreach ($ZIPList as $ListItem) { ?>
                    <li id="list<?=$ListItem?>">
                        <input type="hidden" name="list[]" value="<?=$ListItem?>" />
                        <span class="float_left"><?=ZIP_OPTION[$ListItem]['2']?></span>
                        <span class="remove remove_collector"><a href="#" onclick="remove_selection('<?=$ListItem?>'); return false;" class="float_right brackets">X</a></span>
                        <br style="clear: both;" />
                    </li>
<?php } ?>
                </ul>
                <select id="formats" style="width: 180px;">
<?php
    $OpenGroup = false;
    $LastGroupID = -1;

    foreach (ZIP_OPTION as $Option) {
        [$GroupID, $OptionID, $OptName] = $Option;
        if ($GroupID != $LastGroupID) {
            $LastGroupID = $GroupID;
            if ($OpenGroup) {
?>
                    </optgroup>
<?php        } ?>
                    <optgroup label="<?=ZIP_GROUP[$GroupID]?>">
<?php
            $OpenGroup = true;
        }
?>
                        <option id="opt<?=$GroupID.$OptionID?>" value="<?=$GroupID.$OptionID?>"<?php if (in_array($GroupID.$OptionID, $ZIPList)) { echo ' disabled="disabled"'; }?>><?=$OptName?></option>
<?php } /* foreach */ ?>
                    </optgroup>
                </select>
                <button type="button" onclick="add_selection();">+</button>
                <select name="preference" style="width: 210px;">
                    <option value="0"<?php if ($ZIPPrefs == 0) { echo ' selected="selected"'; } ?>>Prefer Original</option>
                    <option value="1"<?php if ($ZIPPrefs == 1) { echo ' selected="selected"'; } ?>>Prefer Best Seeded</option>
                    <option value="2"<?php if ($ZIPPrefs == 2) { echo ' selected="selected"'; } ?>>Prefer Bonus Tracks</option>
                </select>
                <input type="submit" style="width: 210px;" value="Download" />
                </form>
            </div>
        </div>
<?php } /* zip collector */ ?>
    </div>
    <div class="main_column">
<?php if ($CollageCovers != 0) { ?>
        <div id="coverart" class="box">
            <div class="head" id="coverhead"><strong>Cover Art</strong></div>
            <ul class="collage_images" id="collage_page0">
<?php
    $Idx = 0;
    $limit = min($NumGroups, $CollageCovers);
    while ($Idx < $limit) {
        $tgroup = $tgMan->findById($entryList[$Idx]);
        if ($tgroup) {
            echo $collMan->coverRow($tgroup);
            ++$Idx;
        }
    }
?>
            </ul>
        </div>
<?php if ($NumGroups > $CollageCovers) { ?>
        <div class="linkbox pager" style="clear: left;" id="pageslinksdiv">
            <span id="firstpage" class="invisible"><a href="#" class="pageslink" onclick="collageShow.page(0); return false;"><strong>&laquo; First</strong></a> | </span>
            <span id="prevpage" class="invisible"><a href="#" class="pageslink" onclick="collageShow.prevPage(); return false;"><strong>&lsaquo; Prev</strong></a> | </span>
<?php   for ($i = 0; $i < $NumGroups / $CollageCovers; $i++) { ?>
            <span id="pagelink<?=$i?>" class="<?=(($i > 4) ? 'hidden' : '')?><?=(($i == 0) ? 'selected' : '')?>"><a href="#" class="pageslink" onclick="collageShow.page(<?=$i?>, this); return false;"><strong><?=$CollageCovers * $i + 1?>-<?=min($NumGroups, $CollageCovers * ($i + 1))?></strong></a><?=(($i != ceil($NumGroups / $CollageCovers) - 1) ? ' | ' : '')?></span>
<?php   } ?>
            <span id="nextbar" class="<?=($NumGroups / $CollageCovers > 5) ? 'hidden' : ''?>"> | </span>
            <span id="nextpage"><a href="#" class="pageslink" onclick="collageShow.nextPage(); return false;"><strong>Next &rsaquo;</strong></a></span>
            <span id="lastpage" class="<?=(ceil($NumGroups / $CollageCovers) == 2 ? 'invisible' : '')?>"> | <a href="#" class="pageslink" onclick="collageShow.page(<?=ceil($NumGroups / $CollageCovers) - 1?>); return false;"><strong>Last &raquo;</strong></a></span>
        </div>
<?php
        for ($i = 0; $i < $NumGroups / $CollageCovers; $i++) {
            $chunk = array_slice($entryList, $i * $CollageCovers, $CollageCovers);
            if (!empty($chunk)) {
                $CollagePages[] = implode('',
                    array_map(
                        function($id) use ($collMan, $tgMan) {
                            $tgroup = $tgMan->findById($id);
                            return $tgroup ? $collMan->coverRow($tgroup) : '';
                        }, $chunk
                    )
                );
            }
        }
        if ($NumGroups > $CollageCovers) {
            for ($i = $NumGroups + 1; $i <= ceil($NumGroups / $CollageCovers) * $CollageCovers; $i++) {
                $CollagePages[count($CollagePages) - 1] .= '<li></li>';
            }
        }
?>
        <script type="text/javascript">//<![CDATA[
            collageShow.init(<?=json_encode($CollagePages)?>);
        //]]></script>
<?php
        unset($CollagePages);
    }
}
?>
        <table class="torrent_table grouping cats m_table" id="discog_table">
            <tr class="colhead_dark">
                <td><!-- expand/collapse --></td>
                <td><!-- Category --></td>
                <td class="m_th_left" width="70%"><strong>Torrents</strong></td>
                <td>Size</td>
                <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php
$Number = 0;
foreach ($entryList as $tgroupId) {
    $tgroup = $tgMan->findById($tgroupId);
    if (is_null($tgroup)) {
        continue;
    }
    $torrentIdList = $tgroup->torrentIdList();
    if (count($torrentIdList) === 0) {
        continue;
    }
    $SnatchedGroupClass = $tgroup->isSnatched() ? ' snatched_group' : '';
    $tagList = $tgroup->tagList();
    $primaryTag = current($tagList)['name'];
    $Number++;
    if (count($torrentIdList) > 1 || $tgroup->categoryGrouped()) {
        // Grouped torrents
?>
        <tr class="group groupid_<?=$tgroupId?>_header discog<?= $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
            <td class="center">
                <div id="showimg_<?= $tgroupId ?>" class="<?= $groupsClosed ? 'show' : 'hide' ?>_torrents">
                    <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?= $tgroupId ?>, this, event);"
                       title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all groups on this page."></a>
                </div>
            </td>
            <td class="center">
                <div title="<?= ucfirst($primaryTag) ?>"
                     class="tooltip <?= $tgroup->categoryCss() ?> tags_<?=  str_replace('.', '_', $primaryTag) ?>"></div>
            </td>
            <td colspan="5">
                <strong><?= $Number ?> - <?= $tgroup->displayNameHtml() ?></strong>
                    <span class="float_right">
<?php   if ($bookmark->isTorrentBookmarked($tgroupId)) { ?>
                    <span class="remove_bookmark">
                        <a style="float: right;" href="#" id="bookmarklink_torrent_<?= $tgroupId ?>"
                           class="remove_bookmark brackets"
                           onclick="Unbookmark('torrent', <?= $tgroupId ?>, 'Bookmark'); return false;">Remove bookmark</a>
                    </span>
<?php   } else { ?>
                    <span class="add_bookmark">
                        <a style="float: right;" href="#" id="bookmarklink_torrent_<?= $tgroupId ?>" class="add_bookmark brackets"
                           onclick="Bookmark('torrent', <?= $tgroupId ?>, 'Remove bookmark'); return false;">Bookmark</a>
                    </span>
<?php
        }
        if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) {
?>
                    <br /><?= $vote->setGroupId($tgroupId)->links() ?>
<?php   } ?>
                    </span>
                <div class="tags"><?= implode(', ', array_map(
                    fn($name) => "<a href=\"collages.php?action=search&tags=$name\">$name</a>", $tgroup->tagNameList()
                    )) ?></div>
            </td>
        </tr>
<?php
        $prev = '';
        $EditionID = 0;
        unset($FirstUnknown);

        foreach ($torrentIdList as $torrentId) {
            $torrent = $torMan->findById($torrentId);
            if (is_null($torrent)) {
                continue;
            }
            if ($torrent->isRemasteredUnknown()) {
                $FirstUnknown = !isset($FirstUnknown);
            }

            $current = $torrent->remasterTuple();
            if ($prev != $current || (isset($FirstUnknown) && $FirstUnknown)) {
                $EditionID++;
?>
                <tr class="group_torrent groupid_<?= $tgroupId ?> edition<?= $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                    <td colspan="7" class="edition_info"><strong><a href="#"
                        onclick="toggle_edition(<?= $tgroupId ?>, <?= $EditionID ?>, this, event)"
                        class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?= $torrent->edition() ?>
                        </strong></td>
                </tr>
<?php
            }
            $prev = $current;
?>
            <tr class="group_torrent torrent_row groupid_<?= $tgroupId ?> edition_<?= $EditionID ?><?= ($snatcher->showSnatch($torrent->id()) ? ' snatched_torrent' : '')
                . $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                <td class="td_info" colspan="3">
                    <?= $Twig->render('torrent/action-v2.twig', [
                        'can_fl' => $Viewer->canSpendFLToken($torrent),
                        'key'    => $Viewer->announceKey(),
                        't'      => $torrent,
                    ]) ?>
                    &nbsp;&nbsp;&raquo;&nbsp;<?= $torrent->labelLink() ?>
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
        $torrentId = $torrentIdList[0];
        $torrent = $torMan->findById($torrentId);
?>
        <tr class="torrent torrent_row<?= ($snatcher->showSnatch($torrent->id()) ? ' snatched_torrent' : '')
            . $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
            <td></td>
            <td class="td_collage_category center">
                <div title="<?= ucfirst($primaryTag) ?>"
                     class="tooltip <?= $tgroup->categoryCss() ?> tags_<?=  str_replace('.', '_', $primaryTag) ?>"></div>
            </td>
            <td class="td_info">
                <?= $Twig->render('torrent/action-v2.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $torrent,
                ]) ?>
                <strong><?= $tgroup->link() ?></strong>
<?php   if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) { ?>
                <?= $vote->setGroupId($tgroupId)->links($Viewer->auth()) ?>
<?php   } ?>
                <div class="tags"><?= implode(', ', array_map(
                    fn($name) => "<a href=\"collages.php?action=search&tags=$name\">$name</a>", $tgroup->tagNameList()
                    )) ?></div>
            </td>
            <td class="td_size number_column nobr"><?= Format::get_size($torrent->size()) ?></td>
            <td class="td_snatched m_td_right number_column"><?= number_format($torrent->snatchTotal()) ?></td>
            <td class="td_seeders m_td_right number_column<?= $torrent->seederTotal() ? '' : ' r00' ?>"><?= number_format($torrent->seederTotal()) ?></td>
            <td class="td_leechers m_td_right number_column"><?= number_format($torrent->leecherTotal()) ?></td>
        </tr>
<?php
    }
}
?>
        </table>
    </div>
</div>
