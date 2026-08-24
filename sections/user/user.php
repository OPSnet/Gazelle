<?php

/**
 * phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
 * phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Gazelle\Cache $Cache
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();
$user = $userMan->findById((int)$_GET['id']);
if (is_null($user)) {
    header("Location: log.php?search=User+" . (int)$_GET['id']);
    exit;
}

$userId      = $user->id;
$username    = $user->username();
$Class       = $user->primaryClass();
$donor       = new User\Donor($user);
$userBonus   = new User\Bonus($user);
$viewerBonus = new User\Bonus($Viewer);
$limiter     = new User\UserclassRateLimit($user);
$bonusMan    = new Manager\Bonus();
$ipv4        = new Manager\IPv4();
$tgMan       = new Manager\TGroup();

$resetToken  = $Viewer->permitted('users_mod')
    ? new Manager\UserToken()->findByUser($user, Enum\UserTokenType::password)
    : null;

if (isset($_POST['flsubmit'], $_POST['fltype']) && ($_POST['action'] ?? '') === 'fltoken') {
    authorize();
    if (str_starts_with($_POST['fltype'], 'fl-')) {
        // backwards compatibility
        $_POST['fltype'] = substr($_POST['fltype'], 3);
    }
    $status = $bonusMan->purchaseTokenOther(
        $Viewer, $user, $_POST['fltype'], $_POST['message'] ?? ''
    );
    if ($status === Enum\BonusItemPurchaseStatus::success) {
        header("Location: {$user->location()}");
        exit;
    } else {
        Error400::error(
            match ($status) { /* @phpstan-ignore match.unhandled (alreadyPurchased) */
                Enum\BonusItemPurchaseStatus::declined
                    => "{$user->username()} does not wish to receive tokens",
                Enum\BonusItemPurchaseStatus::insufficientFunds
                    => "You do not have enough bonus points to buy that",
                Enum\BonusItemPurchaseStatus::forbidden
                    => "{$user->username()} cannot receive tokens from you",
                Enum\BonusItemPurchaseStatus::incomplete
                    => "Something was wrong with the parameters provided",
            }
        );
    }
}

if ($userId == $Viewer->id) {
    $Preview = (bool)($_GET['preview'] ?? false);
    $OwnProfile = !$Preview;
    $user->forceCacheFlush(true);
} else {
    $OwnProfile = false;
    // Don't allow any kind of previewing on other profiles
    $Preview = false;
}
$profileViewer = $Preview ? $userMan->findById(PARANOIA_PREVIEW_USER) : $Viewer;
if ($Preview) {
    $user->setOverrideParanoia(Util\Paranoia::calculate(explode(',', $_GET['paranoia'] ?? '')));
}

View::show_header($username, [
    'js' => 'vendor/jquery.imagesloaded,vendor/jquery.wookmark,bbcode,comments,lastfm,requests,user'
        . ($profileViewer->isStaff() ? ',info_paster' : '')
        . ($profileViewer->permitted('users_view_ips') ? ',resolve-ip' : '')
        . ($profileViewer->permitted('users_mod') ? ',reports' : ''),
    'css' => 'tiles'
]);
echo $Twig->render('user/header.twig', [
    'bonus'      => $userBonus,
    'donor'      => $donor,
    'freeleech'  => [
        'offer'  => $OwnProfile || $Preview ? [] : $bonusMan->offerTokenOther($profileViewer, $user),
        'latest' => $viewerBonus->otherLatest($user),
    ],
    'friend'       => new User\Friend($profileViewer),
    'user'         => $user,
    'user_buffer'  => $userMan->userBuffer($user),
    'viewer'       => $profileViewer,
]);

echo $Twig->render('user/sidebar.twig', [
    'ancestry'      => $userMan->ancestry($user),
    'applicant'     => new Manager\Applicant(),
    'invite_source' => $profileViewer->permitted('admin_manage_invite_source')
        ? new Manager\InviteSource()->findSourceNameByUser($user) : null,
    'next_class'    => $userMan->userNextClass($user),
    'user'          => $user,
    'viewer'        => $profileViewer,
]);

