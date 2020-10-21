<?php
authorize();

if (!is_number($_POST['category'])) {
    error(403);
}
$categoryId = (int)$_POST['category'];
$collageMan = new Gazelle\Manager\Collage;

$Val = new Validate;
if ($categoryId > 0 || check_perms('site_collages_renamepersonal')) {
    $Val->SetFields('name', '1', 'string', 'The name must be between 3 and 100 characters', ['maxlength' => 100, 'minlength' => 3]);
    $name = trim($_POST['name']);
} else {
    $name = $collageName->personalCollageName($LoggedUser['Username']);
}
$Val->SetFields('description', '1', 'string', 'The description must be between 10 and 65535 characters', ['maxlength' => 65535, 'minlength' => 10]);

$Err = $Val->ValidateForm($_POST);

$user = new Gazelle\User($LoggedUser['ID']);
if (!$Err && $categoryId === '0') {
    if (!$user->canCreatePersonalCollage()) {
        $Err = 'You may not create a personal collage.';
    } elseif (check_perms('site_collages_renamepersonal') && !stristr($name, $LoggedUser['Username'])) {
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

if (!$Err && empty($CollageCats[$categoryId])) {
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
    $user,
    $categoryId,
    $name,
    $_POST['description'],
    (new Gazelle\Manager\Tag)->normalize(str_replace(',', ' ', $_POST['tags'])),
    new Gazelle\Log
);

header("Location: collages.php?id=" . $collage->id());
