<?php

authorize();

/**
 * Hello there. If you are refactoring this code, please note that this functionality also sort of exists in /classes/referral.class.php
 * Super sorry for doing that, but this is totally not reusable.
 */

$UserCount = Users::get_enabled_users_count();
$UserID = $LoggedUser['ID'];

//This is where we handle things passed to us
$CanLeech = $DB->scalar("
    SELECT can_leech
    FROM users_main
    WHERE ID = ?
    ", $UserID
);

if ($LoggedUser['RatioWatch']
    || !$CanLeech
    || $LoggedUser['DisableInvites'] == '1'
    || $LoggedUser['Invites'] == 0
    && !check_perms('site_send_unlimited_invites')
    || (
        $UserCount >= USER_LIMIT
        && USER_LIMIT != 0
        && !check_perms('site_can_invite_always')
        )
    ) {
        error(403);
} 

$Email = $_POST['email'];
$Username = $LoggedUser['Username'];
$SiteName = SITE_NAME;
$SiteURL = site_url();
$InviteReason = check_perms('users_invite_notes') ? $_POST['reason'] : '';

//MultiInvite
if (strpos($Email, '|') !== false && check_perms('site_send_unlimited_invites')) {
    $Emails = explode('|', $Email);
} else {
    $Emails = [$Email];
}

foreach ($Emails as $CurEmail) {
    $CurEmail = trim($CurEmail);
    if (!preg_match("/^".EMAIL_REGEX."$/i", $CurEmail)) {
        if (count($Emails) > 1) {
            continue;
        } else {
            error('Invalid email.');
            header('Location: user.php?action=invite');
            die();
        }
    }
    $DB->prepared_query("
        SELECT 1
        FROM invites
        WHERE InviterID = ?
            AND Email = ?
        ", $LoggedUser['ID'], $CurEmail
    );
    if ($DB->has_results()) {
        error('You already have a pending invite to that address!');
        header('Location: user.php?action=invite');
        die();
    }
    $InviteKey = randomString();

$DisabledChan = BOT_DISABLED_CHAN;
$IRCServer = BOT_SERVER;

$Message = <<<EOT
The user $Username has invited you to join $SiteName and has specified this address ($CurEmail) as your email address. If you do not know this person, please ignore this email, and do not reply.

Please note that selling invites, trading invites, and giving invites away publicly (e.g. on a forum) is strictly forbidden. If you have received your invite as a result of any of these things, do not bother signing up - you will be banned and lose your chances of ever signing up legitimately.

If you have previously had an account at $SiteName, do not use this invite. Instead, please join $DisabledChan on $IRCServer and ask for your account to be reactivated.

To confirm your invite, click on the following link:

{$SiteURL}register.php?invite=$InviteKey

After you register, you will be able to use your account. Please take note that if you do not use this invite in the next 3 days, it will expire. We urge you to read the RULES and the wiki immediately after you join.

Thank you,
$SiteName Staff
EOT;

    $DB->prepared_query("
        INSERT INTO invites
               (InviterID, InviteKey, Email, Reason, Expires)
        VALUES (?,         ?,         ?,     ?,      now() + INTERVAL 3 DAY)
        ", $LoggedUser['ID'], $InviteKey, $CurEmail, $InviteReason
    );

    if (!check_perms('site_send_unlimited_invites')) {
        $DB->prepared_query("
            UPDATE users_main
            SET Invites = GREATEST(Invites, 1) - 1
            WHERE ID = ?
            ", $LoggedUser['ID']
        );
        $Cache->begin_transaction('user_info_heavy_'.$LoggedUser['ID']);
        $Cache->update_row(false, ['Invites' => '-1']);
        $Cache->commit_transaction(0);
    }

    Misc::send_email($CurEmail, 'You have been invited to '.SITE_NAME, $Message, 'noreply');
}

header('Location: user.php?action=invite');
