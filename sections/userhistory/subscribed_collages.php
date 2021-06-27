<?php

if (!check_perms('site_collages_subscribe')) {
    error(403);
}

$ShowAll = !empty($_GET['showall']);

if ($ShowAll) {
    $sql = "
        SELECT c.ID,
            c.Name,
            c.NumTorrents,
            CASE WHEN ca.CollageID IS NULL THEN 'torrent' ELSE 'artist' END as collageType,
            s.LastVisit
        FROM collages AS c
        INNER JOIN users_collage_subs AS s ON (s.CollageID = c.ID)
        LEFT JOIN collages_artists AS ca ON (ca.CollageID = c.ID)
        LEFT JOIN collages_torrents AS ct ON (ct.CollageID = c.ID)
        WHERE c.Deleted = '0'
            AND s.UserID = ?
        GROUP BY c.ID";
} else {
    $sql = "
        SELECT c.ID,
            c.Name,
            c.NumTorrents,
            CASE WHEN ca.CollageID IS NULL THEN 'torrent' ELSE 'artist' END as collageType,
            s.LastVisit
        FROM collages AS c
        LEFT JOIN users_collage_subs AS s ON (s.CollageID = c.ID)
        LEFT JOIN collages_artists AS ca ON (ca.CollageID = c.ID)
        LEFT JOIN collages_torrents AS ct ON (ct.CollageID = c.ID)
        WHERE c.Deleted = '0'
            AND coalesce(ca.AddedOn, ct.AddedOn) > s.LastVisit
            AND s.UserID = ?
        GROUP BY c.ID";
}

$DB->prepared_query($sql, $Viewer->id());
$NumResults = $DB->record_count();
$CollageSubs = $DB->to_array();
View::show_header('Subscribed collages','browse,collage');

?>
<div class="thin">
    <div class="header">
        <h2><a href="user.php?id=<?= $Viewer->id() ?>"><?= $Viewer->username() ?></a> &rsaquo; Subscribed collages<?=($ShowAll ? '' : ' with new additions')?></h2>

        <div class="linkbox">
<?php if ($ShowAll) { ?>
            <br /><br />
            <a href="userhistory.php?action=subscribed_collages&amp;showall=0" class="brackets">Only display collages with new additions</a>&nbsp;&nbsp;&nbsp;
<?php } else { ?>
            <br /><br />
            <a href="userhistory.php?action=subscribed_collages&amp;showall=1" class="brackets">Show all subscribed collages</a>&nbsp;&nbsp;&nbsp;
<?php } ?>
            <a href="userhistory.php?action=catchup_collages&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;
        </div>
    </div>
<?php if (!$NumResults) { ?>
    <div class="center">
        No subscribed collages<?=($ShowAll ? '' : ' with new additions')?>
    </div>
<?php
} else {
    $HideGroup = '';
    $ActionTitle = 'Hide';
    $ActionURL = 'hide';
    $ShowGroups = 0;
    $artistCollage = [];

    foreach ($CollageSubs as $Collage) {
        $TorrentTable = '';

        [$CollageID, $CollageName, $CollageSize, $type, $LastVisit] = $Collage;
        if ($type == 'artist') {
            $sql = "SELECT ArtistID AS ID
                FROM collages_artists
                WHERE CollageID = ?
                    AND AddedOn > ?
                ORDER BY AddedOn";
        } else {
            $sql = "SELECT GroupID AS ID
                FROM collages_torrents
                WHERE CollageID = ?
                    AND AddedOn > ?
                ORDER BY AddedOn";
        }
        $DB->prepared_query($sql, $CollageID, $LastVisit);
        $NewCount = $DB->record_count();

        if ($type == 'artist') {
            $artistCollage[] = [$CollageID, $CollageName, $NewCount, $DB->collect('ID', false)];
            continue;
        }

        $GroupIDs = $DB->collect('ID', false);
        if (count($GroupIDs) > 0) {
            $TorrentList = Torrents::get_groups($GroupIDs);
        } else {
            $TorrentList = [];
        }

        $Artists = Artists::get_artists($GroupIDs);
        $Number = 0;

        foreach ($GroupIDs as $GroupID) {
            if (!isset($TorrentList[$GroupID])) {
                continue;
            }
            $Group = $TorrentList[$GroupID];
            $GroupID = $Group['ID'];
            $GroupName = $Group['Name'];
            $GroupYear = $Group['Year'];
            $GroupCategoryID = $Group['CategoryID'];
            $GroupRecordLabel = $Group['RecordLabel'];
            $GroupCatalogueNumber = $Group['CatalogueNumber'];
            $GroupVanityHouse = $Group['VanityHouse'];
            $GroupFlags = isset($Group['Flags']) ? $Group['Flags'] : ['IsSnatched' => false];
            $TorrentTags = new Tags($Group['TagList']);
            $ReleaseType = $Group['ReleaseType'];
            $WikiImage = $Group['WikiImage'];
            $Torrents = isset($Group['Torrents']) ? $Group['Torrents'] : [];
            $Artists = $Group['Artists'];
            $ExtendedArtists = $Group['ExtendedArtists'];

            $DisplayName = '';

            if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5]) || !empty($ExtendedArtists[6])) {
                unset($ExtendedArtists[2]);
                unset($ExtendedArtists[3]);
                $DisplayName .= Artists::display_artists($ExtendedArtists);
            } elseif (count($Artists) > 0) {
                $DisplayName .= Artists::display_artists(['1' => $Artists]);
            }
            $DisplayName .= "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";
            if ($GroupYear > 0) {
                $DisplayName = "$DisplayName [$GroupYear]";
            }
            if ($GroupVanityHouse) {
                $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
            }

            $SnatchedGroupClass = $GroupFlags['IsSnatched'] ? ' snatched_group' : '';

            // Start an output buffer, so we can store this output in $TorrentTable
            ob_start();
            if (count($Torrents) > 1 || $GroupCategoryID == 1) {
?>
            <tr class="group groupid_<?=$CollageID . $GroupID?>_header discog<?=$SnatchedGroupClass?>" id="group_<?=$CollageID . $GroupID?>">
                <td class="center">
                    <div id="showimg_<?=$CollageID . $GroupID?>" class="<?=($ShowGroups ? 'hide' : 'show')?>_torrents">
                        <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?=$CollageID . $GroupID?>, this, event);" title="Expand this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to expand all groups on this page."></a>
                    </div>
                </td>
                <td colspan="5" class="big_info">
