<?php

$TorrentList = $Collage->torrentList();
$GroupIDs = $Collage->groupIds();

View::show_header($Collage->name(), ['js' => 'browse,collage,bbcode,voting']);
?>
<div class="thin">
<?= $Twig->render('collage/header.twig', [
    'auth'        => $Viewer->auth(),
    'bookmarked'  => $bookmark->isCollageBookmarked($Viewer->id(), $CollageID),
    'can_create'  => $Viewer->permitted('site_collages_create'),
    'can_delete'  => $Viewer->permitted('site_collages_delete') || $Collage->isOwner($Viewer->id()),
    'can_edit'    => $Viewer->permitted('site_collages_delete') || ($Viewer->permitted('site_edit_wiki') && !$Collage->isLocked()),
    'can_manage'  => $Viewer->permitted('site_collages_manage') && !$Collage->isLocked(),
    'can_sub'     => $Viewer->permitted('site_collages_subscribe'),
    'id'          => $CollageID,
    'name'        => $Collage->name(),
    'object'      => 'torrent',
    'subbed'      => $Collage->isSubscribed($Viewer->id()),
    'user_id'     => $Viewer->id(),
]);
?>
    <div class="sidebar">
<?= $Twig->render('collage/sidebar.twig', [
    'artists'        => $Collage->numArtists(),
    'auth'           => $Viewer->auth(),
    'can_add'        => !$Collage->isLocked()
        && (
            ($Collage->categoryId() != 0 && $Viewer->permitted('site_collages_manage'))
            ||
            ($Collage->categoryId() == 0 && $Collage->isOwner($Viewer->id()))
        ),
    'can_post'       => !$Viewer->disablePosting(),
    'category_id'    => $Collage->categoryId(),
    'category_name'  => COLLAGE[$Collage->categoryId()],
    'comments'       => (new Gazelle\Manager\Comment)->collageSummary($CollageID),
    'contributors_n' => $Collage->numContributors(),
    'contributors'   => array_slice($Collage->contributors(), 0, 5, true),
    'description'    => Text::full_format($Collage->description()),
    'entries'        => $Collage->numEntries(),
    'id'             => $CollageID,
    'is_personal'    => $Collage->categoryId() == 0,
    'object'         => 'torrent',
    'object_name'    => 'torrent group',
    'subscribers'    => $Collage->numSubscribers(),
    'top_artists'    => $Collage->topArtists(10),
    'top_tags'       => $Collage->topTags(5),
    'updated'        => $Collage->updated(),
    'user_id'        => $Collage->ownerId(),
]);

