<?php
use Gazelle\Exception\BonusException;

if (isset($_POST['confirm'])) {
    authorize();
    try {
        if (empty($_POST['user'])) {
            error('You have to enter a username to give tokens to.');
        }
        $user = (new Gazelle\Manager\User)->findByUsername(urldecode($_POST['user']));
        if (is_null($user)) {
            error('Nobody with that name found at ' . SITE_NAME . '. Are you certain the spelling is right?');
        } elseif ($user->id() == $Viewer->id()) {
            error('You cannot gift yourself tokens, they are cheaper to buy directly.');
        }
        try {
            $viewerBonus->purchaseTokenOther($user->id(), $Label, $_POST['message'] ?? '');
        } catch (BonusException $e) {
            if ($e->getMessage() == 'otherToken:no-gift-funds') {
                error('Purchase for other not concluded. Either you lacked funds or they have chosen to decline FL tokens.');
            } else {
                error(0);
            }
        }
        header('Location: bonus.php?complete=' . urlencode($Label));
    } catch (BonusException $e) {
        switch ($e->getMessage()) {
        default:
            error('You cannot afford this item.');
            break;
        }
    }
}

echo $Twig->render('bonus/token-other.twig', [
    'auth'     => $Viewer->auth(),
    'price'    => $Price,
    'label'    => $Label,
    'textarea' => new Gazelle\Util\Textarea('message', ''),
    'item'     => $Item['Title']
]);
