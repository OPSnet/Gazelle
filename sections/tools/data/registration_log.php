<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('users_view_ips', 'users_view_email')) {
    Error403::error();
}

$registration = new Manager\Registration();
$paginator = new Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));

if (($_REQUEST['before_date'] ?? '') !== '') {
    $registration->setBeforeDate($_REQUEST['before_date']);
}
if (($_REQUEST['after_date'] ?? '') !== '') {
    $registration->setAfterDate($_REQUEST['after_date']);
}
if (($_REQUEST['source'] ?? '') !== '') {
    $registration->setSource((int)$_REQUEST['source']);
}

$paginator->setTotal($registration->total());

echo $Twig->render('admin/registration.twig', [
    'after' => $_REQUEST['after_date'] ?? null,
    'before' => $_REQUEST['before_date'] ?? null,
    'invite_source' => $_REQUEST['source'] ?? null,
    'invite_source_list' => new Manager\InviteSource()->usageList(),
    'is_recruit_view' => ($_REQUEST['view'] ?? null) === 'recruit',
    'ipv4' => new Manager\IPv4(),
    'list' => $registration->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
]);