<?php if ($LoggedUser['CoverArt']) { ?>
                    <div class="group_image float_left clear">
                        <?php ImageTools::cover_thumb($WikiImage, $GroupCategoryID) ?>
                    </div>
<?php } ?>
                    <div class="group_info clear">
                        <strong><?=$DisplayName?></strong>
                        <div class="tags"><?=$TorrentTags->format()?></tags>
                    </div>
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

                    if ($Torrent['RemasterTitle'] != $LastRemasterTitle
                        || $Torrent['RemasterYear'] != $LastRemasterYear
                        || $Torrent['RemasterRecordLabel'] != $LastRemasterRecordLabel
                        || $Torrent['RemasterCatalogueNumber'] != $LastRemasterCatalogueNumber
                        || (isset($FirstUnknown) && $FirstUnknown)
                        || $Torrent['Media'] != $LastMedia
                    ) {
                        $EditionID++;
?>
    <tr class="group_torrent groupid_<?=$CollageID . $GroupID?> edition<?=$SnatchedGroupClass?> hidden">
        <td colspan="6" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$CollageID . $GroupID?>, <?=$EditionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($Torrent, $Group)?></strong></td>
    </tr>
<?php
                    }
                    $LastRemasterTitle = $Torrent['RemasterTitle'];
                    $LastRemasterYear = $Torrent['RemasterYear'];
                    $LastRemasterRecordLabel = $Torrent['RemasterRecordLabel'];
                    $LastRemasterCatalogueNumber = $Torrent['RemasterCatalogueNumber'];
                    $LastMedia = $Torrent['Media'];
?>
    <tr class="group_torrent groupid_<?=$CollageID . $GroupID?> edition_<?=$EditionID?> hidden<?=$SnatchedTorrentClass . $SnatchedGroupClass?>">
        <td colspan="2">
            <?= $Twig->render('torrent/action.twig', [
                'can_fl' => Torrents::can_use_token($Torrent),
                'key'    => $LoggedUser['torrent_pass'],
                't'      => $Torrent,
            ]) ?>
            &nbsp;&nbsp;&raquo;&nbsp; <a href="torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>"><?=Torrents::torrent_info($Torrent)?></a>
        </td>
        <td class="number_column nobr"><?=Format::get_size($Torrent['Size'])?></td>
        <td class="number_column"><?=number_format($Torrent['Snatched'])?></td>
        <td class="number_column<?=($Torrent['Seeders'] == 0) ? ' r00' : ''?>"><?=number_format($Torrent['Seeders'])?></td>
        <td class="number_column"><?=number_format($Torrent['Leechers'])?></td>
    </tr>
