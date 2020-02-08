<?php

//------------- Disable inactive user accounts --------------------------//
// Send email
$DB->query("
    SELECT um.Username, um.Email
    FROM users_info AS ui
    INNER JOIN users_main AS um ON (um.ID = ui.UserID)
    LEFT JOIN users_levels AS ul ON (ul.UserID = um.ID AND ul.PermissionID = '".CELEB."')
    WHERE um.PermissionID IN ('".USER."', '".MEMBER    ."')
        AND um.LastAccess < '".time_minus(3600 * 24 * 110, true)."'
        AND um.LastAccess > '".time_minus(3600 * 24 * 111, true)."'
        AND um.LastAccess != '0000-00-00 00:00:00'
        AND ui.Donor = '0'
        AND um.Enabled != '2'
        AND ul.UserID IS NULL
    GROUP BY um.ID");

while (list($Username, $Email) = $DB->next_record()) {
    $Body = "Hi $Username,\n\nIt has been almost 4 months since you used your account at ".site_url().". This is an automated email to inform you that your account will be disabled in 10 days if you do not sign in.";
    Misc::send_email($Email, 'Your '.SITE_NAME.' account is about to be disabled', $Body, 'noreply');
}

$DB->query("
    SELECT um.ID
    FROM users_info AS ui
    INNER JOIN users_main AS um ON (um.ID = ui.UserID)
    LEFT JOIN users_levels AS ul ON (ul.UserID = um.ID AND ul.PermissionID = '".CELEB."')
    WHERE um.PermissionID IN ('".USER."', '".MEMBER    ."')
        AND um.LastAccess < now() - INTERVAL 120 DAY
        AND um.LastAccess != '0000-00-00 00:00:00'
        AND ui.Donor = '0'
        AND um.Enabled != '2'
        AND ul.UserID IS NULL
    GROUP BY um.ID");

if ($DB->has_results()) {
    $UserIDs = $DB->collect('ID');
    Tools::disable_users($UserIDs, 'Disabled for inactivity.', 3);
    Users::flush_enabled_users_count();
    echo count($UserIDs) . " disabled inactive\n";
}
