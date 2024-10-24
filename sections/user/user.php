<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

use Gazelle\Enum\UserTokenType;
use Gazelle\User\Vote;

$userMan = new Gazelle\Manager\User();
$user = $userMan->findById((int)$_GET['id']);
if (is_null($user)) {
    header("Location: log.php?search=User+" . (int)$_GET['id']);
    exit;
}

$userId      = $user->id();
$username    = $user->username();
$Class       = $user->primaryClass();
$donor       = new Gazelle\User\Donor($user);
$userBonus   = new Gazelle\User\Bonus($user);
$viewerBonus = new Gazelle\User\Bonus($Viewer);
$history     = new Gazelle\User\History($user);
$limiter     = new Gazelle\User\UserclassRateLimit($user);
$donorMan    = new Gazelle\Manager\Donation();
$ipv4        = new Gazelle\Manager\IPv4();
$tgMan       = (new Gazelle\Manager\TGroup())->setViewer($Viewer);
$resetToken  = $Viewer->permitted('users_mod')
    ? (new Gazelle\Manager\UserToken())->findByUser($user, UserTokenType::password)
    : false;

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
    $FL_OTHER_tokens = $viewerBonus->purchaseTokenOther($user, $match[1], $_POST['message'] ?? '');
    if (!$FL_OTHER_tokens) {
        error('Purchase of tokens not concluded. Either you lacked funds or they have chosen to decline FL tokens.');
    }
}

if ($userId == $Viewer->id()) {
    $Preview = (bool)($_GET['preview'] ?? false);
    $OwnProfile = !$Preview;
    $user->forceCacheFlush(true);
} else {
    $OwnProfile = false;
    // Don't allow any kind of previewing on other profiles
    $Preview = false;
}
$previewer = $Preview ? $userMan->findById(PARANOIA_PREVIEW_USER) : $Viewer;
$Paranoia  = $Preview ? explode(',', $_GET['paranoia']) : $user->paranoia();

function check_paranoia_here(?string $Setting): int|false {
    global $Paranoia, $Class, $userId, $Preview;
    if (!$Setting) {
        return PARANOIA_ALLOWED;
    }
    if ($Preview) {
        return check_paranoia($Setting, $Paranoia ?? [], $Class);
    } else {
        return check_paranoia($Setting, $Paranoia ?? [], $Class, $userId);
    }
}

// Image proxy CTs
$DisplayCustomTitle = !empty($user->title())
    ? preg_replace_callback('/src=("?)(http.+?)(["\s>])/',
        fn ($m) => 'src=' . $m[1] . image_cache_encode($m[2]) . $m[3], $user->title())
    : $user->title();

View::show_header($username, [
    'js' => 'bbcode,comments,jquery.imagesloaded,jquery.wookmark,lastfm,requests,user'
        . ($Viewer->isStaff() ? ',info_paster' : '')
        . ($Viewer->permitted('users_view_ips') ? ',resolve-ip' : '')
        . ($Viewer->permitted('users_mod') ? ',reports' : ''),
    'css' => 'tiles'
]);
echo $Twig->render('user/header.twig', [
    'bonus'      => $userBonus,
    'donor'      => $donor,
    'freeleech'  => [
        'item'   => $OwnProfile ? [] : $viewerBonus->otherList(),
        'other'  => $FL_OTHER_tokens ?? null,
        'latest' => $viewerBonus->otherLatest($user),
    ],
    'friend'       => new Gazelle\User\Friend($Viewer),
    'preview_user' => $previewer,
    'user'         => $user,
    'userMan'      => $userMan,
    'viewer'       => $Viewer,
]);

echo $Twig->render('user/sidebar.twig', [
    'ancestry'      => $userMan->ancestry($user),
    'applicant'     => new Gazelle\Manager\Applicant(),
    'invite_source' => $Viewer->permitted('admin_manage_invite_source')
        ? (new Gazelle\Manager\InviteSource())->findSourceNameByUser($user) : null,
    'next_class'    => $user->nextClass($userMan),
    'user'          => $user,
    'viewer'        => $Viewer,
]);

// Last.fm statistics and comparability
$lastfmInfo = (new Gazelle\Util\LastFM())->userInfo($user);
if ($lastfmInfo) {
    echo $Twig->render('user/lastfm.twig', [
        'can_reload'  => ($OwnProfile && $Cache->get_value("lastfm_clear_cache_$userId") === false) || $Viewer->permitted('users_mod'),
        'info'        => $lastfmInfo,
        'own_profile' => $OwnProfile,
    ]);
}

