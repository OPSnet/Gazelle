<?php

use Gazelle\User\Vote;

$user = (new Gazelle\Manager\User)->findById((int)$_GET['id']);
if (is_null($user)) {
    json_die("failure", "bad id parameter");
}
$stats = $user->stats();

if (!$user->propertyVisibleMulti($Viewer, ['requestsfilled_count', 'requestsfilled_bounty'])) {
    $RequestsFilled = null;
    $TotalBounty    = null;
    $RequestsVoted  = null;
    $TotalSpent     = null;
} else {
    $RequestsFilled = $stats->requestBountyTotal();
    $TotalBounty    = $stats->requestBountySize();
    $RequestsVoted  = $stats->requestVoteTotal();
    $TotalSpent     = $stats->requestVoteSize();
}

$vote             = new Vote($user);
$releaseVotes     = $vote->userTotal(Vote::UPVOTE|Vote::DOWNVOTE);
$ForumPosts       = $stats->forumPostTotal();
$Uploads          = $user->propertyVisible($Viewer, 'uploads+')     ? $stats->uploadTotal() : null;
$ArtistsAdded     = $user->propertyVisible($Viewer, 'artistsadded') ? $stats->artistAddedTotal() : null;
$torrentComments  = $user->propertyVisible($Viewer, 'torrentcomments++') ? $stats->commentTotal('torrents') : null;
$collageContribs  = $user->propertyVisible($Viewer, 'collagecontribs+') ? $stats->collageContrib() : null;

$rank = new Gazelle\UserRank(
    new Gazelle\UserRank\Configuration(RANKING_WEIGHT),
    [
        'uploaded'   => $user->uploadedSize(),
        'downloaded' => $user->downloadedSize(),
        'uploads'    => $Uploads ?? 0,
        'requests'   => $RequestsFilled ?? 0,
        'posts'      => $ForumPosts,
        'bounty'     => $TotalSpent ?? 0,
        'artists'    => $ArtistsAdded ?? 0,
        'collage'    => $collageContribs ?? 0,
        'votes'      => $releaseVotes,
        'bonus'      => (new Gazelle\User\Bonus($user))->pointsSpent(),
        'comment-t'  => $torrentComments ?? 0,
    ]
);

$uploaded = $user->propertyVisible($Viewer, 'uploaded') ? $user->uploadedSize() : null;
$downloaded = $user->propertyVisible($Viewer, 'downloaded') ? $user->downloadedSize() : null;
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
    'isFriend'    => (new Gazelle\User\Friend($user))->isFriend($Viewer->id()),
    'profileText' => Text::full_format($user->infoProfile()),
    'stats' => [
        'joinedDate'    => $user->joinDate(),
        'lastAccess'    => match(true) {
            $Viewer->id() == $user->id()                => $user->lastAccessRealtime(),
            $Viewer->isStaff()                          => $user->lastAccessRealtime(),
            $user->propertyVisible($Viewer, 'lastseen') => $user->lastAccess(),
            default                                     => null,
        },
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
        'donor'        => (new Gazelle\User\Privilege($user))->isDonor(),
        'warned'       => $user->isWarned(),
        'enabled'      => $user->isEnabled(),
        'passkey'      => $user->id() === $Viewer->id() ? $user->announceKey() : null,
    ],
    'community' => [
        'posts'           => $ForumPosts,
        'torrentComments' => $torrentComments,
        'artistComments'  => $user->propertyVisible($Viewer, 'torrentcomments++') ? $stats->commentTotal('artists') : null,
        'collageComments' => $user->propertyVisible($Viewer, 'torrentcomments++') ? $stats->commentTotal('collages') : null,
        'requestComments' => $user->propertyVisible($Viewer, 'torrentcomments++') ? $stats->commentTotal('requests') : null,
        'collagesStarted' => $user->propertyVisible($Viewer, 'collages+') ? $user->collagesCreated() : null,
        'collagesContrib' => $collageContribs,
        'requestsFilled'  => $RequestsFilled,
        'bountyEarned'    => $TotalBounty,
        'requestsVoted'   => $RequestsVoted,
        'bountySpent'     => $TotalSpent,
        'releaseVotes'    => $releaseVotes,
        'perfectFlacs'    => $user->propertyVisible($Viewer, 'perfectflacs+') ? $stats->perfectFlacTotal() : null,
        'groups'          => $user->propertyVisible($Viewer, 'uniquegroups+') ? $stats->uniqueGroupTotal() : null,
        'uploaded'        => $Uploads,
        'seeding'         => $user->propertyVisible($Viewer, 'seeding+') ? $stats->seedingTotal() : null,
        'leeching'        => $user->propertyVisible($Viewer, 'leeching+') ? $stats->leechTotal() : null,
        'snatched'        => $user->propertyVisible($Viewer, 'snatched+') ? $stats->snatchTotal() : null,
        'invited'         => $user->propertyVisible($Viewer, 'invitedcount') ? $stats->invitedTotal() : null,
        'artistsAdded'    => $ArtistsAdded,
    ]
]);
