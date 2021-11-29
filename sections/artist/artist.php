<?php

$ArtistID = (int)($_GET['id'] ?? 0);
$RevisionID = (int)($_GET['revisionid'] ?? 0);
$artistMan = new Gazelle\Manager\Artist;
$Artist = $artistMan->findById($ArtistID, $RevisionID);
if (is_null($Artist)) {
    error(404);
}
$Artist->loadArtistRole();

$bookmark = new Gazelle\Bookmark($Viewer);
$collageMan = new Gazelle\Manager\Collage;
$authKey = $Viewer->auth();
$isSubscribed = (new Gazelle\Subscription($Viewer))->isSubscribedComments('artist', $ArtistID);

$torMan = new Gazelle\Manager\Torrent;
$torMan->setViewer($Viewer);

function torrentEdition($title, $year, $recordLabel, $catalogueNumber, $media) {
    return implode('::', [$title, $year, $recordLabel, $catalogueNumber, $media]);
}

$name = $Artist->name() ?? 'artist:' . $ArtistID;
$Requests = [];

View::show_header($name, ['js' => 'browse,requests,bbcode,comments,voting,subscriptions']);
?>
<div class="thin">
    <div class="header">
        <h2><?=display_str($name)?><?= $RevisionID ? " (Revision #$RevisionID)" : '' ?><?= $Artist->vanityHouse() ? ' [Vanity House]' : '' ?></h2>
        <div class="linkbox">
<?php if ($Viewer->permitted('torrents_edit')) { ?>
            <a href="artist.php?action=edit&amp;artistid=<?= $ArtistID ?>" class="brackets">Edit</a>
<?php } ?>
            <a href="artist.php?action=editrequest&amp;artistid=<?=$ArtistID?>" class="brackets">Request an Edit</a>
<?php if ($Viewer->permitted('site_submit_requests')) { ?>
            <a href="requests.php?action=new&amp;artistid=<?=$ArtistID?>" class="brackets">Add request</a>
<?php
}

if ($Viewer->permitted('site_torrents_notify')) {
    $urlStem = sprintf('artist.php?artistid=%d&amp;auth=%s', $ArtistID, $authKey);
    if ($Viewer->hasArtistNotification($name)) {
?>
            <a href="<?= $urlStem ?>&amp;action=notifyremove" class="brackets">Do not notify of new uploads</a>
<?php } else { ?>
            <a href="<?= $urlStem ?>&amp;action=notify" class="brackets">Notify of new uploads</a>
<?php
    }
}

if ($bookmark->isArtistBookmarked($ArtistID)) { ?>
            <a href="#" id="bookmarklink_artist_<?= $ArtistID ?>" onclick="Unbookmark('artist', <?= $ArtistID ?>, 'Bookmark'); return false;" class="brackets">Remove bookmark</a>
<?php } else { ?>
            <a href="#" id="bookmarklink_artist_<?= $ArtistID ?>" onclick="Bookmark('artist', <?= $ArtistID ?>, 'Remove bookmark'); return false;" class="brackets">Bookmark</a>
<?php } ?>
            <a href="#" id="subscribelink_artist<?= $ArtistID ?>" class="brackets" onclick="SubscribeComments('artist', <?=
                $ArtistID ?>);return false;"><?= $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>

<?php if ($RevisionID && $Viewer->permitted('site_edit_wiki')) { ?>
            <a href="artist.php?action=revert&amp;artistid=<?=$ArtistID?>&amp;revisionid=<?=$RevisionID?>&amp;auth=<?= $authKey ?>" class="brackets">Revert to this revision</a>
<?php } ?>
            <a href="artist.php?id=<?=$ArtistID?>#info" class="brackets">Info</a>
<?php if (defined('LASTFM_API_KEY')) { ?>
            <a href="artist.php?id=<?=$ArtistID?>#concerts" class="brackets">Concerts</a>
<?php } ?>
            <a href="artist.php?id=<?=$ArtistID?>#artistcomments" class="brackets">Comments</a>
            <a href="artist.php?action=history&amp;artistid=<?= $ArtistID ?>" class="brackets">View history</a>
