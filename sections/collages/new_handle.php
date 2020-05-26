<?php
authorize();

$Val = new Validate;

$P = [];
$P = db_array($_POST);

if ($P['category'] > 0 || check_perms('site_collages_renamepersonal')) {
    $Val->SetFields('name', '1', 'string', 'The name must be between 3 and 100 characters', ['maxlength' => 100, 'minlength' => 3]);
    $name = trim($_POST['name']);
} else {
    // Get a collage name and make sure it's unique
    $name = $LoggedUser['Username'] . "'s personal collage";
    $DB->prepared_query('
        SELECT ID
        FROM collages
        WHERE Name = ?
        ', $name
    );
    $i = 1;
    $basename = $name;
    while ($DB->has_results()) {
        $name = "$basename no. " . ++$i;
        $DB->prepared_query('
            SELECT ID
            FROM collages
            WHERE Name = ?
            ', $name
        );
    }
}
$Val->SetFields('description', '1', 'string', 'The description must be between 10 and 65535 characters', ['maxlength' => 65535, 'minlength' => 10]);

$Err = $Val->ValidateForm($_POST);

if (!$Err && $P['category'] === '0') {
    $DB->query("
        SELECT count(*)
        FROM collages
        WHERE UserID = '$LoggedUser[ID]'
            AND CategoryID = '0'
            AND Deleted = '0'");
    list($CollageCount) = $DB->next_record();
    if (($CollageCount >= $LoggedUser['Permissions']['MaxCollages']) || !check_perms('site_collages_personal')) {
        $Err = 'You may not create a personal collage.';
    } elseif (check_perms('site_collages_renamepersonal') && !stristr($P['name'], $LoggedUser['Username'])) {
        $Err = 'Your personal collage\'s title must include your username.';
    }
}

if (!$Err) {
    $DB->prepared_query('
        SELECT ID, Deleted
        FROM collages
        WHERE Name = ?
        ', $name
    );
    if ($DB->has_results()) {
        list($ID, $Deleted) = $DB->next_record();
        if ($Deleted) {
            $Err = 'That collection already exists but needs to be recovered; please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $Err = "That collection already exists: <a href=\"/collages.php?id=$ID\">$ID</a>.";
        }
    }
}

if (!$Err) {
    if (empty($CollageCats[$P['category']])) {
        $Err = 'Please select a category';
    }
}

if ($Err) {
    $Name = $name;
    $Category = $_POST['category'];
    $Tags = $_POST['tags'];
    $Description = $_POST['description'];
    include(__DIR__ . '/new.php');
    die();
}

$tagMan = new \Gazelle\Manager\Tag;
$TagList = explode(',', $_POST['tags']);
foreach ($TagList as $ID => $Tag) {
    $TagList[$ID] = $tagMan->sanitize($Tag);
}

$DB->prepared_query('
    INSERT INTO collages
           (Name, Description, UserID, TagList, CategoryID)
    VALUES (?,    ?,           ?,      ?,       ?)
    ', $name, trim($_POST['description']), $LoggedUser['ID'], implode(' ', $TagList), (int)$_POST['category']
);
$CollageID = $DB->inserted_id();
$Cache->delete_value("collage_$CollageID");
Misc::write_log("Collage $CollageID (".$_POST['name'].') was created by '.$LoggedUser['Username']);
header("Location: collages.php?id=$CollageID");
