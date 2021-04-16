<?php

$userMan = new Gazelle\Manager\User;
$User = $userMan->findById((int)$_GET['id']);
$viewer = new Gazelle\User($LoggedUser['ID']);
if (is_null($User)) {
    header("Location: log.php?search=User+" . (int)$_GET['id']);
    exit;
}
$UserID = $User->id();
$Username = $User->username();
$Bonus = new Gazelle\Bonus;
$donorMan = new Gazelle\Manager\Donation;
$ClassLevels = $userMan->classLevelList();
$Classes = $userMan->classList();

if (!empty($_POST)) {
    authorize();
    foreach (['action', 'flsubmit', 'fltype'] as $arg) {
        if (!isset($_POST[$arg])) {
            error(403);
        }
    }
    if ($_POST['action'] !== 'fltoken' || $_POST['flsubmit'] !== 'Send') {
        error(403);
    }
    if (!preg_match('/^fl-(other-[1-4])$/', $_POST['fltype'], $match)) {
        error(403);
    }
    try {
        $FL_OTHER_tokens = $Bonus->purchaseTokenOther($LoggedUser['ID'], $UserID, $match[1]);
    } catch (Gazelle\Exception\BonusException $e) {
        if ($e->getMessage() == 'otherToken:no-gift-funds') {
            error('Purchase of tokens not concluded. Either you lacked funds or they have chosen to decline FL tokens.');
        } else {
            error(0);
        }
    }
}

if ($UserID == $LoggedUser['ID']) {
    $OwnProfile = true;
    $User->forceCacheFlush(true);
    $Preview = (int)($_GET['preview'] ?? 0);
    if ($Preview == 1) {
        $OwnProfile = false;
    }
    $FL_Items = [];
} else {
    $OwnProfile = false;
    //Don't allow any kind of previewing on others' profiles
    $Preview = 0;
    $FL_Items = $Bonus->getListOther($LoggedUser['BonusPoints']);
}
$FA_Key = null;

// Image proxy CTs
$DisplayCustomTitle = (check_perms('site_proxy_images') && !empty($User->title()))
    ? preg_replace_callback('/src=("?)(http.+?)(["\s>])/',
        function ($m) { return 'src=' . $m[1] . ImageTools::process($m[2]) . $m[3];}, $User->title())
    : $User->title();

if ($Preview == 1) {
    $Paranoia = explode(',', $_GET['paranoia']);
} else {
    $Paranoia = $User->paranoia();
}
$ParanoiaLevel = 0;
foreach ($Paranoia as $P) {
    $ParanoiaLevel++;
    if (strpos($P, '+') !== false) {
        $ParanoiaLevel++;
    }
}

function check_paranoia_here($Setting) {
    global $Paranoia, $Class, $UserID, $Preview;
    if ($Preview == 1) {
        return check_paranoia($Setting, $Paranoia, $Class);
    } else {
        return check_paranoia($Setting, $Paranoia, $Class, $UserID);
    }
}

$stats = $User->activityStats();
[$ClassRatio, $Buffer] = $User->buffer();

if ((defined('RECOVERY_DB') && !empty(RECOVERY_DB)) && ($OwnProfile || check_perms('users_mod'))) {
    $recovered = $DB->scalar("
        SELECT final FROM users_buffer_log WHERE opsid = ?
        ", $UserID
    );
} else {
    $recovered = null;
}

View::show_header($Username, "jquery.imagesloaded,jquery.wookmark,user,bbcode,requests,lastfm,comments,info_paster", "tiles");
echo G::$Twig->render('user/header.twig', [
    'auth'    => $LoggedUser['AuthKey'],
    'freeleech' => [
        'item'  => $FL_Items,
        'other' => $FL_OTHER_tokens ?? null,
    ],
    'hourly_rate'  => $Bonus->userHourlyRate($UserID),
    'preview_user' => $Preview ? $userMan->findById(PARANOIA_PREVIEW_USER) : $viewer,
    'recovered'    => $recovered,
    'user'         => $User,
    'userMan'      => $userMan,
    'viewer'       => $viewer,
]);
?>

