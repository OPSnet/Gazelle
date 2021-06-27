<?php
$UserID = (int)$_GET['id'];
if (!$UserID) {
    json_die("failure", "bad id parameter");
}
$user = new Gazelle\User($UserID);

$OwnProfile = $UserID == $Viewer->id();

// Always view as a normal user.
$DB->prepared_query("
    SELECT
        um.Username,
        um.Email,
        ula.last_access,
        um.IP,
        p.Level AS Class,
        uls.Uploaded,
        uls.Downloaded,
        um.RequiredRatio,
        um.Enabled,
        um.Paranoia,
        um.Invites,
        um.Title,
        um.torrent_pass,
        um.can_leech,
        i.JoinDate,
        i.Info,
        i.Avatar,
        (donor.UserID IS NOT NULL) AS Donor,
        i.Warned,
        COUNT(posts.id) AS ForumPosts,
        i.Inviter,
        i.DisableInvites,
        inviter.username
    FROM users_main AS um
    LEFT JOIN user_last_access AS ula ON (ula.user_id = um.ID)
    INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
    INNER JOIN users_info AS i ON (i.UserID = um.ID)
    LEFT JOIN users_levels AS donor ON (donor.UserID = um.ID
        AND donor.PermissionID = (SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1)
    )
    LEFT JOIN permissions AS p ON (p.ID = um.PermissionID)
    LEFT JOIN users_main AS inviter ON (i.Inviter = inviter.ID)
    LEFT JOIN forums_posts AS posts ON (posts.AuthorID = um.ID)
    WHERE um.ID = ?
    GROUP BY AuthorID
    ", $UserID);

if (!$DB->has_results()) { // If user doesn't exist
    json_die("failure", "no such user");
}

[$Username, $Email, $LastAccess, $IP, $Class, $Uploaded, $Downloaded,
$RequiredRatio, $Enabled, $Paranoia, $Invites, $CustomTitle, $torrent_pass,
$DisableLeech, $JoinDate, $Info, $Avatar, $Donor, $Warned, $ForumPosts,
$InviterID, $DisableInvites, $InviterName, $RatioWatchEnds,
$RatioWatchDownload]
    = $DB->next_record(MYSQLI_NUM, [9, 11]);

$Paranoia = unserialize($Paranoia);
if (!is_array($Paranoia)) {
    $Paranoia = [];
}
$ParanoiaLevel = 0;
foreach ($Paranoia as $P) {
    $ParanoiaLevel++;
    if (strpos($P, '+') !== false) {
        $ParanoiaLevel++;
    }
}

function check_paranoia_here($Setting) {
    global $Paranoia, $Class, $UserID;
    return check_paranoia($Setting, $Paranoia, $Class, $UserID);
}

if (!(check_paranoia_here('requestsfilled_count') || check_paranoia_here('requestsfilled_bounty'))) {
    $RequestsFilled = null;
    $TotalBounty    = null;
    $RequestsVoted  = null;
    $TotalSpent     = null;
} else {
    list($RequestsFilled, $TotalBounty) = $user->requestsBounty();
    list($RequestsVoted,  $TotalSpent)  = $user->requestsVotes();
}

$Uploads          = check_paranoia_here('uploads+')     ? $user->uploadCount() : null;
$ArtistsAdded     = check_paranoia_here('artistsadded') ? $user->artistsAdded() : null;
$releaseVotes     = $user->releaseVotes();
$bonusPointsSpent = $user->bonusPointsSpent();
$torrentComments  = check_paranoia_here('torrentcomments++') ? $user->torrentCommentCount() : 0;

if (check_paranoia_here('collages+')) {
    $NumCollages = $user->collagesCreated();
}

if (check_paranoia_here('collagecontribs+')) {
    $NumCollageContribs = $user->collagesContributed();
}

$rank = new Gazelle\UserRank(
    new Gazelle\UserRank\Configuration(RANKING_WEIGHT),
    [
        'uploaded'   => $Uploaded ?? 0,
        'downloaded' => $Downloaded ?? 0,
        'uploads'    => $Uploads ?? 0,
        'requests'   => $RequestsFilled ?? 0,
        'posts'      => $ForumPosts,
        'bounty'     => $TotalSpent ?? 0,
        'artists'    => $ArtistsAdded ?? 0,
        'collage'    => $NumCollageContribs ?? 0,
        'votes'      => $releaseVotes,
        'bonus'      => $bonusPointsSpent,
        'comment-t'  => $torrentComments,
    ]
);

// Community section
if (check_paranoia_here('snatched+')) {
    list($Snatched, $UniqueSnatched) = $user->snatchCounts();
}

if (check_paranoia_here('uniquegroups+')) {
    $UniqueGroups = $DB->scalar("
        SELECT count(DISTINCT GroupID)
        FROM torrents
        WHERE UserID = ?
        ", $UserID
    );
}

if (check_paranoia_here('perfectflacs+')) {
    $PerfectFLACs = $DB->scalar("
        SELECT count(*)
        FROM torrents
        WHERE Format = 'FLAC'
            AND (
                (Media = 'CD' AND LogChecksum = '1' AND HasCue = '1' AND HasLogDB = '1' AND LogScore = 100)
                OR
                (Media in ('BD', 'Cassette', 'DAT', 'DVD', 'SACD', 'Soundboard', 'WEB', 'Vinyl'))
            )
            AND UserID = ?
        ", $UserID
    );
}

if (check_paranoia_here('seeding+')) {
    $Seeding = $user->seedingCounts();
}

if (check_paranoia_here('leeching+')) {
    $Leeching = $user->leechingCounts();
}

if (check_paranoia_here('invitedcount')) {
    $Invited = $user->invitedCount();
}

if (!$OwnProfile) {
    $torrent_pass = '';
}

// Run through some paranoia stuff to decide what we can send out.
if (!check_paranoia_here('lastseen')) {
    $LastAccess = null;
}
if (!check_paranoia_here('uploaded')) {
    $Uploaded = null;
}
if (!check_paranoia_here('downloaded')) {
    $Downloaded = null;
}
if (isset($RequiredRatio) && !check_paranoia_here('requiredratio')) {
    $RequiredRatio = null;
}
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

$NumComments        = check_paranoia_here('torrentcomments++') ? $user->torrentCommentCount() : null;
$NumArtistsComments = check_paranoia_here('torrentcomments++') ? $user->artistCommentCount() : null;
$NumCollageComments = check_paranoia_here('torrentcomments++') ? $user->collageCommentCount() : null;
$NumRequestComments = check_paranoia_here('torrentcomments++') ? $user->requestCommentCount() : null;
$ClassLevels = (new Gazelle\Manager\User)->classLevelList();

json_print("success", [
    'username'    => $Username,
    'avatar'      => $Avatar,
    'isFriend'    => $user->isFriend($Viewer->id()),
    'profileText' => Text::full_format($Info),
    'stats' => [
        'joinedDate'    => $JoinDate,
        'lastAccess'    => $LastAccess ?: null,
        'uploaded'      => is_null($Uploaded == null) ? null : (int)$Uploaded,
        'downloaded'    => is_null($Downloaded == null) ? null : (int)$Downloaded,
        'ratio'         => $Downloaded == 0 ? null : (float)round($Uploaded / $Downloaded, 2, PHP_ROUND_HALF_DOWN),
        'requiredRatio' => is_null($RequiredRatio) ? null : (float)$RequiredRatio,
    ],
    'ranks' => [
        'uploaded'   => check_paranoia_here('uploaded') ? $rank->rank('uploaded') : null,
        'downloaded' => check_paranoia_here('downloaded') ? $rank->rank('downloaded') : null,
        'uploads'    => check_paranoia_here('uploads+') ? $rank->rank('uploads') : null,
        'requests'   => check_paranoia_here('requestsfilled_count') ? $rank->rank('requests') : null,
        'bounty'     => check_paranoia_here('requestsvoted_bounty') ? $rank->rank('bounty') : null,
        'posts'      => $rank->rank('posts'),
        'artists'    => check_paranoia_here('artistsadded') ? $rank->rank('artists') : null,
        'collage'    => check_paranoia_here('collagecontribs+') ? $rank->rank('collage') : null,
        'votes'      => $rank->rank('votes'),
        'bonus'      => $rank->rank('bonus'),
        'overall'    => check_paranoia_here(['uploaded', 'downloaded', 'uploads+', 'requestsfilled_count', 'requestsvoted_bounty', 'artistsadded', 'collagecontribs+'])
            ? $rank->score() * $user->rankFactor() : null,
    ],
    'personal' => [
        'class' => $ClassLevels[$Class]['Name'],
        'paranoia' => $ParanoiaLevel,
        'paranoiaText' => $ParanoiaLevelText,
        'donor' => ($Donor == 1),
        'warned' => is_null($Warned),
        'enabled' => ($Enabled == '1' || $Enabled == '0' || !$Enabled),
        'passkey' => $torrent_pass
    ],
    'community' => [
        'posts'           => (int)$ForumPosts,
        'torrentComments' => $NumComments,
        'artistComments'  => $NumArtistsComments,
        'collageComments' => $NumCollageComments,
        'requestComments' => $NumRequestComments,
        'collagesStarted' => (($NumCollages == null) ? null : (int)$NumCollages),
        'collagesContrib' => (($NumCollageContribs == null) ? null : (int)$NumCollageContribs),
        'requestsFilled'  => (($RequestsFilled == null) ? null : (int)$RequestsFilled),
        'bountyEarned'    => (($TotalBounty == null) ? null : (int)$TotalBounty),
        'requestsVoted'   => (($RequestsVoted == null) ? null : (int)$RequestsVoted),
        'bountySpent'     => (($TotalSpent == null) ? null : (int)$TotalSpent),
        'releaseVotes'    => (($releaseVotes == null) ? null : (int)$releaseVotes),
        'perfectFlacs'    => (($PerfectFLACs == null) ? null : (int)$PerfectFLACs),
        'uploaded'        => (($Uploads == null) ? null : (int)$Uploads),
        'groups'          => (($UniqueGroups == null) ? null : (int)$UniqueGroups),
        'seeding'         => (($Seeding == null) ? null : (int)$Seeding),
        'leeching'        => (($Leeching == null) ? null : (int)$Leeching),
        'snatched'        => (($Snatched == null) ? null : (int)$Snatched),
        'invited'         => (($Invited == null) ? null : (int)$Invited),
        'artistsAdded'    => (($ArtistsAdded == null) ? null : (int)$ArtistsAdded)
    ]
]);