<?php if ($Viewer->permitted('site_delete_artist') && $Viewer->permitted('torrents_delete')) { ?>
            &nbsp;&nbsp;&nbsp;<a href="artist.php?action=delete&amp;artistid=<?=$ArtistID?>&amp;auth=<?= $authKey ?>" class="brackets">Delete</a>
<?php } ?>
        </div>
    </div>

    <div class="sidebar">
<?php
$imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);
if ($Artist->image()) {
    $image = $imgProxy->process($Artist->image());
?>
        <div class="box box_image">
            <div class="head"><strong><?= $name ?></strong></div>
            <div style="text-align: center; padding: 10px 0px;">
                <img style="max-width: 220px;" src="<?= $image ?>" alt="<?= $name ?>" onclick="lightbox.init('<?= $image ?>', 220);" />
            </div>
        </div>
<?php } ?>

        <div class="box box_search">
            <div class="head"><strong>Song Search</strong></div>
            <ul class="nobullet" style="padding-bottom: 2px">
                <li>
                    <form class="search_form" name="filelists" action="torrents.php">
                        <input type="hidden" name="artistname" value="<?= $name ?>" />
                        <input type="hidden" name="action" value="advanced" />
                        <input type="text" autocomplete="off" id="filelist" name="filelist" size="24" placeholder="Find a specific song or track..." spellcheck="false" />
                        <input type="submit" value="&#x1f50e;" />
                    </form>
                </li>
            </ul>
        </div>
        <div class="box box_tags">
            <div class="head"><strong>Tags</strong></div>
            <ul class="stats nobullet">
<?php
$artistReleaseType = [];
$sections = $Artist->sections();
foreach ($sections as $sectionId => $groupList) {
    if (!isset($artistReleaseType[$sectionId])) {
        $artistReleaseType[$sectionId] = 0;
    }
    $artistReleaseType[$sectionId]++;
    foreach(array_keys($groupList) as $groupId) {
        $group = $Artist->group($groupId);
        if (!in_array($group['ReleaseType'], [3, 7])) { // Skip compilations and soundtracks
            new Tags($group['TagList'], true);
        }
    }
}
echo Tags::topAsHtml(50, 'torrents.php?taglist=', $name);
Tags::reset();
?>
            </ul>
        </div>
        <div class="box box_info box_statistics_artist">
            <div class="head"><strong>Statistics</strong></div>
            <ul class="stats nobullet">
                <li>Number of groups: <?= number_format($Artist->nrGroups()) ?></li>
                <li>Number of torrents: <?= number_format($Artist->nrTorrents()) ?></li>
                <li>Number of seeders: <?= number_format($Artist->nrSeeders()) ?></li>
                <li>Number of leechers: <?= number_format($Artist->nrLeechers()) ?></li>
                <li>Number of snatches: <?= number_format($Artist->nrSnatches()) ?></li>
            </ul>
        </div>
        <div class="box box_info box_addcollage_artist">
            <div class="head"><strong>Add to artist collage</strong></div>
                <div class="pad">
                    <form action="collages.php" method="post">
                    <select name="collage_combo">
                        <option value="0">Choose recent...</option>
<?php foreach($collageMan->addToArtistCollageDefault($Viewer->id(), $ArtistID) as $id => $collageName) { ?>
                        <option value="<?= $id ?>"><?= display_str($collageName) ?></option>
<?php } ?>
                    </select>
                    <div> or enter Collage ID or URL</div>
                    <input type="text" name="collage_ref" size="25" />
                    <input type="hidden" name="action" value="add_artist" />
                    <input type="hidden" name="artistid" value="<?= $ArtistID ?>" />
                    <input type="hidden" name="userid" value="<?= $Viewer->id() ?>" />
                    <input type="hidden" name="auth" value="<?= $authKey  ?>" />
                    <br /><br /><input type="submit" value="Add" />
                    </form>
            </div>
        </div>
        <div class="box box_info box_metadata_artist">
            <div class="head"><strong>Metadata</strong></div>
            <ul class="stats nobullet">
                <li>Discogs ID: <?= $Artist->discogsId() ?: '<i>not set</i>' ?></li>
