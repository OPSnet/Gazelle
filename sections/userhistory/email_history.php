<?php

if (!check_perms('users_view_email')) {
    error(403);
}

$userId = (int)$_GET['userid'];
if (!$userId) {
    error(404);
}
$user = new Gazelle\User($userId);

// Get history of matches
$DB->prepared_query("
    SELECT
        uhe.Email   as email,
        uhe.UserID  as user_id,
        um.Username as username,
        uhe.Time    as created,
        uhe.IP      as ipv4,
        um.Enabled  as is_enabled,
        ui.Warned   as is_warned,
        (donor.UserID IS NOT NULL) AS is_donor
    FROM users_history_emails AS uhe
    INNER JOIN users_main AS um ON (um.ID = uhe.UserID)
    INNER JOIN users_info AS ui ON (ui.UserID = uhe.UserID)
    LEFT JOIN users_levels AS donor ON (donor.UserID = um.ID)
        AND donor.PermissionID = (SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1)
    WHERE uhe.UserID != ?
        AND uhe.Email in (SELECT DISTINCT Email FROM users_history_emails WHERE userid = ?)
    ORDER BY uhe.Email, uhe.Time DESC
    ", $userId, $userId
);
$other = $DB->to_array(false, MYSQLI_ASSOC, false);

View::show_header("Email history for <?= Users::user_info($userId)['Username'] ?>");
?>
<div class="thin">
    <div class="header">
        <h2>Email history for <a href="user.php?id=<?= $userId ?>"><?= Users::user_info($userId)['Username'] ?></a></h2>
    </div>
    <table>
<?= G::$Twig->render('admin/user-info-email.twig', [ 'info'   => $user->emailHistory() ]) ?>
    </table>

<?= G::$Twig->render('user/email-dup.twig', [ 'other' => $other ]) ?>

</div>
