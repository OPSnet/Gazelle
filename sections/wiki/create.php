<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

echo $Twig->render('wiki/create.twig', [
    'action'     => 'create',
    'body'       => new Util\Textarea('body', '', 92, 20),
    'class_list' => new Manager\User()->classList(),
    'edit'       => 0,
    'read'       => 0,
    'viewer'     => $Viewer,
]);
