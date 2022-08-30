<?php
header('Access-Control-Allow-Origin: *');

$tgroup = (new Gazelle\Manager\TGroup)->setViewer($Viewer)->findById((int)($_GET['id'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}
$GroupID = $tgroup->id();
$RevisionID = (int)($_GET['revisionid'] ?? 0);

// Comments (must be loaded before View::show_header so that subscriptions and quote notifications are handled properly)
$commentPage = new Gazelle\Comment\Torrent($GroupID);
if (isset($_GET['postid'])) {
    $commentPage->setPostId((int)$_GET['postid']);
} elseif (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$artistMan  = new Gazelle\Manager\Artist;
$collageMan = new Gazelle\Manager\Collage;
$userMan    = new Gazelle\Manager\User;
$snatcher   = new Gazelle\User\Snatch($Viewer);
$torMan     = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$imgProxy   = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);
$vote       = (new Gazelle\User\Vote($Viewer))->setGroupId($GroupID);

$isSubscribed = (new Gazelle\Subscription($Viewer))->isSubscribedComments('torrents', $GroupID);
$releaseTypes = (new Gazelle\ReleaseType)->list();
$reportTypes  = (new Gazelle\Manager\ReportV2)->types();
$urlStem      = (new Gazelle\User\Stylesheet($Viewer))->imagePath();

$categoryId   = $tgroup->categoryId();
$musicRelease = $tgroup->categoryName() == 'Music';
$tagList      = $tgroup->tagList();
$year         = $tgroup->year();
$title        = $tgroup->displayNameText();
$coverArt     = $tgroup->coverArt($userMan);
$torrentList  = $tgroup->torrentIdList();
$removed      = $torrentList ? [] : $tgroup->deletedMasteringList();

if (!$musicRelease) {
    $rankList = [];
} else {
    $decade    = $year - ($year % 10);
    $decadeEnd = $decade + 9;
    $advanced  = $Viewer->permitted('site_advanced_top10');
    $rankList = [
        'overall' => [
            'rank' => $vote->rankOverall(),
            'title' => '<a href="top10.php?type=votes">overall</a>',
        ],
        'decade' => [
            'rank' => $vote->rankDecade($year),
            'title' => $advanced
                ? "for the <a href=\"top10.php?advanced=1&amp;type=votes&amp;year1=$decade&amp;year2=$decadeEnd\">{$decade}s</a>"
                : "for the {$decade}s",
        ],
        'year' => [
            'rank' => $vote->rankYear($year),
            'title' => $advanced
                ? "for <a href=\"top10.php?advanced=1&amp;type=votes&amp;year1=$year\">$year</a>"
                : "for $year",
        ],
    ];
}

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

View::show_header(display_str($title), ['js' => 'browse,comments,torrent,bbcode,cover_art,subscriptions,voting']);
?>
<div class="thin">
    <div class="header">
        <h2><?= $tgroup->displayNameHtml() ?></h2>
        <div class="linkbox">
<?php if ($Viewer->permitted('site_edit_wiki')) { ?>
            <a href="<?= $tgroup->url() ?>&amp;action=editgroup" class="brackets">Edit description</a>
<?php } ?>
            <a href="<?= $tgroup->url() ?>&amp;action=editrequest" class="brackets">Request an Edit</a>
<?php if ($RevisionID && $Viewer->permitted('site_edit_wiki')) { ?>
            <a href="<?= $tgroup->url() ?>&amp;action=revert&amp;revisionid=<?=$RevisionID ?>&amp;auth=<?=$Viewer->auth()?>" class="brackets">Revert to this revision</a>
<?php
}
if ((new Gazelle\User\Bookmark($Viewer))->isTorrentBookmarked($GroupID)) {
?>
            <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="remove_bookmark brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
<?php } else { ?>
            <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="add_bookmark brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
<?php } ?>
            <a href="#" id="subscribelink_torrents<?=$GroupID?>" class="brackets" onclick="SubscribeComments('torrents', <?=$GroupID?>); return false;"><?=
                $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>
<?php if ($musicRelease) { ?>
            <a href="upload.php?groupid=<?=$GroupID?>" class="brackets">Add format</a>
<?php
}
if ($Viewer->permitted('site_submit_requests')) {
?>
            <a href="requests.php?action=new&amp;groupid=<?=$GroupID?>" class="brackets">Request format</a>
<?php } ?>
            <a href="<?= $tgroup->url() ?>&amp;action=history" class="brackets">View history</a>
            <a href="<?= $tgroup->url() ?>&amp;action=grouplog" class="brackets">View log</a>
        </div>
    </div>
    <div class="sidebar">
        <div class="box box_image box_image_albumart box_albumart"><!-- .box_albumart deprecated -->
            <div class="head">
                <strong><?= count($coverArt) > 0 ? 'Covers (' . (count($coverArt) + 1) . ')' : 'Cover' ?></strong>
<?php if (!$coverArt) { ?>
                <span>
                    <a class="brackets show_all_covers" href="#">Hide</a>
                </span>
<?php
} elseif ($Viewer->option('ShowExtraCovers')) {
    for ($Index = 0, $last = count($coverArt); $Index <= $last; $Index++) {
?>
                <span id="cover_controls_<?=($Index)?>"<?=($Index > 0 ? ' style="display: none;"' : '')?>>
<?php   if ($Index == count($coverArt)) { ?>
                        <a class="brackets prev_cover" data-gazelle-prev-cover="<?=($Index - 1)?>" href="#">Prev</a>
                        <a class="brackets show_all_covers" href="#">Show all</a>
                        <span class="brackets next_cover">Next</span>
<?php   } elseif ($Index > 0) { ?>
                        <a class="brackets prev_cover" data-gazelle-prev-cover="<?=($Index - 1)?>" href="#">Prev</a>
                        <a class="brackets show_all_covers" href="#">Show all</a>
                        <a class="brackets next_cover" data-gazelle-next-cover="<?=($Index + 1)?>" href="#">Next</a>
<?php   } elseif ($Index == 0 && count($coverArt) > 0) { ?>
                        <span class="brackets prev_cover">Prev</span>
                        <a class="brackets show_all_covers" href="#">Show all</a>
                        <a class="brackets next_cover" data-gazelle-next-cover="<?=($Index + 1)?>" href="#">Next</a>
<?php   } ?>
                </span>
<?php
    }
}
$Index = 0;
?>
            </div>
<div id="covers">
<div id="cover_div_<?=$Index?>" class="pad">
<?php
if ($tgroup->image() != '') {
    $image = $imgProxy->process($tgroup->image());
?>
            <p align="center"><img width="100%" src="<?= $image ?>" alt="<?= $title ?>" onclick="lightbox.init('<?= $image ?>', 220);" /></p>
<?php } else { ?>
            <p align="center"><img width="100%" src="<?=STATIC_SERVER?>/common/noartwork/<?= $tgroup->categoryIcon() ?>" class="brackets tooltip" title="<?= $tgroup->categoryName() ?>" height="220" border="0" /></p>
<?php
}
$Index++;
?>
</div>
<?php foreach ($coverArt as $c) { ?>
                    <div id="cover_div_<?=$Index?>" class="pad"<?= $Viewer->option('ShowExtraCovers') ? '' : ' style="display: none;"' ?>>
                <p align="center">
<?php
    $image = $imgProxy->process($c['Image']);
    if ($Viewer->option('ShowExtraCovers')) {
        $Src = 'src="' . $image . '"';
    } else {
        $Src = 'src="" data-gazelle-temp-src="' . $image . '"';
    }
?>
                    <img id="cover_<?=$Index?>" width="100%" <?=$Src?> alt="<?=$c['Summary']?>" onclick="lightbox.init('<?= $image ?>', 220);" />
                </p>
                <ul class="stats nobullet">
                    <li><?= $c['Summary'] ?>
<?php if ($Viewer->permitted('users_mod')) { ?>
                        added by <?= $c['userlink'] ?>
<?php } ?>
                        <span class="remove remove_cover_art"><a href="#" onclick="if (confirm('Do not delete valid alternative cover art. Are you sure you want to delete this cover art?') == true) { ajax.get('ajax.php?action=torrent_remove_cover_art&amp;auth=<?=
                            $Viewer->auth() ?>&amp;id=<?= $c['ID'] ?>&amp;groupid=<?= $GroupID ?>'); this.parentNode.parentNode.parentNode.style.display = 'none'; this.parentNode.parentNode.parentNode.previousElementSibling.style.display = 'none'; } else { return false; }" class="brackets tooltip" title="Remove image">X</a></span>
                    </li>
                </ul>
            </div>
<?php
    $Index++;
}
?>
</div>

<?php if ($Viewer->permitted('site_edit_wiki') && $tgroup->image() != '') { ?>
        <div id="add_cover_div">
            <div style="padding: 10px;">
                <span style="float: right;" class="additional_add_artists">
                    <a onclick="addCoverField(); return false;" href="#" class="brackets">Add alternate cover</a>
                </span>
            </div>
            <div class="body">
                <form class="add_form" name="covers" id="add_covers_form" action="torrents.php" method="post">
                    <div id="add_cover">
                        <input type="hidden" name="action" value="add_cover_art" />
                        <input type="hidden" name="auth" value="<?=$Viewer->auth() ?>" />
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                    </div>
                </form>
            </div>
        </div>
<?php } ?>
    </div>
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
                $artist = $artistMan->findById($artistInfo['id']);
                if (is_null($artist)) {
                    continue;
                }
?>
                <li class="<?= $s['class'] ?>">
                    <?= $artist->link() ?>&lrm;
<?php           if ($Viewer->permitted('torrents_edit')) { ?>
                    (<span class="tooltip" title="Artist alias ID"><?= $artist->getAlias($artist->name())
                        ?></span>)&nbsp;<span class="remove remove_artist"><a href="javascript:void(0);" onclick="ajax.get('torrents.php?action=delete_alias&amp;auth='+authkey+'&amp;groupid=<?=
                        $GroupID ?>&amp;artistid=<?= $artist->id() ?>&amp;importance=<?=
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
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
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

if ($musicRelease) {
    if ($Viewer->permitted('site_collages_create')) {
?>
        <div class="box box_info box_addcollage_torrent">
            <div class="head"><strong>Add to collage</strong></div>
                <div class="box pad">
                    <form action="collages.php" method="post">
<?php
        $collageList = $collageMan->addToCollageDefault($Viewer->id(), $GroupID);
        if (empty($collageList)) {
?>
                    <div>Search for a collage name:</div>
<?php   } else { ?>
                    <select style="max-width: 100%" name="collage_combo">
                        <option value="0">Choose recent...</option>
<?php       foreach ($collageList as $id => $collageName) { ?>
                        <option value="<?= $id ?>"><?= display_str($collageName) ?></option>
<?php       } ?>
                    </select>
                    <div>or search for collage name:</div>
<?php   } ?>
                    <input type="text" id="collage_ref" name="collage_ref" data-gazelle-autocomplete="true" size="25" />
                    <input type="hidden" name="action" value="add_torrent" />
                    <input type="hidden" name="groupid" value="<?= $GroupID ?>" />
                    <input type="hidden" name="userid" value="<?= $Viewer->id() ?>" />
                    <input type="hidden" name="auth" value="<?= $Viewer->auth()  ?>" />
                    <br /><br /><input type="submit" value="Add" />
                    </form>
            </div>
        </div>
<?php
    }

    $li = [];
    foreach ($rankList as $key => $info) {
        $rank = $info['rank'];
        if (!$rank) {
            continue;
        }
        if ($rank <= 10) {
            $class = ' class="vr_top_10"';
        } elseif ($rank <= 25) {
            $class = ' class="vr_top_25"';
        } elseif ($rank <= 50) {
            $class = ' class="vr_top_50"';
        } else {
            $class = '';
        }
        $li[] = sprintf('<li id="vote_rank_%s"%s>No. %d %s</li>', $key, $class, $rank, $info['title']);
    }
    if ($li) {
?>
        <div class="box" id="votes_ranks">
            <div class="head"><strong><?= SITE_NAME ?> Favorites</strong></div>
            <div class="vote_charts body">
                <ul class="stats nobullet" id="vote_rankings">
<?php   foreach ($li as $item) { ?>
                    <?= $item ?>
<?php   } ?>
                </ul>
            </div>
        </div>
<?php
    }
}

echo $Twig->render('tgroup/stats.twig', [
    'stats' => $tgroup->stats(),
]);

echo $Twig->render('vote/box.twig', [
    'group_id' => $GroupID,
    'percent'  => $vote->total() ? $vote->totalUp() / $vote->total() * 100 : '&mdash;',
    'total'    => $vote->total(),
    'up'       => $vote->totalUp(),
    'down'     => $vote->totalDown(),
    'score'    => $vote->score($vote->total(), $vote->totalUp()) * 100,
    'vote'     => $vote->vote(),
    'viewer'   => $Viewer,
]);

$DeletedTag = $Cache->get_value("deleted_tags_$GroupID" . '_' . $Viewer->id());
?>
        <div class="box box_tags">
            <div class="head">
                <strong>Tags</strong>
<?php if ($DeletedTag) { ?>
                    <form style="display: none;" id="undo_tag_delete_form" name="tags" action="ajax.php" method="post">
                        <input type="hidden" name="action" value="add_tag" />
                        <input type="hidden" name="auth" value="<?=$Viewer->auth() ?>" />
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                        <input type="hidden" name="tagname" value="<?=$DeletedTag?>" />
                        <input type="hidden" name="undo" value="true" />
                    </form>
                    <a class="brackets" href="#" onclick="$('#undo_tag_delete_form').raw().submit(); return false;">Undo delete</a>

<?php } ?>
            </div>
<?php if (empty($tagList)) { ?>
            <ul><li>There are no tags to display.</li></ul>
<?php } else { ?>
            <ul class="stats nobullet">
<?php   foreach ($tagList as $tag) { ?>
                <li>
                    <a href="torrents.php?taglist=<?=$tag['name']?>" style="float: left; display: block;"><?=display_str($tag['name'])?></a>
                    <div style="float: right; display: block; letter-spacing: -1px;" class="edit_tags_votes">
                    <a href="torrents.php?action=vote_tag&amp;way=up&amp;groupid=<?=$GroupID?>&amp;tagid=<?= $tag['id'] ?>&amp;auth=<?=$Viewer->auth() ?>" title="Vote this tag up" class="tooltip vote_tag_up">&#x25b2;</a>
                    <?= $tag['score'] ?>
                    <a href="torrents.php?action=vote_tag&amp;way=down&amp;groupid=<?=$GroupID?>&amp;tagid=<?= $tag['id'] ?>&amp;auth=<?=$Viewer->auth() ?>" title="Vote this tag down" class="tooltip vote_tag_down">&#x25bc;</a>
<?php       if ($Viewer->permitted('users_warn')) { ?>
                    <a href="user.php?id=<?= $tag['userId'] ?>" title="View the profile of the user that added this tag" class="brackets tooltip view_tag_user">U</a>
<?php
            }
            if (!$Viewer->disableTagging() && $Viewer->permitted('site_delete_tag')) {
?>
                    <span class="remove remove_tag"><a href="ajax.php?action=delete_tag&amp;groupid=<?=$GroupID?>&amp;tagid=<?= $tag['id'] ?>&amp;auth=<?=$Viewer->auth() ?>" class="brackets tooltip" title="Remove tag">X</a></span>
<?php       } ?>
                    </div>
                    <br style="clear: both;" />
                </li>
<?php   } /* foreach */ ?>
            </ul>
<?php } ?>
        </div>

<?php if (!$Viewer->disableTagging()) { ?>
        <div class="box box_addtag">
            <div class="head"><strong>Add tag</strong></div>
            <div class="body">
                <form class="add_form" name="tags" action="ajax.php" method="post">
                    <input type="hidden" name="action" value="add_tag" />
                    <input type="hidden" name="auth" value="<?=$Viewer->auth() ?>" />
                    <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                    <input type="text" name="tagname" id="tagname" size="20"<?=
                        $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                    <input type="submit" value="+" />
                </form>
                <br /><br />
                <strong><a href="rules.php?p=tag" class="brackets">View tagging rules</a></strong>
            </div>
        </div>
<?php } ?>
    </div>

    <div class="main_column">
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
            <tr class="releases_<?= $tgroup->releaseTypeName() ?> groupid_<?=$GroupID?> edition group_torrent">
                <td colspan="5" class="edition_info"><strong>[<?= $mastering ?>]</strong></td>
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
            <tr class="releases_<?= $tgroup->releaseTypeName() ?> groupid_<?=$GroupID?> edition group_torrent">
                <td colspan="5" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$GroupID?>, <?=
                    $EditionID?>, this, event);" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group." class="tooltip">&ndash;</a> <?= $torrent->edition() ?></strong></td>
            </tr>
<?php
        }
        $prev = $current;

        $reportTotal = $torrent->reportTotal();
        $reportList  = $torMan->reportList($Viewer, $TorrentID);
    ?>
            <tr class="torrent_row releases_<?= $tgroup->releaseTypeName() ?> groupid_<?=$GroupID?> edition_<?= $EditionID
                ?> group_torrent<?= $snatcher->showSnatch($TorrentID) ? ' snatched_torrent' : ''
                ?>" style="font-weight: normal;" id="torrent<?= $TorrentID ?>">
                <td class="td_info">
<?php
        echo $Twig->render('torrent/action-v2.twig', [
            'can_fl' => $Viewer->canSpendFLToken($torrent),
            'key'    => $Viewer->announceKey(),
            't'      => $torrent,
            'edit'   => $Viewer->permitted('torrents_edit')
                || (($torrent->uploaderId() == $Viewer->id() && !$Viewer->disableWiki()) && !$torrent->isRemasteredUnknown()),
            'remove' => $Viewer->permitted('torrents_delete') || $torrent->uploaderId() == $Viewer->id(),
            'pl'     => true,
            'extra'  => [
                "<a href=\"ajax.php?action=torrent&amp;id=$TorrentID\" download=\"" . $tgroup->displayNameText()
                    . " [$TorrentID] [orpheus.network].json\" class=\"tooltip\" title=\"Download JSON\">JS</a>",
            ],
        ]);
?>
                    <a href="#" onclick="$('#torrent_<?=$TorrentID?>').gtoggle(); return false;">&#x25B6; <?= $torrent->label() ?></a>
                </td>
                <td class="td_size number_column nobr"><?=Format::get_size($torrent->size())?></td>
                <td class="td_snatched m_td_right number_column"><?=number_format($torrent->snatchTotal())?></td>
                <td class="td_seeders m_td_right number_column <?= $torrent->seederTotal() == 0 ? ' r00' : '' ?>"><?=number_format($torrent->seederTotal())?></td>
                <td class="td_leechers m_td_right number_column"><?=number_format($torrent->leecherTotal())?></td>
            </tr>
            <tr class="releases_<?=$tgroup->releaseType() ?> groupid_<?=$GroupID?> edition_<?=$EditionID?> torrentdetails pad <?php if (!isset($_GET['torrentid']) || $_GET['torrentid'] != $TorrentID) { ?>hidden<?php } ?>" id="torrent_<?=$TorrentID; ?>">
                <td colspan="5">
                    <div id="release_<?=$TorrentID?>" class="no_overflow">
                        <blockquote>
<?php
    if ($musicRelease) {
        $folderClash = $torMan->findAllByFoldername($torrent->path());
        $total = count($folderClash);
        if ($total > 1) {
?>
        <strong class="important">The folder of this upload clashes with <?= $total-1 ?> other upload<?= plural($total-1) ?>.<br />
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
                            Uploaded by <?= $torrent->uploader()->link() ?> <?=time_diff($torrent->uploadDate());?>
<?php
    if ($torrent->seederTotal() == 0) {
        $lastActive = $torrent->lastActiveEpoch();
        $delta = time() - $lastActive;
        // If the last time this was seeded was 50 years ago, most likely it has never been seeded, so don't bother
        // displaying "Last active: 2000+ years" as that's dumb
        if ($delta > 1576800000) {
?>
                            <br />Last active: Never
<?php   } elseif ($delta >= 1209600) { // If last active is >= 2 weeks ago, output in bold ?>
                            <br /><strong>Last active: <?=time_diff($torrent->lastActiveDate()); ?></strong>
<?php   } else { ?>
                            <br />Last active: <?= time_diff($torrent->lastActiveDate()); ?>
<?php
        }
        if (($delta >= 345678 && $torrent->lastReseedRequest() && time() - strtotime($torrent->lastReseedRequest()) >= 864000)
            || $Viewer->permitted('users_mod')
        ) {
?>
                            <br /><a href="torrents.php?action=reseed&amp;torrentid=<?=$TorrentID?>&amp;groupid=<?=
                                $GroupID?>" class="brackets" onclick="return confirm('Are you sure you want to request a re-seed of this torrent?');">Request re-seed</a>
<?php
        }
    }
?>
                            <br /><br />If you download this, your ratio will become <?=
                                Format::get_ratio_html($Viewer->uploadedSize(), $Viewer->downloadedSize() + $torrent->size());
                            ?>.
                        </blockquote>
                    </div>
                    <div class="linkbox">
<?php if ($Viewer->permitted('site_moderate_requests')) { ?>
                        <a href="torrents.php?action=masspm&amp;id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>" class="brackets">Mass PM snatchers</a>
<?php
    }
    if ($torrent->media() === 'CD' && $torrent->hasLog() && $torrent->hasLogDb()) {
?>
                        <a href="#" class="brackets" onclick="show_logs('<?= $TorrentID?>', <?=$torrent->hasLogDb()?>, '<?=
                            $torrent->logScore() ?>'); return false;">View log<?=
                            plural(count($torrent->riplogIdList())) ?></a>
<?php
    }
    if ($Viewer->permitted('site_view_torrent_snatchlist')) {
?>
                        <a href="#" class="brackets tooltip" onclick="show_downloads('<?=$TorrentID?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">View downloaders</a>
                        <a href="#" class="brackets tooltip" onclick="show_snatches('<?=$TorrentID?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">View snatchers</a>
<?php } ?>
                        <a href="#" class="brackets tooltip" onclick="show_peers('<?=$TorrentID?>', 0); return false;" title="View the list of peers that are currently seeding this torrent.">View seeders</a>
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
                                        $torrent->path() ? ("/" . $torrent->path() . "/") : '' ?></div>
                                </td>
                                <td class="nobr" style="text-align: right">
                                    <strong>Size</strong>
                                </td>
                            </tr>
<?php
        $file = $torrent->fileList();
        foreach ($file as $fileData) {
            $info = $torMan->splitMetaFilename($fileData);
?>
                            <tr><td><?= $info['name'] ?></td><td class="number_column nobr"><?=
                                Format::get_size($info['size']) ?></td></tr>
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
        foreach ($reportList as $Report) {
            $reporter = $userMan->findById($Report['ReporterID']);
            if (isset($reportTypes[$categoryId][$Report['Type']])) {
                $ReportType = $reportTypes[$categoryId][$Report['Type']];
            } elseif (isset($reportTypes['master'][$Report['Type']])) {
                $ReportType = $reportTypes['master'][$Report['Type']];
            } else {
                //There was a type but it wasn't an option!
                $ReportType = $reportTypes['master']['other'];
            }
?>
        <tr>
            <td>
<?php       if ($Viewer->permitted('admin_reports')) { ?>
                <?= $reporter->link() ?> <a href="reportsv2.php?view=report&amp;id=<?= $Report['ID'] ?>">reported it</a>
<?php       } else { ?>
                Someone reported it
<?php       } ?>
                <?= time_diff($Report['ReportedTime'], 2) ?> for the reason <?= $ReportType['title'] ?>
                <blockquote><?= Text::full_format($Report['UserComment']) ?></blockquote>
            </td>
        </tr>
<?php   } ?>
    </table>
</div>
<?php
    }
    if (!empty($torrent->description())) {
?>
        <blockquote><?= Text::full_format($torrent->description()) ?></blockquote>
<?php } ?>
                </td>
            </tr>
<?php } // $torrentList ?>
        </table>
<?php
}

$Requests = get_group_requests($GroupID);
if ($Viewer->disableRequests() && count($Requests) > 0) {
    $i = 0;
?>
        <div class="box">
            <div class="head">
                <span style="font-weight: bold;">Requests (<?=number_format(count($Requests))?>)</span>
                <a href="#" style="float: right;" onclick="$('#requests').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Show</a>
            </div>
            <table id="requests" class="request_table hidden">
                <tr class="colhead">
                    <td>Format / Bitrate / Media</td>
                    <td>Votes</td>
                    <td>Bounty</td>
                </tr>
<?php
    foreach ($Requests as $Request) {
        $RequestVotes = Requests::get_votes_array($Request['ID']);

        if ($Request['BitrateList'] != '') {
            $BitrateString = implode(', ', explode('|', $Request['BitrateList']));
            $FormatString = implode(', ', explode('|', $Request['FormatList']));
            $MediaString = implode(', ', explode('|', $Request['MediaList']));
            if ($Request['LogCue']) {
                $FormatString .= ' - '.$Request['LogCue'];
            }
        } else {
            $BitrateString = 'Unknown';
            $FormatString = 'Unknown';
            $MediaString = 'Unknown';
        }
?>
                <tr class="requestrows <?=(++$i % 2 ? 'rowa' : 'rowb')?>">
                    <td><a href="requests.php?action=view&amp;id=<?=$Request['ID']?>"><?=$FormatString?> / <?=$BitrateString?> / <?=$MediaString?></a></td>
                    <td>
                        <span id="vote_count_<?=$Request['ID']?>"><?=count($RequestVotes['Voters'])?></span>
<?php       if ($Viewer->permitted('site_album_votes')) { ?>
                        &nbsp;&nbsp; <a href="javascript:Vote(0, <?=$Request['ID']?>)" class="brackets">+</a>
<?php       } ?>
                    </td>
                    <td><?=Format::get_size($RequestVotes['TotalBounty'])?></td>
                </tr>
<?php } ?>
            </table>
        </div>
<?php
}

echo $Twig->render('collage/summary.twig', [
    'class'   => 'collage_rows',
    'object'  => 'album',
    'summary' => $collageMan->tgroupGeneralSummary($GroupID),
]);

echo $Twig->render('collage/summary.twig', [
    'class'   => 'personal_rows',
    'object'  => 'album',
    'summary' => $collageMan->tgroupPersonalSummary($GroupID),
]);

// Matched Votes
$similar = $vote->similarVote();
if (!empty($similar)) {
?>
        <table class="vote_matches_table" id="vote_matches">
            <tr class="colhead">
                <td><a href="#">&uarr;</a>&nbsp;People who like this album also liked... <a href="#" onclick="$('.votes_rows').gtoggle(); return false;">(Show)</a></td>
            </tr>
<?php
    $Groups = Torrents::get_groups($similar, true, true, false);
    $i = 0;
    foreach ($similar as $MatchGroupID) {
        if (!isset($Groups[$MatchGroupID])) {
            continue;
        }
        $MatchGroup = $Groups[$MatchGroupID];
        $i++;
        $Str = Artists::display_artists($MatchGroup['ExtendedArtists']).'<a href="torrents.php?id='.$MatchGroupID.'">'.$MatchGroup['Name'].'</a>';
?>
            <tr class="votes_rows hidden <?=($i & 1) ? 'rowb' : 'rowa'?>">
                <td><span class="like_ranks"><?=$i?>.</span> <?=$Str?></td>
            </tr>
<?php } ?>
        </table>
<?php
}

?>
        <div class="box torrent_description">
            <div class="head"><a href="#">&uarr;</a>&nbsp;<strong><?= $tgroup->releaseTypeName() ? $tgroup->releaseTypeName() . ' info' : 'Info' ?></strong></div>
            <div class="body">
<?php if (!empty($tgroup->description())) { ?>
                <?= Text::full_format($tgroup->description()) ?>
<?php } else { ?>
                There is no information on this torrent.
<?php } ?>
            </div>
        </div>
<?php

echo $Twig->render('comment/thread.twig', [
    'url'       => $_SERVER['REQUEST_URI'],
    'page'      => $commentPage->page(),
    'thread'    => $commentPage->thread(),
    'unread'    => $commentPage->lastRead(),
    'paginator' => $paginator,
    'userMan'   => $userMan,
    'viewer'    => $Viewer,
]);

echo $Twig->render('reply.twig', [
    'action'   => 'take_post',
    'auth'     => $Viewer->auth() ,
    'avatar'   => $userMan->avatarMarkup($Viewer, $Viewer),
    'id'       => $GroupID,
    'name'     => 'pageid',
    'subbed'   => $isSubscribed,
    'textarea' => (new Gazelle\Util\Textarea('quickpost', ''))->setPreviewManual(true),
    'url'      => 'comments.php?page=torrents',
    'user'     => $Viewer,
]);
?>
    </div>
</div>
<?php
View::show_footer();