$vote             = new Vote($user);
$stats            = $user->stats();
$Uploads          = check_paranoia_here('uploads+') ? $stats->uploadTotal() : 0;
$rank = new Gazelle\UserRank(
    new Gazelle\UserRank\Configuration(RANKING_WEIGHT),
    [
        'uploaded'   => $user->uploadedSize(),
        'downloaded' => $user->downloadedSize(),
        'uploads'    => $Uploads,
        'requests'   => $stats->requestBountyTotal(),
        'posts'      => $stats->forumPostTotal(),
        'bounty'     => $stats->requestVoteSize(),
        'artists'    => check_paranoia_here('artistsadded') ? $stats->artistAddedTotal() : 0,
        'collage'    => check_paranoia_here('collagecontribs+') ? $stats->collageTotal() : 0,
        'votes'      => $vote->userTotal(Vote::UPVOTE | Vote::DOWNVOTE),
        'bonus'      => $userBonus->pointsSpent(),
        'comment-t'  => check_paranoia_here('torrentcomments++') ? $stats->commentTotal('torrents') : 0,
    ],
);

$byteFormatter = fn ($value) => byte_format($value);
$numberFormatter = fn ($value) => number_format($value);

$statList = [
    // [dimension, permission, title, formatter, tooltip suffix]
    ['uploaded', 'uploaded', 'Data uploaded', $byteFormatter, 'uploaded'],
    ['downloaded', 'downloaded', 'Data downloaded', $byteFormatter, 'downloaded'],
    ['uploads', 'uploads+', 'Torrents uploaded', $numberFormatter, 'uploads'],
    ['requests', 'requestsfilled_count', 'Requests filled', $numberFormatter, 'filled'],
    ['bounty', 'requestsvoted_bounty', 'Request votes', $byteFormatter, 'spent'],
    ['posts', null, 'Forum posts made', $numberFormatter, 'posts'],
    ['comment-t', 'torrentcomments++', 'Torrent comments', $numberFormatter, 'posted'],
    ['collage', 'collagecontribs+', 'Collage contributions', $numberFormatter, 'contributions'],
    ['artists', 'artistsadded', 'Artists added', $numberFormatter, 'added'],
    ['votes', null, 'Release votes cast', $numberFormatter, 'votes'],
]
?>
        <div class="box box_info box_userinfo_percentile">
            <div class="head colhead_dark">Percentile Rankings (hover for values)</div>
            <ul class="stats nobullet">
<?php
foreach ($statList as $item) {
    if (($Override = check_paranoia_here($item[1]))) {
?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?= $item[3]($rank->raw($item[0])) ?> <?= $item[4] ?>"><?= $item[2] ?>: <?= $rank->rank($item[0]) ?></li>
<?php
    }
}
if ($OwnProfile || $Viewer->permitted('admin_bp_history')) { ?>
                <li class="tooltip<?= !$OwnProfile && $Viewer->permitted('admin_bp_history') ? ' paranoia_override' : '' ?>" title="<?=number_format($rank->raw('bonus')) ?> spent">Bonus points spent: <?= $rank->rank('bonus') ?></li>
<?php
}
if ($user->propertyVisibleMulti($previewer, ['artistsadded', 'collagecontribs+', 'downloaded', 'requestsfilled_count', 'requestsvoted_bounty', 'torrentcomments++', 'uploaded', 'uploads+', ])) {
?>
                <li<?= $user->classLevel() >= 900 ? ' title="Infinite"' : '' ?>><strong>Overall rank: <?= is_null($rank->score())
                    ? 'Server busy'
                    : ($user->classLevel() >= 900 ? '&nbsp;∞' : number_format($rank->score() * $user->rankFactor())) ?></strong></li>
<?php } ?>
            </ul>
        </div>
