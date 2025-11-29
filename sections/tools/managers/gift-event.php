<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}
$manager = new Manager\GiftEvent();

if (isset($_POST['name'], $_POST['begin'], $_POST['end'])) {
    if (!$Viewer->permitted('admin_schedule')) {
        Error403::error();
    }
    authorize();
    $manager->create(
        trim($_POST['name']),
        trim($_POST['begin']),
        trim($_POST['end']),
        $Viewer,
    );
    header('Location: tools.php?action=gift-event');
    exit;
} elseif (isset($_REQUEST['id'])) {
    $event = $manager->findById((int)$_REQUEST['id']);
    if (is_null($event)) {
        Error400::error('Bad event id');
    }
    if (isset($_POST['search'])) {
        $search = trim($_POST['search']);
        $user   = new Manager\User()->find($search);
        if (is_null($user)) {
            Error404::error("No such user: " . html_escape($search));
        }
        header("Location: {$event->location()}&userid={$user->id}");
        exit;
    }
    if (isset($_GET['userid'])) {
        $user = new Manager\User()->findById((int)$_GET['userid']);
        if (is_null($user)) {
            Error400::error('Bad user id');
        }
        echo $Twig->render('gift/admin-event-user.twig', [
            'event'  => $event,
            'user'   => $user,
            'viewer' => $Viewer,
        ]);
        exit;
    }
    $paginator = new Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
    $paginator->setTotal($event->totalUser());
    echo $Twig->render('gift/admin-event-detail.twig', [
        'event' => $event,
        'list'  => $event->userPage(
            $paginator->limit(),
            $paginator->offset(),
            'total',
            'desc',
        ),
        'paginator' => $paginator,
        'viewer'    => $Viewer,
    ]);
    exit;
}

echo $Twig->render('gift/admin-event.twig', [
    'gift_man' => new Manager\Gift(),
    'manager'  => $manager,
    'viewer'   => $Viewer,
]);
