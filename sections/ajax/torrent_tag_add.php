<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */

if ($Viewer->disableTagging()) {
    json_or_error('tagging disabled for your account', 403);
}

if (!defined('AJAX') || !AJAX) {
    authorize();
}

$tgMan = new Gazelle\Manager\TGroup();
$tgroup = $tgMan->findById((int)($_REQUEST['groupid'] ?? 0));
if (is_null($tgroup)) {
    json_or_error('invalid groupid', 0);
}

//Delete cached tag used for undos
if (isset($_REQUEST['undo'])) {
    $Cache->delete_value("deleted_tags_{$tgroup->id()}_{$Viewer->id()}");
}

$added    = [];
$rejected = [];
$tagMan   = new \Gazelle\Manager\Tag();
$Tags     = array_unique(explode(',', $_REQUEST['tagname']));

foreach ($Tags as $tagName) {
    $tagName = $tagMan->sanitize($tagName);
    $resolved = $tagMan->resolve($tagName);

    if (empty($resolved)) {
        $rejected[] = $tagName;
    } else {
        $tagId = $tagMan->create($resolved, $Viewer);
        if ($tagMan->torrentTagHasVote($tagId, $tgroup, $Viewer)) {
            // User has already voted on this tag
            if (defined('AJAX')) {
                json_error('you have already voted on this tag');
            } else {
                header('Location: ' . $tgroup->location());
            }
            exit;
        }
        $tagMan->createTorrentTag($tagId, $tgroup, $Viewer, 3);
        $tagMan->createTorrentTagVote($tagId, $tgroup, $Viewer, 'up');
        $added[] = $resolved;

        (new Gazelle\Log())->group($tgroup, $Viewer, "Tag \"$resolved\" added to group");
    }
}

$tgroup->refresh();
if (AJAX) {
    json_print('success', [
        'added'    => $added,
        'rejected' => $rejected,
    ]);
    exit;
}

header('Location: ' . $tgroup->location());