// Last.fm statistics and comparability
$lastfmInfo = new Util\LastFM()->userInfo($user);
if ($lastfmInfo) {
    echo $Twig->render('user/lastfm.twig', [
        'can_reload'  => ($OwnProfile && $Cache->get_value("lastfm_clear_cache_$userId") === false) || $profileViewer->permitted('users_mod'),
        'info'        => $lastfmInfo,
        'own_profile' => $OwnProfile,
    ]);
}

$vote    = new User\Vote($user);
$stats   = $user->stats();
$Uploads = $user->propertyVisible($profileViewer, 'uploads+') ? $stats->uploadTotal() : 0;
$rank    = new UserRank(
    new UserRank\Configuration(RANKING_WEIGHT),
    [
        'uploaded'       => $user->uploadedSize(),
        'downloaded'     => $user->downloadedSize(),
        'uploads'        => $Uploads,
        'requests'       => $stats->requestBountyTotal(),
        'posts'          => $stats->forumPostTotal(),
        'bounty'         => $stats->requestVoteSize(),
        'artists'        => $user->propertyVisible($profileViewer, 'artistsadded') ? $stats->artistAddedTotal() : 0,
        'collage-add'    => $user->propertyVisible($profileViewer, 'collagecontribs+') ? $stats->collageContrib() : 0,
        'collage-create' => $user->propertyVisible($profileViewer, 'collagecontribs+') ? $stats->collageTotal() : 0,
        'votes'          => $vote->userTotal(User\Vote::UPVOTE | User\Vote::DOWNVOTE),
        'bonus'          => $userBonus->pointsSpent(),
        'comment-t'      => $user->propertyVisible($profileViewer, 'torrentcomments++') ? $stats->commentTotal('torrents') : 0,
    ],
);

$byteFormatter   = fn ($value) => byte_format($value);
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
    ['collage-add', 'collagecontribs+', 'Collage contributions', $numberFormatter, 'contributions'],
    ['collage-create', 'collagecontribs+', 'Collages created', $numberFormatter, 'created'],
    ['artists', 'artistsadded', 'Artists added', $numberFormatter, 'added'],
    ['votes', null, 'Release votes cast', $numberFormatter, 'votes'],
];
?>
        <div class="box box_info box_userinfo_percentile">
            <div class="head colhead_dark">Percentile Rankings (hover for values)</div>
            <ul class="stats nobullet">
<?php
foreach ($statList as $item) {
    $Override = is_null($item[1]) ? PARANOIA_ALLOWED : $user->propertyVisible($profileViewer, $item[1]);
    if ($Override) {
?>
                <li class="tooltip<?=($Override === PARANOIA_OVERRIDDEN ? ' paranoia_override' : '')?>" title="<?= $item[3]($rank->raw($item[0])) ?> <?= $item[4] ?>"><?= $item[2] ?>: <?= $rank->rank($item[0]) ?></li>
<?php
    }
}
if ($OwnProfile || $profileViewer->permitted('admin_bp_history')) { ?>
                <li class="tooltip<?= !$OwnProfile && $profileViewer->permitted('admin_bp_history') ? ' paranoia_override' : '' ?>" title="<?=number_format($rank->raw('bonus')) ?> spent">Bonus points spent: <?= $rank->rank('bonus') ?></li>
<?php
}
if ($user->propertyVisibleMulti($profileViewer, ['artistsadded', 'collagecontribs+', 'downloaded', 'requestsfilled_count', 'requestsvoted_bounty', 'torrentcomments++', 'uploaded', 'uploads+', ])) {
?>
                <li<?= $user->userclassLevel() >= 900 ? ' title="Infinite"' : '' ?>><strong>Overall rank: <?= is_null($rank->score())
                    ? 'Server busy'
                    : ($user->userclassLevel() >= 900 ? '&nbsp;∞' : number_format($rank->score() * $user->rankFactor())) ?></strong></li>
<?php } ?>
            </ul>
        </div>
