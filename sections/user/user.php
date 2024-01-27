<?php

use Gazelle\Enum\UserTokenType;
use Gazelle\User\Vote;

$userMan = new Gazelle\Manager\User;
$User = $userMan->findById((int)$_GET['id']);
if (is_null($User)) {
    header("Location: log.php?search=User+" . (int)$_GET['id']);
    exit;
}

$UserID      = $User->id();
$Username    = $User->username();
$Class       = $User->primaryClass();
$donor       = new Gazelle\User\Donor($User);
$userBonus   = new Gazelle\User\Bonus($User);
$viewerBonus = new Gazelle\User\Bonus($Viewer);
$history     = new Gazelle\User\History($User);
$limiter     = new Gazelle\User\UserclassRateLimit($User);
$donorMan    = new Gazelle\Manager\Donation;
$ipv4        = new Gazelle\Manager\IPv4;
$tgMan       = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$resetToken  = $Viewer->permitted('users_mod')
    ? (new Gazelle\Manager\UserToken)->findByUser($User, UserTokenType::password)
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
    $FL_OTHER_tokens = $viewerBonus->purchaseTokenOther($User, $match[1], $_POST['message'] ?? '');
    if (!$FL_OTHER_tokens) {
        error('Purchase of tokens not concluded. Either you lacked funds or they have chosen to decline FL tokens.');
    }
}

if ($UserID == $Viewer->id()) {
    $Preview = (bool)($_GET['preview'] ?? false);
    $OwnProfile = !$Preview;
    $User->forceCacheFlush(true);
} else {
    $OwnProfile = false;
    // Don't allow any kind of previewing on other profiles
    $Preview = false;
}
$previewer = $Preview ? $userMan->findById(PARANOIA_PREVIEW_USER) : $Viewer;
$Paranoia  = $Preview ? explode(',', $_GET['paranoia']) : $User->paranoia();

function check_paranoia_here(?string $Setting): int|false {
    global $Paranoia, $Class, $UserID, $Preview;
    if (!$Setting) {
        return PARANOIA_ALLOWED;
    }
    if ($Preview) {
        return check_paranoia($Setting, $Paranoia ?? [], $Class);
    } else {
        return check_paranoia($Setting, $Paranoia ?? [], $Class, $UserID);
    }
}

// Image proxy CTs
$DisplayCustomTitle = !empty($User->title())
    ? preg_replace_callback('/src=("?)(http.+?)(["\s>])/',
        fn ($m) => 'src=' . $m[1] . image_cache_encode($m[2]) . $m[3], $User->title())
    : $User->title();

View::show_header($Username, ['js' => 'jquery.imagesloaded,jquery.wookmark,user,bbcode,requests,lastfm,comments,info_paster', 'css' => 'tiles']);
echo $Twig->render('user/header.twig', [
    'badge_list' => $User->privilege()->badgeList(),
    'bonus'      => $userBonus,
    'donor'      => $donor,
    'freeleech'  => [
        'item'   => $OwnProfile ? [] : $viewerBonus->otherList(),
        'other'  => $FL_OTHER_tokens ?? null,
        'latest' => $viewerBonus->otherLatest($User),
    ],
    'friend'       => new Gazelle\User\Friend($Viewer),
    'preview_user' => $previewer,
    'user'         => $User,
    'userMan'      => $userMan,
    'viewer'       => $Viewer,
]);
?>
        <div class="box box_info box_userinfo_personal">
            <div class="head colhead_dark">Personal</div>
            <ul class="stats nobullet">
                <li>Class: <strong><?= $userMan->userclassName($Class) ?></strong></li>
