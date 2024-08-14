<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

echo $Twig->render('wiki/create.twig', [
    'action'     => 'create',
    'body'       => new Gazelle\Util\Textarea('body', '', 92, 20),
    'class_list' => (new Gazelle\Manager\User())->classList(),
    'edit'       => 0,
    'read'       => 0,
    'viewer'     => $Viewer,
]);
