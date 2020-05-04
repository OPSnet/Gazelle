<?php

use \Gazelle\Manager\Notification;

class NotificationsManagerView {
    private static $Settings;

    public static function load_js() {
        $JSIncludes = [
            'noty/noty.js',
            'noty/layouts/bottomRight.js',
            'noty/themes/default.js',
            'user_notifications.js'];
        foreach ($JSIncludes as $inc) {
?>
    <script src="<?= STATIC_SERVER . "functions/$inc" ?>?v=<?= filemtime(SERVER_ROOT . "/public/static/functions/$inc")?>" type="text/javascript"></script>
<?php
        }
    }

    private static function render_push_settings() {
        $PushService = self::$Settings['PushService'];
        $PushOptions = unserialize(self::$Settings['PushOptions']);
        if (empty($PushOptions['PushDevice'])) {
            $PushOptions['PushDevice'] = '';
        }
        ?>
        <tr>
            <td class="label"><strong>Push notifications</strong></td>
            <td>
                <select name="pushservice" id="pushservice">
                    <option value="0"<?php if (empty($PushService)) { ?> selected="selected"<?php } ?>>Disable push notifications</option>
<!--                No option 1, Notify My Android died. -->
                    <option value="2"<?php if ($PushService == 2) { ?> selected="selected"<?php } ?>>Prowl</option>
<!--                No option 3, notifo died. -->
                    <option value="4"<?php if ($PushService == 4) { ?> selected="selected"<?php } ?>>Super Toasty</option>
                    <option value="5"<?php if ($PushService == 5) { ?> selected="selected"<?php } ?>>Pushover</option>
                    <option value="6"<?php if ($PushService == 6) { ?> selected="selected"<?php } ?>>PushBullet</option>
                </select>
                <div id="pushsettings" style="display: none;">
                    <label id="pushservice_title" for="pushkey">API key</label>
                    <input type="text" size="50" name="pushkey" id="pushkey" value="<?=display_str($PushOptions['PushKey'])?>" />
                    <label class="pushdeviceid" id="pushservice_device" for="pushdevice">Device ID</label>
                    <select class="pushdeviceid" name="pushdevice" id="pushdevice">
                        <option value="<?= display_str($PushOptions['PushDevice'])?>" selected="selected"><?= display_str($PushOptions['PushDevice'])?></option>
                    </select>
                    <br />
                    <a href="user.php?action=take_push&amp;push=1&amp;userid=<?=G::$LoggedUser['ID']?>&amp;auth=<?=G::$LoggedUser['AuthKey']?>" class="brackets">Test push</a>
                    <a href="wiki.php?action=article&amp;id=113" class="brackets">View wiki guide</a>
                </div>
            </td>
        </tr>
<?php
    }

    public static function render_settings($Settings) {
        self::$Settings = $Settings;
        self::render_push_settings();
?>
        <tr>
            <td class="label">
                <strong>News announcements</strong>
            </td>
            <td>
<?php           self::render_checkbox(Notification::NEWS); ?>
            </td>
        </tr>
        <tr>
            <td class="label">
                <strong>Blog announcements</strong>
            </td>
            <td>
<?php           self::render_checkbox(Notification::BLOG); ?>
            </td>
        </tr>
        <tr>
            <td class="label">
                <strong>Inbox messages</strong>
            </td>
            <td>
<?php           self::render_checkbox(Notification::INBOX, true); ?>
            </td>
        </tr>
        <tr>
            <td class="label tooltip" title="Enabling this will give you a notification when you receive a new private message from a member of the <?=SITE_NAME?> staff.">
                <strong>Staff messages</strong>
            </td>
            <td>
<?php           self::render_checkbox(Notification::STAFFPM, false, false); ?>
            </td>
        </tr>
        <tr>
            <td class="label">
                <strong>Thread subscriptions</strong>
            </td>
            <td>
<?php           self::render_checkbox(Notification::SUBSCRIPTIONS, false, false); ?>
            </td>
        </tr>
        <tr>
            <td class="label tooltip" title="Enabling this will give you a notification whenever someone quotes you in the forums.">
                <strong>Quote notifications</strong>
            </td>
            <td>
<?php           self::render_checkbox(Notification::QUOTES); ?>
            </td>
        </tr>
<?php   if (check_perms('site_torrents_notify')) { ?>
            <tr>
                <td class="label tooltip" title="Enabling this will give you a notification when the torrent notification filters you have established are triggered.">
                    <strong>Torrent notifications</strong>
                </td>
                <td>
<?php               self::render_checkbox(Notification::TORRENTS, true, false); ?>
                </td>
            </tr>
<?php   } ?>

        <tr>
            <td class="label tooltip" title="Enabling this will give you a notification when a torrent is added to a collage you are subscribed to.">
                <strong>Collage subscriptions</strong>
            </td>
            <td>
<?php           self::render_checkbox(Notification::COLLAGES. false, false); ?>
            </td>
        </tr>
<?php
    }

    private static function render_checkbox($Name, $Traditional = false, $Push = true) {
        $Checked = self::$Settings[$Name];
        $PopupChecked = $Checked == Notification::OPT_POPUP || $Checked == Notification::OPT_POPUP_PUSH || !isset($Checked) ? ' checked="checked"' : '';
        $TraditionalChecked = $Checked == Notification::OPT_TRADITIONAL || $Checked == Notification::OPT_TRADITIONAL_PUSH ? ' checked="checked"' : '';
        $PushChecked = $Checked == Notification::OPT_TRADITIONAL_PUSH || $Checked == Notification::OPT_POPUP_PUSH || $Checked == Notification::OPT_PUSH ? ' checked="checked"' : '';

?>
        <label>
            <input type="checkbox" name="notifications_<?=$Name?>_popup" id="notifications_<?=$Name?>_popup"<?=$PopupChecked?> />
            Pop-up
        </label>
<?php   if ($Traditional) { ?>
        <label>
            <input type="checkbox" name="notifications_<?=$Name?>_traditional" id="notifications_<?=$Name?>_traditional"<?=$TraditionalChecked?> />
            Traditional
        </label>
<?php   }
        if ($Push) { ?>
        <label>
            <input type="checkbox" name="notifications_<?=$Name?>_push" id="notifications_<?=$Name?>_push"<?=$PushChecked?> />
            Push
        </label>
<?php   }
    }

    public static function format_traditional($Contents) {
        return "<a href=\"$Contents[url]\">$Contents[message]</a>";
    }
}
