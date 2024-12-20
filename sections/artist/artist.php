<?php

$RevisionID = (int)($_GET['revisionid'] ?? 0);
$artistMan = new Gazelle\Manager\Artist();
$Artist = $RevisionID
    ? $artistMan->findByIdAndRevision((int)($_GET['id'] ?? 0), $RevisionID)
    : $artistMan->findById((int)($_GET['id'] ?? 0));
if (is_null($Artist)) {
    error(404);
}
$Artist->loadArtistRole();
$artistId = $Artist->id();

$bookmark   = new Gazelle\User\Bookmark($Viewer);
$collageMan = new Gazelle\Manager\Collage();
$tgMan      = (new Gazelle\Manager\TGroup())->setViewer($Viewer);
$torMan     = (new Gazelle\Manager\Torrent())->setViewer($Viewer);
$stats      = new Gazelle\Stats\Artist($artistId);
$userMan    = new Gazelle\Manager\User();
$reportMan  = new Gazelle\Manager\Report($userMan);
$vote       = new Gazelle\User\Vote($Viewer);

$authKey      = $Viewer->auth();
$isSubscribed = (new Gazelle\User\Subscription($Viewer))->isSubscribedComments('artist', $artistId);
$name         = $Artist->name();
$requestList  = $Viewer->disableRequests() ? [] : (new Gazelle\Manager\Request())->findByArtist($Artist);

View::show_header($name, ['js' => 'browse,requests,bbcode,comments,voting,subscriptions']);
?>
<div class="thin">
    <div class="header">
        <h2><?=html_escape($name)?><?= $RevisionID ? " (Revision #$RevisionID)" : '' ?><?= $Artist->isShowcase() ? ' [Showcase]' : '' ?></h2>
        <div class="linkbox">
<?php if ($Viewer->permitted('torrents_edit')) { ?>
            <a href="artist.php?action=edit&amp;artistid=<?= $artistId ?>" class="brackets">Edit</a>
<?php } ?>
            <a href="artist.php?action=editrequest&amp;artistid=<?=$artistId?>" class="brackets">Request an Edit</a>
            <a href="upload.php?artistid=<?= $artistId ?>" class="brackets">Add upload</a>
<?php if ($Viewer->permitted('site_submit_requests')) { ?>
            <a href="requests.php?action=new&amp;artistid=<?=$artistId?>" class="brackets">Add request</a>
<?php
}

if ($Viewer->permitted('site_torrents_notify')) {
    $urlStem = sprintf('artist.php?artistid=%d&amp;auth=%s', $artistId, $authKey);
    if ($Viewer->hasArtistNotification($name)) {
?>
            <a href="<?= $urlStem ?>&amp;action=notifyremove" class="brackets">Do not notify of new uploads</a>
<?php } else { ?>
            <a href="<?= $urlStem ?>&amp;action=notify" class="brackets">Notify of new uploads</a>
<?php
    }
}
echo $Twig->render('bookmark/action.twig', [
    'class'         => 'artist',
    'id'            => $artistId,
    'is_bookmarked' => $bookmark->isArtistBookmarked($artistId),
]);
?>
            <a href="#" id="subscribelink_artist<?= $artistId ?>" class="brackets" onclick="SubscribeComments('artist', <?=
                $artistId ?>);return false;"><?= $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>

<?php if ($RevisionID && $Viewer->permitted('site_edit_wiki')) { ?>
            <a href="artist.php?action=revert&amp;artistid=<?=$artistId?>&amp;revisionid=<?=$RevisionID?>&amp;auth=<?= $authKey ?>" class="brackets">Revert to this revision</a>
<?php } ?>
            <a href="artist.php?id=<?=$artistId?>#info" class="brackets">Info</a>
            <a href="artist.php?id=<?=$artistId?>#artistcomments" class="brackets">Comments</a>
            <a href="artist.php?action=history&amp;artistid=<?= $artistId ?>" class="brackets">View history</a>
