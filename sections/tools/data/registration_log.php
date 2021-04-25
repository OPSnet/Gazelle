<?php

if (!check_perms('users_view_ips') || !check_perms('users_view_email')) {
    error(403);
}

$registration = new Gazelle\Manager\Registration;

if ($_REQUEST['before_date']) {
    if (strpos($_SERVER['REQUEST_URI'], '&before_date=') === false) {
        $_SERVER['REQUEST_URI'] .= "&before_date={$_POST['before_date']}";
    }
    $registration->setBeforeDate($_REQUEST['before_date']);
}
if ($_REQUEST['after_date']) {
    if (strpos($_SERVER['REQUEST_URI'], '&after_date=') === false) {
        $_SERVER['REQUEST_URI'] .= "&after_date={$_POST['after_date']}";
    }
    $registration->setAfterDate($_REQUEST['after_date']);
}

$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($registration->total());

View::show_header('Registration log');
echo $Twig->render('admin/registration.twig', [
    'after'  => $_REQUEST['after_date'] ?? null,
    'before' => $_REQUEST['before_date'] ?? null,
    'list'   => array_map(function ($u) { return new Gazelle\User($u); },
        $registration->page($paginator->limit(), $paginator->offset())),
    'paginator' => $paginator,
]);
View::show_footer();