if ($Viewer->permitted('zip_downloader')) {
    if (isset($LoggedUser['Collector'])) {
        [$ZIPList, $ZIPPrefs] = $LoggedUser['Collector'];
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
<?php
} /* zip collector */ ?>
    </div>
    <div class="main_column">
<?php if ($CollageCovers != 0) { ?>
        <div id="coverart" class="box">
            <div class="head" id="coverhead"><strong>Cover Art</strong></div>
            <ul class="collage_images" id="collage_page0">
<?php
    $collMan = new Gazelle\Manager\Collage;
    for ($Idx = 0; $Idx < min($NumGroups, $CollageCovers); $Idx++) {
        echo $collMan->coverRow($TorrentList[$GroupIDs[$Idx]]);
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
$urlStem = STATIC_SERVER . '/styles/' . $Viewer->stylesheetName() . '/images/';
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
$vote = new Gazelle\Vote($Viewer->id());
$Number = 0;
foreach ($GroupIDs as $Idx => $GroupID) {
    $Group = $TorrentList[$GroupID];
    $GroupName = $Group['Name'];
    $GroupYear = $Group['Year'];
    $GroupCategoryID = $Group['CategoryID'];
    $GroupFlags = isset($Group['Flags']) ? $Group['Flags'] : ['IsSnatched' => false];
    $Torrents = isset($Group['Torrents']) ? $Group['Torrents'] : [];
    $TorrentTags = new Tags($Group['TagList']);
    $Artists = $Group['Artists'];
    $ExtendedArtists = $Group['ExtendedArtists'];

    $Number++;
    $DisplayName = "$Number - ";

    if (!empty($ExtendedArtists[1])
        || !empty($ExtendedArtists[4])
        || !empty($ExtendedArtists[5])
        || !empty($ExtendedArtists[6])
    ) {
        unset($ExtendedArtists[2]);
        unset($ExtendedArtists[3]);
        $DisplayName .= Artists::display_artists($ExtendedArtists);
    }
    elseif (count($GroupArtists) > 0) {
        $DisplayName .= Artists::display_artists(['1' => $GroupArtists]);
    }

    $DisplayName .= "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";
    if ($GroupYear > 0) {
        $DisplayName = "$DisplayName [$GroupYear]";
    }
    if ($Group['VanityHouse']) {
        $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
    }
    $SnatchedGroupClass = ($GroupFlags['IsSnatched'] ? ' snatched_group' : '');
    $UserVote = isset($UserVotes[$GroupID]) ? $UserVotes[$GroupID]['Type'] : '';

    if (count($Torrents) > 1 || $GroupCategoryID == 1) {
        // Grouped torrents
        $ShowGroups = !(!empty($LoggedUser['TorrentGrouping']) && $LoggedUser['TorrentGrouping'] == 1);
?>
        <tr class="group groupid_<?=$GroupID?>_header discog<?= $SnatchedGroupClass ?>" id="group_<?= $GroupID ?>">
            <td class="center">
                <div id="showimg_<?= $GroupID ?>" class="<?= ($ShowGroups ? 'hide' : 'show') ?>_torrents">
                    <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?= $GroupID ?>, this, event);"
                       title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all groups on this page."></a>
                </div>
            </td>
            <td class="center">
                <div title="<?= $TorrentTags->title() ?>"
                     class="tooltip <?= Format::css_category($GroupCategoryID) ?> <?= $TorrentTags->css_name() ?>"></div>
            </td>
            <td colspan="5">
                <strong><?= $DisplayName ?></strong>
<?php   if ($bookmark->isTorrentBookmarked($Viewer->id(), $GroupID)) { ?>
                    <span class="remove_bookmark float_right">
                        <a style="float: right;" href="#" id="bookmarklink_torrent_<?= $GroupID ?>"
                           class="remove_bookmark brackets"
                           onclick="Unbookmark('torrent', <?= $GroupID ?>, 'Bookmark'); return false;">Remove bookmark</a>
                    </span>
<?php   } else { ?>
                    <span class="add_bookmark float_right">
                        <a style="float: right;" href="#" id="bookmarklink_torrent_<?= $GroupID ?>"
                           class="add_bookmark brackets"
                           onclick="Bookmark('torrent', <?= $GroupID ?>, 'Remove bookmark'); return false;">Bookmark</a>
                    </span>
<?php
        }
        if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) {
?>
                    <?= $vote->setGroupId($GroupID)->links($Viewer->auth()) ?>
<?php   } ?>
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

            if ($Torrent['RemasterTitle'] != $LastRemasterTitle
                || $Torrent['RemasterYear'] != $LastRemasterYear
                || $Torrent['RemasterRecordLabel'] != $LastRemasterRecordLabel
                || $Torrent['RemasterCatalogueNumber'] != $LastRemasterCatalogueNumber
                || (isset($FirstUnknown) && $FirstUnknown)
                || $Torrent['Media'] != $LastMedia
            ) {
                $EditionID++;
?>
                <tr class="group_torrent groupid_<?= $GroupID ?> edition<?= $SnatchedGroupClass . (!empty($LoggedUser['TorrentGrouping']) && $LoggedUser['TorrentGrouping'] == 1 ? ' hidden' : '') ?>">
                    <td colspan="7" class="edition_info"><strong><a href="#"
                                                                    onclick="toggle_edition(<?= $GroupID ?>, <?= $EditionID ?>, this, event)"
                                                                    class="tooltip"
                                                                    title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?= Torrents::edition_string($Torrent, $Group) ?>
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
            <tr class="group_torrent torrent_row groupid_<?= $GroupID ?> edition_<?= $EditionID ?><?= $SnatchedTorrentClass . $SnatchedGroupClass . (!empty($LoggedUser['TorrentGrouping']) && $LoggedUser['TorrentGrouping'] == 1 ? ' hidden' : '') ?>">
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
    } else {
        // Viewing a type that does not require grouping
        $TorrentID = key($Torrents);
        $Torrent = current($Torrents);

        $DisplayName = "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";

        if ($Torrent['IsSnatched']) {
            $DisplayName .= ' ' . Format::torrent_label('Snatched!');
        }
        if ($Torrent['FreeTorrent'] == '1') {
            $DisplayName .= ' ' . Format::torrent_label('Freeleech!');
        }
        elseif ($Torrent['FreeTorrent'] == '2') {
            $DisplayName .= ' ' . Format::torrent_label('Neutral Leech!');
        }
        elseif ($Torrent['PersonalFL']) {
            $DisplayName .= ' ' . Format::torrent_label('Personal Freeleech!');
        }
        $SnatchedTorrentClass = ($Torrent['IsSnatched'] ? ' snatched_torrent' : '');
?>
        <tr class="torrent torrent_row<?= $SnatchedTorrentClass . $SnatchedGroupClass ?>" id="group_<?= $GroupID ?>">
            <td></td>
            <td class="td_collage_category center">
                <div title="<?= $TorrentTags->title() ?>"
                     class="tooltip <?= Format::css_category($GroupCategoryID) ?> <?= $TorrentTags->css_name() ?>">
                </div>
            </td>
            <td class="td_info">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => Torrents::can_use_token($Torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $Torrent,
                ]) ?>
                <strong><?= $DisplayName ?></strong>
<?php   if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) { ?>
                <?= $vote->setGroupId($GroupID)->links($Viewer->auth()) ?>
<?php   } ?>
                <div class="tags"><?= $TorrentTags->format() ?></div>
            </td>
            <td class="td_size number_column nobr"><?= Format::get_size($Torrent['Size']) ?></td>
            <td class="td_snatched m_td_right number_column"><?= number_format($Torrent['Snatched']) ?></td>
            <td class="td_seeders m_td_right number_column<?= (($Torrent['Seeders'] == 0) ? ' r00' : '') ?>"><?= number_format($Torrent['Seeders']) ?></td>
            <td class="td_leechers m_td_right number_column"><?= number_format($Torrent['Leechers']) ?></td>
        </tr>
<?php
    }
}
?>
        </table>
    </div>
</div>