<?php if ($Viewer->permitted('site_delete_artist') && $Viewer->permitted('torrents_delete')) { ?>
            &nbsp;&nbsp;&nbsp;<a href="artist.php?action=delete&amp;artistid=<?=$artistId?>&amp;auth=<?= $authKey ?>" class="brackets">Delete</a>
<?php } ?>
        </div>
    </div>

    <div class="sidebar">
<?php
$imgProxy = new Gazelle\Util\ImageProxy($Viewer);
if ($Artist->image()) {
    $image = html_escape(image_cache_encode($Artist->image()));
?>
        <div class="box box_image">
            <div class="head"><strong><?= html_escape($name) ?></strong></div>
            <div style="text-align: center; padding: 10px 0px;">
                <img loading="eager" style="max-width: 220px;" src="<?= $image ?>" alt="artist image"
                     onclick="lightbox.init('<?= $image ?>', 220);"
                     data-origin-src="<?= html_escape($Artist->image()) ?>" />
            </div>
        </div>
<?php } ?>

        <div class="box box_search">
            <div class="head"><strong>Song Search</strong></div>
            <ul class="nobullet" style="padding-bottom: 2px">
                <li>
                    <form class="search_form" name="filelists" action="torrents.php">
                        <input type="hidden" name="artistname" value="<?= html_escape($name) ?>" />
                        <input type="hidden" name="action" value="advanced" />
                        <input type="text" autocomplete="off" id="filelist" name="filelist" size="24" placeholder="Find a specific song or track..." spellcheck="false" />
                        <input type="submit" value="&#x1f50e;" />
                    </form>
                </li>
            </ul>
        </div>
        <div class="box box_tags">
            <div class="head"><strong>Tags</strong></div>
<?php
$tagLeaderboard = $Artist->tagLeaderboard();
if ($tagLeaderboard) {
?>
            <ul class="stats nobullet">
<?php
    $n = 0;
    foreach ($tagLeaderboard as $tag) {
        if (++$n > 5 && $Viewer->primaryClass() === USER) {
            break;
        }
?>
                <li><a href="torrents.php?taglist=<?= $tag['name'] ?>"><?= $tag['name'] ?></a> (<?= $tag['total'] ?>)</li>
<?php
    }
} else {
?>
            <ul class="stats nobullet">
                <li><i>No tags</i></li>
<?php } ?>
            </ul>
        </div>
<?php
if (count($Artist->groupIds()) > 1000) {
    // prevent OOMs
    Gazelle\DB::DB()->disableQueryLog();
}
$artistReleaseType = [];
$sections = $Artist->sections();
foreach ($sections as $sectionId => $groupList) {
    if (!isset($artistReleaseType[$sectionId])) {
        $artistReleaseType[$sectionId] = 0;
    }
    $artistReleaseType[$sectionId]++;
}
?>
        <div class="box box_info box_statistics_artist">
            <div class="head"><strong>Statistics</strong></div>
            <ul class="stats nobullet">
                <li>Number of groups: <?= number_format($stats->tgroupTotal()) ?></li>
                <li>Number of torrents: <?= number_format($stats->torrentTotal()) ?></li>
                <li>Number of snatches: <?= number_format($stats->snatchTotal()) ?></li>
                <li>Number of seeders: <?= number_format($stats->seederTotal()) ?></li>
                <li>Number of leechers: <?= number_format($stats->leecherTotal()) ?></li>
            </ul>
        </div>
<?php
if ($Viewer->permitted('site_collages_manage') || $Viewer->activePersonalCollages()) {
    echo $Twig->render('artist/collage-add.twig', [
        'collage_list' => $collageMan->addToArtistCollageDefault($artistId, $Viewer),
        'artist_id'    => $artistId,
        'viewer'       => $Viewer,
    ]);
}
?>
        <div class="box box_info box_metadata_artist">
            <div class="head"><strong>Metadata</strong></div>
            <ul class="stats nobullet">
