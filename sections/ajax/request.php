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

$CategoryID = $Request['CategoryID'];
$Requestor = Users::user_info($Request['UserID']);
$Filler = $Request['FillerID'] ? Users::user_info($Request['FillerID']) : null;
//Convenience variables
$IsFilled = !empty($Request['TorrentID']);
$CanVote = !$IsFilled && check_perms('site_vote');

if ($CategoryID == 0) {
    $CategoryName = 'Unknown';
} else {
    $CategoryName = $Categories[$CategoryID - 1];
}

//Do we need to get artists?
if ($CategoryName == 'Music') {
    $ReleaseName = (new Gazelle\ReleaseType)->findNameById($Request['ReleaseType']);
}

//Votes time
$RequestVotes = Requests::get_votes_array($requestId);
$VoteCount = count($RequestVotes['Voters']);
$UserCanEdit = (!$IsFilled && $LoggedUser['ID'] == $Request['UserID'] && $VoteCount < 2);
$CanEdit = ($UserCanEdit || check_perms('site_moderate_requests'));

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

$JsonRequestComments = [];
foreach ($thread as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername] = array_values($Post);
    [$AuthorID, $Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $UserTitle] = array_values(Users::user_info($AuthorID));
    $JsonRequestComments[] = [
        'postId'         => $PostID,
        'authorId'       => $AuthorID,
        'name'           => $Username,
        'donor'          => $Donor == 1,
        'warned'         => !is_null($Warned),
        'enabled'        => $Enabled == 1,
        'class'          => Users::make_class_string($PermissionID),
        'addedTime'      => $AddedTime,
        'avatar'         => $Avatar,
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
    'requestorName'   => $Requestor['Username'],
    'isBookmarked'    => (new Gazelle\Bookmark)->isRequestBookmarked($LoggedUser['ID'], $requestId),
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
        ? new stdClass : Requests::get_artist_by_type($requestId),
    'catalogueNumber' => $Request['CatalogueNumber'],
    'releaseType'     => $Request['ReleaseType'],
    'releaseTypeName' => $ReleaseName,
    'bitrateList'     => preg_split('/\|/', $Request['BitrateList'], null, PREG_SPLIT_NO_EMPTY),
    'formatList'      => preg_split('/\|/', $Request['FormatList'], null, PREG_SPLIT_NO_EMPTY),
    'mediaList'       => preg_split('/\|/', $Request['MediaList'], null, PREG_SPLIT_NO_EMPTY),
    'logCue'          => html_entity_decode($Request['LogCue']),
    'isFilled'        => $IsFilled,
    'fillerId'        => (int)$Request['FillerID'],
    'fillerName'      => ($Filler ? $Filler['Username'] : ''),
    'torrentId'       => (int)$Request['TorrentID'],
    'timeFilled'      => $Request['TimeFilled'],
    'tags'            => $JsonTags,
    'comments'        => $JsonRequestComments,
    'commentPage'     => $commentPage->pageNum(),
    'commentPages'    => (int)ceil($commentPage->total() / TORRENT_COMMENTS_PER_PAGE),
    'recordLabel'     => $Request['RecordLabel'],
    'oclc'            => $Request['OCLC']
]);
