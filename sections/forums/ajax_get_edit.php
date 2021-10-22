<?php

if (!$Viewer->permitted('site_admin_forums')) {
    error(403);
}

$PostID = (int)$_GET['postid'];
if (!$PostID) {
    die();
}
$Type = $_GET['type'] ?? '';
if (!in_array($_GET['type'], ['forums', 'collages', 'requests', 'torrents', 'artist'])) {
    die();
}
if ($_GET['depth'] != (int)$_GET['depth']) {
    die();
}
$Depth = (int)$_GET['depth'];

$commentMan = new Gazelle\Manager\Comment;
$Edits = $commentMan->loadEdits($Type, $PostID);

[$UserID, $Time] = $Edits[$Depth];
if ($Depth != 0) {
    $Body = $Edits[$Depth - 1][2];
} else {
    //Not an edit, have to get from the original
    switch ($Type) {
        case 'forums':
            $Body = (new Gazelle\Manager\Forum)->findByPostId($PostID)->postBody($PostID);
            break;
        case 'collages':
        case 'requests':
        case 'artist':
        case 'torrents':
            $Body = $commentMan->findById($PostID)->body();
            break;
    }
}
?>
                <?=Text::full_format($Body)?>
                <br />
                <br />
                <span class="last_edited">
<?php
if ($Depth < count($Edits)) { ?>

                    <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth + 1)?>); return false;">&laquo;</a>
                    <?=(($Depth == 0) ? 'Last edited by' : 'Edited by')?>
                    <?=Users::format_username($UserID, false, false, false) ?> <?=time_diff($Time, 2, true, true)?>

<?php } else { ?>
                    <em>Original Post</em>
<?php
}

if ($Depth > 0) { ?>
                    <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth - 1)?>); return false;">&raquo;</a>
<?php } ?>
                </span>
