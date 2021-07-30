<?php

if (!check_perms('admin_create_users')) {
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
    header ("Location: user.php?id=" . $user->id());
    exit;
}

View::show_header('Create a User');
?>
<div class="header">
    <h2>Create a User</h2>
</div>

<div class="thin box pad">
<form class="create_form" name="user" method="post" action="">
    <input type="hidden" name="action" value="create_user" />
    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
    <table class="layout" cellpadding="2" cellspacing="1" border="0" align="center">
        <tr valign="top">
            <td align="right" class="label">Username:</td>
            <td align="left"><input type="text" name="Username" id="username" class="inputtext" /></td>
        </tr>
        <tr valign="top">
            <td align="right" class="label">Email address:</td>
            <td align="left"><input type="email" name="Email" id="email" class="inputtext" /></td>
        </tr>
        <tr valign="top">
            <td align="right" class="label">Password:</td>
            <td align="left"><input type="password" name="Password" id="password" class="inputtext" /></td>
        </tr>
        <tr>
            <td colspan="2" align="right">
                <input type="submit" name="submit" value="Create User" class="submit" />
            </td>
        </tr>
    </table>
</form>
</div>
<?php

View::show_footer();