<?php
    if ($OwnProfile || check_perms('users_mod')) {
        $nextClass = $User->nextClass();
        if ($nextClass) {
?>
        <div class="box box_info box_userinfo_nextclass">
            <div class="head colhead_dark"><a href="wiki.php?action=article&amp;name=userclasses">Next Class</a></div>
            <ul class="stats nobullet">
                <li>Class: <?=$nextClass['Class']?></li>
<?php
            foreach ($nextClass['Requirements'] as $key => $req) {
                [$current, $goal, $type] = $req;
                if ($goal === 0) {
                    continue;
                }

                switch ($type) {
                case 'time':
                    $percent = (time() - strtotime($current)) / $goal;
                    $current = Gazelle\Util\Time::timeDiff($current, 2, true, false, false, true);
                    $goal = $goal / (86400 * 7);
                    $goal = "$goal week" . plural($goal);
                    break;
                case 'float':
                    if ($current === '∞') {
                        $percent = 1;
                    } else {
                        $percent = $current / $goal;
                        $current = round($current, 2);
                    }
                    break;
                case 'int':
                    $percent = $current === '∞' ? 1 : $current / $goal;
                    break;
                case 'bytes':
                    $percent = $current / $goal;
                    $current = Format::get_size($current);
                    $goal = Format::get_size($goal);
                    break;
                }

                $percent = sprintf('<span class="tooltip %s" title="%s">%s</span>',
                    Format::get_ratio_color($percent),
                    round($percent * 100, 2) . '%',
                    round(min(1.0, $percent) * 100, 0) . '%'
                );
 ?>
                <li><?=$key?>: <?=$current?> / <?=$goal?> (<?=$percent?>)</li>
<?php } ?>
            </ul>
        </div>
<?php
        }
      }
// Last.fm statistics and comparability
$LastFMUsername = LastFM::get_lastfm_username($UserID);
if ($LastFMUsername)  {
    LastFMView::render_sidebar($LastFMUsername, $UserID, $OwnProfile);
}

if (check_paranoia_here('requestsfilled_count') || check_paranoia_here('requestsfilled_bounty')) {
    [$RequestsFilled, $TotalBounty] = $User->requestsBounty();
} else {
    $RequestsFilled = $TotalBounty = 0;
}
if (check_paranoia_here('requestsvoted_count') || check_paranoia_here('requestsvoted_bounty')) {
    [$RequestsVoted, $TotalSpent] = $User->requestsVotes();
    [$RequestsCreated, $RequestsCreatedSpent] = $User->requestsCreated();
} else {
    $RequestsVoted = $TotalSpent = $RequestsCreated = $RequestsCreatedSpent = 0;
}

$Uploads = check_paranoia_here('uploads+') ? $User->uploadCount() : 0;
$ArtistsAdded = check_paranoia_here('artistsadded') ? $User->artistsAdded() : 0;

$collageAdditions = check_paranoia_here('collagecontribs+') ? $User->collageAdditions() : 0;
$releaseVotes     = $User->releaseVotes();
$bonusPointsSpent = $User->bonusPointsSpent();
$torrentComments  = check_paranoia_here('torrentcomments++') ? $User->torrentCommentCount() : 0;
$rank = new Gazelle\UserRank(
    new Gazelle\UserRank\Configuration(RANKING_WEIGHT),
    [
        'uploaded'   => $stats['BytesUploaded'],
        'downloaded' => $stats['BytesDownloaded'],
        'uploads'    => $Uploads,
        'requests'   => $RequestsFilled,
        'posts'      => $User->forumPosts(),
        'bounty'     => $TotalSpent,
        'artists'    => $ArtistsAdded,
        'collage'    => $collageAdditions,
        'votes'      => $releaseVotes,
        'bonus'      => $bonusPointsSpent,
        'comment-t'  => $torrentComments,
    ]
);
function display_rank(Gazelle\UserRank $r, string $dimension) {
    return $r->rank($dimension) === false ? 'Server busy' : $r->rank($dimension);
}
?>
        <div class="box box_info box_userinfo_percentile">
            <div class="head colhead_dark">Percentile Rankings (hover for values)</div>
            <ul class="stats nobullet">
