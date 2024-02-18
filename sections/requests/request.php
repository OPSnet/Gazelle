<?php

/*
 * This is the page that displays the request to the end user after being created.
 */

$request = (new Gazelle\Manager\Request())->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    error(404);
}
$requestId = $request->id();

$commentPage = new Gazelle\Comment\Request($requestId, (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$isSubscribed = (new Gazelle\User\Subscription($Viewer))->isSubscribedComments('requests', $requestId);
$userMan = new Gazelle\Manager\User();
$topVoteList = array_slice($request->userVoteList($userMan), 0, 5);
$filler = $userMan->findById($request->fillerId());
$roleList = $request->artistRole()?->roleList() ?? [];

View::show_header("View request: {$request->text()}", ['js' => 'comments,requests,bbcode,subscriptions']);
?>
<div class="thin">
    <div class="header">
        <h2><a href="requests.php">Requests</a> &rsaquo; <?= $request->categoryName() ?> &rsaquo; <?= $request->selfLink() ?></h2>
        <div class="linkbox">
<?php if ($request->canEdit($Viewer)) { ?>
            <a href="requests.php?action=edit&amp;id=<?=$requestId?>" class="brackets">Edit</a>
<?php
}
if ($Viewer->permitted('site_admin_requests')) {
?>
            <a href="requests.php?action=edit-bounty&amp;id=<?=$requestId?>" class="brackets">Edit bounty</a>
<?php
}
if ($request->canEdit($Viewer)) {
?>
            <a href="requests.php?action=delete&amp;id=<?=$requestId?>" class="brackets">Delete</a>
<?php
}
echo $Twig->render('bookmark/action.twig', [
    'class'         => 'request',
    'float'         => false,
    'id'            => $requestId,
    'is_bookmarked' => (new Gazelle\User\Bookmark($Viewer))->isRequestBookmarked($requestId),
]);
?>
            <a href="#" id="subscribelink_requests<?=$requestId?>" class="brackets" onclick="SubscribeComments('requests',<?=$requestId?>);return false;"><?=
                $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>
           <a href="reports.php?action=report&amp;type=request&amp;id=<?=$requestId?>" class="brackets">Report request</a>
<?php if (!$request->isFilled()) { ?>
            <a href="upload.php?requestid=<?=$requestId?><?=($request->tgroupId() ? "&amp;groupid={$request->tgroupId()}" : '')?>" class="brackets">Upload request</a>
<?php
}
if (!$request->isFilled() && $request->categoryName() === 'Music' && $request->year() === 0) { ?>
            <a href="reports.php?action=report&amp;type=request_update&amp;id=<?=$requestId?>" class="brackets">Request update</a>
<?php
}

if ($request->categoryName() === 'Music') {
    $encoded_title = urlencode(preg_replace("/\([^\)]+\)/", '', $request->title()));
    $encoded_artist = urlencode(str_replace(['arranged by ', 'performed by '], ['', ''], $request->artistRole()->text()));
?>
            <a href="<?= "https://www.worldcat.org/search?qt=worldcat_org_all&amp;q=$encoded_artist%20$encoded_title" ?>" class="brackets">Find in library</a>
            <a href="<?= "https://www.discogs.com/search/?q=$encoded_artist+$encoded_title&amp;type=release" ?>" class="brackets">Find on Discogs</a>
<?php } ?>
        </div>
    </div>
    <div class="sidebar">
        <div class="box box_image box_image_albumart box_albumart"><!-- .box_albumart deprecated -->
            <div class="head"><strong>Cover</strong></div>
            <div id="covers">
                <div class="pad">
<?php
if ($request->image()) {
    $image = html_escape(image_cache_encode($request->image()));
?>
                    <p align="center"><img style="width: 100%;" src="<?= $image ?>" alt="album art"
                                           onclick="lightbox.init('<?= $image ?>', 220);"
                                           data-origin-src="<?= html_escape($request->image()) ?>" /></p>
<?php } else { ?>
                    <p align="center"><img style="width: 100%;" src="<?=STATIC_SERVER?>/common/noartwork/<?=CATEGORY_ICON[$request->categoryId() - 1]?>" alt="<?=$request->categoryName() ?>" class="tooltip" title="<?= $request->categoryName() ?>" height="220" border="0" /></p>
<?php } ?>
                </div>
            </div>
        </div>
<?php
if ($request->categoryName() === 'Music') {
?>
        <div class="box box_artists">
            <div class="head"><strong>Artists</strong></div>
            <ul class="stats nobullet">
<?php if (isset($roleList['composer'])) { ?>
                <li class="artists_composer"><strong>Composers:</strong></li>
<?php   foreach ($roleList['composer'] as $a) { ?>
                <li class="artists_composer"><?= $a['artist']->link() ?></li>
<?php
        }
    }
    if (isset($roleList['dj'])) {
?>
                <li class="artists_dj"><strong>DJ / Compiler:</strong></li>
<?php   foreach ($roleList['dj'] as $a) { ?>
                <li class="artists_dj"><?= $a['artist']->link() ?></li>
<?php
        }
    }
    if (isset($roleList['main'])) {
        if (isset($roleList['dj'])) {
?>
                <li class="artists_main"><strong>Artists</strong></li>
<?php   } elseif (isset($roleList['composer'])) { ?>
                <li class="artists_main"><strong>Performers:</strong></li>
<?php
        }
        foreach ($roleList['main'] as $a) {
?>
                <li class="artists_main"><?= $a['artist']->link() ?></li>
<?php
        }
    }
    if (isset($roleList['guest'])) {
?>
                <li class="artists_with"><strong>With:</strong></li>
<?php foreach ($roleList['guest'] as $a) { ?>
                <li class="artists_with"><?= $a['artist']->link() ?></li>
<?php
        }
    }
    if (isset($roleList['conductor'])) {
?>
                <li class="artists_conductor"><strong>Conducted by:</strong></li>
<?php foreach ($roleList['conductor'] as $a) { ?>
                <li class="artists_conductor"><?= $a['artist']->link() ?></li>
<?php
        }
    }
    if (isset($roleList['remixer'])) {
?>
                <li class="artists_remix"><strong>Remixed by:</strong></li>
<?php foreach ($roleList['remixer'] as $a) { ?>
                <li class="artists_remix"><?= $a['artist']->link() ?></li>
<?php
        }
    }
    if (isset($roleList['producer'])) {
?>
                <li class="artists_producer"><strong>Produced by:</strong></li>
<?php foreach ($roleList['producer'] as $a) { ?>
                <li class="artists_producer"><?= $a['artist']->link() ?></li>
<?php
        }
    }
    if (isset($roleList['arranger'])) {
?>
                <li class="artists_arranger"><strong>Arranged by:</strong></li>
<?php foreach ($roleList['arranger'] as $a) { ?>
                <li class="artists_arranger"><?= $a['artist']->link() ?></li>
<?php
        }
    }
?>
            </ul>
        </div>
<?php } ?>
        <div class="box box_tags">
            <div class="head"><strong>Tags</strong></div>
            <ul class="stats nobullet">
<?php foreach ($request->tagNameList() as $name) { ?>
                <li>
                    <a href="torrents.php?taglist=<?= html_escape($name) ?>"><?= html_escape($name) ?></a>
                    <br style="clear: both;" />
                </li>
<?php } ?>
            </ul>
        </div>
        <div class="box box_votes">
            <div class="head"><strong>Top Contributors</strong></div>
            <table class="layout" id="request_top_contrib">
<?php
$seen = false;
foreach ($topVoteList as $vote) {
    $bold = false;
    if ($vote['user_id'] === $Viewer->id()) {
        $seen = true;
        $bold = true;
    }
?>
                <tr>
                    <td>
                        <a href="user.php?id=<?= $vote['user_id'] ?>"><?= ($bold ? '<strong>' : '') . html_escape($vote['user']->username()) . ($bold ? '</strong>' : '')?></a>
                    </td>
                    <td class="number_column">
                        <?=($bold ? '<strong>' : '') . byte_format($vote['bounty']) . ($bold ? "</strong>\n" : "\n")?>
                    </td>
                </tr>
<?php
}
if (!$seen) {
    $bounty = $request->userBounty($Viewer);
    if ($bounty) {
?>
                <tr>
                    <td>
                        <a href="user.php?id=<?= $Viewer->id() ?>"><strong><?= html_escape($Viewer->username()) ?></strong></a>
                    </td>
                    <td class="number_column">
                        <strong><?= byte_format($bounty) ?></strong>
                    </td>
                </tr>
<?php
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
                    <?=time_diff($request->created()) ?> by <strong><?= $userMan->findById($request->userId())->link() ?></strong>
                </td>
            </tr>
<?php
if ($request->categoryName() === 'Music') {
    if (!empty($request->recordLabel())) {
?>
            <tr>
                <td class="label">Record label</td>
                <td><?= html_escape($request->recordLabel()) ?></td>
            </tr>
<?php
    }
    if (!empty($request->catalogueNumber())) {
?>
            <tr>
                <td class="label">Catalogue number</td>
                <td><?= html_escape($request->catalogueNumber()) ?></td>
            </tr>
<?php } ?>
            <tr>
                <td class="label">Release type</td>
                <td><?= $request->releaseTypeName() ?></td>
            </tr>
            <tr>
                <td class="label">Acceptable encodings</td>
                <td><?= $request->descriptionEncoding() ?? 'Unknown, please read the description.' ?></td>
            </tr>
            <tr>
                <td class="label">Acceptable formats</td>
                <td><?= $request->descriptionFormat() ?? 'Unknown, please read the description.' ?></td>
            </tr>
            <tr>
                <td class="label">Acceptable media</td>
                <td><?= $request->descriptionMedia() ?? 'Unknown, please read the description.' ?></td>
            </tr>
<?php if ($request->needCue() || $request->needLog() || $request->needLogChecksum()) { ?>
            <tr>
                <td class="label">Required CD FLAC only extras</td>
                <td><?= $request->descriptionLogCue() ?></td>
            </tr>
            <tr>
                <td class="label">Required CD FLAC checksum</td>
                <td><?= $request->needLogChecksum() ? 'yes' : 'no'?></td>
            </tr>
<?php
    }
}
if (!empty($Worldcat)) {
?>
            <tr>
                <td class="label">WorldCat (OCLC) ID</td>
                <td><?= $request->oclc() ?></td>
            </tr>
<?php
}
if ($request->tgroupId()) {
?>
            <tr>
                <td class="label">Torrent group</td>
                <td><a href="torrents.php?id=<?= $request->tgroupId() ?>">torrents.php?id=<?= $request->tgroupId() ?></a></td>
            </tr>
<?php } ?>
            <tr>
                <td class="label">Votes</td>
                <td>
                    <span id="votecount"><?=number_format($request->userVotedTotal()) ?></span>
<?php if ($request->canVote($Viewer)) { ?>
                    &nbsp;&nbsp;<a href="javascript:Vote(0)" class="brackets"><strong>+</strong></a>
                    <strong>Costs <?= REQUEST_MIN ?> MiB</strong>
<?php } ?>
                </td>
            </tr>
<?php if (strtotime($request->lastVoteDate()) > strtotime($request->created())) { ?>
            <tr>
                <td class="label">Last voted</td>
                <td><?=time_diff($request->lastVoteDate())?></td>
            </tr>
<?php
}
if ($request->canVote($Viewer)) {
?>
            <tr id="voting">
                <td class="label tooltip" title="These units are in base 2, not base 10. For example, there are 1,024 MiB in 1 GiB.">Custom vote (MiB)</td>
                <td>
                    <input type="text" id="amount_box" size="8" />
                    <select id="unit" name="unit" onchange="Calculate();">
                        <option value="mb">MiB</option>
                        <option value="gb">GiB</option>
                    </select>
                    <?= REQUEST_TAX > 0 ? "<strong>" . REQUEST_TAX * 100 . "% of this is deducted as tax by the system.</strong>" : '' ?>
                    <p>Bounty must be greater than or equal to <?= REQUEST_MIN ?> MiB.</p>
                </td>
            </tr>
            <tr>
                <td class="label">Bounty information</td>
                <td>
                    <form class="add_form" name="request" action="requests.php" method="get" id="request_form">
                        <input type="hidden" name="action" value="vote" />
                        <input type="hidden" id="request_tax" value="<?=REQUEST_TAX?>" />
                        <input type="hidden" id="requestid" name="id" value="<?=$requestId?>" />
                        <input type="hidden" id="auth" name="auth" value="<?= $Viewer->auth() ?>" />
                        <input type="hidden" id="amount" name="amount" value="0" />
                        <input type="hidden" id="current_uploaded" value="<?=$Viewer->uploadedSize()?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$Viewer->downloadedSize()?>" />
                        <input type="hidden" id="current_rr" value="<?=$Viewer->requiredRatio()?>" />
                        <input id="total_bounty" type="hidden" value="<?= $request->bountyTotal() ?>" />
                        <?= REQUEST_TAX > 0
                            ? 'Bounty after tax: <strong><span id="bounty_after_tax">' . sprintf("%0.2f", 100 * (1 - REQUEST_TAX)) . ' MiB</span></strong><br />'
                            : '<span id="bounty_after_tax" style="display: none;">' . sprintf("%0.2f", 100 * (1 - REQUEST_TAX)) . ' MiB</span>'
                        ?>
                        If you add the entered <strong><span id="new_bounty">0 MiB</span></strong> of bounty, your new stats will be: <br />
                        Uploaded: <span id="new_uploaded"><?= byte_format($Viewer->uploadedSize()) ?></span><br />
                        Ratio: <span id="new_ratio"><?= ratio_html($Viewer->uploadedSize(), $Viewer->downloadedSize()) ?></span>
                        <input type="button" id="button" value="Vote!" disabled="disabled" onclick="Vote();" />
                    </form>
                </td>
            </tr>
<?php } ?>
            <tr id="bounty">
                <td class="label">Bounty</td>
                <td id="formatted_bounty"><?= byte_format($request->bountyTotal()) ?></td>
            </tr>
<?php if ($request->isFilled()) { ?>
            <tr>
                <td class="label">Filled</td>
                <td>
                    <strong><a href="torrents.php?torrentid=<?= $request->torrentId() ?>">Yes</a></strong>, by user <?= $filler->username() ?>
<?php       if (in_array($Viewer->id(), [$request->userId(), $request->fillerId()]) || $Viewer->permitted('site_moderate_requests')) { ?>
                        <strong><a href="requests.php?action=unfill&amp;id=<?=$requestId?>" class="brackets">Unfill</a></strong> Unfilling a request without a <a href="/rules.php?p=requests">valid, nontrivial reason</a> will result in a warning.
<?php       } ?>
                </td>
            </tr>
<?php } else { ?>
            <tr>
                <td class="label" valign="top">Fill request</td>
                <td>
                    <form class="edit_form" name="request" action="" method="post">
                        <div class="field_div">
                            <input type="hidden" name="action" value="takefill" />
                            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                            <input type="hidden" name="requestid" value="<?=$requestId?>" />
                            <input type="text" size="50" name="link"<?=(!empty($Link) ? " value=\"$Link\"" : '')?> />
                            <br />
                            <strong>Must be the permalink [PL] of the torrent<br />(e.g. <?=SITE_URL?>/torrents.php?torrentid=nnn).</strong>
                        </div>
<?php   if ($Viewer->permitted('site_moderate_requests')) { ?>
                        <div class="field_div">
                            For user: <input type="text" size="25" name="user"<?= !is_null($filler) ? ' value="' . html_escape($filler->username()) . '"' : '' ?> />
                        </div>
<?php   } ?>
                        <div class="submit_div">
                            <input type="submit" value="Fill request" />
                        </div>
                    </form>
                </td>
            </tr>
<?php } ?>
        </table>
        <div class="box box2 box_request_desc">
            <div class="head"><strong>Description</strong></div>
            <div class="pad"><?= Text::full_format( $request->description()) ?></div>
        </div>
    <div id="request_comments">
<?= $Twig->render('comment/thread.twig', [
    'action'    => 'take_post',
    'id'        => $requestId,
    'comment'   => $commentPage,
    'name'      => 'pageid',
    'paginator' => $paginator,
    'subbed'    => $isSubscribed,
    'textarea'  => (new Gazelle\Util\Textarea('quickpost', '', 90, 8))->setPreviewManual(true),
    'url'       => $_SERVER['REQUEST_URI'],
    'url_stem'  => 'comments.php?page=requests',
    'userMan'   => $userMan,
    'viewer'    => $Viewer,
]) ?>
        </div>
    </div>
</div>
<?php
View::show_footer();