<?php if (!$Artist->discogs()->id()) { ?>
                <li>Discogs ID: <i>not set</i></li>
<?php } else { ?>
                <li>Discogs ID: <?= $Artist->discogs()->id() ?></li>
                <li>Name: <?= html_escape($Artist->discogs()->name()) ?><?= $Artist->discogsIsPreferred()
                    ? '<span title="This artist does not need to display a sequence number for disambiguation">' . " \xE2\x98\x85</span>" : '' ?></li>
                <li><span title="Artists having the same name">Synonyms: <?= $Artist->homonymCount() - 1 ?></span></li>
<?php } ?>
            </ul>
        </div>

<?php
echo $Twig->render('artist/similar.twig', [
    'similar'      => $Artist->similar(),
    'viewer'       => $Viewer,
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
                    <input type="hidden" name="artistid" value="<?=$artistId?>" />
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
        if ($OpenGroup) {
?>
                        </optgroup>
<?php   } ?>
                        <optgroup label="<?=ZIP_GROUP[$GroupID]?>">
<?php
        $OpenGroup = true;
    }
?>
                            <option id="opt<?=$GroupID . $OptionID?>" value="<?=$GroupID . $OptionID?>"<?php if (in_array($GroupID . $OptionID, $ZIPList)) {
echo ' disabled="disabled"'; } ?>><?=$OptName?></option>
<?php } /* foreach */ ?>
                        </optgroup>
                    </select>
                    <button type="button" onclick="add_selection()">+</button>
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
<?php
} /* if ($Viewer->permitted('zip_downloader')) */ ?>

    </div>
    <div class="main_column">

<?= $Twig->render('collage/summary.twig', [
    'class'   => 'collage_rows',
    'object'  => 'artist',
    'summary' => $collageMan->artistSummary($Artist),
]); ?>
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

    if ($requestList) {
?>
    <a href="#requests" class="brackets">Requests</a>
<?php } ?>
    </div>
    <table class="torrent_table grouped release_table m_table">
<?php
    $urlStem = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
    $groupsClosed = (bool)$Viewer->option('TorrentGrouping');
    $snatcher = $Viewer->snatch();

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
    foreach (array_keys($groupList) as $groupId) {
        $groupId = (int)$groupId;
        $tgroup = $tgMan->findById($groupId);
        if (is_null($tgroup)) {
            continue;
        }
        $isSnatched = $tgroup->isSnatched();

?>
            <tr class="releases_<?= $sectionId ?> group groupid_<?= $groupId ?>_header discog<?= ($sectionClosed ? ' hidden' : '') . ($isSnatched ? ' snatched_group' : '') ?>">
<?= $Twig->render('tgroup/collapse-tgroup.twig', [ 'closed' => $groupsClosed, 'id' => $groupId ]) ?>
                <td colspan="5" class="td_info big_info">
<?php   if ($Viewer->option('CoverArt')) { ?>
                    <div class="group_image float_left clear">
                        <?= $imgProxy->tgroupThumbnail($tgroup) ?>
                    </div>
<?php   } ?>
                    <div class="group_info clear">
                        <strong><?= $tgroup->year() ?> – <a href="<?= $tgroup->url() ?>" title="View torrent group" dir="ltr"><?= html_escape($tgroup->name()) ?></a></strong>
                        <span class="float_right">
<?php
        echo $Twig->render('bookmark/action.twig', [
            'class'         => 'torrent',
            'id'            => $groupId,
            'is_bookmarked' => $bookmark->isTorrentBookmarked($groupId),
        ]);

        if (!$Viewer->option('NoVoteLinks')) {
?>
                        <br /><?= $vote->links($tgroup) ?>
<?php   } ?>
                        </span>
                        <div class="tags"><?= implode(' ', $tgroup->torrentTagList()) ?></div>
                    </div>
                </td>
            </tr>
<?php
        echo $Twig->render('torrent/detail-torrentgroup.twig', [
            'colspan_add'     => 1,
            'hide'            => $groupsClosed,
            'is_snatched_grp' => $isSnatched,
            'report_man'      => $reportMan,
            'snatcher'        => $snatcher,
            'tgroup'          => $tgroup,
            'torrent_list'    => object_generator($torMan, $tgroup->torrentIdList()),
            'tor_man'         => $torMan,
            'viewer'          => $Viewer,
        ]);
        unset($tgroup);
    } /* group */
    } /* section */
