<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$request = new Manager\Request()->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    Error404::error();
}

$commentPage = new Comment\Request($request->id, (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');
$userMan = new Manager\User();

$bounty = $Viewer->ordinal()->value('request-bounty-vote');
[$amount, $unit] = array_values(byte_format_array((float)$bounty));
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
    'filler'        => $userMan->findById((int)$request->fillerId()),
    'is_bookmarked' => new User\Bookmark($Viewer)->isBookmarked($request),
    'is_subscribed' => new User\Subscription($Viewer)->isSubscribedComments('requests', $request->id),
    'paginator'     => $paginator,
    'reply'         => new Util\Textarea('quickpost', '', 90, 8)->setPreviewManual(true),
    'request'       => $request,
    'tax_rate'      => sprintf("%0.2f", 100 * (1 - REQUEST_TAX)),
    'tgroup'        => new Manager\TGroup()->findById((int)$request->tgroupId()),
    'uri'           => $_SERVER['REQUEST_URI'],
    'user_man'      => $userMan,
    'viewer'        => $Viewer,
    'vote_list'     => array_slice($request->userVoteList($userMan), 0, 5),
]);
