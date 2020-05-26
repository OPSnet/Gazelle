<?php
authorize();

if (!isset($_REQUEST['page']) || !in_array($_REQUEST['page'], ['artist', 'collages', 'requests', 'torrents']) || !isset($_POST['pageid']) || !is_number($_POST['pageid']) || !isset($_POST['body']) || trim($_POST['body']) === '') {
    error(0);
}

if ($LoggedUser['DisablePosting']) {
    error('Your posting privileges have been removed.');
}

$Page = $_REQUEST['page'];
$PageID = (int)$_POST['pageid'];
if (!$PageID) {
    error(404);
}

$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
if (isset($_POST['subscribe']) && !$subscription->isSubscribedComments($Page, $PageID)) {
    $subscription->subscribeComments($Page, $PageID);
}

$PostID = Comments::post($Page, $PageID, $_POST['body']);

header("Location: " . Comments::get_url($Page, $PageID, $PostID));
