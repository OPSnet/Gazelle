<?php

authorize();
if (!$Viewer->permitted('site_album_votes')) {
    json_error('forbidden');
}

$tgroupId = (int)$_REQUEST['groupid'];
if (!$tgroupId) {
    json_error('no such group');
}
$vote = new Gazelle\User\Vote($Viewer);

if ($_REQUEST['do'] == 'unvote') {
    [$ok, $message] = $vote->clear($tgroupId);
} elseif ($_REQUEST['do'] == 'vote') {
    switch ($_REQUEST['vote']) {
        case 'up':
            [$ok, $message] = $vote->upvote($tgroupId);
            break;
        case 'down':
            [$ok, $message] = $vote->downvote($tgroupId);
            break;
        default:
            json_error('bad vote');
    }
} else {
    error(0);
}

if (!$ok) {
    json_error($message);
}
json_print('success', [
    'action' => $message,
    'id'     => $tgroupId,
    'total'  => $vote->total($tgroupId),
    'up'     => $vote->totalUp($tgroupId),
    'down'   => $vote->totalDown($tgroupId),
    'score'  => $vote->score($vote->total($tgroupId), $vote->totalUp($tgroupId)),
]);