<?php if ($Artist->discogsId()) { ?>
                <li>Name: <?= $Artist->discogsName() ?><?= $Artist->discogsIsPreferred()
                    ? '<span title="This artist does not need to display a sequence number for disambiguation">' . " \xE2\x98\x85</span>" : '' ?></li>
                <li><span title="Artists having the same name">Synonyms: <?= $Artist->homonymCount() - 1 ?></span></li>
<?php } ?>
            </ul>
        </div>

<?php
echo $Twig->render('artist/similar.twig', [
    'admin'        => $Viewer->permitted('site_delete_tag'),
    'artist_id'    => $ArtistID,
    'auth'         => $authKey,
    'autocomplete' => $Viewer->hasAutocomplete('other'),
    'similar'      => $Artist->similarArtists(),
]);

if ($Viewer->permitted('zip_downloader')) {
    if ($Viewer->option('Collector')) {
        [$ZIPList, $ZIPPrefs] = $Viewer->option('Collector');
        $ZIPList = explode(':', $ZIPList);
    } else {
        $ZIPList = ['00', '11'];
        $ZIPPrefs = 1;
    }
?>
        <div class="box box_zipdownload">
            <div class="head colhead_dark"><strong>Collector</strong></div>
            <div class="pad">
                <form class="download_form" name="zip" action="artist.php" method="post">
                    <input type="hidden" name="action" value="download" />
                    <input type="hidden" name="auth" value="<?=$authKey?>" />
                    <input type="hidden" name="artistid" value="<?=$ArtistID?>" />
                    <ul id="list" class="nobullet">
<?php foreach ($ZIPList as $ListItem) { ?>
                        <li id="list<?=$ListItem?>">
                            <input type="hidden" name="list[]" value="<?=$ListItem?>" />
                            <span style="float: left;"><?=ZIP_OPTION[$ListItem]['2']?></span>
                            <span class="remove remove_collector"><a href="#" onclick="remove_selection('<?=$ListItem?>'); return false;" style="float: right;" class="brackets tooltip" title="Remove format from the Collector">X</a></span>
                            <br style="clear: all;" />
                        </li>
<?php } /* foreach */ ?>
                    </ul>
                    <select id="formats" style="width: 180px;">
<?php
$OpenGroup = false;
$LastGroupID = -1;
foreach (ZIP_OPTION as $Option) {
    [$GroupID, $OptionID, $OptName] = $Option;

    if ($GroupID != $LastGroupID) {
        $LastGroupID = $GroupID;
        if ($OpenGroup) { ?>
                        </optgroup>
<?php   } ?>
                        <optgroup label="<?=ZIP_GROUP[$GroupID]?>">
<?php      $OpenGroup = true;
    }
?>
                            <option id="opt<?=$GroupID.$OptionID?>" value="<?=$GroupID.$OptionID?>"<?php if (in_array($GroupID.$OptionID, $ZIPList)) { echo ' disabled="disabled"'; } ?>><?=$OptName?></option>
<?php } /* foreach */ ?>
                        </optgroup>
                    </select>
                    <button type="button" onclick="add_selection()">+</button>
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
} /* if ($Viewer->permitted('zip_downloader')) */ ?>

    </div>
    <div class="main_column">

<div id="discog_table">
    <div class="box center">
