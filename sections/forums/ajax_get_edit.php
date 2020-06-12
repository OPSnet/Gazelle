<?php
if (!check_perms('site_admin_forums')) {
    error(403);
}

$PostID = (int)$_GET['postid'];
if ($PostID < 1) {
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

if (!($Edits = $Cache->get_value($Type.'_edits_'.$PostID))) {
    $DB->prepared_query("
        SELECT EditUser, EditTime, Body
        FROM comments_edits
        WHERE Page = ?
            AND PostID = ?
        ORDER BY EditTime DESC
        ", $Type, $PostID
    );
    $Edits = $DB->to_array();
    $Cache->cache_value($Type.'_edits_'.$PostID, $Edits, 0);
}

list($UserID, $Time) = $Edits[$Depth];
if ($Depth != 0) {
    list(,,$Body) = $Edits[$Depth - 1];
} else {
    //Not an edit, have to get from the original
    switch ($Type) {
        case 'forums':
            $Body = $DB->scalar("
                SELECT Body
                FROM forums_posts
                WHERE ID = ?
                ", $PostID
            );
            break;
        case 'collages':
        case 'requests':
        case 'artist':
        case 'torrents':
            $Body = $DB->scalar("
                SELECT Body
                FROM comments
                WHERE Page = ?
                    AND ID = ?
                ", $Type, $PostID
            );
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

<?php
} else { ?>
                    <em>Original Post</em>
<?php
}

if ($Depth > 0) { ?>
                    <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth - 1)?>); return false;">&raquo;</a>
<?php
} ?>
                </span>
