<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();
if (!$Viewer->permitted('site_album_votes')) {
    json_error('forbidden');
}

$tgroup = (new Gazelle\Manager\TGroup())->findById((int)$_REQUEST['groupid']);
if (is_null($tgroup)) {
    json_error('no such group');
}
$vote = new Gazelle\User\Vote($Viewer);

if ($_REQUEST['do'] != 'vote') {
    json_error('bad args');
}
switch ($_REQUEST['vote']) {
    case 'up':
        [$ok, $message] = $vote->upvote($tgroup);
        break;
    case 'down':
        [$ok, $message] = $vote->downvote($tgroup);
        break;
    case 'clear':
        [$ok, $message] = $vote->clear($tgroup);
        break;
    default:
        json_error('bad vote');
}

if (!$ok) {
    json_error($message);
}
json_print('success', [
    'action' => $message,
    'id'     => $tgroup->id(),
    'total'  => $vote->total($tgroup),
    'up'     => $vote->totalUp($tgroup),
    'down'   => $vote->totalDown($tgroup),
    'score'  => $vote->score($tgroup),
]);
