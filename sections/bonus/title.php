<?php

/** @var \Gazelle\Bonus $viewerBonus */

use Gazelle\Exception\BonusException;

if (isset($_REQUEST['preview']) && isset($_REQUEST['title']) && isset($_REQUEST['BBCode'])) {
    echo $_REQUEST['BBCode'] === 'true'
        ? Text::full_format($_REQUEST['title'])
        : Text::strip_bbcode($_REQUEST['title']);
    exit;
}

$Label = $_REQUEST['label'];
if ($Label === 'title-off') {
    authorize();
    $Viewer->removeTitle()->modify();
    header('Location: bonus.php?complete=' . urlencode($Label));
    exit;
}
if ($Label === 'title-bb-y') {
    $BBCode = 'true';
} elseif ($Label === 'title-bb-n') {
    $BBCode = 'false';
} else {
    error(403);
}

if (isset($_POST['confirm'])) {
    authorize();
    if (!isset($_POST['title'])) {
        error(403);
    }
    try {
        $viewerBonus->purchaseTitle($Label, $_POST['title']);
        header('Location: bonus.php?complete=' . urlencode($Label));
    } catch (BonusException $e) {
        switch ($e->getMessage()) {
        case 'title:too-long':
            error('This title is too long, you must reduce the length.');
            break;
        default:
            error('You cannot afford this item.');
            break;
        }
    }
}

echo $Twig->render('bonus/title.twig', [
    'auth'   => $Viewer->auth(),
    'bbcode' => $BBCode,
    'label'  => $Label,
    'price'  => $Price,
    'title'  => $Item['Title'],
]);
