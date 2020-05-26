<?php

if (!check_perms('users_mod')) {
    error(403);
}

// validation functions
$tagSpecs = ['maxlength'=>'100', 'minlength'=>'2'];
$Val->SetFields('tag',     true, 'string', 'Enter a single tag to change.', $tagSpecs);
$Val->SetFields('replace', true, 'string', 'Enter a single replacement name.', $tagSpecs);

$tagMan = new \Gazelle\Manager\Tag;

View::show_header('Batch Tag Editor', 'validate');
echo $Val->GenerateJS('tagform');
?>

<div class="header">
    <div class="linkbox">
        <a href="tools.php?action=tags" class="brackets">Batch Tag Editor</a>
        <a href="tools.php?action=tags_aliases" class="brackets">Tag Aliases</a>
        <a href="tools.php?action=tags_official" class="brackets">Official Tags</a>
        <a href="tools.php" class="brackets">Back to toolbox</a>
    </div>
    <h2>Batch Tag Editor</h2>
</div>
<div class="thin">
<?= G::$Twig->render('tag/batch-editor.twig') ?>
    <br />
<?php

// use a loop for fast exit if any precondition fails
$failure = [];
$success = [];
while (isset($_GET['tag']) && isset($_GET['replace'])) {
    $err = $Val->ValidateForm($_GET);
    if ($err) {
        $failure[] = $err;
        break;
    }

    // what are we merging
    $current = $_GET['dirty'] ? trim($_GET['tag']) : $tagMan->sanitize($_GET['tag']);
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
            $failure[] = "Cannot merge tag <b><?= $current ?></b> with <b><?= $r ?></b>, this is an alias for <b><?= $tagMan->resolve($r) ?></b>";
        }
    }

    // trying to merge tag with itself would create big problems
    if (in_array($current, $replacement)) {
        $failure[] = "Cannot merge tag <?= $current ?> to itself";
    }

    if ($_GET['alias'] && count($replacement) > 1) {
        $failure[] = "Cannot create an alias for multiple tags";
    }

    if ($_GET['list']) {
        $affectedTorrents = $tagMan->torrentLookup($currentId);
        $affectedRequests = $tagMan->requestLookup($currentId);
    }

    if ($failure) {
        break;
    }

    $changed = $tagMan->merge($currentId, $replacement, $LoggedUser['ID']);
    $success[] = "<b>$changed tag" . ($changed == 1 ? '' : 's') . "</b> changed";

    if ($_GET['alias']) {
        $madeAlias = $tagMan->createAlias($current, $replacement[0]);
        $success[] = "<b>" . $replacement[0] . "</b> is now an alias for <b>" . $current . "</b>";
    }

    if ($_GET['official']) {
        $madeOfficial = 0;
        foreach ($replacement as $r) {
            $madeOfficial += $tagMan->officialize($r, $LoggedUser['ID']);
        }
        $success[] = "<b>$madeOfficial tag" . ($madeOfficial == 1 ? '' : 's') . "</b> made official";
    }
    break;
}

if ($failure || $success) {
?>
    <div class="box pad center">
<?php if ($failure) { ?>
        <strong>Error: unable to merge tags</strong>
        <ul class="nobullet">
<?php   foreach ($failure as $message) { ?>
            <li><?= $message ?></li>
<?php   } ?>
        </ul>
<?php
    }
    if ($success) {
?>
        <strong>Success: merge completed</strong>
        <ul class="nobullet">
<?php   foreach ($success as $message) { ?>
            <li><?= $message ?></li>
<?php   } ?>
        </ul>
<?php
    }
}

if (!$failure && $success && $_GET['list']) {
    echo G::$Twig->render('tag/merged.twig', [
        'torrents' => $affectedTorrents,
        'requests' => $affectedRequests,
    ]);
}
?>
</div>
<?php

View::show_footer();
