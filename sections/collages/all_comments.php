<?php

$collage = new Gazelle\Collage((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}

$commentPage = new Gazelle\Comment\Collage($collage->id());
if (isset($_GET['postid'])) {
    $commentPage->setPostId((int)$_GET['postid']);
} elseif (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$textarea = new Gazelle\Util\Textarea('quickpost', '', 90, 8);
$textarea->setPreviewManual(true);

echo $Twig->render('collage/comment.twig', [
    'avatar'        => (new Gazelle\Manager\User)->avatarMarkup($Viewer, $Viewer),
    'collage'       => $collage,
    'comment'       => $commentPage,
    'is_subscribed' => (new Gazelle\Subscription($Viewer))->isSubscribedComments('collages', $collage->id()),
    'paginator'     => $paginator,
    'textarea'      => $textarea,
    'url'           => $_SERVER['REQUEST_URI'],
    'viewer'        => $Viewer,
]);