<?php if ($profileViewer->permitted('users_mod') || $profileViewer->permitted('users_view_ips') || $profileViewer->permitted('users_view_keys')) { ?>
        <div class="box box_info box_userinfo_history">
            <div class="head colhead_dark">History</div>
            <ul class="stats nobullet">
<?php if ($profileViewer->permitted('users_view_email')) { ?>
                <li>Emails: <?=number_format($user->history()->emailTotal())?> <a href="userhistory.php?action=email&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php
    }
    if ($profileViewer->permitted('users_view_ips')) {
?>
                <li>IPs: <?=number_format($ipv4->userTotal($user))?> <a href="userhistory.php?action=ips&amp;userid=<?=$userId?>" class="brackets">View</a>&nbsp;<a href="userhistory.php?action=ips&amp;userid=<?=$userId?>&amp;usersonly=1" class="brackets">View users</a></li>
                <li>Tracker IPs: <?=number_format($user->trackerIPCount())?> <a href="userhistory.php?action=tracker_ips&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php
    }
    if ($profileViewer->permitted('users_view_keys')) {
?>
                <li>Announce keys: <?=number_format($user->history()->announceKeyTotal())?> <a href="userhistory.php?action=passkeys&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php
    }
    if ($profileViewer->permitted('users_mod')) {
        if ($resetToken instanceof User\Token) {
?>
                <li><span class="tooltip" title="User requested a password reset by email">Password reset expiry: <?= time_diff($resetToken->expiry()) ?></li>
<?php   } ?>
                <li>Password history: <?=number_format($user->history()->passwordTotal())?> <a href="userhistory.php?action=passwords&amp;userid=<?=$userId?>" class="brackets">View</a></li>
                <li>Stats: N/A <a href="userhistory.php?action=stats&amp;userid=<?=$userId?>" class="brackets">View</a></li>
<?php } ?>
            </ul>
        </div>
<?php } ?>

<?php

if ($user->propertyVisible($profileViewer, 'snatched')) {
    echo $Twig->render('user/tag-snatch.twig', [
        'user' => $user,
    ]);
}

echo $Twig->render('user/sidebar-stats.twig', [
    'prl'          => $limiter,
    'upload_total' => $Uploads,
    'user'         => $user,
    'viewer'       => $profileViewer,
    'visible'      => [
        'collages+'             => $user->propertyVisible($profileViewer, 'collages+'),
        'collages'              => $user->propertyVisible($profileViewer, 'collages'),
        'collagescontrib+'      => $user->propertyVisible($profileViewer, 'collagecontribs+'),
        'collagecontribs'       => $user->propertyVisible($profileViewer, 'collagecontribs'),
        'downloaded'            => $OwnProfile || $profileViewer->permitted('site_view_torrent_snatchlist'),
        'invitedcount'          => $user->propertyVisible($profileViewer, 'invitedcount'),
        'leeching+'             => $user->propertyVisible($profileViewer, 'leeching+'),
        'leeching'              => $user->propertyVisible($profileViewer, 'leeching'),
        'perfectflacs+'         => $user->propertyVisible($profileViewer, 'perfectflacs+'),
        'perfectflacs'          => $user->propertyVisible($profileViewer, 'perfectflacs'),
        'seeding+'              => $user->propertyVisible($profileViewer, 'seeding+'),
        'seeding'               => $user->propertyVisible($profileViewer, 'seeding'),
        'snatched+'             => $user->propertyVisible($profileViewer, 'snatched+'),
        'snatched'              => $user->propertyVisible($profileViewer, 'snatched'),
        'torrentcomments+'      => $user->propertyVisible($profileViewer, 'torrentcomments+'),
        'torrentcomments'       => $user->propertyVisible($profileViewer, 'torrentcomments'),
        'requestsfilled_list'   => $user->propertyVisible($profileViewer, 'requestsfilled_list'),
        'requestsfilled_count'  => $user->propertyVisible($profileViewer, 'requestsfilled_count'),
        'requestsfilled_bounty' => $user->propertyVisible($profileViewer, 'requestsfilled_bounty'),
        'requestsvoted_list'    => $user->propertyVisible($profileViewer, 'requestsvoted_list'),
        'requestsvoted_count'   => $user->propertyVisible($profileViewer, 'requestsvoted_count'),
        'requestsvoted_bounty'  => $user->propertyVisible($profileViewer, 'requestsvoted_bounty'),
        'uniquegroups+'         => $user->propertyVisible($profileViewer, 'uniquegroups+'),
        'uniquegroups'          => $user->propertyVisible($profileViewer, 'uniquegroups'),
        'uploads+'              => $user->propertyVisible($profileViewer, 'uploads+'),
        'uploads'               => $user->propertyVisible($profileViewer, 'uploads'),
    ],
]);