<?php
if ($sections = $Artist->sections()) {
    /* Move the sections to the way the viewer wants to see them. */
    $sortHide = $Viewer->option('SortHide') ?? [];
    $reorderedSections = [];
    foreach (array_keys($sortHide) as $reltype) {
        if (isset($artistReleaseType[$reltype])) {
            $reorderedSections[$reltype] = $sections[$reltype];
            unset($artistReleaseType[$reltype]);
        }
    }
    /* Any left-over release types */
    foreach (array_keys($artistReleaseType) as $reltype) {
        $reorderedSections[$reltype] = $sections[$reltype];
    }
    $sections = $reorderedSections;

    foreach (array_keys($sections) as $sectionId) {
        $collapseSection = ($sortHide[$sectionId] ?? 0) == 1;
?>
        <a href="#torrents_<?= $artistMan->sectionLabel($sectionId) ?>" class="brackets"<?=
            $collapseSection ? " onclick=\"$('.releases_$sectionId').gshow(); return true;\"" : '' ?>><?=
            $artistMan->sectionTitle($sectionId) ?></a>
<?php
    }

    $Requests = $Viewer->disableRequests() ? [] : $Artist->requests();
    if (count($Requests)) {
?>
    <a href="#requests" class="brackets">Requests</a>
<?php } ?>
    </div>
    <table class="torrent_table grouped release_table m_table">
<?php
    $urlStem = STATIC_SERVER . '/styles/' . $Viewer->stylesheetName() . '/images/';
    $groupsClosed = (bool)$Viewer->option('TorrentGrouping');

    foreach ($sections as $sectionId => $groupList) {
        $sectionClosed = (bool)($sortHide[$sectionId] ?? 0);
        $groupsHidden = ($groupsClosed || $sectionClosed) ? ' hidden' : '';
?>
                <tr class="colhead_dark" id="torrents_<?= $artistMan->sectionLabel($sectionId) ?>">
                    <td class="small"><!-- expand/collapse --></td>
                    <td class="m_th_left m_th_left_collapsable" width="70%"><a href="#">&uarr;</a>&nbsp;<strong><?=
                        $artistMan->sectionTitle($sectionId) ?></strong> <a href="#" class="tooltip brackets" onclick="$('.releases_<?=
                        $sectionId ?>').gtoggle(true); return false;" title="Show/hide this section">Toggle</a></td>
                    <td>Size</td>
                    <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                    <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                    <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
                </tr>
<?php
    foreach(array_keys($groupList) as $groupId) {
        $Group = $Artist->group($groupId);
        $GroupID = $Group['ID'];
        $GroupName = $Group['Name'];
        $GroupYear = $Group['Year'];
        $isSnatched = isset($Group['Flags']) ? $Group['Flags']['IsSnatched'] : false;
        $TorrentTags = new Tags($Group['TagList'], false);
        $Torrents = isset($Group['Torrents']) ? $Group['Torrents'] : [];
        $Artists = $Group['Artists'];
        $ExtendedArtists = $Group['ExtendedArtists'];

        $DisplayName = "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";

        switch ($sectionId) {
            case ARTIST_SECTION_PRODUCER:
            case ARTIST_SECTION_REMIXER:
            case ARTIST_SECTION_GUEST:
            case ARTIST_GUEST:
                if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5]) || !empty($ExtendedArtists[6])) {
                    unset($ExtendedArtists[2], $ExtendedArtists[3]);
                    $DisplayName = Artists::display_artists($ExtendedArtists).$DisplayName;
                } elseif (count($Artists)) {
                    $DisplayName = Artists::display_artists([1 => $Artists], true, true).$DisplayName;
                }
                break;
            case ARTIST_SECTION_ARRANGER:
            case ARTIST_SECTION_COMPOSER:
                if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
                    unset($ExtendedArtists[3], $ExtendedArtists[4], $ExtendedArtists[6]);
                    $DisplayName = Artists::display_artists($ExtendedArtists).$DisplayName;
                } elseif (count($Artists)) {
                    $DisplayName = Artists::display_artists([1 => $Artists], true, true).$DisplayName;
                }
                break;
            default: // Show composers otherwise
                if (!empty($ExtendedArtists[4])) {
                    $DisplayName = Artists::display_artists([4 => $ExtendedArtists[4]], true, true).$DisplayName;
                }
        }

        if ($GroupYear > 0) {
            $DisplayName = "$GroupYear - $DisplayName";
        }

        if ($Group['VanityHouse']) {
            $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
        }
