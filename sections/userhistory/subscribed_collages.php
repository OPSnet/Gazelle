<?php

if (!$Viewer->permitted('site_collages_subscribe')) {
    error(403);
}

$ShowAll = !empty($_GET['showall']);
$collMan = new Gazelle\Manager\Collage;
$groupSubs = $collMan->subscribedGroupCollageList($Viewer->id(), !$ShowAll);
$artistSubs = $collMan->subscribedArtistCollageList($Viewer->id(), !$ShowAll);

$tgMan = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$torMan = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);

View::show_header('Subscribed collages', ['js' => 'browse,collage']);
?>
<div class="thin">
    <div class="header">
        <h2><?= $Viewer->link() ?> &rsaquo; Subscribed collages<?=($ShowAll ? '' : ' with new additions')?></h2>

        <div class="linkbox">
<?php if ($ShowAll) { ?>
            <br /><br />
            <a href="userhistory.php?action=subscribed_collages&amp;showall=0" class="brackets">Only display collages with new additions</a>&nbsp;&nbsp;&nbsp;
<?php } else { ?>
            <br /><br />
            <a href="userhistory.php?action=subscribed_collages&amp;showall=1" class="brackets">Show all subscribed collages</a>&nbsp;&nbsp;&nbsp;
<?php } ?>
            <a href="userhistory.php?action=catchup_collages&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;
        </div>
    </div>
<?php if (!count($groupSubs)) { ?>
    <div class="center">
        No subscribed collages<?=($ShowAll ? '' : ' with new additions')?>
    </div>
<?php
} else {
    $ShowGroups = 0;
    $urlStem = STATIC_SERVER . '/styles/' . $Viewer->stylesheetName() . '/images/';
    foreach ($groupSubs as $s) {
        $GroupIDs = $s['groupIds'];
        $NewCount = count($GroupIDs);
        $TorrentList = Torrents::get_groups($GroupIDs);
        $TorrentTable = '';
        foreach ($GroupIDs as $GroupID) {
            $tgroup = $tgMan->findById($GroupID);
            if (is_null($tgroup)) {
                continue;
            }
            $GroupCategoryID = $tgroup->categoryId();
            $SnatchedGroupClass = $tgroup->isSnatched() ? ' snatched_group' : '';
            if (!isset($TorrentList[$GroupID])) {
                continue;
            }
            $Group = $TorrentList[$GroupID];
            $WikiImage = $Group['WikiImage'];
            $Torrents = $Group['Torrents'] ?? [];
            $vh = $Group['VanityHouse'] ? ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]' : '';

            // Start an output buffer, so we can store this output in $TorrentTable
            ob_start();
            if (count($Torrents) > 1 || $GroupCategoryID == 1) {
?>
            <tr class="group groupid_<?= $s['collageId'] . $GroupID?>_header discog<?=$SnatchedGroupClass?>" id="group_<?= $s['collageId'] . $GroupID?>">
                <td class="center">
                    <div id="showimg_<?= $s['collageId'] . $GroupID?>" class="<?=($ShowGroups ? 'hide' : 'show')?>_torrents">
                        <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?= $s['collageId'] . $GroupID?>, this, event);" title="Expand this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to expand all groups on this page."></a>
                    </div>
                </td>
                <td colspan="5" class="big_info">
<?php if ($Viewer->option('CoverArt')) { ?>
                    <div class="group_image float_left clear">
                        <?= $imgProxy->thumbnail($WikiImage, $GroupCategoryID) ?>
                    </div>
<?php } ?>
                    <div class="group_info clear">
                        <strong><?= $tgroup->artistHtml() ?> - <?= $tgroup->name() ?> [<?= $tgroup->year() ?>]<?= $vh ?></strong>
                        <div class="tags"><?= implode(', ', $tgroup->torrentTagList()) ?></tags>
                    </div>
                </td>
            </tr>
<?php
                $prev = '';
                $EditionID = 0;
                unset($FirstUnknown);
                foreach ($Torrents as $TorrentID => $t) {
                    $torrent = $torMan->findById($TorrentID);
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
    <tr class="group_torrent groupid_<?= $s['collageId'] . $GroupID?> edition<?=$SnatchedGroupClass?> hidden">
        <td colspan="6" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?= $s['collageId'] . $GroupID?>, <?=$EditionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($t, $Group)?></strong></td>
    </tr>
<?php
                    }
                    $prev = $current;
?>
    <tr class="group_torrent groupid_<?= $s['collageId'] . $GroupID?> edition_<?=
            $EditionID?> hidden<?= ($torrent->isSnatched($Viewer->id()) ? ' snatched_torrent' : '')
            . $SnatchedGroupClass?>">
        <td colspan="2">
            <?= $Twig->render('torrent/action.twig', [
                'can_fl' => $Viewer->canSpendFLToken($torrent),
                'key'    => $Viewer->announceKey(),
                't'      => $t,
            ]) ?>
            &nbsp;&nbsp;&raquo;&nbsp; <a href="torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>"><?=Torrents::torrent_info($t)?></a>
        </td>
        <td class="number_column nobr"><?=Format::get_size($t['Size'])?></td>
        <td class="number_column"><?=number_format($t['Snatched'])?></td>
        <td class="number_column<?=($t['Seeders'] == 0) ? ' r00' : ''?>"><?=number_format($t['Seeders'])?></td>
        <td class="number_column"><?=number_format($t['Leechers'])?></td>
    </tr>
<?php
                } /* foreach ($Torrents) */
            } else {
                // Viewing a type that does not require grouping
                $TorrentID = key($Torrents);
                $torrent = $torMan->findById($TorrentID);
                if (is_null($torrent)) {
                    continue;
                }
                $Torrent = current($Torrents);
?>
    <tr class="torrent<?= $Torrent['IsSnatched'] ? ' snatched_torrent' : '' ?>" id="group_<?= $s['collageId'] . $GroupID?>">
        <td></td>
        <td class="td_collage_category center">
            <div title="<?=$TorrentTags->title()?>" class="tooltip <?=Format::css_category($GroupCategoryID)?> <?=$TorrentTags->css_name()?>">
            </div>
        </td>
        <td class="td_info big_info">
<?php           if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->thumbnail($WikiImage, $GroupCategoryID) ?>
            </div>
<?php           } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $Torrent,
                ]) ?>
                <strong><?= $torrent->link() ?></strong>
                <div class="tags"><?=$TorrentTags->format()?></div>
            </div>
        </td>
        <td class="td_size number_column nobr"><?=Format::get_size($Torrent['Size'])?></td>
        <td class="td_snatched m_td_right number_column"><?=number_format($Torrent['Snatched'])?></td>
        <td class="td_seeders m_td_right number_column<?=($Torrent['Seeders'] == 0) ? ' r00' : ''?>"><?=number_format($Torrent['Seeders'])?></td>
        <td class="td_leechers m_td_right number_column"><?=number_format($Torrent['Leechers'])?></td>
    </tr>
