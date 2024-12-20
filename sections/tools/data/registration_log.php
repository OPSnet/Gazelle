<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permittedAny('users_view_ips', 'users_view_email')) {
    error(403);
}

$registration = new Gazelle\Manager\Registration(new Gazelle\Manager\User());

if (isset($_REQUEST['before_date'])) {
    if (!str_contains($_SERVER['REQUEST_URI'], '&before_date=')) {
        $_SERVER['REQUEST_URI'] .= "&before_date={$_POST['before_date']}";
    }
    $registration->setBeforeDate($_REQUEST['before_date']);
}
if (isset($_REQUEST['after_date'])) {
    if (!str_contains($_SERVER['REQUEST_URI'], '&after_date=')) {
        $_SERVER['REQUEST_URI'] .= "&after_date={$_POST['after_date']}";
    }
    $registration->setAfterDate($_REQUEST['after_date']);
}

$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($registration->total());

echo $Twig->render('admin/registration.twig', [
    'after'     => $_REQUEST['after_date'] ?? null,
    'before'    => $_REQUEST['before_date'] ?? null,
    'ipv4'      => new Gazelle\Manager\IPv4(),
    'list'      => $registration->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
]);
