<?php
if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    json_die("failure", "bad id parameter");
}
$UserID = $_GET['id'];
$user = new \Gazelle\User($UserID);

$OwnProfile = $UserID == $LoggedUser['ID'];

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

list($Username, $Email, $LastAccess, $IP, $Class, $Uploaded, $Downloaded, $RequiredRatio, $Enabled, $Paranoia, $Invites, $CustomTitle, $torrent_pass, $DisableLeech, $JoinDate, $Info, $Avatar, $Donor, $Warned, $ForumPosts, $InviterID, $DisableInvites, $InviterName, $RatioWatchEnds, $RatioWatchDownload) = $DB->next_record(MYSQLI_NUM, [9, 11]);

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

$Friend = $user->isFriend($LoggedUser['ID']);

if (!(check_paranoia_here('requestsfilled_count') || check_paranoia_here('requestsfilled_bounty'))) {
    $RequestsFilled = null;
    $TotalBounty = null;
    $RequestsVoted = null;
    $TotalSpent = null;
} else {
    list($RequestsFilled, $TotalBounty) = $user->requestsBounty();
    list($RequestsVoted, $TotalSpent) = $user->requestsVotes();
}

$Uploads      = check_paranoia_here('uploads+')     ? null : $user->uploadCount();
$ArtistsAdded = check_paranoia_here('artistsadded') ? null : $user->artistsAdded();

// Do the ranks.
$UploadedRank = check_paranoia_here('uploaded') ? UserRank::get_rank('uploaded', $Uploaded) : null;
$DownloadRank = check_paranoia_here('downloaded') ? UserRank::get_rank('downloaded', $Downloaded) : null;
$UploadsRank  = check_paranoia_here('uploads+') ? UserRank::get_rank('uploads', $Uploads) : null;
$RequestRank  = check_paranoia_here('requestsfilled_count') ? UserRank::get_rank('requests', $RequestsFilled) : null;
$PostRank     = UserRank::get_rank('posts', $ForumPosts);
$BountyRank   = check_paranoia_here('requestsvoted_bounty') ? UserRank::get_rank('bounty', $TotalSpent) : null;
$ArtistsRank  = check_paranoia_here('artistsadded') ? UserRank::get_rank('artists', $ArtistsAdded) : null;

if ($Downloaded == 0) {
    $Ratio = 1;
} elseif ($Uploaded == 0) {
    $Ratio = 0.5;
} else {
    $Ratio = round($Uploaded / $Downloaded, 2);
}
if (check_paranoia_here(['uploaded', 'downloaded', 'uploads+', 'requestsfilled_count', 'requestsvoted_bounty', 'artistsadded'])) {
    $OverallRank = floor(UserRank::overall_score($UploadedRank, $DownloadRank, $UploadsRank, $RequestRank, $PostRank, $BountyRank, $ArtistsRank, $Ratio));
} else {
    $OverallRank = null;
}

// Community section
if (check_paranoia_here('snatched+')) {
    list($Snatched, $UniqueSnatched) = $user->snatchCounts();
}

$NumComments        = check_paranoia_here('torrentcomments++') ? null : $user->torrentCommentCount();
$NumArtistsComments = check_paranoia_here('torrentcomments++') ? null : $user->artistCommentCount();
$NumCollageComments = check_paranoia_here('torrentcomments++') ? null : $user->collageCommentCount();
$NumRequestComments = check_paranoia_here('torrentcomments++') ? null : $user->requestCommentCount();

if (check_paranoia_here('collages+')) {
    $NumCollages = $user->collagesCreated();
}

if (check_paranoia_here('collagecontribs+')) {
    $NumCollageContribs = $user->collagesContributed();
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
$Ratio = check_paranoia_here('ratio') ? Format::get_ratio($Uploaded, $Downloaded, 5) : null;
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

header('Content-Type: text/plain; charset=utf-8');

json_print("success", [
    'username' => $Username,
    'avatar' => $Avatar,
    'isFriend' => $Friend,
    'profileText' => Text::full_format($Info),
    'stats' => [
        'joinedDate' => $JoinDate,
        'lastAccess' => $LastAccess ?? '',
        'uploaded' => (($Uploaded == null) ? null : (int)$Uploaded),
        'downloaded' => (($Downloaded == null) ? null : (int)$Downloaded),
        'ratio' => $Ratio,
        'requiredRatio' => (($RequiredRatio == null) ? null : (float)$RequiredRatio)
    ],
    'ranks' => [
        'uploaded' => $UploadedRank,
        'downloaded' => $DownloadRank,
        'uploads' => $UploadsRank,
        'requests' => $RequestRank,
        'bounty' => $BountyRank,
        'posts' => $PostRank,
        'artists' => $ArtistsRank,
        'overall' => (($OverallRank == null) ? 0 : $OverallRank)
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
        'posts' => (int)$ForumPosts,
        'torrentComments' => (($NumComments == null) ? null : (int)$NumComments),
        'artistComments' => (($NumArtistComments == null) ? null : (int)$NumArtistComments),
        'collageComments' => (($NumCollageComments == null) ? null : (int)$NumCollageComments),
        'requestComments' => (($NumRequestComments == null) ? null : (int)$NumRequestComments),
        'collagesStarted' => (($NumCollages == null) ? null : (int)$NumCollages),
        'collagesContrib' => (($NumCollageContribs == null) ? null : (int)$NumCollageContribs),
        'requestsFilled' => (($RequestsFilled == null) ? null : (int)$RequestsFilled),
        'bountyEarned' => (($TotalBounty == null) ? null : (int)$TotalBounty),
        'requestsVoted' => (($RequestsVoted == null) ? null : (int)$RequestsVoted),
        'bountySpent' => (($TotalSpent == null) ? null : (int)$TotalSpent),
        'perfectFlacs' => (($PerfectFLACs == null) ? null : (int)$PerfectFLACs),
        'uploaded' => (($Uploads == null) ? null : (int)$Uploads),
        'groups' => (($UniqueGroups == null) ? null : (int)$UniqueGroups),
        'seeding' => (($Seeding == null) ? null : (int)$Seeding),
        'leeching' => (($Leeching == null) ? null : (int)$Leeching),
        'snatched' => (($Snatched == null) ? null : (int)$Snatched),
        'invited' => (($Invited == null) ? null : (int)$Invited),
        'artistsAdded' => (($ArtistsAdded == null) ? null : (int)$ArtistsAdded)
    ]
]);
