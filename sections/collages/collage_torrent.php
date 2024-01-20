<?php

$tgMan         = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$torMan        = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$bookmark      = new Gazelle\User\Bookmark($Viewer);
$collMan       = (new Gazelle\Manager\Collage)->setImageProxy(new \Gazelle\Util\ImageProxy($Viewer));
$urlStem       = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
$vote          = new Gazelle\User\Vote($Viewer);

/** @var Gazelle\Collage $Collage required from collage.php */

$Collage->setViewer($Viewer);
$CollageID     = $Collage->id();
$CollageCovers = ($Viewer->option('CollageCovers') ?: 25) * (1 - (int)$Viewer->option('HideCollage'));
$CollagePages  = [];
$NumGroups     = $Collage->numEntries();
$snatcher      = $Viewer->snatch();
$entryList     = $Collage->entryList();
$groupsClosed  = (bool)$Viewer->option('TorrentGrouping');

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
                        <option id="opt<?=$GroupID . $OptionID?>" value="<?=$GroupID . $OptionID?>"<?php if (in_array($GroupID . $OptionID, $ZIPList)) {
echo ' disabled="disabled"'; }?>><?=$OptName?></option>
<?php } /* foreach */ ?>
                    </optgroup>
                </select>
                <button type="button" onclick="add_selection();">+</button>
                <select name="preference" style="width: 210px;">
                    <option value="0"<?php if ($ZIPPrefs == 0) {
echo ' selected="selected"'; } ?>>Prefer Original</option>
                    <option value="1"<?php if ($ZIPPrefs == 1) {
echo ' selected="selected"'; } ?>>Prefer Best Seeded</option>
                    <option value="2"<?php if ($ZIPPrefs == 2) {
echo ' selected="selected"'; } ?>>Prefer Bonus Tracks</option>
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
    foreach ($entryList as $tgroupId) {
        $tgroup = $tgMan->findById($tgroupId);
        if ($tgroup) {
            echo $collMan->tgroupCover($tgroup);
            ++$Idx;
        }
        if ($Idx >= $limit) {
            break;
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
                        function ($id) use ($collMan, $tgMan) {
                            $tgroup = $tgMan->findById($id);
                            return $tgroup ? $collMan->tgroupCover($tgroup) : '';
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
    $prev = '';
    $EditionID = 0;
    unset($FirstUnknown);
    $Number++;
    foreach ($torrentIdList as $torrentId) {
        $torrent = $torMan->findById($torrentId);
        if (is_null($torrent)) {
            continue;
        }

        if (count($torrentIdList) > 1 || $tgroup->categoryGrouped()) {
            if ($prev === '') {
            // Grouped torrents
?>
            <tr class="group groupid_<?=$tgroupId?>_header discog<?= $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
<?= $Twig->render('tgroup/collapse-tgroup.twig', [ 'closed' => $groupsClosed, 'id' => $tgroupId ]) ?>
                <td class="center">
                    <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>"></div>
                </td>
                <td colspan="5">
                    <strong><?= $Number ?> – <?= $tgroup->link() ?></strong>
                        <span class="float_right">
<?php
                echo $Twig->render('bookmark/action.twig', [
                    'class'         => 'torrent',
                    'id'            => $tgroupId,
                    'is_bookmarked' => $bookmark->isTorrentBookmarked($tgroupId),
                ]);
                if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) {
?>
                        <br /><?= $vote->links($tgroupId) ?>
<?php           } ?>
                        </span>
                    <div class="tags"><?= implode(', ', array_map(
                        fn ($name) => "<a href=\"collages.php?action=search&tags=$name\">$name</a>", $tgroup->tagNameList()
                        )) ?></div>
                </td>
            </tr>
<?php
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
            <tr class="group_torrent torrent_row groupid_<?= $tgroupId ?> edition_<?= $EditionID ?><?= ($snatcher->showSnatch($torrent) ? ' snatched_torrent' : '')
                . $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                <td class="td_info" colspan="3">
                    <?= $Twig->render('torrent/action-v2.twig', [
                        'pl'      => true,
                        'torrent' => $torrent,
                        'viewer'  => $Viewer,
                    ]) ?>
                    &nbsp;&nbsp;&raquo;&nbsp;<?= $torrent->shortLabelLink() ?>
<?php   } else { ?>
            <tr class="torrent torrent_row<?= ($snatcher->showSnatch($torrent) ? ' snatched_torrent' : '')
                . $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
                <td></td>
                <td class="td_collage_category center">
                    <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>"></div>
                </td>
                <td class="td_info">
                    <?= $Twig->render('torrent/action-v2.twig', [
                        'pl'      => true,
                        'torrent' => $torrent,
                        'viewer'  => $Viewer,
                    ]) ?>
                    <?= $Number ?> – <strong><?= $tgroup->link() ?></strong>
<?php       if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) { ?>
                    <?= $vote->links($tgroupId) ?>
<?php       } ?>
                    <div class="tags">
                        <?= implode(', ', array_map(fn ($name) => "<a href=\"collages.php?action=search&tags=$name\">$name</a>", $tgroup->tagNameList())) ?>
                    </div>
    <?php } ?>
                </td>
                <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
            </tr>
<?php
    }
}
?>
        </table>
    </div>
</div>
