<?php
if (!check_perms('site_collages_recover')) {
    error(403);
}

if (isset($_POST['id']) || isset($_POST['name'])) {
    authorize();
    $collageMan = new Gazelle\Manager\Collage;
    $collage = null;

    if (isset($_POST['id'])) {
        $collage = $collageMan->recoverById((int)$_POST['id']);
    }
    if (!$collage && isset($_POST['name'])) {
        $collage = $collageMan->recoverByName(trim($_POST['name']));
    }
    if (!$collage) {
        error('Collage is completely deleted');
    } else {
        $collageId = $collage->flush()->id();
        (new Gazelle\Log)->general("Collage $collageId was recovered by " . $LoggedUser['Username']);
        header("Location: collages.php?id=$collageId");
        exit;
    }
}

View::show_header('Collage recovery!');
echo G::$Twig->render('collage/recover.twig', [
    'auth' => $LoggedUser['AuthKey'],
]);
View::show_footer();
