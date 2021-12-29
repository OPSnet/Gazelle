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
            $viewerBonus->purchaseTokenOther($user->id(), $Label, $_POST['message']);
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

View::show_header('Bonus Points - Gift Tokens', ['js' => 'bonus']);
?>
<div class="thin">
    <table>
        <thead>
        <tr>
            <td>Gift Tokens - <?=number_format($Price)?> Points</td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <form action="bonus.php?action=purchase&label=<?= $Label ?>" method="post">
                    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                    <input type="hidden" name="confirm" value="true" />
                    <input type="text" style="width: 98%" id="user" name="user" placeholder="User"/> <br />
                    <input type="text" style="width: 98%" id="message" name="message" placeholder="Message"/> <br />
                    <input type="submit" onclick="ConfirmPurchase(event,'<?=$Item['Title']?>')" value="Submit" />
                </form>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<?php  View::show_footer();
