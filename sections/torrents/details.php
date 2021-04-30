<?php
function compare($X, $Y) {
    return($Y['score'] - $X['score']);
}
header('Access-Control-Allow-Origin: *');

$GroupID = (int)$_GET['id'];
if (!empty($_GET['revisionid']) && is_number($_GET['revisionid'])) {
    $RevisionID = $_GET['revisionid'];
} else {
    $RevisionID = 0;
}

$TorrentCache = get_group_info($GroupID, $RevisionID);
$TorrentDetails = $TorrentCache[0];
$TorrentList = $TorrentCache[1];

// Group details
[$WikiBody, $WikiImage, $GroupID, $GroupName, $GroupYear,
    $GroupRecordLabel, $GroupCatalogueNumber, $ReleaseType, $GroupCategoryID,
    $GroupTime, $GroupVanityHouse, $TorrentTags, $TorrentTagIDs, $TorrentTagUserIDs,
    $TagPositiveVotes, $TagNegativeVotes, $GroupFlags] = array_values($TorrentDetails);

$DisplayName = "<span dir=\"ltr\">$GroupName</span>";
$AltName = $GroupName; // Goes in the alt text of the image
$Title = $GroupName; // goes in <title>
$WikiBody = Text::full_format($WikiBody);

$Artists = Artists::get_artist($GroupID);

if ($Artists) {
    $DisplayName = Artists::display_artists($Artists, true) . "$DisplayName";
    $AltName = display_str(Artists::display_artists($Artists, false)) . $AltName;
    $Title = $AltName;
}

if ($GroupYear > 0) {
    $DisplayName .= " [$GroupYear]";
    $AltName .= " [$GroupYear]";
    $Title .= " [$GroupYear]";
}
if ($GroupVanityHouse) {
    $DisplayName .= ' [Vanity House]';
    $AltName .= ' [Vanity House]';
}
$releaseTypes = (new Gazelle\ReleaseType)->list();
if ($GroupCategoryID == 1) {
    $name = $releaseTypes[$ReleaseType];
    $DisplayName .= " [$name] ";
    $AltName .= " [$name] ";
}

$Tags = [];
if ($TorrentTags != '') {
    $TorrentTags = explode('|', $TorrentTags);
    $TorrentTagIDs = explode('|', $TorrentTagIDs);
    $TorrentTagUserIDs = explode('|', $TorrentTagUserIDs);
    $TagPositiveVotes = explode('|', $TagPositiveVotes);
    $TagNegativeVotes = explode('|', $TagNegativeVotes);

    foreach ($TorrentTags as $TagKey => $TagName) {
        $Tags[$TagKey]['name'] = $TagName;
        $Tags[$TagKey]['score'] = ($TagPositiveVotes[$TagKey] - $TagNegativeVotes[$TagKey]);
        $Tags[$TagKey]['id'] = $TorrentTagIDs[$TagKey];
        $Tags[$TagKey]['userid'] = $TorrentTagUserIDs[$TagKey];
    }
    uasort($Tags, 'compare');
}

