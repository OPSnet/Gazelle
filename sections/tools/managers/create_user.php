<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_create_users')) {
    error(403);
}

if (isset($_POST['Username'])) {
    authorize();

    //Create variables for all the fields
    $username = trim($_POST['Username']);
    $email    = trim($_POST['Email']);
    $password = $_POST['Password'];

    if (empty($username)) {
        error('Please supply a username');
    } elseif (empty($email)) {
        error('Please supply an email address');
    } elseif (empty($password)) {
        error('Please supply a password');
    }

    $creator = new Gazelle\UserCreator();
    try {
        $user = $creator->setUsername($username)
            ->setEmail($email)
            ->setPassword($password)
            ->setAdminComment('Created by ' . $Viewer->username() . ' via admin toolbox')
            ->create();
    } catch (Gazelle\Exception\UserCreatorException $e) {
        error(match ($e->getMessage()) {
            'username-invalid' => 'Specified username is forbidden',
            default            => 'Unable to create user',
        });
    }
    header ("Location: " . $user->location());
    exit;
}

echo $Twig->render('admin/user-create.twig', [
    'auth' => $Viewer->auth(),
]);
