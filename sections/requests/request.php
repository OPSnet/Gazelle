<?php

$request = (new Gazelle\Manager\Request())->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    error(404);
}

$commentPage = new Gazelle\Comment\Request($request->id(), (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');
$userMan = new Gazelle\Manager\User();

echo $Twig->render('request/detail.twig', [
    'bounty'        => $Viewer->ordinal()->value('request-bounty-vote'),
    'comment_page'  => $commentPage,
    'filler'        => $userMan->findById($request->fillerId()),
    'is_bookmarked' => (new Gazelle\User\Bookmark($Viewer))->isRequestBookmarked($request->id()),
    'is_subscribed' => (new Gazelle\User\Subscription($Viewer))->isSubscribedComments('requests', $request->id()),
    'paginator'     => $paginator,
    'reply'         => (new Gazelle\Util\Textarea('quickpost', '', 90, 8))->setPreviewManual(true),
    'request'       => $request,
    'tax_rate'      => sprintf("%0.2f", 100 * (1 - REQUEST_TAX)),
    'tgroup'        => (new Gazelle\Manager\TGroup())->findById((int)$request->tgroupId()),
    'uri'           => $_SERVER['REQUEST_URI'],
    'user_man'      => $userMan,
    'viewer'        => $Viewer,
    'vote_list'     => array_slice($request->userVoteList($userMan), 0, 5),
]);
