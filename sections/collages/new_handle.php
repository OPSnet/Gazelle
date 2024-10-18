<?php
/** @phpstan-var \Gazelle\User $Viewer */

use Gazelle\Enum\CollageType;

if (!$Viewer->permitted('site_collages_create') && !$Viewer->canCreatePersonalCollage()) {
    error(403);
}

authorize();

if (!isset($_POST['category'])) {
    error(403);
}
$categoryId = (int)$_POST['category'];
$collageMan = new Gazelle\Manager\Collage();

$Val = new Gazelle\Util\Validator();
if ($categoryId != CollageType::personal->value || $Viewer->permitted('site_collages_renamepersonal')) {
    $Val->setField('name', true, 'string', 'The name must be between 3 and 100 characters', ['range' => [3, 100]]);
    $name = trim($_POST['name']);
} else {
    $name = $collageMan->personalCollageName($Viewer->username());
}
$Val->setField('description', true, 'string', 'The description must be between 10 and 65535 characters', ['range' => [10, 65535]]);
$Err = $Val->validate($_POST) ? false : $Val->errorMessage();

if (!$Err && $categoryId === CollageType::personal->value) {
    if (!$Viewer->canCreatePersonalCollage()) {
        $Err = 'You may not create a personal collage.';
    } elseif ($Viewer->permitted('site_collages_renamepersonal') && !stristr($name, $Viewer->username())) {
        $Err = 'The title of your personal collage must include your username.';
    }
}

if (!$Err) {
    $check = $collageMan->findByName($name);
    if ($check) {
        if ($check->isDeleted()) {
            $Err = 'That collage already exists but needs to be recovered; please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $checkId = $check->id();
            $Err = "That collage already exists: {$check->link()}.";
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
    (new Gazelle\Manager\Tag())->normalize(str_replace(',', ' ', (string)$_POST['tags'])),
    new Gazelle\Log()
);

if ($Viewer->option('AutoSubscribe')) {
    $collage->toggleSubscription($Viewer);
    (new Gazelle\User\Subscription($Viewer))->subscribeComments('collages', $collage->id());
}

header('Location: ' . $collage->location());