$CoverArt = $Cache->get_value("torrents_cover_art_$GroupID");
if (!$CoverArt) {
    $DB->prepared_query('
        SELECT ID, Image, Summary, UserID, Time
        FROM cover_art
        WHERE GroupID = ?
        ORDER BY Time ASC', $GroupID);
    $CoverArt = $DB->to_array();
    if ($DB->has_results()) {
        $Cache->cache_value("torrents_cover_art_$GroupID", $CoverArt, 0);
    }
}

// Comments (must be loaded before View::show_header so that subscriptions and quote notifications are handled properly)
$user = new Gazelle\User($LoggedUser['ID']);
$commentPage = new Gazelle\Comment\Torrent($GroupID);
if (isset($_GET['postid'])) {
    $commentPage->setPostId((int)$_GET['postid']);
} elseif (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$commentPage->load()->handleSubscription($user);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total());

$collageMan = new Gazelle\Manager\Collage;
$isSubscribed = (new Gazelle\Manager\Subscription($LoggedUser['ID']))->isSubscribedComments('torrents', $GroupID);

View::show_header($Title, 'browse,comments,torrent,bbcode,cover_art,subscriptions,voting');
?>
<div class="thin">
    <div class="header">
        <h2><?=$DisplayName?></h2>
        <div class="linkbox">
<?php if (check_perms('site_edit_wiki')) { ?>
            <a href="torrents.php?action=editgroup&amp;groupid=<?=$GroupID?>" class="brackets">Edit description</a>
<?php } ?>
            <a href="torrents.php?action=editrequest&amp;groupid=<?=$GroupID?>" class="brackets">Request an Edit</a>
<?php if ($RevisionID && check_perms('site_edit_wiki')) { ?>
            <a href="torrents.php?action=revert&amp;groupid=<?=$GroupID ?>&amp;revisionid=<?=$RevisionID ?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Revert to this revision</a>
<?php
}
$bookmark = new \Gazelle\Bookmark;
if ($bookmark->isTorrentBookmarked($LoggedUser['ID'], $GroupID)) {
?>
            <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="remove_bookmark brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
<?php } else { ?>
            <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="add_bookmark brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
<?php } ?>
            <a href="#" id="subscribelink_torrents<?=$GroupID?>" class="brackets" onclick="SubscribeComments('torrents', <?=$GroupID?>); return false;"><?=
                $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>
<?php if ($Categories[$GroupCategoryID-1] == 'Music') { ?>
            <a href="upload.php?groupid=<?=$GroupID?>" class="brackets">Add format</a>
<?php
}
if (check_perms('site_submit_requests')) {
?>
            <a href="requests.php?action=new&amp;groupid=<?=$GroupID?>" class="brackets">Request format</a>
<?php } ?>
            <a href="torrents.php?action=history&amp;groupid=<?=$GroupID?>" class="brackets">View history</a>
            <a href="torrents.php?action=grouplog&amp;groupid=<?=$GroupID?>" class="brackets">View log</a>
        </div>
    </div>
    <div class="sidebar">
        <div class="box box_image box_image_albumart box_albumart"><!-- .box_albumart deprecated -->
            <div class="head">
                <strong><?=(count($CoverArt) > 0 ? 'Covers (' . (count($CoverArt) + 1) . ')' : 'Cover')?></strong>
<?php
    if (count($CoverArt) > 0) {
        if (empty($LoggedUser['ShowExtraCovers'])) {
            for ($Index = 0; $Index <= count($CoverArt); $Index++) {
?>
                <span id="cover_controls_<?=($Index)?>"<?=($Index > 0 ? ' style="display: none;"' : '')?>>
<?php           if ($Index == count($CoverArt)) { ?>
                        <a class="brackets prev_cover" data-gazelle-prev-cover="<?=($Index - 1)?>" href="#">Prev</a>
                        <a class="brackets show_all_covers" href="#">Show all</a>
                        <span class="brackets next_cover">Next</span>
<?php           } elseif ($Index > 0) { ?>
                        <a class="brackets prev_cover" data-gazelle-prev-cover="<?=($Index - 1)?>" href="#">Prev</a>
                        <a class="brackets show_all_covers" href="#">Show all</a>
                        <a class="brackets next_cover" data-gazelle-next-cover="<?=($Index + 1)?>" href="#">Next</a>
<?php           } elseif ($Index == 0 && count($CoverArt) > 0) { ?>
                        <span class="brackets prev_cover">Prev</span>
                        <a class="brackets show_all_covers" href="#">Show all</a>
                        <a class="brackets next_cover" data-gazelle-next-cover="<?=($Index + 1)?>" href="#">Next</a>
<?php           } ?>
                </span>
<?php
            }
        } else {
?>
                <span>
                    <a class="brackets show_all_covers" href="#">Hide</a>
                </span>
<?php
        }
    }
?>
            </div>
<?php $Index = 0; ?>
<div id="covers">
<div id="cover_div_<?=$Index?>" class="pad">
<?php if ($WikiImage != '') { ?>
            <p align="center"><img width="100%" src="<?=ImageTools::process($WikiImage, true)?>" alt="<?=$AltName?>" onclick="lightbox.init('<?=ImageTools::process($WikiImage)?>', 220);" /></p>
<?php } else { ?>
            <p align="center"><img width="100%" src="<?=STATIC_SERVER?>/common/noartwork/<?=$CategoryIcons[$GroupCategoryID - 1]?>" alt="<?=$Categories[$GroupCategoryID - 1]?>" class="brackets tooltip" title="<?=$Categories[$GroupCategoryID - 1]?>" height="220" border="0" /></p>
<?php
}
$Index++;
?>
</div>
<?php
            foreach ($CoverArt as $Cover) {
                [$ImageID, $Image, $Summary, $AddedBy] = $Cover;
?>
                    <div id="cover_div_<?=$Index?>" class="pad"<?=(empty($LoggedUser['ShowExtraCovers']) ? ' style="display: none;"' : '')?>>
                <p align="center">
<?php
                if (empty($LoggedUser['ShowExtraCovers'])) {
                    $Src = 'src="" data-gazelle-temp-src="' . ImageTools::process($Image, true) . '"';
                } else {
                    $Src = 'src="' . ImageTools::process($Image, true) . '"';
                }
?>
                    <img id="cover_<?=$Index?>" width="100%" <?=$Src?> alt="<?=$Summary?>" onclick="lightbox.init('<?=ImageTools::process($Image)?>', 220);" />
                </p>
                <ul class="stats nobullet">
                    <li>
                        <?=$Summary?>
                        <?=(check_perms('users_mod') ? ' added by ' . Users::format_username($AddedBy, false, false, false, false, false) : '')?>
                        <span class="remove remove_cover_art"><a href="#" onclick="if (confirm('Do not delete valid alternative cover art. Are you sure you want to delete this cover art?') == true) { ajax.get('torrents.php?action=remove_cover_art&amp;auth=<?=$LoggedUser['AuthKey']?>&amp;id=<?=$ImageID?>&amp;groupid=<?=$GroupID?>'); this.parentNode.parentNode.parentNode.style.display = 'none'; this.parentNode.parentNode.parentNode.previousElementSibling.style.display = 'none'; } else { return false; }" class="brackets tooltip" title="Remove image">X</a></span>
                    </li>
                </ul>
            </div>
<?php
                $Index++;
            }
?>
        </div>

<?php   if (check_perms('site_edit_wiki') && $WikiImage != '') { ?>
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
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                    </div>
                </form>
            </div>
        </div>
<?php   } ?>

    </div>
<?php
if ($Categories[$GroupCategoryID - 1] == 'Music') {
    $ShownWith = false;
    $title = '';
    if (!empty($Artists[6]) && !empty($Artists[1])) {
        $title = 'Artists:';
    } elseif (!empty($Artists[4]) && !empty($Artists[1])) {
        $title = 'Performers:';
    }
    $section = [
        ['offset' => 4, 'class' => 'artists_composers', 'title' => 'Composers:', 'role' => 'Composer'],
        ['offset' => 6, 'class' => 'artists_dj', 'title' => 'DJ / Compiler:', 'role' => 'DJ'],
        ['offset' => 1, 'class' => 'artists_main', 'title' => $title, 'role' => 'Artist'],
        ['offset' => 2, 'class' => 'artists_guest', 'title' => 'With:', 'role' => 'Guest'],
        ['offset' => 5, 'class' => 'artists_conductors', 'title' => 'Conducted by:', 'role' => 'Conductor'],
        ['offset' => 3, 'class' => 'artists_remix', 'title' => 'Remixed by:', 'role' => 'Remixer'],
        ['offset' => 7, 'class' => 'artists_producer', 'title' => 'Produced by:', 'role' => 'Producer'],
        ['offset' => 8, 'class' => 'artists_arranger', 'title' => 'Arranged by:', 'role' => 'Arranger'],
    ];
?>
        <div class="box box_artists">
            <div class="head"><strong>Artists</strong>
            <?=check_perms('torrents_edit') ? '<span style="float: right;" class="edit_artists"><a onclick="ArtistManager(); return false;" href="#" class="brackets">Edit</a></span>' : ''?>
            </div>
            <ul class="stats nobullet" id="artist_list">
<?php   foreach ($section as $s) { ?>
<?php       if (!empty($Artists[$s['offset']]) && count($Artists[$s['offset']]) > 0) { ?>
                <li class="<?= $s['class'] ?>"><strong class="artists_label"><?= $s['title'] ?></strong></li>
<?php
            foreach ($Artists[$s['offset']] as $Artist) {
                try {
                    $a = new \Gazelle\Artist($Artist['id']);
                } catch (Gazelle\Exception\ResourceNotFoundException $e) {
                    continue;
                }
?>
                <li class="<?= $s['class'] ?>">
                    <?= Artists::display_artist($Artist) ?>&lrm;
<?php               if (check_perms('torrents_edit')) { ?>
                    (<span class="tooltip" title="Artist alias ID"><?= $a->getAlias($Artist['name'])
                        ?></span>)&nbsp;<span class="remove remove_artist"><a href="javascript:void(0);" onclick="ajax.get('torrents.php?action=delete_alias&amp;auth=' + authkey + '&amp;groupid=<?= $GroupID
                        ?>&amp;artistid=<?=$Artist['id']?>&amp;importance=<?=$s['offset']
                        ?>'); this.parentNode.parentNode.style.display = 'none';" class="brackets tooltip" title="Remove <?= $s['role']
                        ?>">X</a></span>
<?php           } ?>
                </li>
<?php
                }
            }
        } /* foreach section */
?>
            </ul>
        </div>
<?php if (check_perms('torrents_add_artist')) { ?>
        <div class="box box_addartists">
            <div class="head"><strong>Add artist</strong><span style="float: right;" class="additional_add_artist"><a onclick="AddArtistField(); return false;" href="#" class="brackets">+</a></span></div>
            <div class="body">
                <form class="add_form" name="artists" action="torrents.php" method="post">
                    <div id="AddArtists">
                        <input type="hidden" name="action" value="add_alias" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                        <input type="text" id="artist" name="aliasname[]" size="17"<?=
                            $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                        <select name="importance[]">
                            <option value="1">Main</option>
                            <option value="2">Guest</option>
                            <option value="4">Composer</option>
                            <option value="5">Conductor</option>
                            <option value="6">DJ / Compiler</option>
                            <option value="3">Remixer</option>
                            <option value="7">Producer</option>
                            <option value="8">Arranger</option>
                        </select>
                    </div>
                    <input type="submit" value="Add" />
                </form>
            </div>
        </div>
<?php
        }
        if (check_perms('site_collages_create')) {
?>
        <div class="box box_info box_addcollage_torrent">
            <div class="head"><strong>Add to collage</strong></div>
                <div class="box pad">
                    <form action="collages.php" method="post">
<?php
            $collageList = $collageMan->addToCollageDefault($user, $GroupID);
            if (empty($collageList)) {
?>
                    <div>Search for a collage name:</div>
<?php       } else { ?>
                    <select style="max-width: 100%" name="collage_combo">
                        <option value="0">Choose recent...</option>
<?php           foreach ($collageList as $id => $name) { ?>
                        <option value="<?= $id ?>"><?= $name ?></option>
<?php           } ?>
                    </select>
                    <div>or search for collage name:</div>
<?php       } ?>
                    <input type="text" id="collage_ref" name="collage_ref" data-gazelle-autocomplete="true" size="25" />
                    <input type="hidden" name="action" value="add_torrent" />
                    <input type="hidden" name="groupid" value="<?= $GroupID ?>" />
                    <input type="hidden" name="userid" value="<?= $LoggedUser['ID'] ?>" />
                    <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                    <br /><br /><input type="submit" value="Add" />
                    </form>
            </div>
        </div>
<?php
    }
}
$vote = (new Gazelle\Vote($LoggedUser['ID']))->setGroupId($GroupID);
if ($GroupCategoryID === 1) {
    $decade = $GroupYear - ($GroupYear % 10);
    $decadeEnd = $decade + 9;
    $advanced = check_perms('site_advanced_top10');

    $rankList = [
        'overall' => [
            'rank' => $vote->rankOverall(),
            'title' => '<a href="top10.php?type=votes">overall</a>',
        ],
        'decade' => [
            'rank' => $vote->rankDecade($GroupYear),
            'title' => $advanced
                ? "for the <a href=\"top10.php?advanced=1&amp;type=votes&amp;year1=$decade&amp;year2=$decadeEnd\">{$decade}s</a>"
                : "for the {$decade}s",
        ],
        'year' => [
            'rank' => $vote->rankYear($GroupYear),
            'title' => $advanced
                ? "for <a href=\"top10.php?advanced=1&amp;type=votes&amp;year1=$GroupYear\">$GroupYear</a>"
                : "for $GroupYear",
        ],
    ];

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

echo $Twig->render('vote/box.twig', [
    'auth'     => $LoggedUser['AuthKey'],
    'can_vote' => check_perms('site_album_votes'),
    'group_id' => $GroupID,
    'percent'  => $vote->total() ? $vote->totalUp() / $vote->total() * 100 : '&mdash;',
    'total'    => $vote->total(),
    'up'       => $vote->totalUp(),
    'down'     => $vote->totalDown(),
    'score'    => $vote->score($vote->total(), $vote->totalUp()) * 100,
    'vote'     => $vote->vote(),
]);

$DeletedTag = $Cache->get_value("deleted_tags_$GroupID".'_'.$LoggedUser['ID']);
?>
        <div class="box box_tags">
            <div class="head">
                <strong>Tags</strong>
<?php if (!empty($DeletedTag)) { ?>
                    <form style="display: none;" id="undo_tag_delete_form" name="tags" action="torrents.php" method="post">
                        <input type="hidden" name="action" value="add_tag" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                        <input type="hidden" name="tagname" value="<?=$DeletedTag?>" />
                        <input type="hidden" name="undo" value="true" />
                    </form>
                    <a class="brackets" href="#" onclick="$('#undo_tag_delete_form').raw().submit(); return false;">Undo delete</a>

<?php } ?>
            </div>
<?php if (empty($Tags)) { ?>
            <ul><li>There are no tags to display.</li></ul>
<?php } else { ?>
            <ul class="stats nobullet">
<?php   foreach ($Tags as $TagKey => $Tag) { ?>
                <li>
                    <a href="torrents.php?taglist=<?=$Tag['name']?>" style="float: left; display: block;"><?=display_str($Tag['name'])?></a>
                    <div style="float: right; display: block; letter-spacing: -1px;" class="edit_tags_votes">
                    <a href="torrents.php?action=vote_tag&amp;way=up&amp;groupid=<?=$GroupID?>&amp;tagid=<?=$Tag['id']?>&amp;auth=<?=$LoggedUser['AuthKey']?>" title="Vote this tag up" class="brackets tooltip vote_tag_up">&and;</a>
                    <?=$Tag['score']?>
                    <a href="torrents.php?action=vote_tag&amp;way=down&amp;groupid=<?=$GroupID?>&amp;tagid=<?=$Tag['id']?>&amp;auth=<?=$LoggedUser['AuthKey']?>" title="Vote this tag down" class="brackets tooltip vote_tag_down">&or;</a>
<?php       if (check_perms('users_warn')) { ?>
                    <a href="user.php?id=<?=$Tag['userid']?>" title="View the profile of the user that added this tag" class="brackets tooltip view_tag_user">U</a>
<?php
            }
            if (empty($LoggedUser['DisableTagging']) && check_perms('site_delete_tag')) {
?>
                    <span class="remove remove_tag"><a href="torrents.php?action=delete_tag&amp;groupid=<?=$GroupID?>&amp;tagid=<?=$Tag['id']?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets tooltip" title="Remove tag">X</a></span>
<?php       } ?>
                    </div>
                    <br style="clear: both;" />
                </li>
<?php   } /* foreach */ ?>
            </ul>
<?php } ?>
        </div>

<?php if (empty($LoggedUser['DisableTagging'])) { ?>
        <div class="box box_addtag">
            <div class="head"><strong>Add tag</strong></div>
            <div class="body">
                <form class="add_form" name="tags" action="torrents.php" method="post">
                    <input type="hidden" name="action" value="add_tag" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                    <input type="text" name="tagname" id="tagname" size="20"<?=
                        $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                    <input type="submit" value="+" />
                </form>
                <br /><br />
                <strong><a href="rules.php?p=tag" class="brackets">View tagging rules</a></strong>
            </div>
        </div>
<?php } ?>
    </div>

    <div class="main_column">
        <table class="torrent_table details<?=$GroupFlags['IsSnatched'] ? ' snatched' : ''?> m_table" id="torrent_details">
            <tr class="colhead_dark">
                <td class="m_th_left" width="80%"><strong>Torrents</strong></td>
                <td><strong>Size</strong></td>
                <td class="m_th_right sign snatches"><img src="<?= STATIC_SERVER ?>/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="m_th_right sign seeders"><img src="<?= STATIC_SERVER ?>/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="m_th_right sign leechers"><img src="<?= STATIC_SERVER ?>/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php
function filelist($Str) {
    return "</td><td>".Format::get_size($Str[1])."</td></tr>";
}

$LastRemasterYear = '-';
$LastRemasterTitle = '';
$LastRemasterRecordLabel = '';
$LastRemasterCatalogueNumber = '';

$EditionID = 0;

$Edition = [];
foreach ($TorrentList as $t) {
    if ($t['is_deleted'] == 0) {
        $r = $t['Remastered'];
        $key = implode('.', [
            $r ? $t['RemasterYear'] : $GroupYear,
            $r ? $t['RemasterRecordLabel'] : $GroupRecordLabel,
            $r ? $t['RemasterCatalogueNumber'] : $GroupCatalogueNumber,
            $r ? $t['RemasterTitle'] : $GroupName
        ]);
        if (isset($Edition[$key])) {
            $Edition[$key]++;
        } else {
            $Edition[$key] = 1;
        }
    }
}

$LastMedia = null;
$UnknownCounter = 0;
foreach ($TorrentList as $Torrent) {
    [$TorrentID, $Media, $Format, $Encoding, $Remastered, $RemasterYear,
        $RemasterTitle, $RemasterRecordLabel, $RemasterCatalogueNumber, $Scene,
        $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum, $FileCount, $Size, $Seeders, $Leechers,
        $Snatched, $FreeTorrent, $TorrentTime, $Description, $FileList,
        $FilePath, $UserID, $LastActive, $InfoHash, $BadTags, $BadFolders, $BadFiles,
        $MissingLineage, $CassetteApproved, $LossymasterApproved, $LossywebApproved,
        $LastReseedRequest, $HasFile, $LogCount, $is_deleted, $PersonalFL, $IsSnatched] = array_values($Torrent);

    if ($is_deleted && count($TorrentList) > 1) {
        continue;
    }
    if ($Remastered && !$RemasterYear) {
        $UnknownCounter++;
    }

    unset($ReportedTimes);
    $Reports = Torrents::get_reports($TorrentID);
    $NumReports = count($Reports);

    if ($NumReports == 0) {
        $Reported = false;
    }
    else {
        $Reported = true;
        $Torrent['Reported'] = $NumReports;
        $reportMan = new Gazelle\Manager\ReportV2;
        $Types = $reportMan->types();
        $ReportInfo = '
        <table class="reportinfo_table">
            <tr class="colhead_dark" style="font-weight: bold;">
                <td>This torrent has '.$NumReports.' active '.($NumReports === 1 ? 'report' : 'reports').":</td>
            </tr>";

        foreach ($Reports as $Report) {
            $ReportLinks = !check_perms('admin_reports')
                ? 'Someone reported it'
                : sprintf('<a href="user.php?id=%d">%s</a> <a href="reportsv2.php?view=report&amp;id=%d">reported it</a>',
                    $Report['ReporterID'],
                    (new Gazelle\User($Report['ReporterID']))->username(),
                    $Report['ID']);

            if (isset($Types[$GroupCategoryID][$Report['Type']])) {
                $ReportType = $Types[$GroupCategoryID][$Report['Type']];
            } elseif (isset($Types['master'][$Report['Type']])) {
                $ReportType = $Types['master'][$Report['Type']];
            } else {
                //There was a type but it wasn't an option!
                $ReportType = $Types['master']['other'];
            }
            $ReportInfo .= "
            <tr>
                <td>$ReportLinks ".time_diff($Report['ReportedTime'], 2, true, true).' for the reason "'.$ReportType['title'].'":
                    <blockquote>'.Text::full_format($Report['UserComment']).'</blockquote>
                </td>
            </tr>';
        }
        $ReportInfo .= "\n\t\t</table>";
    }

    $CanEdit = (check_perms('torrents_edit') || (($UserID == $LoggedUser['ID'] && !$LoggedUser['DisableWiki']) && !($Remastered && !$RemasterYear)));

    $RegenLink = check_perms('users_mod') ? ' <a href="torrents.php?action=regen_filelist&amp;torrentid='.$TorrentID.'" class="brackets">Regenerate</a>' : '';
    $FileTable = '
    <table class="filelist_table">
        <tr class="colhead_dark">
            <td>
                <div class="filelist_title" style="float: left;">File Names' . $RegenLink . '</div>
                <div class="filelist_path" style="float: right;">' . ($FilePath ? "/$FilePath/" : '') . '</div>
            </td>
            <td class="nobr">
                <strong>Size</strong>
            </td>
        </tr>';
    $FileListSplit = explode("\n", $FileList);
    foreach ($FileListSplit as $File) {
        $FileInfo = Torrents::filelist_get_file($File);
        $FileTable .= sprintf("\n<tr><td>%s</td><td class=\"number_column nobr\">%s</td></tr>", $FileInfo['name'], Format::get_size($FileInfo['size']));
    }
    $FileTable .= '
    </table>';

    $ExtraInfo = Torrents::torrent_info($Torrent, false, false, true, $GroupName);

    if ($GroupCategoryID == 1
        && ($RemasterTitle != $LastRemasterTitle
        || $RemasterYear != $LastRemasterYear
        || $RemasterRecordLabel != $LastRemasterRecordLabel
        || $RemasterCatalogueNumber != $LastRemasterCatalogueNumber
        || $UnknownCounter === 1
        || $Media != $LastMedia)) {

        $EditionID++;
?>
        <tr class="releases_<?=$ReleaseType?> groupid_<?=$GroupID?> edition group_torrent">
            <td colspan="5" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$GroupID?>, <?=$EditionID?>, this, event);" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group." class="tooltip">&minus;</a> <?=Torrents::edition_string($Torrent, $TorrentDetails)?></strong></td>
        </tr>
<?php
    }
    $LastRemasterTitle = $RemasterTitle;
    $LastRemasterYear = $RemasterYear;
    $LastRemasterRecordLabel = $RemasterRecordLabel;
    $LastRemasterCatalogueNumber = $RemasterCatalogueNumber;
    $LastMedia = $Media;
?>

            <tr class="torrent_row releases_<?=$ReleaseType?> groupid_<?=$GroupID?> edition_<?=$EditionID?> group_torrent<?=($IsSnatched ? ' snatched_torrent' : '')?>" style="font-weight: normal;" id="torrent<?=$TorrentID?>">
                <td class="td_info">
<?php
    if ($is_deleted) {
        $key = implode('.', [
            $RemasterYear ?: $GroupYear,
            $RemasterRecordLabel ?: $GroupRecordLabel,
            $RemasterCatalogueNumber ?: $GroupCatalogueNumber,
            $RemasterTitle ?: $GroupName]);
        if (!array_key_exists($key, $Edition)) {
?>
                    <i>deleted</i>
                </td>
                <td class="td_size nobr">—</td>
                <td class="td_snatched m_td_right">—</td>
                <td class="td_seeders m_td_right">—</td>
                <td class="td_leechers m_td_right">—</td>
<?php
        }
    } else {
?>
                    <span>[ <a href="torrents.php?action=download&amp;id=<?=$TorrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="tooltip" title="Download"><?=($HasFile ? 'DL' : 'Missing')?></a>
<?php   if (Torrents::can_use_token($Torrent)) { ?>
                        | <a href="torrents.php?action=download&amp;id=<?=$TorrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('<?=FL_confirmation_msg($Torrent['Seeders'], $Torrent['Size'])?>');">FL</a>
<?php   } ?>
                        | <a href="reportsv2.php?action=report&amp;id=<?=$TorrentID?>" class="tooltip" title="Report">RP</a>
<?php   if ($CanEdit) { ?>
                        | <a href="torrents.php?action=edit&amp;id=<?=$TorrentID ?>" class="tooltip" title="Edit">ED</a>
<?php   }
        if (check_perms('torrents_delete') || $UserID == $LoggedUser['ID']) { ?>
                        | <a href="torrents.php?action=delete&amp;torrentid=<?=$TorrentID ?>" class="tooltip" title="Remove">RM</a>
<?php   } ?>
                        | <a href="torrents.php?torrentid=<?=$TorrentID ?>" class="tooltip" title="Permalink">PL</a>
                        | <a href="ajax.php?action=torrent&id=<?=($TorrentID)?>" download="<?=$Title?> [<?=($TorrentID)?>] [orpheus.network].json" class="tooltip" title="Download JSON">JS</a>
                    ]</span>
                    &raquo; <a href="#" onclick="$('#torrent_<?=$TorrentID?>').gtoggle(); return false;"><?=$ExtraInfo; ?></a>
                </td>
                <td class="td_size number_column nobr"><?=Format::get_size($Size)?></td>
                <td class="td_snatched m_td_right number_column"><?=number_format($Snatched)?></td>
                <td class="td_seeders m_td_right number_column"><?=number_format($Seeders)?></td>
                <td class="td_leechers m_td_right number_column"><?=number_format($Leechers)?></td>
<?php
    } /* !$is_deleted */ ?>
            </tr>
            <tr class="releases_<?=$ReleaseType?> groupid_<?=$GroupID?> edition_<?=$EditionID?> torrentdetails pad <?php if (!isset($_GET['torrentid']) || $_GET['torrentid'] != $TorrentID) { ?>hidden<?php } ?>" id="torrent_<?=$TorrentID; ?>">
                <td colspan="5">
                    <div id="release_<?=$TorrentID?>" class="no_overflow">
                        <blockquote>
                            Uploaded by <?=Users::format_username($UserID, false, false, false)?> <?=time_diff($TorrentTime);?>
<?php
    if ($Seeders == 0) {
        // If the last time this was seeded was 50 years ago, most likely it has never been seeded, so don't bother
        // displaying "Last active: 2000+ years" as that's dumb
        if (time() - strtotime($LastActive) > 1576800000) { ?>
                            <br />Last active: Never
<?php
        // If last active is >= 2 weeks ago, output in bold
        } elseif (time() - strtotime($LastActive) >= 1209600) { ?>
                            <br /><strong>Last active: <?=time_diff($LastActive); ?></strong>
<?php   } else { ?>
                            <br />Last active: <?= time_diff($LastActive); ?>
<?php
        }
    }

    if (($Seeders == 0 && time() - strtotime($LastActive) >= 345678 && time() - strtotime($LastReseedRequest) >= 864000) || check_perms('users_mod')) { ?>
                            <br /><a href="torrents.php?action=reseed&amp;torrentid=<?=$TorrentID?>&amp;groupid=<?=$GroupID?>" class="brackets" onclick="return confirm('Are you sure you want to request a re-seed of this torrent?');">Request re-seed</a>
<?php } ?>
                            <br /><br />If you download this, your ratio will become <?=
                                Format::get_ratio_html($LoggedUser['BytesUploaded'],
                                    $LoggedUser['BytesDownloaded'] + $Size);
                            ?>.
                        </blockquote>
                    </div>
                    <div class="linkbox">
<?php if (check_perms('site_moderate_requests')) { ?>
                        <a href="torrents.php?action=masspm&amp;id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>" class="brackets">Mass PM snatchers</a>
<?php } ?>
                        <a href="#" class="brackets" onclick="show_peers('<?=$TorrentID?>', 0); return false;">View peer list</a>
<?php if ($Media === 'CD' && $HasLog && $HasLogDB) { ?>
                        <a href="#" class="brackets" onclick="show_logs('<?=$TorrentID?>', <?=$HasLogDB?>, '<?=$LogScore?>'); return false;">View log</a>
<?php
    }
    if (check_perms('site_view_torrent_snatchlist')) { ?>
                        <a href="#" class="brackets tooltip" onclick="show_downloads('<?=$TorrentID?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">View download list</a>
                        <a href="#" class="brackets tooltip" onclick="show_snatches('<?=$TorrentID?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">View snatch list</a>
<?php } ?>
                        <a href="#" class="brackets" onclick="show_files('<?=$TorrentID?>'); return false;">View file list</a>
<?php if ($Reported) { ?>
                        <a href="#" class="brackets" onclick="show_reported('<?=$TorrentID?>'); return false;">View report information</a>
<?php } ?>
                    </div>
                    <div id="viewlog_<?=$TorrentID?>" class="hidden"></div>
                    <div id="peers_<?=$TorrentID?>" class="hidden"></div>
                    <div id="downloads_<?=$TorrentID?>" class="hidden"></div>
                    <div id="snatches_<?=$TorrentID?>" class="hidden"></div>
                    <div id="files_<?=$TorrentID?>" class="hidden"><?=$FileTable?></div>
<?php if ($Reported) { ?>
                    <div id="reported_<?=$TorrentID?>" class="hidden"><?=$ReportInfo?></div>
<?php
    }
    if (!empty($Description)) {
            echo "\n<blockquote>".Text::full_format($Description).'</blockquote>';
    }
?>
                </td>
            </tr>
<?php } ?>
        </table>
<?php
$Requests = get_group_requests($GroupID);
if (empty($LoggedUser['DisableRequests']) && count($Requests) > 0) {
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
<?php       if (check_perms('site_vote')) { ?>
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
$Collages = $Cache->get_value("torrent_collages_$GroupID");
if (!is_array($Collages)) {
    $DB->prepared_query("
        SELECT c.Name, c.NumTorrents, c.ID
        FROM collages AS c
        INNER JOIN collages_torrents AS ct ON (ct.CollageID = c.ID)
        WHERE Deleted = '0'
            AND CategoryID != '0'
            AND ct.GroupID = ?
        ", $GroupID
    );
    $Collages = $DB->to_array();
    $Cache->cache_value("torrent_collages_$GroupID", $Collages, 3600 * 6);
}
if (count($Collages) > 0) {
    if (count($Collages) > COLLAGE_SAMPLE_THRESHOLD) {
        // Pick some at random
        $Range = range(0, count($Collages) - 1);
        shuffle($Range);
        $Indices = array_slice($Range, 0, COLLAGE_SAMPLE_THRESHOLD);
        $SeeAll = ' <a href="#" onclick="$(\'.collage_rows\').gtoggle(); return false;">(See all)</a>';
    } else {
        $Indices = range(0, count($Collages) - 1);
        $SeeAll = '';
    }
?>
        <table class="collage_table" id="collages">
            <tr class="colhead">
                <td width="85%"><a href="#">&uarr;</a>&nbsp;This album is in <?=number_format(count($Collages))?> collage<?= plural(count($Collages)) ?><?=$SeeAll?></td>
                <td># torrents</td>
            </tr>
<?php
    foreach ($Indices as $i) {
        [$CollageName, $CollageTorrents, $CollageID] = $Collages[$i];
        unset($Collages[$i]);
?>
            <tr>
                <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                <td class="number_column"><?=number_format($CollageTorrents)?></td>
            </tr>
<?php
    }
    foreach ($Collages as $Collage) {
        [$CollageName, $CollageTorrents, $CollageID] = $Collage;
?>
            <tr class="collage_rows hidden">
                <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                <td class="number_column"><?=number_format($CollageTorrents)?></td>
            </tr>
<?php } ?>
        </table>
<?php
}

$PersonalCollages = $Cache->get_value("torrent_collages_personal_$GroupID");
if (!is_array($PersonalCollages)) {
    $DB->prepared_query("
        SELECT c.Name, c.NumTorrents, c.ID
        FROM collages AS c
            JOIN collages_torrents AS ct ON ct.CollageID = c.ID
        WHERE Deleted = '0'
            AND CategoryID = '0'
            AND ct.GroupID = ?
        ", $GroupID
    );
    $PersonalCollages = $DB->to_array(false, MYSQLI_NUM);
    $Cache->cache_value("torrent_collages_personal_$GroupID", $PersonalCollages, 3600 * 6);
}

if (count($PersonalCollages) > 0) {
    if (count($PersonalCollages) > PERSONAL_COLLAGE_SAMPLE_THRESHOLD) {
        // Pick some at random
        $Range = range(0,count($PersonalCollages) - 1);
        shuffle($Range);
        $Indices = array_slice($Range, 0, PERSONAL_COLLAGE_SAMPLE_THRESHOLD);
        $SeeAll = ' <a href="#" onclick="$(\'.personal_rows\').gtoggle(); return false;">(See all)</a>';
    } else {
        $Indices = range(0, count($PersonalCollages) - 1);
        $SeeAll = '';
    }
?>
        <table class="collage_table" id="personal_collages">
            <tr class="colhead">
                <td width="85%"><a href="#">&uarr;</a>&nbsp;This album is in <?=number_format(count($PersonalCollages))?> personal collage<?= plural(count($PersonalCollages)) ?><?=$SeeAll?></td>
                <td># torrents</td>
            </tr>
<?php
    foreach ($Indices as $i) {
        [$CollageName, $CollageTorrents, $CollageID] = $PersonalCollages[$i];
        unset($PersonalCollages[$i]);
?>
            <tr>
                <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                <td class="number_column"><?=number_format($CollageTorrents)?></td>
            </tr>
<?php
    }
    foreach ($PersonalCollages as $Collage) {
        [$CollageName, $CollageTorrents, $CollageID] = $Collage;
?>
            <tr class="personal_rows hidden">
                <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                <td class="number_column"><?=number_format($CollageTorrents)?></td>
            </tr>
<?php } ?>
        </table>
<?php
}
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
<?php } /* foreach */ ?>
        </table>
<?php } /* count($similar) */ ?>

        <div class="box torrent_description">
            <div class="head"><a href="#">&uarr;</a>&nbsp;<strong><?=(!empty($ReleaseType) ? $releaseTypes[$ReleaseType].' info' : 'Info' )?></strong></div>
            <div class="body"><?php if ($WikiBody != '') { echo $WikiBody; } else { echo 'There is no information on this torrent.'; } ?></div>
        </div>
<?php
echo $paginator->linkbox();
$comments = new Gazelle\CommentViewer\Torrent($Twig, $LoggedUser['ID'], $GroupID);
$comments->renderThread($commentPage->thread(), $commentPage->lastRead());
echo $paginator->linkbox();
echo $Twig->render('reply.twig', [
    'action'   => 'take_post',
    'auth'     => $LoggedUser['AuthKey'],
    'avatar'   => (new Gazelle\Manager\User)->avatarMarkup($user, $user),
    'id'       => $GroupID,
    'name'     => 'pageid',
    'subbed'   => $isSubscribed,
    'textarea' => new TEXTAREA_PREVIEW('body', 'quickpost', '',
        65, 8, false, false, true, ['tabindex="1"', 'onkeyup="resize(\'quickpost\')"' ]),
    'url'      => 'comments.php?page=torrents',
    'user'     => $user,
]);
?>
        </div>
    </div>
</div>
<?php
View::show_footer();
