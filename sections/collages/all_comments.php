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

$user = new Gazelle\User($LoggedUser['ID']);
$commentPage = new Gazelle\Comment\Collage($CollageID);
if (isset($_GET['postid'])) {
    $commentPage->setPostId((int)$_GET['postid']);
} elseif (isset($_GET['page'])) {
    $commentPage->setPageNum((int)$_GET['page']);
}
$commentPage->load()->handleSubscription($user);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total());

$subscription = new Gazelle\Manager\Subscription($LoggedUser['ID']);
$Collage = new Gazelle\Collage($CollageID);

View::show_header("Comments for collage " . $Collage->name(), 'comments,bbcode,subscriptions');
?>
<div class="thin">
    <div class="header">
        <h2>
            <a href="collages.php">Collages</a> &rsaquo;
            <a href="collages.php?id=<?=$CollageID?>"><?=$Collage->name()?></a>
        </h2>
        <div class="linkbox">
            <a href="#" id="subscribelink_collages<?=$CollageID?>" class="brackets" onclick="SubscribeComments('collages', <?=$CollageID?>); return false;"><?=
                $subscription->isSubscribedComments('collages', $CollageID) ? 'Unsubscribe' : 'Subscribe'?></a>
        </div>
    </div>
<?php
echo $paginator->linkbox();
$comments = new Gazelle\CommentViewer\Collage(G::$Twig, $LoggedUser['ID'], $CollageID);
$comments->renderThread($commentPage->thread(), $commentPage->lastRead());
echo $paginator->linkbox();

if (!$LoggedUser['DisablePosting']) {
    View::parse('generic/reply/quickreply.php', [
        'InputName'    => 'pageid',
        'InputID'      => $CollageID,
        'Action'       => 'comments.php?page=collages',
        'InputAction'  => 'take_post',
        'TextareaCols' => 90,
        'SubscribeBox' => true
    ]);
}
?>
</div>
<?php
View::show_footer();
