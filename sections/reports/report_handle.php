<?php

use Gazelle\Util\Irc;

authorize();

$subjectId = (int)$_POST['id'];
if (!$subjectId || empty($_POST['type']) || ($_POST['type'] !== 'request_update' && empty($_POST['reason']))) {
    error(404);
}

require_once('array.php');
/** @var array $Types */
if (!array_key_exists($_POST['type'], $Types)) {
    error(403);
}
$subjectType = $_POST['type'];

if ($subjectType !== 'request_update') {
    $reason = $_POST['reason'];
} else {
    $year = trim($_POST['year']);
    if (empty($year) || !is_number($year)) {
        error('Year must be specified.');
    }
    $reason = "[b]Year[/b]: {$year}.\n\n";
    // If the release type is somehow invalid, return "Not given"; otherwise, return the release type.
    $reason .= '[b]Release type[/b]: ' . ((empty($_POST['releasetype']) || !is_number($_POST['releasetype']) || $_POST['releasetype'] == '0')
        ? 'Not given' : (new Gazelle\ReleaseType)->findNameById($_POST['releasetype'])) . " . \n\n";
    $reason .= '[b]Additional comments[/b]: ' . $_POST['comment'];
}

$location = match ($subjectType) {
    'collage'        => "collages.php?id=$subjectId",
    'comment'        => "comments.php?action=jump&postid=$subjectId",
    'post'           => (new Gazelle\Manager\ForumPost)->findById($subjectId)?->location(), // could be null
    'request',
    'request_update' => "requests.php?action=view&id=$subjectId",
    'thread'         => "forums.php?action=viewthread&threadid=$subjectId",
    'user'           => "user.php?id=$subjectId",
    default          => null, // definitely a problem
};
if (is_null($location)) {
    error("Cannot generate a link to the reported item");
}

$report = (new Gazelle\Manager\Report(new Gazelle\Manager\User))->create($Viewer, $subjectId, $subjectType, $reason);
if (in_array($report->subjectType(), ['user', 'comment'])) {
    Irc::sendMessage(
        IRC_CHAN_MOD,
        "Report #{$report->id()} – {$Viewer->username()} reported a $subjectType: " . SITE_URL . "/$location"
    );
}
header("Location: $location");
