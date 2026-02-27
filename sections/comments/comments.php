<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\CommentType;

$userMan = new Manager\User();
if (!isset($_GET['id'])) {
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)($_GET['id'] ?? 0));
    if (is_null($user)) {
        Error404::error();
    }
    if (!$user->propertyVisible($Viewer, 'torrentcomments')) {
        Error403::error();
    }
}
$commentType = match ($_REQUEST['action'] ?? '') {
    'artist'   => CommentType::artist,
    'collages' => CommentType::collage,
    'requests' => CommentType::request,
    default    => CommentType::tgroup,
};

$search = new Search\Comment(
    $commentType,
    $_REQUEST['type'] ?? 'default',
    $user->id === $Viewer->id,
    $user,
);

$paginator = new Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('comment/list.twig', [
    'paginator'  => $paginator,
    'other_type' => array_filter(
        [CommentType::artist, CommentType::collage, CommentType::request, CommentType::tgroup],
        fn ($t) => $t !== $commentType,
    ),
    'search' => $search,
    'user'   => $user,
    'viewer' => $Viewer,
]);
