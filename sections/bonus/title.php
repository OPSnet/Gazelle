<?php

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
    $viewerBonus = new \Gazelle\User\Bonus($Viewer);
    if (!$viewerBonus->purchaseTitle($Label, $_POST['title'])) {
        error('This title is too long, you must reduce the length (or you do not have enough bonus points).');
    }
    header('Location: bonus.php?complete=' . urlencode($Label));
    exit;
}

echo $Twig->render('bonus/title.twig', [
    'auth'   => $Viewer->auth(),
    'bbcode' => $BBCode,
    'label'  => $Label,
    'price'  => $Price,
    'title'  => $Item['Title'],
]);
