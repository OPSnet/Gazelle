<?php
if (!check_perms('users_mod')) {
    error(403);
}

$args = [];
$cond = [];
if (isset($_GET['username'])) {
    $cond[] = "um.Username REGEXP ?";
    $args[] = trim($_GET['username']);
}

$from = "FROM users_main AS um
    INNER JOIN users_donor_ranks AS d ON (um.ID = d.UserID)
    INNER JOIN donor_rewards AS r USING (UserID)
    " . (empty($cond) ? '' : ("WHERE " . implode(' AND ', $cond)));

[$page, $limit] = Format::page_limit(USERS_PER_PAGE);
$pages = Format::get_pages($page, $DB->scalar("SELECT count(*) $from", ... $args), USERS_PER_PAGE);

$DB->prepared_query("
    SELECT um.Username,
        d.UserID AS user_id,
        d.Rank AS rank,
        IF(hidden=0, 'No', 'Yes') AS hidden,
        d.DonationTime AS donation_time,
        r.IconMouseOverText AS icon_mouse,
        r.AvatarMouseOverText AS avatar_mouse,
        r.CustomIcon AS custom_icon,
        r.SecondAvatar AS second_avatar,
        r.CustomIconLink AS custom_link
    $from
    ORDER BY d.Rank DESC, d.DonationTime ASC
    LIMIT $limit
    ", ...$args
);

$title = "Donor Rewards";

View::show_header($title);

echo $Twig->render('donation/reward-list.twig', [
    'pages' => $pages,
    'title' => $title,
    'user' => $DB->to_array(false, MYSQLI_ASSOC, false),
]);

View::show_footer();
