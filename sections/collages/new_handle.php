<?php
authorize();

if (!is_number($_POST['category'])) {
    error(403);
}
$categoryId = (int)$_POST['category'];
$collageMan = new Gazelle\Manager\Collage;

$Val = new Gazelle\Util\Validator;
if ($categoryId > 0 || check_perms('site_collages_renamepersonal')) {
    $Val->setField('name', '1', 'string', 'The name must be between 3 and 100 characters', ['range' => [3, 100]]);
    $name = trim($_POST['name']);
} else {
    $name = $collageMan->personalCollageName($Viewer->username());
}
$Val->setField('description', '1', 'string', 'The description must be between 10 and 65535 characters', ['range' => [10, 65535]]);
$Err = $Val->validate($_POST) ? false : $Val->errorMessage();

if (!$Err && $categoryId === '0') {
    if (!$Viewer->canCreatePersonalCollage()) {
        $Err = 'You may not create a personal collage.';
    } elseif (check_perms('site_collages_renamepersonal') && !stristr($name, $Viewer->username())) {
        $Err = 'The title of your personal collage must include your username.';
    }
}

if (!$Err) {
    [$ID, $Deleted] = $collageMan->exists($name);
    if ($ID) {
        if ($Deleted) {
            $Err = 'That collection already exists but needs to be recovered; please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $Err = "That collection already exists: <a href=\"/collages.php?id=$ID\">$ID</a>.";
        }
    }
}

if (!$Err && empty(COLLAGE[$categoryId])) {
    $Err = 'Please select a category';
}

if ($Err) {
    $Name = $name;
    $Category = $categoryId;
    $Tags = $_POST['tags'];
    $Description = $_POST['description'];
    require('new.php');
    exit;
}

$collage = $collageMan->create(
    $Viewer,
    $categoryId,
    $name,
    $_POST['description'],
    (new Gazelle\Manager\Tag)->normalize(str_replace(',', ' ', $_POST['tags'])),
    new Gazelle\Log
);

if ($Viewer->option('AutoSubscribe')) {
    $collage->toggleSubscription($Viewer->id());
    (new Gazelle\Manager\Subscription($Viewer->id()))->subscribeComments('collages', $collage->id());
}

header("Location: collages.php?id=" . $collage->id());