<?php
                } /* foreach ($Torrents) */
            } else {
                // Viewing a type that does not require grouping
                $TorrentID = key($Torrents);
                $Torrent = current($Torrents);

                $DisplayName = "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";

                if ($Torrent['IsSnatched']) {
                    $DisplayName .= ' ' . Format::torrent_label('Snatched!');
                }
                if (!empty($Torrent['FreeTorrent'])) {
                    $DisplayName .= ' ' . Format::torrent_label('Freeleech!');
                }
                $SnatchedTorrentClass = $Torrent['IsSnatched'] ? ' snatched_torrent' : '';
?>
    <tr class="torrent<?=$SnatchedTorrentClass?>" id="group_<?=$CollageID . $GroupID?>">
        <td></td>
        <td class="td_collage_category center">
            <div title="<?=$TorrentTags->title()?>" class="tooltip <?=Format::css_category($GroupCategoryID)?> <?=$TorrentTags->css_name()?>">
            </div>
        </td>
        <td class="td_info big_info">
<?php if ($LoggedUser['CoverArt']) { ?>
            <div class="group_image float_left clear">
                <?php ImageTools::cover_thumb($WikiImage, $GroupCategoryID) ?>
            </div>
<?php } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => Torrents::can_use_token($Torrent),
                    'key'    => $LoggedUser['torrent_pass'],
                    't'      => $Torrent,
                ]) ?>
                <strong><?=$DisplayName?></strong>
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
                <strong><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></strong> (<?=$NewCount?> new torrent<?= plural($NewCount) ?>)
            </span>&nbsp;
            <span style="float: right;">
                <a href="#" onclick="$('#discog_table_<?=$CollageID?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=($ShowAll ? 'Show' : 'Hide')?></a>&nbsp;&nbsp;&nbsp;<a href="userhistory.php?action=catchup_collages&amp;auth=<?=$LoggedUser['AuthKey']?>&amp;collageid=<?=$CollageID?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;<a href="#" onclick="CollageSubscribe(<?=$CollageID?>); return false;" id="subscribelink<?=$CollageID?>" class="brackets">Unsubscribe</a>
            </span>
        </td>
    </tr>
</table>

<!--</div>-->
<table class="torrent_table<?=$ShowAll ? ' hidden' : ''?> m_table" id="discog_table_<?=$CollageID?>">
    <tr class="colhead">
        <td width="1%"><!-- expand/collapse --></td>
        <td class="m_th_left" width="70%"><strong>Torrents</strong></td>
        <td>Size</td>
        <td class="sign snatches"><img src="<?= STATIC_SERVER ?>/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
        <td class="sign seeders"><img src="<?= STATIC_SERVER ?>/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
        <td class="sign leechers"><img src="<?= STATIC_SERVER ?>/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
    </tr>
<?=$TorrentTable?>
</table>
<?php
    } // foreach ($CollageSubs)
    if ($artistCollage) {
?>
        <h2>Subscribed artist collages<?=($ShowAll ? '' : ' with new additions')?></h2>
<?php
        foreach ($artistCollage as $c) {
            [$id, $name, $new, $artistIds] = $c;
?>
<table style="margin-top: 8px;" class="subscribed_collages_table">
    <tr class="colhead_dark">
        <td>
            <span style="float: left;">
                <strong><a href="collages.php?id=<?= $id ?>"><?= $name ?></a></strong> (<?= $new ?> new artist<?= plural($new) ?>)
            </span>&nbsp;
            <span style="float: right;">
                <a href="#" onclick="$('#discog_table_<?= $id ?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=($ShowAll ? 'Show' : 'Hide')?></a>&nbsp;&nbsp;&nbsp;<a href="userhistory.php?action=catchup_collages&amp;auth=<?=$LoggedUser['AuthKey']?>&amp;collageid=<?= $id ?>" class="brackets">Catch up</a>&nbsp;&nbsp;&nbsp;<a href="#" onclick="CollageSubscribe(<?= $id ?>); return false;" id="subscribelink<?= $id ?>" class="brackets">Unsubscribe</a>
            </span>
        </td>
    </tr>
</table>
<table class="artist_table<?=$ShowAll ? ' hidden' : ''?> m_table" id="discog_table_<?= $id ?>">
<?php       foreach ($artistIds as $artistId) { ?>
    <tr class="colhead">
        <td><?= (new Gazelle\Artist($artistId))->url() ?></td>
    </tr>
<?php       } ?>
</table>
<?php
        }
    }
} // else -- if (empty($NumResults))
?>
</div>
<?php
View::show_footer();
