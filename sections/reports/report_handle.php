<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Util\Irc;

authorize();

$subjectId = (int)$_POST['id'];
if (!$subjectId || empty($_POST['type'])) {
    Error400::error();
}

require __DIR__ . '/array.php';
/** @var array $Types */
if (!array_key_exists($_POST['type'], $Types)) {
    Error400::error();
}
$subjectType = (string)$_POST['type'];
$location = match ($subjectType) {
    'collage' => "collages.php?id=$subjectId",
    'comment' => "comments.php?action=jump&postid=$subjectId",
    'post'    => new Manager\ForumPost()->findById($subjectId)?->location(), // could be null
    'request' => "requests.php?action=view&id=$subjectId",
    'thread'  => "forums.php?action=viewthread&threadid=$subjectId",
    'user'    => "user.php?id=$subjectId",
    default   => null, // definitely a problem
};
if (is_null($location)) {
    Error400::error("Cannot generate a link to the reported item '$subjectType'");
}

$reason = "[b]comments[/b]: {$_POST['reason']}";
$report = new Manager\Report()->create($Viewer, $subjectId, $subjectType, $reason);
if (in_array($report->subjectType(), ['user', 'comment'])) {
    Irc::sendMessage(
        IRC_CHAN_MOD,
        "{$Viewer->username()} reported a $subjectType, see {$report->location()}"
    );
}
header("Location: $location");