<?php if ($Viewer->permitted('users_mod') || $Viewer->permitted('users_view_ips') || $Viewer->permitted('users_view_keys')) { ?>
        <div class="box box_info box_userinfo_history">
            <div class="head colhead_dark">History</div>
            <ul class="stats nobullet">
<?php if ($Viewer->permitted('users_view_email')) { ?>
                <li>Emails: <?=number_format($history->emailTotal())?> <a href="userhistory.php?action=email&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php
    }
    if ($Viewer->permitted('users_view_ips')) {
?>
                <li>IPs: <?=number_format($ipv4->userTotal($user))?> <a href="userhistory.php?action=ips&amp;userid=<?=$userId?>" class="brackets">View</a>&nbsp;<a href="userhistory.php?action=ips&amp;userid=<?=$userId?>&amp;usersonly=1" class="brackets">View users</a></li>
<?php   if ($Viewer->permitted('users_mod')) { ?>
                <li>Tracker IPs: <?=number_format($user->trackerIPCount())?> <a href="userhistory.php?action=tracker_ips&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php
        }
    }
    if ($Viewer->permitted('users_view_keys')) {
?>
                <li>Announce keys: <?=number_format($user->announceKeyCount())?> <a href="userhistory.php?action=passkeys&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php
    }
    if ($Viewer->permitted('users_mod')) {
        if ($resetToken) {
?>
                <li><span class="tooltip" title="User requested a password reset by email">Password reset expiry: <?= time_diff($resetToken->expiry()) ?></li>
<?php   } ?>
                <li>Password history: <?=number_format($user->passwordCount())?> <a href="userhistory.php?action=passwords&amp;userid=<?=$userId?>" class="brackets">View</a></li>
                <li>Stats: N/A <a href="userhistory.php?action=stats&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php } ?>
            </ul>
        </div>
<?php } ?>

<?php

if (check_paranoia_here('snatched')) {
    echo $Twig->render('user/tag-snatch.twig', [
        'user' => $user,
    ]);
}

echo $Twig->render('user/sidebar-stats.twig', [
    'prl'            => $limiter,
    'upload_total'   => $Uploads,
    'user'           => $user,
    'viewer'         => $Viewer,
    'visible'        => [
        'collages+'             => check_paranoia_here('collages+'),
        'collages'              => check_paranoia_here('collages'),
        'collagescontrib+'      => check_paranoia_here('collagecontribs+'),
        'collagecontribs'       => check_paranoia_here('collagecontribs'),
        'downloaded'            => $OwnProfile || $Viewer->permitted('site_view_torrent_snatchlist'),
        'invitedcount'          => check_paranoia_here('invitedcount'),
        'leeching+'             => check_paranoia_here('leeching+'),
        'leeching'              => check_paranoia_here('leeching'),
        'perfectflacs+'         => check_paranoia_here('perfectflacs+'),
        'perfectflacs'          => check_paranoia_here('perfectflacs'),
        'seeding+'              => check_paranoia_here('seeding+'),
        'seeding'               => check_paranoia_here('seeding'),
        'snatched+'             => check_paranoia_here('snatched+'),
        'snatched'              => check_paranoia_here('snatched'),
        'torrentcomments+'      => check_paranoia_here('torrentcomments+'),
        'torrentcomments'       => check_paranoia_here('torrentcomments'),
        'requestsfilled_list'   => check_paranoia_here('requestsfilled_list'),
        'requestsfilled_count'  => check_paranoia_here('requestsfilled_count'),
        'requestsfilled_bounty' => check_paranoia_here('requestsfilled_bounty'),
        'requestsvoted_list'    => check_paranoia_here('requestsvoted_list'),
        'requestsvoted_count'   => check_paranoia_here('requestsvoted_count'),
        'requestsvoted_bounty'  => check_paranoia_here('requestsvoted_bounty'),
        'uniquegroups+'         => check_paranoia_here('uniquegroups+'),
        'uniquegroups'          => check_paranoia_here('uniquegroups'),
        'uploads+'              => check_paranoia_here('uploads+'),
        'uploads'               => check_paranoia_here('uploads'),
    ],
]);

if ($Viewer->permitted("users_mod") || $OwnProfile || $donor->isVisible()) {
    echo $Twig->render('donation/stats.twig', [
        'donor'  => $donor,
        'viewer' => $Viewer,
    ]);
}
?>
    </div>
    <div class="main_column">
<?php
if ($Viewer->permitted('users_mod') && $user->onRatioWatch()) {
    echo $Twig->render('user/ratio-watch.twig', [
        'user' => $user,
    ]);
}
?>
        <div class="box">
            <div class="head">
                <?= html_escape($user->profileTitle()) ?>
                <span style="float: right;"><a href="#" onclick="$('#profilediv').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>&nbsp;
            </div>
            <div class="pad profileinfo" id="profilediv">
                <?= $user->profileInfo() ? Text::full_format($user->profileInfo()) : 'This profile is currently empty.' ?>
            </div>
        </div>
<?php
foreach (range(1, 4) as $level) {
    $profileInfo = $donor->profileInfo($level);
    if (!empty($profileInfo)) {
?>
    <div class="box">
        <div class="head">
            <?= html_escape($donor->profileTitle($level) ?? "Extra Info $level") ?>
            <span style="float: right;"><a href="#" onclick="$('#profilediv_<?= $level ?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>
        </div>
        <div class="pad profileinfo" id="profilediv_<?= $level ?>"><?= Text::full_format($profileInfo) ?></div>
    </div>
<?php
    }
}

if (check_paranoia_here('snatched')) {
    echo $Twig->render('user/recent.twig', [
        'id'     => $userId,
        'recent' => array_map(fn ($id) => $tgMan->findById($id), $user->snatch()->recentSnatchList()),
        'title'  => 'Snatches',
        'type'   => 'snatched',
        'thing'  => 'snatches',
    ]);
}

if (check_paranoia_here('uploads')) {
    echo $Twig->render('user/recent.twig', [
        'id'     => $userId,
        'recent' => array_map(fn ($id) => $tgMan->findById($id), $user->recentUploadList()),
        'title'  => 'Uploads',
        'type'   => 'uploaded',
        'thing'  => 'uploads',
    ]);
}

if ($OwnProfile || !$user->hasAttr('hide-vote-recent') || $Viewer->permitted('view-release-votes')) {
    echo $Twig->render('user/recent-vote.twig', [
        'recent'    => $vote->recent($tgMan),
        'show_link' => $OwnProfile || !$user->hasAttr('hide-vote-history') || $Viewer->permitted('view-release-votes'),
        'user_id'   => $userId,
    ]);
}

echo $Twig->render('user/collage-list.twig', [
    'list'    => (new Gazelle\Manager\Collage())->findPersonalByUser($user),
    'manager' => $tgMan,
]);

// Linked accounts
if ($Viewer->permitted('users_linked_users')) {
    echo $Twig->render('user/linked.twig', [
        'hash'      => sha1($comments ?? ''),
        'user_link' => (new Gazelle\User\UserLink($user))->info(),
        'user'      => $user,
        'viewer'    => $Viewer,
    ]);
}

if ($Viewer->permitted('users_view_invites')) {
    $tree = new Gazelle\User\InviteTree($user, $userMan);
    if ($tree->hasInvitees()) {
?>
        <div class="box" id="invitetree_box">
            <div class="head">
                Invite Tree <a href="#" onclick="$('#invitetree').gtoggle(); return false;" class="brackets">View</a>
            </div>
            <div id="invitetree" class="hidden">
                <?= $Twig->render('user/invite-tree.twig', [
                    ...$tree->details($Viewer),
                    'user'   => $user,
                    'viewer' => $Viewer,
                ]) ?>
            </div>
        </div>
<?php
    }
}

if ($Viewer->permitted('users_give_donor')) {
    echo $Twig->render('donation/history.twig', [
        'history' => $donor->historyList(),
    ]);
}

if (!$Viewer->disableRequests() && $user->propertyVisible($previewer, 'requestsvoted_list')) {
    echo $Twig->render('request/user-unfilled.twig', [
        'bounty' => $Viewer->ordinal()->value('request-bounty-vote'),
        'list'   => (new Gazelle\Manager\Request())->findUnfilledByUser($user, 100),
        'viewer' => $Viewer,
    ]);
}

if ($Viewer->permitted('users_mod') || $Viewer->isStaffPMReader()) {
    echo $Twig->render('admin/staffpm-list.twig', [
        'list' => (new Gazelle\Staff($Viewer))->userStaffPmList($user),
    ]);
}

if ($Viewer->permitted('admin_reports')) {
    $reports = (new Gazelle\Manager\Report($userMan))->findByReportedUser($user);
    if ($reports) {
        echo $Twig->render('admin/user-reports-list.twig', [
            'list' => $reports
        ]);
    }
}

// Displays a table of forum warnings viewable only to Forum Moderators
if ($Viewer->permitted('users_warn')) {
    $ForumWarnings = $user->forumWarning();
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

if ($Viewer->permitted('users_auto_reports')) {
    $raTypeMan = new \Gazelle\Manager\ReportAutoType();
    $raSearch = new Gazelle\Search\ReportAuto(new \Gazelle\Manager\ReportAuto($raTypeMan), $raTypeMan);
    $openReports = $raSearch->setUser($user)->setState(\Gazelle\Enum\ReportAutoState::open)->userTotalList($userMan);
    if ($openReports && $openReports[0][1]) { ?>
<div class="box">
    <div class="head">
        <a href="report_auto.php?userid=<?=$user->id()?>"><?=$openReports[0][1]?> open automated report<?=plural($openReports[0][1])?></a>
    </div>
</div>
<?php
    }
}

echo $Twig->render('user/main-column.twig', [
    'asn'           => new Gazelle\Search\ASN(),
    'class_list'    => $userMan->classLevelList(),
    'donor'         => $donor,
    'forum_man'     => new Gazelle\Manager\Forum(),
    'history'       => $history,
    'invite_source' => (new Gazelle\Manager\InviteSource())->inviterConfiguration($user),
    'is_traced'     => $Viewer->permitted('admin_tracker') && (new Gazelle\Tracker())->isTraced($user),
    'prl'           => $limiter,
    'user'          => $user,
    'viewer'        => $Viewer,
]);
