<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$collage = new Manager\Collage()->findById((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    Error404::error();
}

$commentPage = new Comment\Collage($collage->id, (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

echo $Twig->render('collage/comment.twig', [
    'collage'       => $collage,
    'comment'       => $commentPage,
    'is_subscribed' => new User\Subscription($Viewer)->isSubscribedComments('collages', $collage->id),
    'paginator'     => $paginator,
    'textarea'      => new Util\Textarea('quickpost', '', 90, 8)->setPreviewManual(true),
    'url'           => $_SERVER['REQUEST_URI'],
    'userMan'       => new Manager\User(),
    'viewer'        => $Viewer,
]);
