<?php
if (!check_perms('admin_whitelist')) {
    error(403);
}

$whitelist = new \Gazelle\Manager\ClientWhitelist;

View::show_header('Client Whitelist Manager');
echo $Twig->render('admin/client-whitelist.twig', [
    'auth' => $Viewer->auth(),
    'list' => $whitelist->list(),
]);
View::show_footer();