<?php if (($secondary = $User->privilege()->secondaryClassList())) { ?>
                <li>
                    <ul class="stats">
<?php
        asort($secondary);
        foreach ($secondary as $id => $name) {
            if ($id == DONOR && !$User->propertyVisible($Viewer, 'hide_donor_heart')) {
                continue;
            }
?>
                        <li><?= $name ?></li>
<?php } ?>
                    </ul>
                </li>
<?php
}
echo $Twig->render('user/sidebar.twig', [
    'applicant'     => new Gazelle\Manager\Applicant,
    'invite_source' => $Viewer->permitted('admin_manage_invite_source')
        ? (new Gazelle\Manager\InviteSource)->findSourceNameByUser($User) : null,
    'user'          => $User,
    'viewer'        => $Viewer,
]);
?>
            </ul>
        </div>
<?php
if ($OwnProfile || $Viewer->permitted('users_mod')) {
    $nextClass = $User->nextClass($userMan);
    if ($nextClass) {
?>
        <div class="box box_info box_userinfo_nextclass">
            <div class="head colhead_dark"><a href="wiki.php?action=article&amp;name=userclasses">Next Class</a></div>
            <ul class="stats nobullet">
                <li>Class: <?= $nextClass['class']?></li>
<?php   foreach ($nextClass['goal'] as $label => $require) { ?>
                <li><?= $label ?>: <?= $require['current'] ?> / <?= $require['target'] ?> (<?= $require['percent'] ?>)</li>
<?php   } ?>
            </ul>
        </div>
<?php
    }
}

// Last.fm statistics and comparability
$lastfmInfo = (new Gazelle\Util\LastFM)->userInfo($User);
if ($lastfmInfo) {
    echo $Twig->render('user/lastfm.twig', [
        'can_reload'  => ($OwnProfile && $Cache->get_value("lastfm_clear_cache_$UserID") === false) || $Viewer->permitted('users_mod'),
        'info'        => $lastfmInfo,
        'own_profile' => $OwnProfile,
    ]);
}

