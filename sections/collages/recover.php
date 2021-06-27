<?php
if (!check_perms('site_collages_recover')) {
    error(403);
}

$_POST['id'] = (int)($_POST['id'] ?? 0);
$_POST['name'] = trim($_POST['name'] ?? '');

if (!empty($_POST['id']) || $_POST['name'] !== '') {
    authorize();
    $collageMan = new Gazelle\Manager\Collage;
    $collage = null;

    if (!empty($_POST['id'])) {
        $collage = $collageMan->recoverById($_POST['id']);
    }
    if (!$collage && $_POST['name'] !== '') {
        $collage = $collageMan->recoverByName($_POST['name']);
    }
    if (!$collage) {
        error('Collage is completely deleted');
    } else {
        $collageId = $collage->flush()->id();
        (new Gazelle\Log)->general("Collage $collageId was recovered by " . $Viewer->username());
        header("Location: collages.php?id=$collageId");
        exit;
    }
}

View::show_header('Collage recovery!');
echo $Twig->render('collage/recover.twig', [
    'auth' => $Viewer->auth(),
]);
View::show_footer();
