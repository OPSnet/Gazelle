<?php

$user = (new Gazelle\Manager\User)->findById((int)$_GET['id']);
if (is_null($user)) {
    json_die("failure", "bad id parameter");
}

if (!$user->propertyVisibleMulti($Viewer, ['requestsfilled_count', 'requestsfilled_bounty'])) {
    $RequestsFilled = null;
    $TotalBounty    = null;
    $RequestsVoted  = null;
    $TotalSpent     = null;
} else {
    $RequestsFilled = $user->stats()->requestBountyTotal();
    $TotalBounty    = $user->stats()->requestBountySize();
    $RequestsVoted  = $user->stats()->requestVoteTotal();
    $TotalSpent     = $user->stats()->requestVoteSize();
}
$ForumPosts       = $user->stats()->forumPostTotal();
$activityStats    = $user->activityStats();
$Uploads          = $user->propertyVisible($Viewer, 'uploads+')     ? $user->stats()->uploadTotal() : null;
$ArtistsAdded     = $user->propertyVisible($Viewer, 'artistsadded') ? $user->stats()->artistAddedTotal() : null;
$releaseVotes     = $user->releaseVotes();
$bonusPointsSpent = $user->bonusPointsSpent();
$torrentComments  = $user->propertyVisible($Viewer, 'torrentcomments++') ? $user->torrentCommentCount() : null;
$collageContribs  = $user->propertyVisible($Viewer, 'collagecontribs+') ? $user->collagesContributed() : null;

$rank = new Gazelle\UserRank(
    new Gazelle\UserRank\Configuration(RANKING_WEIGHT),
    [
        'uploaded'   => $activityStats['BytesUploaded'],
        'downloaded' => $activityStats['BytesDownloaded'],
        'uploads'    => $Uploads ?? 0,
        'requests'   => $RequestsFilled ?? 0,
        'posts'      => $ForumPosts,
        'bounty'     => $TotalSpent ?? 0,
        'artists'    => $ArtistsAdded ?? 0,
        'collage'    => $collageContribs ?? 0,
        'votes'      => $releaseVotes,
        'bonus'      => $bonusPointsSpent,
        'comment-t'  => $torrentComments ?? 0,
    ]
);

$uploaded = $user->propertyVisible($Viewer, 'uploaded') ? $activityStats['BytesUploaded'] : null;
$downloaded = $user->propertyVisible($Viewer, 'downloaded') ? $activityStats['BytesDownloaded'] : null;
if (is_null($uploaded) || is_null($downloaded)) {
    $ratio = null;
} else {
    if ($downloaded == 0) {
        $ratio = (float)0;
    } else {
        $ratio = (float)round($uploaded / $downloaded, 2, PHP_ROUND_HALF_DOWN);
    }
}

json_print("success", [
    'username'    => $user->username(),
    'avatar'      => $user->avatar(),
    'isFriend'    => $user->isFriend($Viewer->id()),
    'profileText' => Text::full_format($user->infoProfile()),
    'stats' => [
        'joinedDate'    => $user->joinDate(),
        'lastAccess'    => $user->propertyVisible($Viewer, 'lastseen') ? $user->lastAccess() : null,
        'uploaded'      => $uploaded,
        'downloaded'    => $downloaded,
        'ratio'         => $ratio,
        'requiredRatio' => $user->propertyVisible($Viewer, 'requiredratio') ? $user->requiredRatio() : null,
    ],
    'ranks' => [
        'uploaded'   => $user->propertyVisible($Viewer, 'uploaded') ? $rank->rank('uploaded') : null,
        'downloaded' => $user->propertyVisible($Viewer, 'downloaded') ? $rank->rank('downloaded') : null,
        'uploads'    => $user->propertyVisible($Viewer, 'uploads+') ? $rank->rank('uploads') : null,
        'requests'   => $user->propertyVisible($Viewer, 'requestsfilled_count') ? $rank->rank('requests') : null,
        'bounty'     => $user->propertyVisible($Viewer, 'requestsvoted_bounty') ? $rank->rank('bounty') : null,
        'posts'      => $rank->rank('posts'),
        'artists'    => $user->propertyVisible($Viewer, 'artistsadded') ? $rank->rank('artists') : null,
        'collage'    => $user->propertyVisible($Viewer, 'collagecontribs+') ? $rank->rank('collage') : null,
        'votes'      => $rank->rank('votes'),
        'bonus'      => $rank->rank('bonus'),
        'overall'    => $user->propertyVisibleMulti($Viewer, ['uploaded', 'downloaded', 'uploads+', 'requestsfilled_count', 'requestsvoted_bounty', 'artistsadded', 'collagecontribs+'])
            ? $rank->score() * $user->rankFactor() : null,
    ],
    'personal' => [
        'class'        => $user->userclassName(),
        'paranoia'     => $user->paranoiaLevel(),
        'paranoiaText' => $user->paranoiaLabel(),
        'donor'        => $user->isDonor(),
        'warned'       => $user->isWarned(),
        'enabled'      => $user->isEnabled(),
        'passkey'      => $user->id() === $Viewer->id() ? $user->announceKey() : null,
    ],
    'community' => [
        'posts'           => $ForumPosts,
        'torrentComments' => $torrentComments,
        'artistComments'  => $user->propertyVisible($Viewer, 'torrentcomments++') ? $user->artistCommentCount() : null,
        'collageComments' => $user->propertyVisible($Viewer, 'torrentcomments++') ? $user->collageCommentCount() : null,
        'requestComments' => $user->propertyVisible($Viewer, 'torrentcomments++') ? $user->requestCommentCount() : null,
        'collagesStarted' => $user->propertyVisible($Viewer, 'collages+') ? $user->collagesCreated() : null,
        'collagesContrib' => $collageContribs,
        'requestsFilled'  => $RequestsFilled,
        'bountyEarned'    => $TotalBounty,
        'requestsVoted'   => $RequestsVoted,
        'bountySpent'     => $TotalSpent,
        'releaseVotes'    => $releaseVotes,
        'perfectFlacs'    => $user->propertyVisible($Viewer, 'perfectflacs+') ? $user->stats()->perfectFlacTotal() : null,
        'groups'          => $user->propertyVisible($Viewer, 'uniquegroups+') ? $user->stats()->uniqueGroupTotal() : null,
        'uploaded'        => $Uploads,
        'seeding'         => $user->propertyVisible($Viewer, 'seeding+') ? $user->stats()->seedingTotal() : null,
        'leeching'        => $user->propertyVisible($Viewer, 'leeching+') ? $user->stats()->leechTotal() : null,
        'snatched'        => $user->propertyVisible($Viewer, 'snatched+') ? $user->stats()->snatchTotal() : null,
        'invited'         => $user->propertyVisible($Viewer, 'invitedcount') ? $user->stats()->invitedTotal() : null,
        'artistsAdded'    => $ArtistsAdded,
    ]
]);
