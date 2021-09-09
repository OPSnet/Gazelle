<?php
$RequestTax = REQUEST_TAX;

// Minimum and default amount of upload to remove from the user when they vote.
// Also change in static/functions/requests.js
$MinimumVote = 20 * 1024 * 1024;

/*
 * This is the page that displays the request to the end user after being created.
 */

$requestId = (int)($_GET['id'] ?? 0);
if (!$requestId) {
    json_die("failure");
}

//First things first, lets get the data for the request.
$Request = Requests::get_request($requestId);
if ($Request === false) {
    json_die("failure");
}

$userMan = new Gazelle\Manager\User;

$CategoryID = $Request['CategoryID'];
$Requestor = $userMan->findById($Request['UserID']);
$Filler = $userMan->findById($Request['FillerID']);
//Convenience variables
$IsFilled = !empty($Request['TorrentID']);
$CanVote = !$IsFilled && $Viewer->permitted('site_vote');

if ($CategoryID == 0) {
    $CategoryName = 'Unknown';
} else {
    $CategoryName = CATEGORY[$CategoryID - 1];
}

//Do we need to get artists?
if ($CategoryName == 'Music') {
    $ReleaseName = (new Gazelle\ReleaseType)->findNameById($Request['ReleaseType']);
}

//Votes time
$RequestVotes = Requests::get_votes_array($requestId);
$VoteCount = count($RequestVotes['Voters']);
$UserCanEdit = (!$IsFilled && $Viewer->id() == $Request['UserID'] && $VoteCount < 2);
$CanEdit = ($UserCanEdit || $Viewer->permitted('site_moderate_requests'));

$JsonTopContributors = [];
$VoteMax = ($VoteCount < 5 ? $VoteCount : 5);
for ($i = 0; $i < $VoteMax; $i++) {
    $User = array_shift($RequestVotes['Voters']);
    $JsonTopContributors[] = [
        'userId'   => (int)$User['UserID'],
        'userName' => $User['Username'],
        'bounty'   => (int)$User['Bounty']
    ];
}
reset($RequestVotes['Voters']);

$commentPage = new Gazelle\Comment\Request($requestId);
if (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$thread = $commentPage->load()->thread();

$authorCache = [];
$JsonRequestComments = [];
foreach ($thread as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername] = array_values($Post);
    if (!isset($authorCache[$AuthorID])) {
        $authorCache[$AuthorID] = $userMan->findById($AuthorID);
    }
    $author = $authorCache[$AuthorID];
    $JsonRequestComments[] = [
        'postId'         => $PostID,
        'authorId'       => $AuthorID,
        'name'           => $author->username(),
        'donor'          => $author->isDonor(),
        'warned'         => $author->isWarned(),
        'enabled'        => $author->isEnabled(),
        'class'          => $userMan->userclassName($author->primaryClass()),
        'addedTime'      => $AddedTime,
        'avatar'         => $author->avatar(),
        'bbBody'         => $Body,
        'comment'        => Text::full_format($Body),
        'editedUserId'   => $EditedUserID,
        'editedUsername' => $EditedUsername,
        'editedTime'     => $EditedTime
    ];
}

$JsonTags = [];
foreach ($Request['Tags'] as $Tag) {
    $JsonTags[] = $Tag;
}
json_print('success', [
    'requestId'       => $requestId,
    'requestorId'     => $Request['UserID'],
    'requestorName'   => $Requestor->username(),
    'isBookmarked'    => (new Gazelle\Bookmark)->isRequestBookmarked($Viewer->id(), $requestId),
    'requestTax'      => $RequestTax,
    'timeAdded'       => $Request['TimeAdded'],
    'canEdit'         => $CanEdit,
    'canVote'         => $CanVote,
    'minimumVote'     => $MinimumVote,
    'voteCount'       => $VoteCount,
    'lastVote'        => $Request['LastVote'],
    'topContributors' => $JsonTopContributors,
    'totalBounty'     => $RequestVotes['TotalBounty'],
    'categoryId'      => $CategoryID,
    'categoryName'    => $CategoryName,
    'title'           => $Request['Title'],
    'year'            => (int)$Request['Year'],
    'image'           => $Request['Image'],
    'bbDescription'   => $Request['Description'],
    'description'     => Text::full_format($Request['Description']),
    'musicInfo'       => $CategoryName != "Music"
        ? null : Requests::get_artist_by_type($requestId),
    'catalogueNumber' => $Request['CatalogueNumber'],
    'releaseType'     => $Request['ReleaseType'],
    'releaseTypeName' => $ReleaseName,
    'bitrateList'     => preg_split('/\|/', $Request['BitrateList'], null, PREG_SPLIT_NO_EMPTY),
    'formatList'      => preg_split('/\|/', $Request['FormatList'], null, PREG_SPLIT_NO_EMPTY),
    'mediaList'       => preg_split('/\|/', $Request['MediaList'], null, PREG_SPLIT_NO_EMPTY),
    'logCue'          => html_entity_decode($Request['LogCue']),
    'isFilled'        => $IsFilled,
    'fillerId'        => (int)$Request['FillerID'],
    'fillerName'      => is_null($Filler) ? '' : $Filler->username(),
    'torrentId'       => (int)$Request['TorrentID'],
    'timeFilled'      => $Request['TimeFilled'],
    'tags'            => $JsonTags,
    'comments'        => $JsonRequestComments,
    'commentPage'     => $commentPage->pageNum(),
    'commentPages'    => (int)ceil($commentPage->total() / TORRENT_COMMENTS_PER_PAGE),
    'recordLabel'     => $Request['RecordLabel'],
    'oclc'            => $Request['OCLC']
]);
