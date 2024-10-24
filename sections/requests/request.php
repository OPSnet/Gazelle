<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$request = (new Gazelle\Manager\Request())->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    error(404);
}

$commentPage = new Gazelle\Comment\Request($request->id(), (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');
$userMan = new Gazelle\Manager\User();

$bounty = $Viewer->ordinal()->value('request-bounty-create');
[$amount, $unit] = array_values(byte_format_array($bounty));
if (in_array($unit, ['GiB', 'TiB'])) {
    $unitGiB = true;
    if ($unit == 'TiB') {
        // the bounty box only knows about MiB and GiB, so if someone
        // uses a value > 1 TiB it needs to be scaled down.
        $bounty *= 1024;
    }
}

echo $Twig->render('request/detail.twig', [
    'amount'        => $bounty,
    'amount_box'    => $amount,
    'unit_GiB'      => isset($unitGiB),
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
