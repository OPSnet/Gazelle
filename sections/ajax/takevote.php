<?php

authorize();
if (!$Viewer->permitted('site_album_votes')) {
    json_error('forbidden');
}

$groupId = (int)$_REQUEST['groupid'];
if (!$groupId) {
    json_error('no such group');
}
$vote = new Gazelle\User\Vote($Viewer);
$vote->setGroupId($groupId);

if ($_REQUEST['do'] == 'unvote') {
    [$ok, $message] = $vote->clear();
} elseif ($_REQUEST['do'] == 'vote') {
    switch($_REQUEST['vote']) {
        case 'up':
            [$ok, $message] = $vote->upvote();
            break;
        case 'down':
            [$ok, $message] = $vote->downvote();
            break;
        default:
            json_error('bad vote');
            break;
    }
}

if (!$ok) {
    json_error($message);
}
json_print('success', [
    'action' => $message,
    'id'     => $groupId,
    'total'  => $vote->total(),
    'up'     => $vote->totalUp(),
    'down'   => $vote->totalDown(),
    'score'  => $vote->score($vote->total(), $vote->totalUp()),
]);