?>
                </table>
<?php } /* all sections */ ?>
</div>

<?php
echo $Twig->render('request/list.twig', [
    'list'            => $requestList,
    'standard_bounty' => REQUEST_MIN * 1024 * 1024,
    'viewer'          => $Viewer,
]);

$graph = $Artist->similar()->similarGraph(SIMILAR_WIDTH, SIMILAR_HEIGHT);
if ($graph) {
?>
    <div id="similar_artist_map" class="box">
      <div id="flipper_head" class="head">
        <a href="#">&uarr;</a>&nbsp;
        <strong id="flipper_title">Similar Artist Map</strong>
        <a id="flip_to" class="brackets" href="#" onclick="flipView(); return false;">Switch to cloud</a>
      </div>
      <div id="flip_view_1" style="width: <?= SIMILAR_WIDTH ?>px; height: <?= SIMILAR_HEIGHT ?>px;">
        <div id="similar-artist" style=" top: <?= SIMILAR_HEIGHT / 2 - 25 ?>px; left: <?= SIMILAR_WIDTH / 2 - mb_strlen($Artist->name()) * 4 ?>px;">
          <span class="name"><?= html_escape($Artist->name()) ?></span>
        </div>
        <div class="similar-artist-graph" style="padding-top: <?= SIMILAR_HEIGHT / SIMILAR_WIDTH * 100 ?>%;">
        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMinYMin meet" viewBox="0 0 <?=
            SIMILAR_WIDTH ?> <?= SIMILAR_HEIGHT ?>" style="display: inline-block; position: absolute; top: 0; left: 0;">
<?php
    $names = '';
    foreach ($graph as $s) {
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
        $names .= "<a xlink:href=\"artist.php?id={$s['artist_id']}\"><text x=\"{$xPos}\" y=\"{$yPos}\" >" . html_escape($s['artist_name']) . "</text></a>";
        foreach ($s['related'] as $r) {
            if ($r >= $s['artist_id']) {
?>
          <line x1="<?= $graph[$r]['x'] ?>" y1="<?= $graph[$r]['y'] ?>" x2="<?= $s['x'] ?>" y2="<?=
            $s['y'] ?>" style="stroke:rgb(0,153,0);stroke-width:1" />
<?php
            }
        }
?>
          <line x1="<?= SIMILAR_WIDTH / 2 ?>" y1="<?= SIMILAR_HEIGHT / 2 ?>" x2="<?= $s['x'] ?>" y2="<?=
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
<?php } // if $graph ?>

        <div id="artist_information" class="box">
            <div id="info" class="head">
                <a href="#">&uarr;</a>&nbsp;
                <strong>Artist Information</strong>
                <a href="#" class="brackets" onclick="$('#body').gtoggle(); return false;">Toggle</a>
            </div>
            <div id="body" class="body"><?=Text::full_format($Artist->body())?></div>
        </div>
    <div id="artistcomments">
<?php
$commentPage = new Gazelle\Comment\Artist($artistId, (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

echo $Twig->render('comment/thread.twig', [
    'action'    => 'take_post',
    'object'    => $Artist,
    'name'      => 'pageid',
    'comment'   => $commentPage,
    'paginator' => $paginator,
    'subbed'    => $isSubscribed,
    'textarea'  => (new Gazelle\Util\Textarea('quickpost', '', 90, 8))->setPreviewManual(true),
    'url'       => $_SERVER['REQUEST_URI'],
    'url_stem'  => 'comments.php?page=artist',
    'userMan'   => $userMan,
    'viewer'    => $Viewer,
]);
?>
        </div>
    </div>
</div>
</div>
<?php
View::show_footer();