if ($profileViewer->permitted("users_mod") || $OwnProfile || $donor->isVisible()) {
    echo $Twig->render('donation/stats.twig', [
        'donor'  => $donor,
        'viewer' => $profileViewer,
    ]);
}
?>
    </div><?php /* class="sidebar" */?>
    <div class="main_column">
<?php
if ($profileViewer->permitted('users_mod') && $user->onRatioWatch()) {
    echo $Twig->render('user/ratio-watch.twig', [
        'user' => $user,
    ]);
}

echo $Twig->render('user/profileinfo.twig', [
    'donor' => $donor,
    'user'  => $user,
]);

if ($user->propertyVisible($profileViewer, 'snatched')) {
    echo $Twig->render('user/recent.twig', [
        'id'     => $userId,
        'recent' => array_map($tgMan->findById(...), $user->snatch()->recentSnatchList()),
        'title'  => 'Snatches',
        'type'   => 'snatched',
        'thing'  => 'snatches',
    ]);
}

if ($user->propertyVisible($profileViewer, 'uploads')) {
    echo $Twig->render('user/recent.twig', [
        'id'     => $userId,
        'recent' => array_map($tgMan->findById(...), $user->recentUploadList()),
        'title'  => 'Uploads',
        'type'   => 'uploaded',
        'thing'  => 'uploads',
    ]);
}

if ($OwnProfile || !$user->hasAttr('hide-vote-recent') || $profileViewer->permitted('view-release-votes')) {
    echo $Twig->render('user/recent-vote.twig', [
        'recent'    => $vote->recent(),
        'show_link' => $OwnProfile || !$user->hasAttr('hide-vote-history') || $profileViewer->permitted('view-release-votes'),
        'user_id'   => $userId,
    ]);
}

echo $Twig->render('user/collage-list.twig', [
    'list'    => new Manager\Collage()->findPersonalByUser($user),
    'manager' => $tgMan,
]);

echo $Twig->render('user/information.twig', [
    'hash'          => signature($comments ?? '', USER_EDIT_SALT),
    'user'          => $user,
    'viewer'        => $profileViewer,

    // optional (possibly expensive) elements requiring additional privileges
    'donor_history' => $profileViewer->permitted('users_give_donor')
        ? $donor->historyList() : [],

    'has_invitees' => $profileViewer->permitted('users_view_invites')
        ? new User\InviteTree($user)->hasInvitees() : [],

    'report_list' => $profileViewer->permitted('admin_reports')
        ? new Manager\Report($userMan)->findByReportedUser($user) : [],

    'user_link' => $profileViewer->permitted('users_linked_users')
        ? new User\UserLink($user)->info() : [],

    'request_list' => !$profileViewer->disableRequests() && $user->propertyVisible($profileViewer, 'requestsvoted_list')
        ? new Manager\Request()->findUnfilledByUser($user, 100) : [],

    'staffpm_list' => $profileViewer->permitted('users_mod') || $profileViewer->isStaffPMReader()
        ? new Staff($profileViewer)->userStaffPmList($user) : [],

    'warning_list' => $profileViewer->permitted('users_warn')
        ? $user->forumWarning() : [],

    'auto_list' => $profileViewer->permitted('users_auto_reports')
        ? (function ($u) {
            $raTypeMan = new Manager\ReportAutoType();
            $raSearch  = new Search\ReportAuto(new Manager\ReportAuto($raTypeMan), $raTypeMan);
            return $raSearch->setUser($u)->setState(Enum\ReportAutoState::open)->userTotalList(0);
        })($user)
        : [],
]);

echo $Twig->render('user/main-column.twig', [
    'asn'           => new Search\ASN(),
    'class_list'    => array_merge(
        array_filter(
            $userMan->classList(),
            fn ($c) => in_array($c['Name'], USERCLASS_PROMOTE),
        ),
        $userMan->staffClassList(),
    ),
    'donor'         => $donor,
    'forum_man'     => new Manager\Forum(),
    'invite_source' => new Manager\InviteSource()->inviterConfiguration($user),
    'is_traced'     => $profileViewer->permitted('admin_tracker') && new Tracker()->isTraced($user),
    'max_level'     => max(array_map(fn ($c) => $c['Level'], $userMan->staffClassList())), /** @phpstan-ignore-line never empty */
    'over_usercap'  => new Stats\Users()->overUsercap(),
    'prl'           => $limiter,
    'user'          => $user,
    'viewer'        => $profileViewer,
]);