$vote             = new Vote($User);
$stats            = $User->stats();
$Uploads          = check_paranoia_here('uploads+') ? $stats->uploadTotal() : 0;
$rank = new Gazelle\UserRank(
    new Gazelle\UserRank\Configuration(RANKING_WEIGHT),
    [
        'uploaded'   => $User->uploadedSize(),
        'downloaded' => $User->downloadedSize(),
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

$byteFormatter = function ($value) { return byte_format($value); };
$numberFormatter = function ($value) { return number_format($value); };

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
if ($User->propertyVisibleMulti($previewer, ['artistsadded', 'collagecontribs+', 'downloaded', 'requestsfilled_count', 'requestsvoted_bounty', 'torrentcomments++', 'uploaded', 'uploads+', ])) {
?>
                <li<?= $User->classLevel() >= 900 ? ' title="Infinite"' : '' ?>><strong>Overall rank: <?= is_null($rank->score())
                    ? 'Server busy'
                    : ($User->classLevel() >= 900 ? '&nbsp;&infin;' : number_format($rank->score() * $User->rankFactor())) ?></strong></li>
<?php } ?>
            </ul>
        </div>
<?php if ($Viewer->permitted('users_mod') || $Viewer->permitted('users_view_ips') || $Viewer->permitted('users_view_keys')) { ?>
        <div class="box box_info box_userinfo_history">
            <div class="head colhead_dark">History</div>
            <ul class="stats nobullet">
<?php if ($Viewer->permitted('users_view_email')) { ?>
                <li>Emails: <?=number_format($history->emailTotal())?> <a href="userhistory.php?action=email&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
    }
    if ($Viewer->permitted('users_view_ips')) {
?>
                <li>IPs: <?=number_format($ipv4->userTotal($User))?> <a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>" class="brackets">View</a>&nbsp;<a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>&amp;usersonly=1" class="brackets">View users</a></li>
<?php   if ($Viewer->permitted('users_mod')) { ?>
                <li>Tracker IPs: <?=number_format($User->trackerIPCount())?> <a href="userhistory.php?action=tracker_ips&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
        }
    }
    if ($Viewer->permitted('users_view_keys')) {
?>
                <li>Announce keys: <?=number_format($User->announceKeyCount())?> <a href="userhistory.php?action=passkeys&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
    }
    if ($Viewer->permitted('users_mod')) {
        if ($resetToken) {
?>
                <li><span class="tooltip" title="User requested a password reset by email">Password reset expiry: <?= time_diff($resetToken->expiry()) ?></li>
<?php   } ?>
                <li>Password history: <?=number_format($User->passwordCount())?> <a href="userhistory.php?action=passwords&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
                <li>Stats: N/A <a href="userhistory.php?action=stats&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php } ?>
            </ul>
        </div>
<?php } ?>

<?php

if (check_paranoia_here('snatched')) {
    echo $Twig->render('user/tag-snatch.twig', [
        'user' => $User,
    ]);
}

echo $Twig->render('user/sidebar-stats.twig', [
    'prl'            => $limiter,
    'upload_total'   => $Uploads,
    'user'           => $User,
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
if ($Viewer->permitted('users_mod') && $User->onRatioWatch()) {
    echo $Twig->render('user/ratio-watch.twig', [
        'user' => $User,
    ]);
}
?>
        <div class="box">
            <div class="head">
                <?= html_escape($User->profileTitle()) ?>
                <span style="float: right;"><a href="#" onclick="$('#profilediv').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>&nbsp;
            </div>
            <div class="pad profileinfo" id="profilediv">
                <?= $User->profileInfo() ? Text::full_format($User->profileInfo()) : 'This profile is currently empty.' ?>
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
        'id'     => $UserID,
        'recent' => array_map(fn ($id) => $tgMan->findById($id), $User->snatch()->recentSnatchList()),
        'title'  => 'Snatches',
        'type'   => 'snatched',
        'thing'  => 'snatches',
    ]);
}

if (check_paranoia_here('uploads')) {
    echo $Twig->render('user/recent.twig', [
        'id'     => $UserID,
        'recent' => array_map(fn ($id) => $tgMan->findById($id), $User->recentUploadList()),
        'title'  => 'Uploads',
        'type'   => 'uploaded',
        'thing'  => 'uploads',
    ]);
}

if ($OwnProfile || !$User->hasAttr('hide-vote-recent') || $Viewer->permitted('view-release-votes')) {
    echo $Twig->render('user/recent-vote.twig', [
        'recent'    => $vote->recent($tgMan),
        'show_link' => $OwnProfile || !$User->hasAttr('hide-vote-history') || $Viewer->permitted('view-release-votes'),
        'user_id'   => $UserID,
    ]);
}

$FirstCol = true;
$Collages = (new Gazelle\Manager\Collage)->findPersonalByUser($User);
foreach ($Collages as $collage) {
?>
    <table class="layout recent" id="collage<?=$collage->id()?>_box" cellpadding="0" cellspacing="0" border="0">
        <tr class="colhead">
            <td colspan="5">
                <span style="float: left;">
                    <?=html_escape($collage->name())?> - <a href="collages.php?id=<?=$collage->id()?>" class="brackets">See full</a>
                </span>
                <span style="float: right;">
                    <a href="#" onclick="$('#collage<?=$collage->id()?>_box .images').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=$FirstCol ? 'Hide' : 'Show' ?></a>
                </span>
            </td>
        </tr>
        <tr class="images<?=$FirstCol ? '' : ' hidden'?>">
<?php
    $list = array_slice($collage->groupIds(), 0, 5);
    foreach ($list as $tgroupId) {
        $tgroup = $tgMan->findById($tgroupId);
?>
            <td>
                <a href="torrents.php?id=<?= $tgroupId ?>">
                    <img class="tooltip" title="<?= html_escape($tgroup->text()) ?>" src="<?= html_escape(image_cache_encode($tgroup->cover())) ?>" width="107" />
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
if ($Viewer->permitted('users_linked_users')) {
    [$linkGroupId, $comments, $list] = (new Gazelle\Manager\UserLink($User))->info();
    echo $Twig->render('user/linked.twig', [
        'auth'     => $Viewer->auth(),
        'comments' => $comments,
        'group_id' => $linkGroupId,
        'hash'     => sha1($comments ?? ''),
        'list'     => $list,
        'user_id'  => $UserID,
    ]);
}

if ($Viewer->permitted('users_view_invites')) {
    $tree = new Gazelle\User\InviteTree($User, $userMan);
    if ($tree->hasInvitees()) {
?>
        <div class="box" id="invitetree_box">
            <div class="head">
                Invite Tree <a href="#" onclick="$('#invitetree').gtoggle(); return false;" class="brackets">View</a>
            </div>
            <div id="invitetree" class="hidden">
                <?= $Twig->render('user/invite-tree.twig', [
                    ...$tree->details($Viewer),
                    'user'   => $User,
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

if (!$Viewer->disableRequests() && $User->propertyVisible($previewer, 'requestsvoted_list')) {
    echo $Twig->render('request/user-unfilled.twig', [
        'list'   => (new Gazelle\Manager\Request)->findUnfilledByUser($User, 100),
        'viewer' => $Viewer,
    ]);
}

if ($Viewer->permitted('users_mod') || $Viewer->isStaffPMReader()) {
    echo $Twig->render('admin/staffpm-list.twig', [
        'list' => (new Gazelle\Staff($Viewer))->userStaffPmList($User),
    ]);
}

// Displays a table of forum warnings viewable only to Forum Moderators
if ($Viewer->permitted('users_warn')) {
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

if ($Viewer->permitted('users_mod') || $Viewer->isStaff()) { ?>
        <form class="manage_form" name="user" id="form" action="user.php" method="post">
        <input type="hidden" name="action" value="moderate" />
        <input type="hidden" name="userid" value="<?=$UserID?>" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />

        <div class="box box2" id="staff_notes_box">
            <div class="head">
                Staff Notes
                <a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;" class="brackets">Edit</a>
                <a href="#" onclick="$('#staffnotes').gtoggle(); return false;" class="brackets">Toggle</a>
            </div>
            <div id="staffnotes" class="pad">
                <input type="hidden" name="comment_hash" value="<?= $User->info()['CommentHash'] ?>" />
                <div id="admincommentlinks" class="AdminComment" style="width: 98%;"><?=Text::full_format($User->staffNotes())?></div>
                <textarea id="admincomment" name="admincomment" onkeyup="resize('admincomment');"
                          class="AdminComment hidden" name="AdminComment" cols="65" rows="26" style="width: 98%;"><?=
                    html_escape($User->staffNotes())?></textarea>
                <a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;"
                   class="brackets">Toggle edit</a>
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
    if ($Viewer->permitted('users_edit_usernames')) {
        echo $Twig->render('user/edit-username.twig', [
            'username' => $Username,
        ]);
    }

    if ($Viewer->permitted('users_edit_titles')) {
        echo $Twig->render('user/edit-title.twig', [
            'title' => $User->title(),
        ]);
    }

    if ($Viewer->permitted('users_promote_below') || $Viewer->permitted('users_promote_to', $Viewer->classLevel() - 1)) {
?>
            <tr>
                <td class="label">Primary class:</td>
                <td>
                    <select name="Class">
<?php
        $ClassLevels = $userMan->classLevelList();
        foreach ($ClassLevels as $CurClass) {
            if ($CurClass['Secondary']) {
                continue;
            } elseif (!$OwnProfile && !$Viewer->permitted('users_promote_to', $Viewer->classLevel() - 1) && $CurClass['Level'] == $Viewer->privilege()->effectiveClassLevel()) {
                break;
            } elseif ($CurClass['Level'] > $Viewer->privilege()->effectiveClassLevel()) {
                break;
            }
            if ($User->classLevel() == $CurClass['Level']) {
                $Selected = ' selected="selected"';
            } else {
                $Selected = '';
            }
?>
                        <option value="<?=$CurClass['ID']?>"<?=$Selected?>><?=$CurClass['Name'] . ' (' . $CurClass['Level'] . ')'?></option>
<?php        } ?>
                    </select>
                </td>
            </tr>
<?php
    }

    if ($Viewer->permitted('users_promote_below') || $Viewer->permitted('users_promote_to')) {
        echo $Twig->render('user/edit-secondary-class.twig', [
            'permission' => $User->privilege()->secondaryClassesList(),
        ]);
    }

    if ($Viewer->permitted('users_make_invisible')) {
        echo $Twig->render('user/edit-peer-visibility.twig', [
            'is_visible' => $User->isVisible(),
        ]);
    }

    if ($Viewer->permitted('admin_rate_limit_manage')) {
        echo $Twig->render('user/edit-rate-limit.twig', [
            'prl'  => $limiter,
            'user' => $User,
        ]);
    }

    if ($Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $OwnProfile)) {
        echo $Twig->render('user/edit-buffer.twig', [
            'user'  => $User,
            'donor' => $donor,
        ]);
    }

    if ($Viewer->permitted('users_edit_invites')) {
        echo $Twig->render('user/edit-invite.twig', [
            'amount' => $User->unusedInviteTotal(),
        ]);
    }

    if ($Viewer->permitted('admin_manage_user_fls')) {
        echo $Twig->render('user/edit-fltoken.twig', [
            'amount' => $User->tokenCount(),
        ]);
    }

    if ($Viewer->permitted('admin_manage_fls') || ($Viewer->permitted('users_mod') && $OwnProfile)) {
        echo $Twig->render('user/edit-remark.twig', [
            'user' => $User,
        ]);
    }

    if ($Viewer->permitted('users_edit_reset_keys')) {
        echo $Twig->render('user/edit-reset.twig');
    }

    if ($Viewer->permitted('users_edit_password')) {
        echo $Twig->render('user/edit-password.twig', [
            'user' => $User,
        ]);
    }
?>
</table>

<?php
    if ($Viewer->permitted('users_disable_posts') || $Viewer->permitted('users_disable_any')) {
        $fm = new Gazelle\Manager\Forum;
        echo $Twig->render('user/edit-privileges.twig', [
            'asn'     => new Gazelle\Search\ASN,
            'history' => $history,
            'user'    => $User,
            'viewer'  => $Viewer,
            'forum'   => [
                'restricted_names' => implode(', ', array_map(fn ($id) => $fm->findById($id)?->name() ?? $id, $User->forbiddenForums())),
                'permitted_names'  => implode(', ', array_map(fn ($id) => $fm->findById($id)?->name() ?? $id, $User->permittedForums())),
            ],
        ]);
    }

    if ($User->isInterviewer() || $User->isRecruiter() || $User->isStaff()) {
        echo $Twig->render('user/edit-invite-sources.twig', [
            'list' => (new \Gazelle\Manager\InviteSource)->inviterConfiguration($User),
        ]);
    }

    if ($Viewer->permitted('users_give_donor')) {
        echo $Twig->render('donation/admin-panel.twig', [
            'donor' => $donor,
        ]);
    }

    if ($Viewer->permitted('users_warn')) {
        echo $Twig->render('user/edit-warn.twig', [
            'user' => $User,
        ]);
    }

    if ($Viewer->permitted('users_disable_any')) {
        echo $Twig->render('user/edit-lock.twig', [
            'user'   => $User,
            'viewer' => $Viewer,
        ]);
    }

    echo $Twig->render('user/edit-submit.twig');
?>
        </form>
<?php } /* $Viewer->permitted('users_mod') */ ?>
    </div>
</div>
<?php
View::show_footer();
