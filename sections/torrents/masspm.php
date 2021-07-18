<?php
if (!isset($_GET['id']) || !is_number($_GET['id']) || !isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) {
    error(0);
}
$GroupID = $_GET['id'];
$TorrentID = $_GET['torrentid'];

$GroupName = Torrents::display_string($GroupID, Torrents::DISPLAYSTRING_SHORT);

if (!check_perms('site_moderate_requests')) {
    error(403);
}

View::show_header('Mass PM Snatchers: ' . $GroupName, ['js' => 'upload']);
?>
<div class="thin">
    <div class="header">
        <h2>Send PM To all snatchers of "<?= $GroupName ?>"</h2>
    </div>
    <form class="send_form" name="mass_message" action="torrents.php" method="post">
        <input type="hidden" name="action" value="takemasspm" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
        <table class="layout">
            <tr>
                <td class="label">Subject</td>
                <td>
                    <input type="text" name="subject" value="" size="60" />
                </td>
            </tr>
            <tr>
                <td class="label">Message</td>
                <td>
                    <textarea name="message" id="message" cols="60" rows="8"></textarea>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Send Mass PM" />
                </td>
            </tr>
        </table>
    </form>
</div>
<?php
View::show_footer();
