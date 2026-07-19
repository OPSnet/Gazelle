<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

$manager = new Manager\Tag();
$message = [];

if (isset($_POST['create']) && $_POST['create'] !== '') {
    authorize();
    $name = $manager->sanitize($_POST['create']);
    if ($name !== '') {
        $message[] = $manager->createRejected($name, $Viewer)
            ? "Created tag \"$name\""
            : "Did not create tag \"$name\", check if it already exists";
    }
}
if (isset($_POST['remove']) && $_POST['remove'] !== []) {
    authorize();
    $result = $manager->removeRejectedList(
        array_map('intval', $_POST['remove'])
    );
    $message[] = "$result tag" . plural($result) . " removed";
}

echo $Twig->render('tag/reject.twig', [
    'list'       => $manager->rejectedList(),
    'message'    => $message,
    'viewer'     => $Viewer,
]);
