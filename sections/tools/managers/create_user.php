<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_create_users')) {
    Error403::error();
}

if (isset($_POST['Username'])) {
    authorize();

    //Create variables for all the fields
    $username = trim($_POST['Username']);
    $email    = trim($_POST['Email']);
    $password = $_POST['Password'];

    if (empty($username)) {
        Error400::error('Please supply a username');
    } elseif (empty($email)) {
        Error400::error('Please supply an email address');
    } elseif (empty($password)) {
        Error400::error('Please supply a password');
    }

    $creator = new UserCreator();
    try {
        $user = $creator->setUsername($username)
            ->setEmail($email)
            ->setPassword($password)
            ->addNote('Created by ' . $Viewer->username() . ' via admin toolbox')
            ->create();
    } catch (Exception\UserCreatorException $e) {
        Error400::error(match ($e->getMessage()) {
            'username-invalid' => 'Specified username is forbidden',
            default            => 'Unable to create user',
        });
    }
    header ("Location: " . $user->location());
    exit;
}

echo $Twig->render('admin/user-create.twig', [
    'viewer' => $Viewer,
]);
