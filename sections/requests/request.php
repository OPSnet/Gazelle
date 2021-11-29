<?php

/*
 * This is the page that displays the request to the end user after being created.
 */

$RequestID = (int)($_GET['id'] ?? 0);
$Request = Requests::get_request($RequestID);
if ($Request === false) {
    error(404);
}

//Convenience variables
$RequestTaxPercent = (REQUEST_TAX * 100);
$IsFilled = !empty($Request['TorrentID']);
$CanVote = !$IsFilled && $Viewer->permitted('site_vote');

if ($Request['CategoryID'] === '0') {
    $CategoryName = 'Unknown';
} else {
    $CategoryName = CATEGORY[$Request['CategoryID'] - 1];
}

$ArtistForm = Requests::get_artists($RequestID);
$ArtistName = Artists::display_artists($ArtistForm, false, true);
$ArtistLink = Artists::display_artists($ArtistForm, true, true);

if ($IsFilled) {
    $DisplayLink = "$ArtistLink<a href=\"torrents.php?torrentid={$Request['TorrentID']}\" dir=\"ltr\">{$Request['Title']}</a> [{$Request['Year']}]";
} else {
    $DisplayLink = $ArtistLink.'<span dir="ltr">'.$Request['Title']."</span> [{$Request['Year']}]";
}
$FullName = $ArtistName.$Request['Title']." [{$Request['Year']}]";

if ($Request['BitrateList'] != '') {
    $BitrateString = implode(', ', explode('|', $Request['BitrateList']));
    $FormatString = implode(', ', explode('|', $Request['FormatList']));
    $MediaString = implode(', ', explode('|', $Request['MediaList']));
} else {
    $BitrateString = 'Unknown, please read the description.';
    $FormatString = 'Unknown, please read the description.';
    $MediaString = 'Unknown, please read the description.';
}

if (empty($Request['ReleaseType'])) {
    $ReleaseName = 'Unknown';
} else {
    $ReleaseName = (new Gazelle\ReleaseType)->findNameById($Request['ReleaseType']);
}

$RequestVotes = Requests::get_votes_array($RequestID);
$VoteCount = count($RequestVotes['Voters']);
$UserCanEdit = (!$IsFilled && $Viewer->id() == $Request['UserID'] && $VoteCount < 2);
$CanEdit = ($UserCanEdit || $Viewer->permitted('site_moderate_requests') || $Viewer->permitted('site_edit_requests'));

