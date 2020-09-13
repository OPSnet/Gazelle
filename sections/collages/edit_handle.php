<?php
authorize();

$CollageID = (int)$_POST['collageid'];
if ($CollageID < 1) {
    error(404);
}

[$UserID, $CategoryID, $Locked, $MaxGroups, $MaxGroupsPerUser] = $DB->row("
    SELECT UserID, CategoryID, Locked, MaxGroups, MaxGroupsPerUser
    FROM collages
    WHERE ID = '$CollageID'");
if ($CategoryID == 0 && $UserID != $LoggedUser['ID'] && !check_perms('site_collages_delete')) {
    error(403);
}

if (isset($_POST['name'])) {
    $name = trim($_POST['name']);
    [$ID, $Deleted] = $DB->row("
        SELECT ID, Deleted
        FROM collages
        WHERE Name = ?
            AND ID != ?
        LIMIT 1
        ", $name, $CollageID
    );
    if ($ID) {
        if ($Deleted) {
            $Err = 'A collage with that name already exists but needs to be recovered, please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $Err = "A collage with that name already exists: <a href=\"/collages.php?id=$ID\">$name</a>.";
        }
        $ErrNoEscape = true;
        require('edit.php');
        exit;
    }
}

$tagMan = new Gazelle\Manager\Tag;
$TagList = explode(',', $_POST['tags']);
foreach ($TagList as $ID => $Tag) {
    $TagList[$ID] = $tagMan->sanitize($Tag);
}
$TagList = implode(' ', $TagList);

$Updates = ["Description = ?", "TagList = ?"];
$args = [$_POST['description'], $TagList];

if (!check_perms('site_collages_delete') && ($CategoryID == 0 && $UserID == $LoggedUser['ID'] && check_perms('site_collages_renamepersonal'))) {
    if (!stristr($_POST['name'], $LoggedUser['Username'])) {
        error("Your personal collage's title must include your username.");
    }
}

if (isset($_POST['featured']) && $CategoryID == 0 && (($LoggedUser['ID'] == $UserID && check_perms('site_collages_personal')) || check_perms('site_collages_delete'))) {
    $DB->prepared_query("
        UPDATE collages SET
            Featured = 0
        WHERE CategoryID = 0
            AND UserID = ?
        ", $UserID
    );
    $Updates[] = 'Featured = 1';
}

if (check_perms('site_collages_delete') || ($CategoryID == 0 && $UserID == $LoggedUser['ID'] && check_perms('site_collages_renamepersonal'))) {
    $Updates[] = "Name = ?";
    $args[] = trim($_POST['name']);
}

if (isset($_POST['category']) && !empty($CollageCats[$_POST['category']]) && $_POST['category'] != $CategoryID && ($_POST['category'] != 0 || check_perms('site_collages_delete'))) {
    $Updates[] = 'CategoryID = ?';
    $args[] = $_POST['category'];
}

if (check_perms('site_collages_delete')) {
    if (isset($_POST['locked']) != $Locked) {
        $Updates[] = 'Locked = ?';
        $args[] = $Locked ? '0' : '1';
    }
    if (isset($_POST['maxgroups']) && ($_POST['maxgroups'] == 0 || is_number($_POST['maxgroups'])) && $_POST['maxgroups'] != $MaxGroups) {
        $Updates[] = 'MaxGroups = ?';
        $args[] = $_POST['maxgroups'];
    }
    if (isset($_POST['maxgroups']) && ($_POST['maxgroupsperuser'] == 0 || is_number($_POST['maxgroupsperuser'])) && $_POST['maxgroupsperuser'] != $MaxGroupsPerUser) {
        $Updates[] = 'MaxGroupsPerUser = ?';
        $args[] = $_POST['maxgroupsperuser'];
    }
}

if ($Updates) {
    $args[] = $CollageID;
    $set = implode(', ', $Updates);
    $DB->prepared_query("
        UPDATE collages SET
            $set
        WHERE ID = ?
        ", ...$args
    );
}
$Cache->delete_value('collage_'.$CollageID);
header('Location: collages.php?id='.$CollageID);
