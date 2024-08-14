<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$validator = new Gazelle\Util\Validator();
$validator->setFields([
    ['tag',     true, 'string', 'Enter a single tag to change.', ['range' => [2, 100]]],
    ['replace', true, 'string', 'Enter a single replacement name.', ['range' => [2, 100]]],
]);
$tagMan = new Gazelle\Manager\Tag();

$affectedTorrents = [];
$affectedRequests = [];
$failure          = [];
$success          = [];

$changed = 0;

// use a loop for fast exit if any precondition fails
while (isset($_GET['tag']) && isset($_GET['replace'])) {
    if (!$validator->validate($_GET)) {
        $failure[] = $validator->errorMessage();
        break;
    }

    // what are we merging
    $current = isset($_GET['dirty']) ? trim($_GET['tag']) : $tagMan->sanitize($_GET['tag']);
    $currentId = $tagMan->lookup($current);
    if (!$currentId) {
        $failure[] = "No such tag: <b>$current</b>";
        break;
    }

    // what are we merging it with
    $replacement = [];
    foreach (explode(',', $_GET['replace']) as $r) {
        $replacement[] = $tagMan->sanitize($r);
    }
    $replacement = array_unique($replacement);
    foreach ($replacement as $r) {
        if ($tagMan->lookupBad($r)) {
            $failure[] = "Cannot merge tag <b>{$current}</b> with <b>{$r}</b>, this is an alias for <b>{$tagMan->resolve($r)}</b>";
        }
    }

    // trying to merge tag with itself would create big problems
    if (in_array($current, $replacement)) {
        $failure[] = "Cannot merge tag {$current} to itself";
    }

    if (isset($_GET['alias']) && count($replacement) > 1) {
        $failure[] = "Cannot create an alias for multiple tags";
    }

    if (isset($_GET['list'])) {
        $affectedTorrents = $tagMan->torrentLookup($currentId);
        $affectedRequests = $tagMan->requestLookup($currentId);
    }

    if ($failure) {
        break;
    }

    $changed = $tagMan->rename($currentId, $replacement, $Viewer);

    if (isset($_GET['alias'])) {
        $madeAlias = $tagMan->createAlias($current, $replacement[0]);
        $success[] = "<b>" . $replacement[0] . "</b> is now an alias for <b>" . $current . "</b>";
    }

    if (isset($_GET['official'])) {
        $madeOfficial = 0;
        foreach ($replacement as $r) {
            $madeOfficial += $tagMan->officialize($r, $Viewer);
        }
        $success[] = "<b>$madeOfficial tag" . plural($madeOfficial) . "</b> made official";
    }
    break;
}

echo $Twig->render('admin/tag-editor.twig', [
    'changed'      => $changed,
    'failure'      => $failure,
    'success'      => $success,
    'torrent_list' => $affectedTorrents,
    'request_list' => $affectedRequests,
]);