// Comments (must be loaded before View::show_header so that subscriptions and quote notifications are handled properly)
$commentPage = new Gazelle\Comment\Request($RequestID);
if (isset($_GET['postid'])) {
    $commentPage->setPostId((int)$_GET['postid']);
} elseif (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$isSubscribed = (new Gazelle\Subscription($Viewer))->isSubscribedComments('requests', $RequestID);

View::show_header("View request: $FullName", ['js' => 'comments,requests,bbcode,subscriptions']);
?>
<div class="thin">
    <div class="header">
        <h2><a href="requests.php">Requests</a> &rsaquo; <?=$CategoryName?> &rsaquo; <?=$DisplayLink?></h2>
        <div class="linkbox">
<?php if ($CanEdit) { ?>
            <a href="requests.php?action=edit&amp;id=<?=$RequestID?>" class="brackets">Edit</a>
<?php
    }
    if ($Viewer->permitted('site_admin_requests')) { ?>
            <a href="requests.php?action=edit-bounty&amp;id=<?=$RequestID?>" class="brackets">Edit bounty</a>
<?php
    }
    if ($UserCanEdit || $Viewer->permitted('site_moderate_requests')) { ?>
            <a href="requests.php?action=delete&amp;id=<?=$RequestID?>" class="brackets">Delete</a>
<?php
    }
    if ((new Gazelle\Bookmark($Viewer))->isRequestBookmarked($RequestID)) { ?>
            <a href="#" id="bookmarklink_request_<?=$RequestID?>" onclick="Unbookmark('request', <?=$RequestID?>, 'Bookmark'); return false;" class="brackets">Remove bookmark</a>
<?php    } else { ?>
            <a href="#" id="bookmarklink_request_<?=$RequestID?>" onclick="Bookmark('request', <?=$RequestID?>, 'Remove bookmark'); return false;" class="brackets">Bookmark</a>
<?php    } ?>
            <a href="#" id="subscribelink_requests<?=$RequestID?>" class="brackets" onclick="SubscribeComments('requests',<?=$RequestID?>);return false;"><?=
                $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>
            <a href="reports.php?action=report&amp;type=request&amp;id=<?=$RequestID?>" class="brackets">Report request</a>
<?php    if (!$IsFilled) { ?>
            <a href="upload.php?requestid=<?=$RequestID?><?=($Request['GroupID'] ? "&amp;groupid={$Request['GroupID']}" : '')?>" class="brackets">Upload request</a>
<?php    }
    if (!$IsFilled && ($Request['CategoryID'] === '0' || ($CategoryName === 'Music' && $Request['Year'] === '0'))) { ?>
            <a href="reports.php?action=report&amp;type=request_update&amp;id=<?=$RequestID?>" class="brackets">Request update</a>
<?php }

$encoded_title = urlencode(preg_replace("/\([^\)]+\)/", '', $Request['Title']));
$encoded_artist = substr(str_replace('&amp;', 'and', $ArtistName), 0, -3);
$encoded_artist = str_ireplace('Performed By', '', $encoded_artist);
$encoded_artist = urlencode(preg_replace("/\([^\)]+\)/", '', $encoded_artist));
?>
            <a href="<?= "https://www.worldcat.org/search?qt=worldcat_org_all&amp;q=$encoded_artist%20$encoded_title" ?>" class="brackets">Find in library</a>
            <a href="<?= "https://www.discogs.com/search/?q=$encoded_artist+$encoded_title&amp;type=release" ?>" class="brackets">Find on Discogs</a>
        </div>
    </div>
    <div class="sidebar">
<?php    if ($Request['CategoryID'] !== '0') { ?>
        <div class="box box_image box_image_albumart box_albumart"><!-- .box_albumart deprecated -->
            <div class="head"><strong>Cover</strong></div>
            <div id="covers">
                <div class="pad">
<?php
        if (!empty($Request['Image'])) {
            $image = (new Gazelle\Util\ImageProxy)->setViewer($Viewer)->process($Request['Image']);
?>
                    <p align="center"><img style="width: 100%;" src="<?= $image ?>" alt="<?=
                        $FullName?>" onclick="lightbox.init('<?= $image ?>', 220);" /></p>
<?php        } else { ?>
                    <p align="center"><img style="width: 100%;" src="<?=STATIC_SERVER?>/common/noartwork/<?=CATEGORY_ICON[$Request['CategoryID'] - 1]?>" alt="<?=$CategoryName?>" class="tooltip" title="<?=$CategoryName?>" height="220" border="0" /></p>
<?php        } ?>
                </div>
            </div>
        </div>
<?php
    }
    if ($CategoryName === 'Music') { ?>
        <div class="box box_artists">
            <div class="head"><strong>Artists</strong></div>
            <ul class="stats nobullet">
<?php        if (!empty($ArtistForm[4]) && count($ArtistForm[4]) > 0) { ?>
                <li class="artists_composer"><strong>Composers:</strong></li>
<?php            foreach ($ArtistForm[4] as $Artist) { ?>
                <li class="artists_composer">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
        if (!empty($ArtistForm[6]) && count($ArtistForm[6]) > 0) {
?>
                <li class="artists_dj"><strong>DJ / Compiler:</strong></li>
<?php            foreach ($ArtistForm[6] as $Artist) { ?>
                <li class="artists_dj">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
        if (!empty($ArtistForm[6]) && !empty($ArtistForm[1]) && (count($ArtistForm[6]) > 0) && (count($ArtistForm[1]) > 0)) {
            print '                <li class="artists_main"><strong>Artists:</strong></li>';
        } elseif (!empty($ArtistForm[4]) && !empty($ArtistForm[1]) && (count($ArtistForm[4]) > 0) && (count($ArtistForm[1]) > 0)) {
            print '                <li class="artists_main"><strong>Performers:</strong></li>';
        }
        if (!empty($ArtistForm[1]) && count($ArtistForm[1]) > 0) {
            foreach ($ArtistForm[1] as $Artist) {
?>
                <li class="artists_main">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
        if (!empty($ArtistForm[2]) && count($ArtistForm[2]) > 0) {
?>
                <li class="artists_with"><strong>With:</strong></li>
<?php            foreach ($ArtistForm[2] as $Artist) { ?>
                <li class="artists_with">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
        if (!empty($ArtistForm[5]) && count($ArtistForm[5]) > 0) {
?>
                <li class="artists_conductor"><strong>Conducted by:</strong></li>
<?php            foreach ($ArtistForm[5] as $Artist) { ?>
                <li class="artist_guest">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
        if (!empty($ArtistForm[3]) && count($ArtistForm[3]) > 0) {
?>
                <li class="artists_remix"><strong>Remixed by:</strong></li>
<?php            foreach ($ArtistForm[3] as $Artist) { ?>
                <li class="artists_remix">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
        if (!empty($ArtistForm[7]) && count($ArtistForm[7]) > 0) {
?>
                <li class="artists_producer"><strong>Produced by:</strong></li>
<?php            foreach ($ArtistForm[7] as $Artist) { ?>
                <li class="artists_remix">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
        if (!empty($ArtistForm[8]) && count($ArtistForm[8]) > 0) {
?>
                <li class="artists_arranger"><strong>Arranged by:</strong></li>
<?php            foreach ($ArtistForm[8] as $Artist) { ?>
                <li class="artists_remix">
                    <?=Artists::display_artist($Artist)?>
                </li>
<?php
            }
        }
?>
            </ul>
        </div>
<?php    } ?>
        <div class="box box_tags">
            <div class="head"><strong>Tags</strong></div>
            <ul class="stats nobullet">
<?php    foreach ($Request['Tags'] as $TagID => $TagName) { ?>
                <li>
                    <a href="torrents.php?taglist=<?=$TagName?>"><?=display_str($TagName)?></a>
                    <br style="clear: both;" />
                </li>
<?php    } ?>
            </ul>
        </div>
        <div class="box box_votes">
            <div class="head"><strong>Top Contributors</strong></div>
            <table class="layout" id="request_top_contrib">
<?php
    $VoteMax = ($VoteCount < 5 ? $VoteCount : 5);
    $ViewerVote = false;
    for ($i = 0; $i < $VoteMax; $i++) {
        $User = array_shift($RequestVotes['Voters']);
        $Boldify = false;
        if ($User['UserID'] === $Viewer->id()) {
            $ViewerVote = true;
            $Boldify = true;
        }
?>
                <tr>
                    <td>
                        <a href="user.php?id=<?=$User['UserID']?>"><?=($Boldify ? '<strong>' : '') . display_str($User['Username']) . ($Boldify ? '</strong>' : '')?></a>
                    </td>
                    <td class="number_column">
                        <?=($Boldify ? '<strong>' : '') . Format::get_size($User['Bounty']) . ($Boldify ? "</strong>\n" : "\n")?>
                    </td>
                </tr>
<?php    }
    reset($RequestVotes['Voters']);
    if (!$ViewerVote) {
        foreach ($RequestVotes['Voters'] as $User) {
            if ($User['UserID'] === $Viewer->id()) { ?>
                <tr>
                    <td>
                        <a href="user.php?id=<?=$User['UserID']?>"><strong><?=display_str($User['Username'])?></strong></a>
                    </td>
                    <td class="number_column">
                        <strong><?=Format::get_size($User['Bounty'])?></strong>
                    </td>
                </tr>
<?php            }
        }
    }
?>
            </table>
        </div>
    </div>
    <div class="main_column">
        <table class="layout">
            <tr>
                <td class="label">Created</td>
                <td>
                    <?=time_diff($Request['TimeAdded'])?> by <strong><?=Users::format_username($Request['UserID'], false, false, false)?></strong>
                </td>
            </tr>
<?php    if ($CategoryName === 'Music') {
        if (!empty($Request['RecordLabel'])) { ?>
            <tr>
                <td class="label">Record label</td>
                <td><?=$Request['RecordLabel']?></td>
            </tr>
<?php        }
        if (!empty($Request['CatalogueNumber'])) { ?>
            <tr>
                <td class="label">Catalogue number</td>
                <td><?=$Request['CatalogueNumber']?></td>
            </tr>
<?php        } ?>
            <tr>
                <td class="label">Release type</td>
                <td><?=$ReleaseName?></td>
            </tr>
            <tr>
                <td class="label">Acceptable bitrates</td>
                <td><?=$BitrateString?></td>
            </tr>
            <tr>
                <td class="label">Acceptable formats</td>
                <td><?=$FormatString?></td>
            </tr>
            <tr>
                <td class="label">Acceptable media</td>
                <td><?=$MediaString?></td>
            </tr>
<?php        if (!empty($Request['LogCue']) || !empty($Request['Checksum'])) { ?>
            <tr>
                <td class="label">Required CD FLAC only extras</td>
                <td><?=$Request['LogCue']?></td>
            </tr>
            <tr>
                <td class="label">Required CD FLAC checksum</td>
                <td><?=$Request['Checksum'] ? 'yes' : 'no'?></td>
            </tr>
<?php
        }
    }
    $Worldcat = '';
    $OCLC = str_replace(' ', '', $Request['OCLC']);
    if ($OCLC !== '') {
        $OCLCs = explode(',', $OCLC);
        for ($i = 0; $i < count($OCLCs); $i++) {
            if (!empty($Worldcat)) {
                $Worldcat .= ', <a href="https://www.worldcat.org/oclc/'.$OCLCs[$i].'">'.$OCLCs[$i].'</a>';
            } else {
                $Worldcat = '<a href="https://www.worldcat.org/oclc/'.$OCLCs[$i].'">'.$OCLCs[$i].'</a>';
            }
        }
    }
    if (!empty($Worldcat)) {
?>
        <tr>
            <td class="label">WorldCat (OCLC) ID</td>
            <td><?=$Worldcat?></td>
        </tr>
<?php
    }
    if ($Request['GroupID']) {
?>
            <tr>
                <td class="label">Torrent group</td>
                <td><a href="torrents.php?id=<?=$Request['GroupID']?>">torrents.php?id=<?=$Request['GroupID']?></a></td>
            </tr>
<?php    } ?>
            <tr>
                <td class="label">Votes</td>
                <td>
                    <span id="votecount"><?=number_format($VoteCount)?></span>
<?php    if ($CanVote) { ?>
                    &nbsp;&nbsp;<a href="javascript:Vote(0)" class="brackets"><strong>+</strong></a>
                    <strong>Costs <?=Format::get_size(REQUEST_MIN)?></strong>
<?php    } ?>
                </td>
            </tr>
<?php    if ($Request['LastVote'] > $Request['TimeAdded']) { ?>
            <tr>
                <td class="label">Last voted</td>
                <td><?=time_diff($Request['LastVote'])?></td>
            </tr>
<?php
    }
    if ($CanVote) {
?>
            <tr id="voting">
                <td class="label tooltip" title="These units are in base 2, not base 10. For example, there are 1,024 MiB in 1 GiB.">Custom vote (MiB)</td>
                <td>
                    <input type="text" id="amount_box" size="8" />
                    <select id="unit" name="unit" onchange="Calculate();">
                        <option value="mb">MiB</option>
                        <option value="gb">GiB</option>
                    </select>
                    <?= REQUEST_TAX > 0 ? "<strong>{$RequestTaxPercent}% of this is deducted as tax by the system.</strong>" : '' ?>
                    <p>Bounty must be greater than or equal to 100 MiB.</p>
                </td>
            </tr>
            <tr>
                <td class="label">Bounty information</td>
                <td>
                    <form class="add_form" name="request" action="requests.php" method="get" id="request_form">
                        <input type="hidden" name="action" value="vote" />
                        <input type="hidden" id="request_tax" value="<?=REQUEST_TAX?>" />
                        <input type="hidden" id="requestid" name="id" value="<?=$RequestID?>" />
                        <input type="hidden" id="auth" name="auth" value="<?= $Viewer->auth() ?>" />
                        <input type="hidden" id="amount" name="amount" value="0" />
                        <input type="hidden" id="current_uploaded" value="<?=$Viewer->uploadedSize()?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$Viewer->downloadedSize()?>" />
                        <input type="hidden" id="current_rr" value="<?=$Viewer->requiredRatio()?>" />
                        <input id="total_bounty" type="hidden" value="<?=$RequestVotes['TotalBounty']?>" />
                        <?= REQUEST_TAX > 0
                            ? 'Bounty after tax: <strong><span id="bounty_after_tax">' . sprintf("%0.2f", 100 * (1 - REQUEST_TAX)) . ' MiB</span></strong><br />'
                            : '<span id="bounty_after_tax" style="display: none;">' . sprintf("%0.2f", 100 * (1 - REQUEST_TAX)) . ' MiB</span>'
                        ?>
                        If you add the entered <strong><span id="new_bounty">0.00 MiB</span></strong> of bounty, your new stats will be: <br />
                        Uploaded: <span id="new_uploaded"><?=Format::get_size($Viewer->uploadedSize())?></span><br />
                        Ratio: <span id="new_ratio"><?=Format::get_ratio_html($Viewer->uploadedSize(),$Viewer->downloadedSize())?></span>
                        <input type="button" id="button" value="Vote!" disabled="disabled" onclick="Vote();" />
                    </form>
                </td>
            </tr>
<?php    } ?>
            <tr id="bounty">
                <td class="label">Bounty</td>
                <td id="formatted_bounty"><?=Format::get_size($RequestVotes['TotalBounty'])?></td>
            </tr>
<?php  if ($IsFilled) { ?>
            <tr>
                <td class="label">Filled</td>
                <td>
                    <strong><a href="torrents.php?torrentid=<?=$Request['TorrentID']?>">Yes</a></strong>,
                    by user <?=Users::format_username($Request['FillerID'], false, false, false)?>
<?php        if ($Viewer->id() == $Request['UserID'] || $Viewer->id() == $Request['FillerID'] || $Viewer->permitted('site_moderate_requests')) { ?>
                        <strong><a href="requests.php?action=unfill&amp;id=<?=$RequestID?>" class="brackets">Unfill</a></strong> Unfilling a request without a <a href="/rules.php?p=requests">valid, nontrivial reason</a> will result in a warning.
<?php        } ?>
                </td>
            </tr>
<?php    } else { ?>
            <tr>
                <td class="label" valign="top">Fill request</td>
                <td>
                    <form class="edit_form" name="request" action="" method="post">
                        <div class="field_div">
                            <input type="hidden" name="action" value="takefill" />
                            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                            <input type="hidden" name="requestid" value="<?=$RequestID?>" />
                            <input type="text" size="50" name="link"<?=(!empty($Link) ? " value=\"$Link\"" : '')?> />
                            <br />
                            <strong>Must be the permalink [PL] of the torrent<br />(e.g. <?=SITE_URL?>/torrents.php?torrentid=nnn).</strong>
                        </div>
<?php        if ($Viewer->permitted('site_moderate_requests')) { ?>
                        <div class="field_div">
                            For user: <input type="text" size="25" name="user"<?=(!empty($FillerUsername) ? " value=\"$FillerUsername\"" : '')?> />
                        </div>
<?php        } ?>
                        <div class="submit_div">
                            <input type="submit" value="Fill request" />
                        </div>
                    </form>
                </td>
            </tr>
<?php    } ?>
        </table>
        <div class="box box2 box_request_desc">
            <div class="head"><strong>Description</strong></div>
            <div class="pad">
<?=                Text::full_format($Request['Description']);?>
            </div>
        </div>
    <div id="request_comments">
<?php
echo $paginator->linkbox();
$comments = new Gazelle\CommentViewer\Request($Viewer, $RequestID);
$comments->renderThread($commentPage->thread(), $commentPage->lastRead());
$textarea = new Gazelle\Util\Textarea('quickpost', '', 90, 8);
$textarea->setPreviewManual(true);
echo $paginator->linkbox();
echo $Twig->render('reply.twig', [
    'action'   => 'take_post',
    'auth'     => $Viewer->auth(),
    'avatar'   => (new Gazelle\Manager\User)->avatarMarkup($Viewer, $Viewer),
    'id'       => $RequestID,
    'name'     => 'pageid',
    'subbed'   => $isSubscribed,
    'textarea' => $textarea,
    'url'      => 'comments.php?page=requests',
    'user'     => $Viewer,
]);
?>
        </div>
    </div>
</div>
<?php
View::show_footer();