?>
            <tr class="releases_<?= $sectionId ?> group groupid_<?=$GroupID?>_header discog<?=
                ($sectionClosed ? ' hidden' : '') . ($isSnatched ? ' snatched_group' : '') ?>">
                    <td class="td_collapse center m_td_left">
                        <div id="showimg_<?=$GroupID?>" class="<?= $groupsClosed ? 'show' : 'hide' ?>_torrents">
                            <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?= $GroupID ?>, this, event);" title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all groups in this release type."></a>
                        </div>
                    </td>
                    <td colspan="5" class="td_info big_info">
<?php   if ($Viewer->option('CoverArt')) { ?>
                        <div class="group_image float_left clear">
                            <?= $imgProxy->thumbnail($Group['WikiImage'], $Group['CategoryID']) ?>
                        </div>
<?php   } ?>
                        <div class="group_info clear">
                            <strong><?=$DisplayName?></strong>
<?php if ($bookmark->isTorrentBookmarked($GroupID)) { ?>
                            <span class="remove_bookmark float_right">
                                <a style="float: right;" href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                            </span>
<?php   } else { ?>
                            <span class="add_bookmark float_right">
                                <a style="float: right;" href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
                            </span>
<?php
        }
        if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) {
?>
                            <?= (new Gazelle\Vote($Viewer->id()))->setGroupId($GroupID)->links($authKey) ?>
<?php   } ?>
                            <div class="tags"><?=$TorrentTags->format('torrents.php?taglist=', $name)?></div>
                        </div>
                    </td>
                </tr>
<?php
        $SnatchedGroupClass = ($Group['Flags']['IsSnatched'] ? ' snatched_group' : '');
        $prevEdition = torrentEdition('', '-', '', '', '');
        $EditionID = 0;
        $UnknownCounter = 0;

        foreach ($Torrents as $TorrentID => $Torrent) {
            $torrent = $torMan->findById($TorrentID);
            if (is_null($torrent)) {
                continue;
            }
            $Torrent['PersonalFL'] = $torrent->isFreeleechPersonal();
            $torrentEdition = torrentEdition(
                $Torrent['RemasterTitle'], $Torrent['RemasterYear'], $Torrent['RemasterRecordLabel'],
                $Torrent['RemasterCatalogueNumber'], $Torrent['Media']
            );
            if ($Torrent['Remastered'] && !$Torrent['RemasterYear']) {
                $UnknownCounter++;
            }

            if ($prevEdition != $torrentEdition || $UnknownCounter === 1) {
                $EditionID++;
?>
        <tr class="releases_<?= $sectionId ?> groupid_<?=$GroupID?> edition group_torrent discog<?=$SnatchedGroupClass . $groupsHidden ?>">
            <td colspan="6" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$GroupID?>, <?=$EditionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($Torrent, $Group)?></strong></td>
        </tr>
<?php
            }
            $prevEdition = $torrentEdition;
            $SnatchedTorrentClass = ($torrent->isSnatched($Viewer->id()) ? ' snatched_torrent' : '');
?>
        <tr class="releases_<?=$sectionId?> torrent_row groupid_<?=$GroupID?> edition_<?=$EditionID?> group_torrent discog<?= $SnatchedTorrentClass . $SnatchedGroupClass . $groupsHidden ?>">
            <td class="td_info" colspan="2">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $Torrent,
                    'extra'  => [
                        "<a href=\"ajax.php?action=torrent&amp;id=$TorrentID\" download=\"$name - $GroupName  [$GroupYear] $TorrentID [orpheus.network].json\" class=\"tooltip\" title=\"Download JSON\">JS</a>",
                    ],
                ]) ?>
                &nbsp;&nbsp;&raquo;&nbsp; <a href="torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>"><?= Torrents::torrent_info($Torrent) ?></a>
            </td>
            <td class="td_size number_column nobr"><?=Format::get_size($Torrent['Size'])?></td>
            <td class="td_snatched number_column m_td_right"><?=number_format($Torrent['Snatched'])?></td>
            <td class="td_seeders number_column<?=(($Torrent['Seeders'] == 0) ? ' r00' : '')?> m_td_right"><?=number_format($Torrent['Seeders'])?></td>
            <td class="td_leechers number_column m_td_right"><?=number_format($Torrent['Leechers'])?></td>
        </tr>