<?php    if (($Override = check_paranoia_here('uploaded'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($stats['BytesUploaded'])?> uploaded">Data uploaded: <?= display_rank($rank, 'uploaded') ?></li>
<?php
    }
    if (($Override = check_paranoia_here('downloaded'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($stats['BytesDownloaded'])?> downloaded">Data downloaded: <?= display_rank($rank, 'downloaded') ?></li>
<?php
    }
    if (($Override = check_paranoia_here('uploads+'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=number_format($Uploads)?> uploads">Torrents uploaded: <?= display_rank($rank, 'uploads') ?></li>
<?php
    }
    if (($Override = check_paranoia_here('requestsfilled_count'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=number_format($RequestsFilled)?> filled">Requests filled: <?= display_rank($rank, 'requests') ?></li>
<?php
    }
    if (($Override = check_paranoia_here('requestsvoted_bounty'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($TotalSpent)?> spent">Request votes: <?= display_rank($rank, 'bounty') ?></li>
<?php } ?>
                <li class="tooltip" title="<?=number_format($User->forumPosts())?> posts">Forum posts made: <?= display_rank($rank, 'posts') ?></li>
<?php
    if (($Override = check_paranoia_here('torrentcomments++'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?= number_format($torrentComments) ?> posted">Torrent comments: <?= display_rank($rank, 'comment-t') ?></li>
<?php
    }
    if (($Override = check_paranoia_here('collagecontribs+'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=number_format($collageAdditions)?> contributions">Collage contributions: <?= display_rank($rank, 'collage') ?></li>
<?php }
    if (($Override = check_paranoia_here('artistsadded'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=number_format($ArtistsAdded)?> added">Artists added: <?= display_rank($rank, 'artists') ?></li>
<?php } ?>
                <li class="tooltip" title="<?=number_format($releaseVotes)?> votes">Release votes cast: <?= display_rank($rank, 'votes') ?></li>
<?php
    if ($OwnProfile || check_perms('admin_bp_history')) { ?>
                <li class="tooltip<?= !$OwnProfile && check_perms('admin_bp_history') ? ' paranoia_override' : '' ?>" title="<?=number_format($bonusPointsSpent)?> spent">Bonus points spent: <?= display_rank($rank, 'bonus') ?></li>
<?php
    }
    if (check_paranoia_here(['artistsadded', 'collagecontribs+', 'downloaded', 'requestsfilled_count', 'requestsvoted_bounty', 'torrentcomments++', 'uploaded', 'uploads+', ])) { ?>
                <li<?= $User->classLevel() >= 900 ? ' title="Infinite"' : '' ?>><strong>Overall rank: <?= $rank->score() === false ? 'Server busy'
                    : $User->classLevel() >= 900 ? '&nbsp;&infin;' : number_format($rank->score() * $User->rankFactor()) ?></strong></li>
<?php    } ?>
            </ul>
        </div>
<?php
    if (check_perms('users_mod') || check_perms('users_view_ips') || check_perms('users_view_keys')) {
?>
        <div class="box box_info box_userinfo_history">
            <div class="head colhead_dark">History</div>
            <ul class="stats nobullet">
<?php        if (check_perms('users_view_email')) { ?>
                <li>Emails: <?=number_format($User->emailCount())?> <a href="userhistory.php?action=email&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
        }
        if (check_perms('users_view_ips')) {
?>
                <li>IPs: <?=number_format($User->siteIPCount())?> <a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>" class="brackets">View</a>&nbsp;<a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>&amp;usersonly=1" class="brackets">View users</a></li>
<?php            if (check_perms('users_view_ips') && check_perms('users_mod')) { ?>
                <li>Tracker IPs: <?=number_format($User->trackerIPCount())?> <a href="userhistory.php?action=tracker_ips&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
            }
        }
        if (check_perms('users_view_keys')) {
?>
                <li>Announce keys: <?=number_format($User->announceKeyCount())?> <a href="userhistory.php?action=passkeys&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
        }
        if (check_perms('users_mod')) {
?>
                <li>Passwords: <?=number_format($User->passwordCount())?> <a href="userhistory.php?action=passwords&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
                <li>Stats: N/A <a href="userhistory.php?action=stats&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php        } ?>
            </ul>
        </div>
<?php    } ?>
        <div class="box box_info box_userinfo_personal">
            <div class="head colhead_dark">Personal</div>
            <ul class="stats nobullet">
                <li>Class: <?=$ClassLevels[$User->classLevel()]['Name']?></li>
<?php if (($secondary = array_values($User->secondaryClasses()))) { ?>
                <li>
                    <ul class="stats">
<?php asort($secondary); foreach ($secondary as $name) { ?>
                        <li><?= $name ?></li>
<?php    } ?>
                    </ul>
                </li>
<?php
}
// An easy way for people to measure the paranoia of a user, for e.g. contest eligibility
if ($ParanoiaLevel == 0) {
    $ParanoiaLevelText = 'Off';
} elseif ($ParanoiaLevel == 1) {
    $ParanoiaLevelText = 'Very Low';
} elseif ($ParanoiaLevel <= 5) {
    $ParanoiaLevelText = 'Low';
} elseif ($ParanoiaLevel <= 20) {
    $ParanoiaLevelText = 'High';
} else {
    $ParanoiaLevelText = 'Very high';
}
?>
                <li>Paranoia level: <span class="tooltip" title="<?=$ParanoiaLevel?>"><?=$ParanoiaLevelText?></span></li>
<?php if (check_perms('users_view_email') || $OwnProfile) { ?>
                <li>Email: <a href="mailto:<?=display_str($User->email())?>"><?=display_str($User->email())?></a>
<?php   if (check_perms('users_view_email')) { ?>
                    <a href="user.php?action=search&amp;email_history=on&amp;email=<?=display_str($User->email())?>" title="Search" class="brackets tooltip">S</a>
<?php   } ?>
                </li>
<?php
}
if (check_perms('users_view_ips')) {
?>
                <li>IP: <?=Tools::display_ip($User->ipaddr())?></li>
                <li>Host: <?=Tools::get_host_by_ajax($User->ipaddr())?></li>
<?php
}

if (check_perms('users_view_keys') || $OwnProfile) {
?>
                <li>Passkey: <a href="#" id="passkey" onclick="togglePassKey('<?= display_str($User->announceKey()) ?>'); return false;" class="brackets">View</a></li>
<?php
}
if (check_perms('users_view_invites')) {
    if (is_null($User->inviter())) {
        $Invited = '<span style="font-style: italic;">Nobody</span>';
    } else {
        $Invited = '<a href="user.php?id=' . $User->inviter()->id() . '">' . $User->inviter()->username() . "</a>";
    }
?>
                <li>Invited by: <?=$Invited?></li>
                <li>Invites: <?= $User->disableInvites() ? 'X' : number_format($User->inviteCount()) ?>
                    <?= '(' . $User->pendingInviteCount() . ' in use)' ?></li>
<?php
}
$appMan = new Gazelle\Manager\Applicant;
if ($appMan->userIsApplicant($UserID) && (check_perms('admin_manage_applicants') || $OwnProfile)) {
?>
                <li>Roles applied for: <a href="/apply.php?action=view" class="brackets">View</a></li>
<?php
}
if ($OwnProfile || check_perms('users_mod') || $viewer->isFLS()) {
?>
                <li<?= !$OwnProfile ? ' class="paranoia_override"' : '' ?>>Torrent clients: <?=
                    implode('; ', $User->clients()) ?></li>
                <li<?= !$OwnProfile ? ' class="paranoia_override"' : '' ?>>Password age: <?= $User->passwordAge() ?></li>
<?php }
if ($OwnProfile || check_perms('users_override_paranoia')) { ?>
    <li>IRC Key: <?=strlen($User->IRCKey()) ? 'Yes' : 'No' ?></li>
<?php } ?>
            </ul>
        </div>
<?php
if (check_paranoia_here('snatched')) {
    echo G::$Twig->render('user/tag-snatch.twig', [
        'id'   => $UserID,
        'list' => $User->tagSnatchCounts(),
    ]);
}
require('community_stats.php');

if (check_perms("users_mod") || $OwnProfile || $User->donorVisible()) {
    echo G::$Twig->render('donation/stats.twig', [
        'is_donor'    => $User->isDonor(),
        'is_self'     => $OwnProfile,
        'is_mod'      => check_perms('users_mod'),
        'total_rank'  => $donorMan->totalRank($UserID),
        'current'     => $donorMan->rankLabel($UserID, true),
        'leaderboard' => $donorMan->leaderboardRank($UserID),
        'last'        => $donorMan->lastDonation($UserID),
        'expiry'      => $donorMan->rankExpiry($UserID),
    ]);
}
?>
    </div>
    <div class="main_column">
<?php if (check_perms('users_mod') && $User->onRatioWatch()) { ?>
        <div class="box">
            <div class="head">Ratio watch</div>
            <div class="pad">This user is currently on ratio watch and must upload <?=Format::get_size(($stats['BytesDownloaded'] * $stats['RequiredRatio']) - $stats['BytesUploaded'])?> in the next <?=time_diff($User->ratioWatchExpiry()) ?>, or their leeching privileges will be revoked. Amount downloaded while on ratio watch: <?=Format::get_size($stats['BytesDownloaded'] - $stats['RatioWatchDownload'])?></div>
        </div>
<?php } ?>
        <div class="box">
            <div class="head">
                <?= display_str($User->infoTitle()) ?>
                <span style="float: right;"><a href="#" onclick="$('#profilediv').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>&nbsp;
            </div>
            <div class="pad profileinfo" id="profilediv">
                <?= $User->infoProfile() ? Text::full_format($User->infoProfile()) : 'This profile is currently empty.' ?>
            </div>
        </div>
<?php
$EnabledRewards = $donorMan->enabledRewards($UserID);
$ProfileRewards = $donorMan->profileRewards($UserID);
for ($i = 1; $i <= 4; $i++) {
    if ($EnabledRewards['HasProfileInfo' . $i] && $ProfileRewards['ProfileInfo' . $i]) {
?>
    <div class="box">
        <div class="head" style="height: 13px;">
            <span style="float: left;"><?=!empty($ProfileRewards['ProfileInfoTitle' . $i]) ? display_str($ProfileRewards['ProfileInfoTitle' . $i]) : "Extra Profile " . ($i + 1)?></span>
            <span style="float: right;"><a href="#" onclick="$('#profilediv_<?=$i?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>
        </div>
        <div class="pad profileinfo" id="profilediv_<?= $i ?>"><?= Text::full_format($ProfileRewards['ProfileInfo' . $i]); ?></div>
    </div>
<?php
    }
}

if (check_paranoia_here('snatched')) {
    echo G::$Twig->render('user/recent.twig', [
        'id'     => $UserID,
        'recent' => $User->recentSnatches(),
        'title'  => 'Snatches',
        'type'   => 'snatched',
    ]);
}

if (check_paranoia_here('uploads')) {
    echo G::$Twig->render('user/recent.twig', [
        'id'     => $UserID,
        'recent' => $User->recentUploads(),
        'title'  => 'Uploads',
        'type'   => 'uploaded',
    ]);
}

$Collages = $User->personalCollages();
$FirstCol = true;
foreach ($Collages as $CollageInfo) {
    [$CollageID, $CName] = $CollageInfo;
    $DB->prepared_query('
        SELECT ct.GroupID,
            tg.WikiImage,
            tg.CategoryID
        FROM collages_torrents AS ct
        INNER JOIN torrents_group AS tg ON (tg.ID = ct.GroupID)
        WHERE ct.CollageID = ?
        ORDER BY ct.Sort
        LIMIT 5
        ', $CollageID
    );
    $Collage = $DB->to_array(false, MYSQLI_ASSOC, false);
?>
    <table class="layout recent" id="collage<?=$CollageID?>_box" cellpadding="0" cellspacing="0" border="0">
        <tr class="colhead">
            <td colspan="5">
                <span style="float: left;">
                    <?=display_str($CName)?> - <a href="collages.php?id=<?=$CollageID?>" class="brackets">See full</a>
                </span>
                <span style="float: right;">
                    <a href="#" onclick="$('#collage<?=$CollageID?>_box .images').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=$FirstCol ? 'Hide' : 'Show' ?></a>
                </span>
            </td>
        </tr>
        <tr class="images<?=$FirstCol ? '' : ' hidden'?>">
<?php   foreach ($Collage as $C) {
            $Group = Torrents::get_groups([$C['GroupID']], true, true, false);
            $Name = Artists::display_artists(['1' => $Group['Artists']], false, true) . $Group['Name'];
?>
            <td>
                <a href="torrents.php?id=<?= $C['GroupID'] ?>">
                    <img class="tooltip" title="<?= $Name ?>" src="<?=ImageTools::process($C['WikiImage'], true)?>" alt="<?= $Name ?>" width="107" />
                </a>
            </td>
<?php    } ?>
        </tr>
    </table>
<?php
    $FirstCol = false;
}
?>
    <!-- for the "jump to staff tools" button -->
    <a id="staff_tools"></a>
<?php

// Linked accounts
if (check_perms('users_edit_usernames')) {
    [$linkGroupId, $comments, $list] = (new Gazelle\Manager\UserLink($User))->info();
    echo G::$Twig->render('user/linked.twig', [
        'auth'     => $LoggedUser['AuthKey'],
        'comments' => $comments,
        'group_id' => $linkGroupId,
        'hash'     => sha1($comments),
        'list'     => $list,
        'user_id'  => $UserID,
    ]);
}

if (check_perms('users_view_invites')) {
    $tree = new Gazelle\InviteTree($UserID);
    if ($tree->hasInvitees()) {
?>
        <div class="box" id="invitetree_box">
            <div class="head">
                Invite Tree <a href="#" onclick="$('#invitetree').gtoggle(); return false;" class="brackets">View</a>
            </div>
            <div id="invitetree" class="hidden">
                <?= $tree->render(G::$Twig) ?>
            </div>
        </div>
<?php
    }
}

if (check_perms('users_give_donor')) {
    echo G::$Twig->render('donation/history.twig', [
        'history' => $donorMan->history($UserID),
    ]);
}

// Requests
if (empty($LoggedUser['DisableRequests']) && check_paranoia_here('requestsvoted_list')) {
    $SphQL = new SphinxqlQuery();
    $SphQLResult = $SphQL->select('id, votes, bounty')
        ->from('requests, requests_delta')
        ->where('userid', $UserID)
        ->where('torrentid', 0)
        ->order_by('votes', 'desc')
        ->order_by('bounty', 'desc')
        ->limit(0, 100, 100) // Limit to 100 requests
        ->query();
    if ($SphQLResult->has_results()) {
        $SphRequests = $SphQLResult->to_array('id', MYSQLI_ASSOC);
?>
        <div class="box" id="requests_box">
            <div class="head">
                Requests <a href="#" onclick="$('#requests').gtoggle(); return false;" class="brackets">View</a>
            </div>
            <div id="requests" class="request_table hidden">
                <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
                    <tr class="colhead_dark">
                        <td style="width: 48%;">
                            <strong>Request Name</strong>
                        </td>
                        <td>
                            <strong>Vote</strong>
                        </td>
                        <td>
                            <strong>Bounty</strong>
                        </td>
                        <td>
                            <strong>Added</strong>
                        </td>
                    </tr>
<?php
        $Row = 'a';
        $Requests = Requests::get_requests(array_keys($SphRequests));
        foreach ($SphRequests as $RequestID => $SphRequest) {
            $Request = $Requests[$RequestID];
            $VotesCount = $SphRequest['votes'];
            $Bounty = $SphRequest['bounty'] * 1024; // Sphinx stores bounty in kB
            $CategoryName = $Categories[$Request['CategoryID'] - 1];

            if ($CategoryName == 'Music') {
                $ArtistForm = Requests::get_artists($RequestID);
                $ArtistLink = Artists::display_artists($ArtistForm, true, true);
                $FullName = "$ArtistLink<a href=\"requests.php?action=view&amp;id=$RequestID\">{$Request['Title']} [{$Request['Year']}]</a>";
            } elseif ($CategoryName == 'Audiobooks' || $CategoryName == 'Comedy') {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\">{$Request['Title']} [{$Request['Year']}]</a>";
            } else {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\">{$Request['Title']}</a>";
            }
?>
                    <tr class="row<?=$Row === 'b' ? 'a' : 'b'?>">
                        <td>
                            <?=$FullName ?>
                            <div class="tags">
<?php
            $Tags = $Request['Tags'];
            $TagList = [];
            foreach ($Tags as $TagID => $TagName) {
                $TagList[] = "<a href=\"requests.php?tags=$TagName\">".display_str($TagName).'</a>';
            }
?>
                                <?= implode(', ', $TagList) ?>
                            </div>
                        </td>
                        <td>
                            <span id="vote_count_<?=$RequestID?>"><?=$VotesCount?></span>
<?php            if (check_perms('site_vote')) { ?>
                            &nbsp;&nbsp; <a href="javascript:Vote(0, <?=$RequestID?>)" class="brackets">+</a>
<?php            } ?>
                        </td>
                        <td>
                            <span id="bounty_<?=$RequestID?>"><?=Format::get_size($Bounty)?></span>
                        </td>
                        <td>
                            <?=time_diff($Request['TimeAdded']) ?>
                        </td>
                    </tr>
<?php        } ?>
                </table>
            </div>
        </div>
<?php
    }
}

if (check_perms('users_mod') || $viewer->isStaffPMReader()) {
    echo G::$Twig->render('admin/staffpm-list.twig', [
        'list' => (new Gazelle\Staff($User))->userStaffPmList($LoggedUser['ID']),
    ]);
}

// Displays a table of forum warnings viewable only to Forum Moderators
if ($User->isStaff() && check_perms('users_warn')) {
    $ForumWarnings = $User->forumWarning();
    if ($ForumWarnings) {
?>
<div class="box">
    <div class="head">Forum warnings</div>
    <div class="pad">
        <div id="forumwarningslinks" class="AdminComment" style="width: 98%;"><?=Text::full_format($ForumWarnings)?></div>
    </div>
</div>
<?php
    }
}

if (check_perms('users_mod') || $viewer->isStaff()) { ?>
        <form class="manage_form" name="user" id="form" action="user.php" method="post">
        <input type="hidden" name="action" value="moderate" />
        <input type="hidden" name="userid" value="<?=$UserID?>" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />

        <div class="box box2" id="staff_notes_box">
            <div class="head">
                Staff Notes
                <a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;" class="brackets">Edit</a>
                <a href="#" onclick="$('#staffnotes').gtoggle(); return false;" class="brackets">Toggle</a>
            </div>
            <div id="staffnotes" class="pad">
                <input type="hidden" name="comment_hash" value="<?= $User->info()['CommentHash'] ?>" />
                <div id="admincommentlinks" class="AdminComment" style="width: 98%;"><?=Text::full_format($User->staffNotes())?></div>
                <textarea id="admincomment" onkeyup="resize('admincomment');" class="AdminComment hidden" name="AdminComment" cols="65" rows="26" style="width: 98%;"><?=display_str($User->staffNotes())?></textarea>
                <a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;" class="brackets">Toggle edit</a>
                <script type="text/javascript">
                    resize('admincomment');
                </script>
            </div>
        </div>

<table class="layout" id="user_info_box">
    <tr class="colhead">
        <td colspan="2">
            User Information
        </td>
    </tr>

<?php
    if (check_perms('users_edit_usernames')) {
        echo G::$Twig->render('user/edit-username.twig', [
            'username' => $Username,
        ]);
    }

    if (check_perms('users_edit_titles')) {
        echo G::$Twig->render('user/edit-title.twig', [
            'title' => $User->title(),
        ]);
    }

    if (check_perms('users_promote_below') || check_perms('users_promote_to', $viewer->classLevel() - 1)) {
?>
            <tr>
                <td class="label">Primary class:</td>
                <td>
                    <select name="Class">
<?php
        foreach ($ClassLevels as $CurClass) {
            if ($CurClass['Secondary']) {
                continue;
            }
            elseif (!$OwnProfile && !check_perms('users_promote_to', $viewer->classLevel() - 1) && $CurClass['Level'] == $viewer->effectiveClass()) {
                break;
            }
            elseif ($CurClass['Level'] > $viewer->effectiveClass()) {
                break;
            }
            if ($User->classLevel() == $CurClass['Level']) {
                $Selected = ' selected="selected"';
            } else {
                $Selected = '';
            }
?>
                        <option value="<?=$CurClass['ID']?>"<?=$Selected?>><?=$CurClass['Name'].' ('.$CurClass['Level'].')'?></option>
<?php        } ?>
                    </select>
                </td>
            </tr>
<?php
    }

    if (check_perms('users_promote_below') || check_perms('users_promote_to')) {
        echo G::$Twig->render('user/edit-secondary-class.twig', [
            'permission' => $User->secondaryClassesList(),
        ]);
    }

    if (check_perms('users_make_invisible')) {
        echo G::$Twig->render('user/edit-peer-visibility.twig', [
            'is_visible' => $User->isVisible(),
        ]);
    }

    if (check_perms('admin_rate_limit_manage')) {
        echo G::$Twig->render('user/edit-rate-limit.twig', [
            'unlimited' => $User->hasUnlimitedDownload(),
        ]);
    }

    if (check_perms('users_edit_ratio') || (check_perms('users_edit_own_ratio') && $OwnProfile)) {
        echo G::$Twig->render('user/edit-buffer.twig', [
            'up'             => $stats['BytesUploaded'],
            'down'           => $stats['BytesDownloaded'],
            'bonus'          => $stats['BonusPoints'],
            'collages'       => $User->paidPersonalCollages(),
            'donor_collages' => $donorMan->personalCollages($UserID),
        ]);
    }

    if (check_perms('users_edit_invites')) {
        echo G::$Twig->render('user/edit-invite.twig', [
            'amount' => $User->inviteCount(),
        ]);
    }

    if (check_perms('admin_manage_user_fls')) {
        echo G::$Twig->render('user/edit-fltoken.twig', [
            'amount' => $User->tokenCount(),
        ]);
    }

    if (check_perms('admin_manage_fls') || (check_perms('users_mod') && $OwnProfile)) {
        echo G::$Twig->render('user/edit-remark.twig', [
            'remark' => $User->supportFor(),
        ]);
    }

    if (check_perms('users_edit_reset_keys')) {
        echo G::$Twig->render('user/edit-reset.twig');
    }

    if (check_perms('users_edit_password')) {
        echo G::$Twig->render('user/edit-password.twig', [
            'key_2fa' => $User->TFAKey(),
            'user_id' => $UserID,
        ]);
    }
?>
</table>

<?php
    if (check_perms('users_disable_posts') || check_perms('users_disable_any')) {
        echo G::$Twig->render('user/edit-privileges.twig', [
            'email'          => $User->emailHistory(),
            'is_unconfirmed' => $User->isUnconfirmed(),
            'is_enabled'     => $User->isEnabled(),
            'is_disabled'    => $User->isDisabled(),
            'forum' => [
                'restricted' => implode(',', $User->forbiddenForums()),
                'permitted'  => implode(',', $User->permittedForums()),
            ],
            'permission' => [
                'disable_any' => check_perms('users_disable_any'),
                'delete_user' => check_perms('users_delete_users'),
            ],
            'disable' => [
                'avatar'  => !$User->showAvatars(),
                'bonus'   => $User->disableBonusPoints(),
                'forum'   => $User->disableForums(),
                'invite'  => $User->disableInvites(),
                'irc'     => $User->disableIrc(),
                'leech'   => !$User->canLeech(),
                'pm'      => $User->disablePM(),
                'posting' => $User->disablePosting(),
                'request' => $User->disableRequests(),
                'tag'     => $User->disableTagging(),
                'upload'  => $User->disableUpload(),
                'wiki'    => $User->disableWiki(),
            ],
        ]);
    }

    if (check_perms('users_give_donor')) {
        echo G::$Twig->render('donation/admin-panel.twig', [
            'rank' => $donorMan->rank($UserID),
            'special_rank' => $donorMan->specialRank($UserID),
            'total_rank' => $donorMan->totalRank($UserID),
        ]);
    }

    if (check_perms('users_warn')) {
        echo G::$Twig->render('user/edit-warn.twig', [
            'is_warned' => $User->isWarned(),
            'until'     => $User->warningExpiry(),
        ]);
    }

    if (check_perms('users_disable_any')) {
        echo G::$Twig->render('user/edit-lock.twig', [
            'is_locked'  => $User->isLocked(),
            'staff_lock' => STAFF_LOCKED,
            'can_logout' => check_perms('users_logout'),
        ]);
    }

    echo G::$Twig->render('user/edit-submit.twig');
?>
        </form>
<?php } /* check_perms('users_mod') */ ?>
    </div>
</div>
<?php

View::show_footer();
