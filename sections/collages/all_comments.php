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

[$NumComments, $Page, $Thread, $LastRead] = Comments::load('collages', $CollageID);

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
<?php
$Pages = Format::get_pages($Page, $NumComments, TORRENT_COMMENTS_PER_PAGE, 9);
if ($Pages) {
    echo '<br /><br />' . $Pages;
}
?>
        </div>
    </div>
<?php
$comments = new Gazelle\CommentViewer\Collage(G::$Twig, $LoggedUser['ID'], $CollageID);
$comments->renderThread($Thread, $LastRead ?: 0);

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
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php
View::show_footer();