<?php
        } /* torrents */
    } /* group */
    } /* section */
?>
                </table>
            </div>
<?php
} /* all sections */

$Collages = $Cache->get_value("artists_collages_$ArtistID");
if (!is_array($Collages)) {
    $DB->prepared_query("
        SELECT c.Name, c.NumTorrents, c.ID
        FROM collages AS c
        INNER JOIN collages_artists AS ca ON (ca.CollageID = c.ID)
        WHERE CategoryID = '7'
            AND Deleted = '0'
            AND ca.ArtistID = ?
        ", $ArtistID
    );
    $Collages = $DB->to_array();
    $Cache->cache_value("artists_collages_$ArtistID", $Collages, 3600 * 6);
}
if (count($Collages) > 0) {
    if (count($Collages) > COLLAGE_SAMPLE_THRESHOLD) {
        // Pick some at random
        $Range = range(0,count($Collages) - 1);
        shuffle($Range);
        $Indices = array_slice($Range, 0, COLLAGE_SAMPLE_THRESHOLD);
        $SeeAll = ' <a href="#" onclick="$(\'.collage_rows\').gtoggle(); return false;">(See all)</a>';
    } else {
        $Indices = range(0, count($Collages)-1);
        $SeeAll = '';
    }
?>
    <table class="collage_table" id="collages">
        <tr class="colhead">
            <td width="85%"><a href="#">&uarr;</a>&nbsp;This artist is in <?=number_format(count($Collages))?> collage<?= plural(count($Collages)) ?><?=$SeeAll?></td>
            <td># artists</td>
        </tr>
<?php
            foreach ($Indices as $i) {
                [$CollageName, $CollageArtists, $CollageID] = $Collages[$i];
                unset($Collages[$i]);
?>
                    <tr>
                        <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                        <td><?=number_format($CollageArtists)?></td>
                    </tr>
<?php
            }
            foreach ($Collages as $Collage) {
                [$CollageName, $CollageArtists, $CollageID] = $Collage;
?>
                    <tr class="collage_rows hidden">
                        <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                        <td><?=number_format($CollageArtists)?></td>
                    </tr>
<?php       } ?>
    </table>
<?php
}

if ($Requests) {
?>
    <table cellpadding="6" cellspacing="1" border="0" class="request_table border" width="100%" id="requests">
        <tr class="colhead_dark">
            <td style="width: 48%;">
                <a href="#">&uarr;</a>&nbsp;
                <strong>Request Name</strong>
            </td>
            <td class="nobr">
                <strong>Vote</strong>
            </td>
            <td class="nobr">
                <strong>Bounty</strong>
            </td>
            <td>
                <strong>Added</strong>
            </td>
        </tr>
<?php
    $Tags = Requests::get_tags(array_keys($Requests));
    $Row = 'b';
    foreach ($Requests as $RequestID => $Request) {
            $CategoryName = CATEGORY[$Request['CategoryID'] - 1];
            $Title = display_str($Request['Title']);
            if ($CategoryName == 'Music') {
                $ArtistForm = Requests::get_artists($RequestID);
                $ArtistLink = Artists::display_artists($ArtistForm, true, true);
                $FullName = $ArtistLink."<a href=\"requests.php?action=view&amp;id=$RequestID\"><span dir=\"ltr\">$Title</span> [{$Request['Year']}]</a>";
            } elseif ($CategoryName == 'Audiobooks' || $CategoryName == 'Comedy') {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\"><span dir=\"ltr\">$Title</span> [{$Request['Year']}]</a>";
            } else {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\" dir=\"ltr\">$Title</a>";
            }

            if (!empty($Tags[$RequestID])) {
                $ReqTagList = [];
                foreach ($Tags[$RequestID] as $TagID => $TagName) {
                    $ReqTagList[] = "<a href=\"requests.php?tags=$TagName\">".display_str($TagName).'</a>';
                }
                $ReqTagList = implode(', ', $ReqTagList);
            } else {
                $ReqTagList = '';
            }
?>
        <tr class="row<?=($Row === 'b' ? 'a' : 'b')?>">
            <td>
                <?=$FullName?>
                <div class="tags"><?=$ReqTagList?></div>
            </td>
            <td class="nobr">
                <span id="vote_count_<?=$RequestID?>"><?=$Request['Votes']?></span>
<?php       if ($Viewer->permitted('site_album_votes')) { ?>
                <input type="hidden" id="auth" name="auth" value="<?=$authKey?>" />
                &nbsp;&nbsp; <a href="javascript:Vote(0, <?=$RequestID?>)" class="brackets"><strong>+</strong></a>
<?php       } ?>
            </td>
            <td class="nobr">
                <span id="bounty_<?=$RequestID?>"><?=Format::get_size($Request['Bounty'])?></span>
            </td>
            <td>
                <?=time_diff($Request['TimeAdded'])?>
            </td>
        </tr>
<?php   } ?>
    </table>
<?php
}

