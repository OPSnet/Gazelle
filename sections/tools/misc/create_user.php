<?php

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

    $creator = new Gazelle\UserCreator;
    try {
        $user = $creator->setUsername($username)
            ->setEmail($email)
            ->setPassword($password)
            ->setIpaddr('127.0.0.1')
            ->setAdminComment('Created by ' . $Viewer->username() . ' via admin toolbox')
            ->create();
    }
    catch (Gazelle\Exception\UserCreatorException $e) {
        switch ($e->getMessage()) {
            case 'username-invalid':
                error('Specified username is forbidden');
                break;
            default:
                error('Unable to create user');
                break;
        }
    }
    header ("Location: " . $user->url());
    exit;
}

echo $Twig->render('admin/user-create.twig', [
    'auth' => $Viewer->auth(),
]);