<?php
            }
            $TorrentTable .= ob_get_clean();
        }
    /* I hate that proton is making me do it like this */
?>
<table style="margin-top: 8px;" class="subscribed_collages_table">
    <tr class="colhead_dark">
        <td>
            <span style="float: left;">
                <strong><a href="collages.php?id=<?= $s['collageId'] ?>"><?= $s['name'] ?></a></strong> (<?=$NewCount?> new torrent<?= plural($NewCount) ?>)
            </span>
            <span style="float: right;">
<?php if ($NewCount) { ?>
                <a href="#" onclick="$('#discog_table_<?= $s['collageId'] ?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=
                $ShowAll ? 'Show' : 'Hide' ?></a>
                &nbsp;&nbsp;&nbsp;
                <a href="userhistory.php?action=catchup_collages&amp;auth=<?= $Viewer->auth() ?>&amp;collageid=<?= $s['collageId'] ?>" class="brackets">Catch up</a>
                &nbsp;&nbsp;&nbsp;
<?php } ?>
                <a href="#" onclick="CollageSubscribe(<?= $s['collageId'] ?>); return false;" id="subscribelink<?= $s['collageId'] ?>" class="brackets">Unsubscribe</a>
            </span>
        </td>
    </tr>
</table>

<table class="torrent_table<?=$ShowAll ? ' hidden' : ''?> m_table" id="discog_table_<?= $s['collageId'] ?>">
    <tr class="colhead">
        <td width="1%"><!-- expand/collapse --></td>
        <td class="m_th_left" width="70%"><strong>Torrents</strong></td>
        <td>Size</td>
        <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
        <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
        <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
    </tr>
<?= $TorrentTable ?>
</table>
<?php
    } // foreach ($CollageSubs)
}

if ($artistSubs) {
?>
        <h2>Subscribed artist collages<?=($ShowAll ? '' : ' with new additions')?></h2>
<?php
    foreach ($artistSubs as $s) {
        $new = count($s['artistIds']);
?>
<table style="margin-top: 8px;" class="subscribed_collages_table">
    <tr class="colhead_dark">
        <td>
            <span style="float: left;">
                <strong><a href="collages.php?id=<?= $s['collageId'] ?>"><?= $s['name'] ?></a></strong> (<?= $new ?> new artist<?= plural($new) ?>)
            </span>
            <span style="float: right;">
<?php   if ($new) { ?>
                <a href="#" onclick="$('#discog_table_<?= $s['collageId'] ?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=
                    $ShowAll ? 'Show' : 'Hide' ?></a>
                &nbsp;&nbsp;&nbsp;
                <a href="userhistory.php?action=catchup_collages&amp;auth=<?= $Viewer->auth() ?>&amp;collageid=<?= $s['collageId'] ?>" class="brackets">Catch up</a>
                &nbsp;&nbsp;&nbsp;
<?php   } ?>
                <a href="#" onclick="CollageSubscribe(<?= $s['collageId'] ?>); return false;" id="subscribelink<?= $s['collageId'] ?>" class="brackets">Unsubscribe</a>
            </span>
        </td>
    </tr>
</table>
<table class="artist_table<?=$ShowAll ? ' hidden' : ''?> m_table" id="discog_table_<?= $s['collageId'] ?>">
<?php   foreach ($s['artistIds'] as $artistId) { ?>
    <tr class="colhead">
        <td><?= (new Gazelle\Artist($artistId))->url() ?></td>
    </tr>
<?php   } ?>
</table>
<?php
    }
}
?>
</div>
<?php
View::show_footer();
