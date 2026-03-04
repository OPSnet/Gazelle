<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_search_audit_trail')) {
    Error403::error();
}

$search  = new Search\AuditTrail();
$userMan = new Manager\User();

$after = trim($_GET['after'] ?? '');
if ($after !== '') {
    $search->setCreatedAfter($after);
}
$before = trim($_GET['before'] ?? '');
if ($before !== '') {
    $search->setCreatedBefore($before);
}
$note = trim($_GET['note'] ?? '');
if ($note !== '') {
    $search->setNote($note);
}
$userList = [];
if (isset($_GET['user'])) {
    foreach (array_unique(array_map('trim', explode(' ', $_GET['user']))) as $name) {
        $user = $userMan->find($name);
        if ($user) {
            $userList[] = $user;
        }
    }
    if ($userList !== []) {
        $search->setUserList($userList);
    }
}
$authorList = [];
if (isset($_GET['author'])) {
    $authorList = [];
    foreach (array_unique(array_map('trim', explode(' ', $_GET['author']))) as $name) {
        $user = $userMan->find($name);
        if ($user) {
            $authorList[] = $user;
        }
    }
    if ($authorList !== []) {
        $search->setAuthorList($authorList);
    }
}
$search->setEventList(array_key_extract_suffix('event-', $_GET, cast: false));

$paginator = new Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('admin/search-audit-trail.twig', [
    'form' => [
        'after'  => $after,
        'before' => $before,
        'note'   => $note,
        'author' => implode(' ', array_map(fn ($u) => "@{$u->username()}", $authorList)),
        'user'   => implode(' ', array_map(fn ($u) => "@{$u->username()}", $userList)),
        'event'  => $search->eventList(),
    ],
    'heading'   => $search->heading(),
    'page'      => $search->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
