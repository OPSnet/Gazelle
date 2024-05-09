<?php

use Gazelle\Enum\CacheBucket;

header('Access-Control-Allow-Origin: *');

$tgMan  = (new Gazelle\Manager\TGroup())->setViewer($Viewer);
$tgroup = $tgMan->findById((int)($_GET['id'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}
$tgroupId = $tgroup->id();
$RevisionID = (int)($_GET['revisionid'] ?? 0);

// Comments (must be loaded before View::show_header so that subscriptions and quote notifications are handled properly)
$commentPage = new Gazelle\Comment\Torrent($tgroupId, (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$artistMan     = new Gazelle\Manager\Artist();
$collageMan    = new Gazelle\Manager\Collage();
$torMan        = (new Gazelle\Manager\Torrent())->setViewer($Viewer);
$reportMan     = new Gazelle\Manager\Torrent\Report($torMan);
$reportTypeMan = new Gazelle\Manager\Torrent\ReportType();
$requestMan    = new Gazelle\Manager\Request();
$userMan       = new Gazelle\Manager\User();
$snatcher      = $Viewer->snatch();
$vote          = new Gazelle\User\Vote($Viewer);

$isSubscribed   = (new Gazelle\User\Subscription($Viewer))->isSubscribedComments('torrents', $tgroupId);
$releaseTypes   = (new Gazelle\ReleaseType())->list();
$urlStem        = (new Gazelle\User\Stylesheet($Viewer))->imagePath();

$categoryId     = $tgroup->categoryId();
$musicRelease   = $tgroup->categoryName() == 'Music';
$year           = $tgroup->year();
$torrentList    = $tgroup->torrentIdList();
$removed        = $torrentList ? [] : $tgroup->deletedMasteringList();

$section = [
    ['id' => ARTIST_COMPOSER,  'name' => 'composer',  'class' => 'artists_composers',  'role' => 'Composer',  'title' => 'Composers:'],
    ['id' => ARTIST_DJ,        'name' => 'dj',        'class' => 'artists_dj',         'role' => 'DJ',        'title' => 'DJ / Compiler:'],
    ['id' => ARTIST_MAIN,      'name' => 'main',      'class' => 'artists_main',       'role' => 'Artist',    'title' => empty($role['conductor']) ? 'Artists:' : 'Performers:'],
    ['id' => ARTIST_GUEST,     'name' => 'guest',     'class' => 'artists_guest',      'role' => 'Guest',     'title' => 'With:'],
    ['id' => ARTIST_CONDUCTOR, 'name' => 'conductor', 'class' => 'artists_conductors', 'role' => 'Conductor', 'title' => 'Conducted by:'],
    ['id' => ARTIST_REMIXER,   'name' => 'remixer',   'class' => 'artists_remix',      'role' => 'Remixer',   'title' => 'Remixed by:'],
    ['id' => ARTIST_PRODUCER,  'name' => 'producer',  'class' => 'artists_producer',   'role' => 'Producer',  'title' => 'Produced by:'],
    ['id' => ARTIST_ARRANGER,  'name' => 'arranger',  'class' => 'artists_arranger',   'role' => 'Arranger',  'title' => 'Arranged by:'],
];

echo $Twig->render('torrent/detail-header.twig', [
    'is_bookmarked' => (new Gazelle\User\Bookmark($Viewer))->isTorrentBookmarked($tgroup->id()),
    'revision_id'   => $RevisionID,
    'tgroup'        => $tgroup,
    'viewer'        => $Viewer,
]);
?>

<?php
if ($musicRelease) {
    $role = $tgroup->artistRole()->roleList();
?>
        <div class="box box_artists">
            <div class="head"><strong>Artists</strong>
<?php if ($Viewer->permitted('torrents_edit')) { ?>
            <span style="float: right;" class="edit_artists"><a onclick="ArtistManager(); return false;" href="#" class="brackets">Edit</a></span>
<?php } ?>
            </div>
            <ul class="stats nobullet" id="artist_list">
<?php
    foreach ($section as $s) {
        if ($role[$s['name']]) {
?>
                <li class="<?= $s['class'] ?>"><strong class="artists_label"><?= $s['title'] ?></strong></li>
<?php
            foreach ($role[$s['name']] as $artistInfo) {
                $artist = $artistMan->findByAliasId($artistInfo['aliasid']);
                if (is_null($artist)) {
                    continue;
                }
?>
                <li class="<?= $s['class'] ?>">
                    <?= $artist->link() ?>&lrm;
<?php           if ($Viewer->permitted('torrents_edit')) { ?>
                    (<span class="tooltip" title="Artist alias ID"><?= $artist->aliasId()
                        ?></span>)&nbsp;<span class="remove remove_artist"><a href="javascript:void(0);" onclick="ajax.get('torrents.php?action=delete_alias&amp;auth='+authkey+'&amp;groupid=<?=
                        $tgroupId ?>&amp;artistid=<?= $artist->id() ?>&amp;importance=<?=
                        $s['id'] ?>'); this.parentNode.parentNode.style.display = 'none';" class="brackets tooltip" title="Remove <?=
                        $s['role'] ?>">X</a></span>
<?php           } ?>
                </li>
<?php
            }
        }
    } /* foreach section */
?>
            </ul>
        </div>
<?php
    if ($Viewer->permitted('torrents_add_artist')) {
        usort($section, fn ($x, $y) => $x['id'] <=> $y['id']);
?>
        <div class="box box_addartists">
            <div class="head"><strong>Add artist</strong><span style="float: right;" class="additional_add_artist"><a onclick="AddArtistField(); return false;" href="#" class="brackets">+</a></span></div>
            <div class="body">
                <form class="add_form" name="artists" action="torrents.php" method="post">
                    <div id="AddArtists">
                        <input type="hidden" name="action" value="add_alias" />
                        <input type="hidden" name="auth" value="<?=$Viewer->auth() ?>" />
                        <input type="hidden" name="groupid" value="<?=$tgroupId?>" />
                        <input type="text" id="artist" name="aliasname[]" size="17"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                        <select name="importance[]">
<?php       foreach ($section as $s) { ?>
                            <option value="<?= $s['id'] ?>"><?= $s['role'] === 'Artist' ? 'Main' : $s['role'] ?></option>
<?php       } ?>
                        </select>
                    </div>
                    <input type="submit" value="Add" />
                </form>
            </div>
        </div>
<?php
    }
}

echo $Twig->render('tgroup/stats.twig', [
    'collage_list' => $collageMan->addToCollageDefault($tgroupId, $Viewer),
    'featured'     => (new Gazelle\Manager\FeaturedAlbum())->findById($tgroupId),
    'tag_undo'     => $Cache->get_value("deleted_tags_{$tgroupId}_{$Viewer->id()}"),
    'tgroup'       => $tgroup,
    'viewer'       => $Viewer,
    'vote'         => $vote,
]);
?>
    </div>
    <div class="main_column">
<?php
echo $Twig->render('collage/summary.twig', [
    'class'   => 'collage_rows',
    'object'  => 'album',
    'summary' => $collageMan->tgroupGeneralSummary($tgroupId),
]);

echo $Twig->render('collage/summary.twig', [
    'class'   => 'personal_rows',
    'object'  => 'album',
    'summary' => $collageMan->tgroupPersonalSummary($tgroupId),
]);
?>
        <table class="torrent_table details<?= $tgroup->isSnatched() ? ' snatched' : ''?> m_table" id="torrent_details">
            <tr class="colhead_dark">
                <td class="m_th_left" width="80%"><strong>Torrents</strong></td>
                <td><strong>Size</strong></td>
                <td class="m_th_right sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="m_th_right sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="m_th_right sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php
if (!$torrentList) {
    // if there are no live torrents left in this group, retrieve info about deleted masterings
    foreach ($removed as $info) {
        $mastering = implode('/', [$info['year'], $info['title'], $info['record_label'], $info['catalogue_number'], $info['media']]);
?>
            <tr class="releases_<?= $tgroup->releaseTypeName() ?> groupid_<?=$tgroupId?> edition group_torrent">
                <td colspan="5" class="edition_info"><strong>[<?= html_escape($mastering) ?>]</strong></td>
            </tr>
            <tr>
                <td><i>deleted</i></td>
                <td class="td_size nobr">&ndash;</td>
                <td class="td_snatched m_td_right">&ndash;</td>
                <td class="td_seeders m_td_right">&ndash;</td>
                <td class="td_leechers m_td_right">&ndash;</td>
            </tr>
<?php
    }
} else {
?>
<?php
    $prev           = false;
    $EditionID      = 0;
    $FirstUnknown   = false;

    foreach ($torrentList as $TorrentID) {
        $torrent = $torMan->findById($TorrentID);
        if (is_null($torrent)) {
            continue;
        }

        $current = $torrent->remasterTuple();
        if ($torrent->isRemasteredUnknown()) {
            $FirstUnknown = true;
        }
        if ($tgroup->categoryGrouped() && ($prev != $current || $FirstUnknown)) {
            $EditionID++;
?>
            <tr class="releases_<?= $tgroup->releaseTypeName() ?> groupid_<?=$tgroupId?> edition group_torrent">
                <td colspan="5" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$tgroupId?>, <?=
                    $EditionID?>, this, event);" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group." class="tooltip">&ndash;</a> <?= html_escape($torrent->edition()) ?></strong></td>
            </tr>
<?php
        }
        $prev = $current;

        $reportTotal = $torrent->reportTotal($Viewer);
        $reportList  = array_map(fn ($id) => $reportMan->findById($id), $torrent->reportIdList($Viewer));
    ?>
            <tr class="torrent_row releases_<?= $tgroup->releaseTypeName() ?> groupid_<?=$tgroupId?> edition_<?= $EditionID
                ?> group_torrent<?= $snatcher->showSnatch($torrent) ? ' snatched_torrent' : ''
                ?>" style="font-weight: normal;" id="torrent<?= $TorrentID ?>">
                <td class="td_info">
<?php
        echo $Twig->render('torrent/action-v2.twig', [
            'edit'    => true,
            'pl'      => true,
            'remove'  => true,
            'torrent' => $torrent,
            'viewer'  => $Viewer,
            'extra'   => [
                "<a href=\"ajax.php?action=torrent&amp;id=$TorrentID\" download=\"" . html_escape($tgroup->text())
                    . " [$TorrentID] [orpheus.network].json\" class=\"tooltip\" title=\"Download JSON\">JS</a>",
            ],
        ]);
?>
                    <a href="#" onclick="$('#torrent_<?=$TorrentID?>').gtoggle(); return false;">&#x25B6; <?= $torrent->label($Viewer) ?></a>
                </td>
                <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
            </tr>
            <tr class="releases_<?=$tgroup->releaseType() ?> groupid_<?=$tgroupId?> edition_<?=$EditionID?> torrentdetails pad <?php if (!isset($_GET['torrentid']) || $_GET['torrentid'] != $TorrentID) {
?>hidden<?php } ?>" id="torrent_<?=$TorrentID; ?>">
                <td colspan="5">
                    <div id="release_<?=$TorrentID?>" class="no_overflow">
                        <blockquote>
<?php
    if ($musicRelease) {
        $folderClash = $torMan->findAllByFoldername($torrent->path());
        $total = count($folderClash);
        if ($total > 1) {
?>
        <strong class="important">The folder of this upload clashes with <?= $total - 1 ?> other upload<?= plural($total - 1) ?>.<br />
        Downloading two or more uploads to the same folder may result in corrupted files.</strong>
        <ul class="nobullet">
<?php
            foreach ($folderClash as $tclash) {
                if ($tclash->id() === $TorrentID) {
                    continue;
                }
                $gclash = $tclash->group();
?>
            <li><a href="<?= $torrent->url() ?>"><?= $gclash->link() ?> (torrent id=<?= $tclash->id() ?>)</li>
<?php       } ?>
        </ul>
<?php
        }
    }
?>
                            Uploaded by <?= $torrent->uploader()->link() ?> <?=time_diff($torrent->created());?>
<?php
    if ($torrent->seederTotal() == 0) {
        // If the last time this was seeded was 50 years ago, most likely it has never been seeded, so don't bother
        // displaying "Last active: 2000+ years" as that's dumb
        if (is_null($torrent->lastActiveDate())) {
?>
                            <br />Last active: Never
<?php   } else { ?>
                            <br />Last active: <?= time_diff($torrent->lastActiveDate()); ?>
<?php
        }
        if ($torrent->isReseedRequestAllowed() || $Viewer->permitted('users_mod')
        ) {
?>
                            <br /><a href="torrents.php?action=reseed&amp;torrentid=<?=$TorrentID?>&amp;groupid=<?=
                                $tgroupId?>" class="brackets" onclick="return confirm('Are you sure you want to request a re-seed of this torrent?');">Request re-seed</a>
<?php
        }
    }
?>
                            <br /><br />If you download this, your ratio will become <?=
                                ratio_html($Viewer->uploadedSize(), $Viewer->downloadedSize() + $torrent->size());
                            ?>.
                        </blockquote>
                    </div>
                    <div class="linkbox">
<?php if ($Viewer->permitted('site_moderate_requests')) { ?>
                        <a href="torrents.php?action=masspm&amp;id=<?=$tgroupId?>&amp;torrentid=<?=$TorrentID?>" class="brackets">Mass PM snatchers</a>
<?php
    }
    if ($torrent->media() === 'CD' && $torrent->hasLog() && $torrent->hasLogDb()) {
?>
                        <a href="#" class="brackets" onclick="show_logs('<?= $TorrentID?>', <?=$torrent->hasLogDb()?>, '<?=
                            $torrent->logScore() ?>'); return false;">View log<?=
                            plural(count($torrent->ripLogIdList())) ?></a>
<?php
    }
    if ($Viewer->permitted('site_view_torrent_snatchlist')) {
?>
                        <a href="#" class="brackets tooltip" onclick="show_downloads('<?=$TorrentID?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">View downloaders</a>
                        <a href="#" class="brackets tooltip" onclick="show_snatches('<?=$TorrentID?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">View snatchers</a>
<?php } ?>
                        <a href="#" class="brackets tooltip" onclick="show_seeders('<?=$TorrentID?>', 0); return false;" title="View the list of peers that are currently seeding this torrent.">View seeders</a>
                        <a href="#" class="brackets" onclick="show_files('<?=$TorrentID?>'); return false;">View contents</a>
<?php if ($reportTotal) { ?>
                        <a href="#" class="brackets" onclick="show_reported('<?=$TorrentID?>'); return false;">View report information</a>
<?php } ?>
                    </div>
                    <div id="viewlog_<?=$TorrentID?>" class="hidden"></div>
                    <div id="peers_<?=$TorrentID?>" class="hidden"></div>
                    <div id="downloads_<?=$TorrentID?>" class="hidden"></div>
                    <div id="snatches_<?=$TorrentID?>" class="hidden"></div>
                    <div id="files_<?=$TorrentID?>" class="hidden">
                        <table class="filelist_table">
                            <tr class="colhead_dark">
                                <td>
                                    <div class="filelist_title" style="float: left;">File Names
<?php   if ($Viewer->permitted('users_mod')) { ?>
            <a href="torrents.php?action=regen_filelist&amp;torrentid=<?= $TorrentID ?>" class="brackets">Regenerate</a>
<?php   } ?>
                                    </div>
                                    <div class="filelist_path" style="float: right;"><?=
                                        $torrent->path() ? html_escape("/" . $torrent->path() . "/") : '' ?></div>
                                </td>
                                <td class="nobr" style="text-align: right">
                                    <strong>Size</strong>
                                </td>
                            </tr>
<?php   foreach ($torrent->fileList() as $file) { ?>
                            <tr><td><?= $file['name'] ?></td><td class="number_column nobr"><?= byte_format($file['size']) ?></td></tr>
<?php   } ?>
                        </table>
                    </div>
<?php if ($reportTotal) { ?>
<div id="reported_<?= $TorrentID ?>" class="hidden">
    <table class="reportinfo_table">
        <tr class="colhead_dark" style="font-weight: bold;">
            <td>This torrent has <?= $reportTotal ?> active report<?= plural($reportTotal) ?>:</td>
        </tr>
<?php
        foreach ($reportList as $report) {
?>
        <tr>
            <td>
<?php       if ($Viewer->permitted('admin_reports')) { ?>
                <?= $userMan->findById($report->reporterId())?->link() ?? 'System' ?> <a href="<?= $report->url() ?>">reported it</a>
<?php       } else { ?>
                Someone reported it
<?php       } ?>
                <?= time_diff($report->created(), 1) ?> for the reason <?= $report->reportType()->name() ?>
                <blockquote><?= Text::full_format($report->reason()) ?></blockquote>
            </td>
        </tr>
<?php   } ?>
    </table>
</div>
<?php
    }
    if (!empty($torrent->description())) {
?>
        <blockquote><?= Text::full_format($torrent->description(), cache: IMAGE_CACHE_ENABLED, bucket: CacheBucket::tgroup) ?></blockquote>
<?php } ?>
                </td>
            </tr>
<?php } // $torrentList ?>
        </table>
<?php
}

if (!$Viewer->disableRequests()) {
    echo $Twig->render('request/torrent.twig', [
        'list'            => $requestMan->findByTGroup($tgroup),
        'standard_bounty' => REQUEST_MIN,
        'viewer'          => $Viewer,
    ]);
}

echo $Twig->render('tgroup/similar.twig', [
    'similar' => $tgMan->similarVote($tgroup),
]);
?>
        <div class="box torrent_description">
            <div class="head"><a href="#">&uarr;</a>&nbsp;<strong><?= $tgroup->releaseTypeName() ? $tgroup->releaseTypeName() . ' info' : 'Info' ?></strong></div>
            <div class="body">
<?php if (!empty($tgroup->description())) { ?>
                <?= Text::full_format($tgroup->description(), cache: IMAGE_CACHE_ENABLED, bucket: CacheBucket::tgroup) ?>
<?php } else { ?>
                There is no information on this torrent.
<?php } ?>
            </div>
        </div>
<?= $Twig->render('comment/thread.twig', [
    'action'    => 'take_post',
    'id'        => $tgroupId,
    'comment'   => $commentPage,
    'name'      => 'pageid',
    'paginator' => $paginator,
    'subbed'    => $isSubscribed,
    'textarea'  => (new Gazelle\Util\Textarea('quickpost', ''))->setPreviewManual(true),
    'url'       => $_SERVER['REQUEST_URI'],
    'url_stem'  => 'comments.php?page=torrents',
    'userMan'   => $userMan,
    'viewer'    => $Viewer,
]) ?>
    </div>
</div>
<?php
View::show_footer();
