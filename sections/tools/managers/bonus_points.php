<?php
if (!check_perms('users_mod')) {
    error(403);
}
$message = "";
if (isset($_REQUEST['add_points'])) {
    authorize();
    $active = (int)($_POST['active_points'] ?? 0);
    $upload = (int)($_POST['upload_points'] ?? 0);
    $seed   = (int)($_POST['seed_points'] ?? 0);
    $since  = trim($_POST['since_date'] ?? date("Y-m-d", strtotime("-120 day", time())));

    if ($active < 0 || $upload < 0 ||  $seed < 0) {
        error('Please enter a positive number of points.');
    }

    $bonus = new \Gazelle\Bonus;
    $activeCount = $bonus->addActivePoints($active, $since);
    $uploadCount = $bonus->addUploadPoints($upload, $since);
    $seedCount   = $bonus->addSeedPoints($seed);
    if ($activeCount) {
        $message .= '<strong>' . number_format($active) . ' bonus points added to ' . number_format($activeCount) . ' active users.</strong><br />';
    }
    if ($uploadCount) {
        $message .= '<strong>' . number_format($upload) . ' bonus points added to ' . number_format($uploadCount) . ' active uploaders.</strong><br />';
    }
    if ($seedCount) {
        $message .= '<strong>' . number_format($seed) . ' bonus points added to ' . number_format($seedCount) . ' active seeders.</strong><br />';
    }
    if ($message) {
        $message .= '<br />';
    }
}

View::show_header('Add tokens sitewide');
echo $Twig->render('admin/bonus-points.twig', [
    'auth'    => $LoggedUser['AuthKey'],
    'message' => $message,
    'since'   =>  date("Y-m-d", strtotime("-120 day", time())),
]);
View::show_footer();
