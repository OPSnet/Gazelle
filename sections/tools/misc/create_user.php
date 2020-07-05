<?php
//TODO: rewrite this, make it cleaner, make it work right, add it common stuff
if (!check_perms('admin_create_users')) {
    error(403);
}

//Show our beautiful header
View::show_header('Create a User');

//Make sure the form was sent
if (isset($_POST['Username'])) {
    authorize();

    //Create variables for all the fields
    $username = trim($_POST['Username']);
    $email = trim($_POST['Email']);
    $password = $_POST['Password'];

    //Make sure all the fields are filled in
    //Don't allow a username of "0" or "1" because of PHP's type juggling
    if (!preg_match(USERNAME_REGEX, $username)) {

        //Give the Error -- Invalid username
        error('Invalid username');

    } else if (!empty($username) && !empty($email) && !empty($password) && $username != '0' && $username != '1') {

        //Create hashes...
        $secret = randomString();
        $torrentPass = randomString();

        //Create the account
        $DB->prepared_query("
            INSERT INTO users_main
                (Username, Email, PassHash, torrent_pass, Enabled, PermissionID)
            VALUES
                (?,        ?,     ?,        ?,            '1',     ?)",
            $username, $email, Users::make_password_hash($password), $torrentPass, USER);

        //Increment site user count
        $Cache->increment('stats_user_count');

        //Grab the userID
        $userId = $DB->inserted_id();

        Tracker::update_tracker('add_user', ['id' => $userId, 'passkey' => $torrentPass]);

        //Default stylesheet
        $DB->query("
            SELECT ID
            FROM stylesheets");
        list($StyleID) = $DB->next_record();

        //Auth key
        $authKey = randomString();

        //Give them a row in users_info
        $DB->prepared_query("
            INSERT INTO users_info
                   (UserID, StyleID, AuthKey)
            VALUES (?,      ?,       ?)
            ", $userId, $StyleID, $authKey
        );

        // Give the notification settings
        $DB->prepared_query("INSERT INTO users_notifications_settings (UserID) VALUES (?)", $userId);

        $DB->prepared_query("
            INSERT INTO users_leech_stats
                (UserID, Uploaded)
            VALUES
                (?,      ?)",
            $userId, STARTING_UPLOAD); 

        $DB->prepared_query('
            INSERT INTO user_bonus
                   (user_id)
            VALUES (?)
            ', $UserID
        );

        $DB->prepared_query('
            INSERT INTO user_flt
                   (user_id)
            VALUES (?)
            ', $userId
        );

        //Redirect to users profile
        header ("Location: user.php?id=$userId");

    //What to do if we don't have a username, email, or password
    } elseif (empty($username)) {

        //Give the Error -- We do not have a username
        error('Please supply a username');

    } elseif (empty($email)) {

        //Give the Error -- We do not have an email address
        error('Please supply an email address');

    } elseif (empty($password)) {

        //Give the Error -- We do not have a password
        error('Please supply a password');

    } else {

        //Uh oh, something went wrong
        error('Unknown error');

    }

//Form wasn't sent -- Show form
} else {

    ?>
    <div class="header">
        <h2>Create a User</h2>
    </div>

    <div class="thin box pad">
    <form class="create_form" name="user" method="post" action="">
        <input type="hidden" name="action" value="create_user" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
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
}

View::show_footer();
