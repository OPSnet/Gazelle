<?php

use Gazelle\Exception\BonusException;

if (isset($_REQUEST['preview']) && isset($_REQUEST['title']) && isset($_REQUEST['BBCode'])) {
    echo $_REQUEST['BBCode'] === 'true'
        ? Text::full_format($_REQUEST['title'])
        : Text::strip_bbcode($_REQUEST['title']);
    die();
}

$Label = $_REQUEST['label'];
if ($Label === 'title-off') {
    authorize();
    $Viewer->removeTitle()->modify();
    header('Location: bonus.php?complete=' . urlencode($Label));
}
if ($Label === 'title-bb-y') {
    $BBCode = 'true';
}
elseif ($Label === 'title-bb-n') {
    $BBCode = 'false';
}
else {
    error(403);
}

if (isset($_POST['confirm'])) {
    authorize();
    if (!isset($_POST['title'])) {
        error(403);
    }
    try {
        $Bonus->purchaseTitle($Viewer->id(), $Label, $_POST['title']);
        header('Location: bonus.php?complete=' . urlencode($Label));
    } catch (BonusException $e) {
        switch ($e->getMessage()) {
        case 'title:too-long':
            error('This title is too long, you must reduce the length.');
            break;
        default:
            error('You cannot afford this item.');
            break;
        }
    }
}

View::show_header('Bonus Points - Title', ['js' => 'bonus']);
?>
<div class="thin">
    <table>
        <thead>
        <tr>
            <td>Custom Title, <?= ($BBCode === 'true') ? 'BBCode allowed' : 'no BBCode allowed' ?> - <?=number_format($Price)?> Points</td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <form action="bonus.php?action=purchase&label=<?= $Label ?>" method="post">
                    <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                    <input type="hidden" name="confirm" value="true" />
                    <input type="text" style="width: 98%" id="title" name="title" placeholder="Custom Title"/> <br />
                    <input type="submit" onclick="ConfirmPurchase(event, '<?=$Item['Title']?>')" value="Submit" />&nbsp;<input type="button" onclick="PreviewTitle(<?=$BBCode?>);" value="Preview" /><br /><br />
                    <div id="preview"></div>
                </form>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<?php  View::show_footer();
