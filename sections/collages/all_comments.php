<?php

/* Things to expect in $_GET:
 *     CollageID: ID of the collage curently being browsed
 *     page:    The page the user's on.
 *     page = 1 is the same as no page
 */

$CollageID = (int)$_GET['collageid'];
if ($CollageID < 1) {
    error(404);
}

$commentPage = new Gazelle\Comment\Collage($CollageID);
if (isset($_GET['postid'])) {
    $commentPage->setPostId((int)$_GET['postid']);
} elseif (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$isSubscribed = (new Gazelle\Subscription($Viewer))->isSubscribedComments('collages', $CollageID);
$Collage = new Gazelle\Collage($CollageID);

View::show_header("Comments for collage " . $Collage->name(), ['js' => 'comments,bbcode,subscriptions']);
?>
<div class="thin">
    <div class="header">
        <h2><a href="collages.php">Collages</a> &rsaquo; <?= $Collage->link() ?></h2>
        <div class="linkbox">
            <a href="#" id="subscribelink_collages<?=$CollageID?>" class="brackets" onclick="SubscribeComments('collages', <?=$CollageID?>); return false;"><?=
                $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>
        </div>
    </div>
<?php
echo $Twig->render('comment/thread.twig', [
    'page'      => $_SERVER['REQUEST_URI'],
    'thread'    => $commentPage->thread(),
    'unread'    => $commentPage->lastRead(),
    'paginator' => $paginator,
    'userMan'   => $userMan,
    'viewer'    => $Viewer,
]);
$textarea = new Gazelle\Util\Textarea('quickpost', '', 90, 8);
$textarea->setPreviewManual(true);
echo $Twig->render('reply.twig', [
    'action'   => 'take_post',
    'auth'     => $Viewer->auth(),
    'avatar'   => (new Gazelle\Manager\User)->avatarMarkup($Viewer, $Viewer),
    'id'       => $CollageID,
    'name'     => 'pageid',
    'subbed'   => $isSubscribed,
    'textarea' => $textarea,
    'url'      => 'comments.php?page=collages',
    'user'     => $Viewer,
]);
?>
</div>
<?php
View::show_footer();