$similar = $Artist->similarGraph(SIMILAR_WIDTH, SIMILAR_HEIGHT);
if ($similar) {
?>
    <div id="similar_artist_map" class="box">
      <div id="flipper_head" class="head">
        <a href="#">&uarr;</a>&nbsp;
        <strong id="flipper_title">Similar Artist Map</strong>
        <a id="flip_to" class="brackets" href="#" onclick="flipView(); return false;">Switch to cloud</a>
      </div>
      <div id="flip_view_1" style="width: <?= SIMILAR_WIDTH ?>px; height: <?= SIMILAR_HEIGHT ?>px;">
        <div id="similar-artist" style=" top: <?= SIMILAR_HEIGHT/2 - 25 ?>px; left: <?= SIMILAR_WIDTH/2 - mb_strlen($Artist->name()) * 4 ?>px;">
          <span class="name"><?= $Artist->name() ?></span>
        </div>
        <div class="similar-artist-graph" style="padding-top: <?= SIMILAR_HEIGHT / SIMILAR_WIDTH * 100 ?>%;">
        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMinYMin meet" viewBox="0 0 <?=
            SIMILAR_WIDTH ?> <?= SIMILAR_HEIGHT ?>" style="display: inline-block; position: absolute; top: 0; left: 0;">
<?php
    $names = '';
    foreach ($similar as $s) {
        if ($s['proportion'] <= 0.2) {
            $pt = 8;
        } elseif ($s['proportion'] <= 0.3) {
            $pt = 9;
        } elseif ($s['proportion'] <= 0.4) {
            $pt = 10;
        } else {
            $pt = 11;
        }
        $xPos = max(3, $s['x'] - ($s['x'] < SIMILAR_WIDTH * 0.85 ? 0 : (int)(mb_strlen($s['artist_name']) * $pt * 0.6)));
        $yPos = max(3, $s['y'] + ($s['y'] < SIMILAR_HEIGHT * 0.5 ? -2 : 10));
        $names .= "<a xlink:href=\"artist.php?id={$s['artist_id']}\"><text x=\"{$xPos}\" y=\"{$yPos}\" >{$s['artist_name']}</text></a>";
        foreach ($s['related'] as $r) {
            if ($r >= $s['artist_id']) {
?>
          <line x1="<?= $similar[$r]['x'] ?>" y1="<?= $similar[$r]['y'] ?>" x2="<?= $s['x'] ?>" y2="<?=
            $s['y'] ?>" style="stroke:rgb(0,153,0);stroke-width:1" />
<?php
            }
        }
?>
          <line x1="<?= SIMILAR_WIDTH/2 ?>" y1="<?= SIMILAR_HEIGHT/2 ?>" x2="<?= $s['x'] ?>" y2="<?=
            $s['y'] ?>" style="stroke:rgb(77,153,0);stroke-width:<?= (int)ceil($s['proportion'] * 4) + 1 ?>" />
<?php
    }
?>
          <?= $names // last, to overlay text on graph ?>
        </svg>
      </div>
    </div>
    <div id="flip_view_2" style="display: none; position: relative; width: <?= SIMILAR_WIDTH ?>px; height: <?= SIMILAR_HEIGHT ?>px;">
      <canvas id="similarArtistsCanvas" style="position: absolute;" width="<?= SIMILAR_WIDTH - 20 ?>px" height="<?= SIMILAR_HEIGHT - 20 ?>px"></canvas>
      <div id="artistTags" style="display: none;"><ul><li></li></ul></div>
      <strong><br /><a id="currentArtist" style="position: relative; margin-left: 15px" href="#null">Loading...</a></strong>
    </div>
  </div>

<script type="text/javascript">//<![CDATA[
var cloudLoaded = false;
function flipView() {
    if (document.getElementById('flip_view_1').style.display == 'block') {
        document.getElementById('flip_view_1').style.display = 'none';
        document.getElementById('flip_view_2').style.display = 'block';
        document.getElementById('flipper_title').innerHTML = 'Similar Artist Cloud';
        document.getElementById('flip_to').innerHTML = 'switch to map';
        if (!cloudLoaded) {
            require("<?= STATIC_SERVER ?>/functions/tagcanvas.js", function () {
                require("<?= STATIC_SERVER ?>/functions/artist_cloud.js", function () {});
            });
            cloudLoaded = true;
        }
    } else {
        document.getElementById('flip_view_1').style.display = 'block';
        document.getElementById('flip_view_2').style.display = 'none';
        document.getElementById('flipper_title').innerHTML = 'Similar Artist Map';
        document.getElementById('flip_to').innerHTML = 'switch to cloud';
    }
}

//TODO move this to global, perhaps it will be used elsewhere in the future
//http://stackoverflow.com/questions/7293344/load-javascript-dynamically
function require(file, callback) {
    var script = document.getElementsByTagName('script')[0],
    newjs = document.createElement('script');

    // IE
    newjs.onreadystatechange = function () {
        if (newjs.readyState === 'loaded' || newjs.readyState === 'complete') {
            newjs.onreadystatechange = null;
            callback();
        }
    };
    // others
    newjs.onload = function () {
        callback();
    };
    newjs.src = file;
    script.parentNode.insertBefore(newjs, script);
}
//]]>
</script>
<?php } // if $similar ?>

        <div id="artist_information" class="box">
            <div id="info" class="head">
                <a href="#">&uarr;</a>&nbsp;
                <strong>Artist Information</strong>
                <a href="#" class="brackets" onclick="$('#body').gtoggle(); return false;">Toggle</a>
            </div>
            <div id="body" class="body"><?=Text::full_format($Artist->body())?></div>
        </div>
<?php
if (defined('LASTFM_API_KEY')) {
    require_once('concerts.php');
}
?>
    <div id="artistcomments">
<?php
$commentPage = new Gazelle\Comment\Artist($ArtistID);
if (isset($_GET['postid'])) {
    $commentPage->setPostId((int)$_GET['postid']);
} elseif (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');
echo $paginator->linkbox();
$comments = new Gazelle\CommentViewer\Artist($Viewer, $ArtistID);
$comments->renderThread($commentPage->thread(), $commentPage->lastRead());
$textarea = new Gazelle\Util\Textarea('quickpost', '', 90, 8);
$textarea->setPreviewManual(true);
echo $paginator->linkbox();
echo $Twig->render('reply.twig', [
    'action'   => 'take_post',
    'auth'     => $authKey,
    'avatar'   => (new Gazelle\Manager\User)->avatarMarkup($Viewer, $Viewer),
    'id'       => $ArtistID,
    'name'     => 'pageid',
    'subbed'   => $isSubscribed,
    'textarea' => $textarea,
    'url'      => 'comments.php?page=artist',
    'user'     => $Viewer,
]);
?>
        </div>
    </div>
</div>
</div>
<?php
View::show_footer();
