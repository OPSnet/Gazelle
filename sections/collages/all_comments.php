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

$isSubscribed = (new Gazelle\Manager\Subscription($LoggedUser['ID']))->isSubscribedComments('collages', $CollageID);
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
                $isSubscribed ? 'Unsubscribe' : 'Subscribe'?></a>
        </div>
    </div>
<?php
echo $paginator->linkbox();
$comments = new Gazelle\CommentViewer\Collage($Twig, $LoggedUser['ID'], $CollageID);
$comments->renderThread($commentPage->thread(), $commentPage->lastRead());
echo $paginator->linkbox();
echo $Twig->render('reply.twig', [
    'action'   => 'take_post',
    'auth'     => $LoggedUser['AuthKey'],
    'avatar'   => (new Gazelle\Manager\User)->avatarMarkup($user, $user),
    'id'       => $CollageID,
    'name'     => 'pageid',
    'subbed'   => $isSubscribed,
    'textarea' => new TEXTAREA_PREVIEW('body', 'quickpost', '',
        90, 8, false, false, true, ['tabindex="1"', 'onkeyup="resize(\'quickpost\')"' ]),
    'url'      => 'comments.php?page=collages',
    'user'     => $user,
]);
?>
</div>
<?php
View::show_footer();
