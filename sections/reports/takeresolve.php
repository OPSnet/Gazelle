<?php

use Gazelle\Util\Irc;

authorize();

if (!$Viewer->permitted('admin_reports') && !$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

$ReportID = (int) $_POST['reportid'];

$db = Gazelle\DB::DB();
$Type = $db->scalar("
    SELECT Type
    FROM reports
    WHERE ID = ?
    ", $ReportID
);
if (!$Viewer->permitted('admin_reports')) {
    if ($Viewer->permitted('site_moderate_forums')) {
        if (!in_array($Type, ['comment', 'post', 'thread'])) {
            error($Type);
        }
    }
}

$db->prepared_query("
    UPDATE reports SET
        Status = 'Resolved',
        ResolvedTime = now(),
        ResolverID = ?
    WHERE ID = ?
    ", $Viewer->id(), $ReportID
);

$Channels = [];

if ($Type == 'request_update') {
    $Channels[] = '#requestedits';
    $Cache->decrement('num_update_reports');
}

if (in_array($Type, ['comment', 'post', 'thread'])) {
    $Channels[] = '#forumreports';
    $Cache->decrement('num_forum_reports');
}

$Remaining = $db->scalar("
    SELECT count(*)
    FROM reports
    WHERE Status = 'New'
");

foreach ($Channels as $Channel) {
    Irc::sendMessage($Channel, "Report $ReportID resolved by " . preg_replace('/^(.{2})/', '$1·', $Viewer->username()) . ' on site (' . (int)$Remaining . ' remaining).');
}

$Cache->delete_value('num_other_reports');

header('Location: reports.php');
