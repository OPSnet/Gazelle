<?php

if (!$Viewer->permitted("admin_global_notification")) {
    error(403);
}

$notifMan = new Gazelle\Manager\Notification;
$level = $notifMan->level();

$GlobalNotification = $notifMan->global();
if ($GlobalNotification !== false) {
    $Expiration = $GlobalNotification['Expiration'] / 60;
} else {
    $Expiration = '';
    $GlobalNotification = [
        'Message'    => '',
        'URL'        => '',
        'Importance' => '',
    ];
}

View::show_header("Global Notification");
?>
<h2>Set global notification</h2>

<div class="thin box pad">
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="take_global_notification" />
        <input type="hidden" name="type" value="set" />
        <table align="center">
            <tr>
                <td class="label">Message</td>
                <td>
                    <input type="text" name="message" id="message" size="50" value="<?=$GlobalNotification['Message']?>" />
                </td>
            </tr>
            <tr>
                <td class="label">URL</td>
                <td>
                    <input type="text" name="url" id="url" size="50" value="<?=$GlobalNotification['URL']?>" />
                </td>
            </tr>
            <tr>
                <td class="label">Importance</td>
                <td>
                    <select name="importance" id="importance">
<?php   foreach ($level as $name) { ?>
                        <option value="<?= $name ?>"<?=$name == $GlobalNotification['Importance'] ? ' selected="selected"' : ''?>><?= $name ?></option>
<?php   } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">Length (in minutes)</td>
                <td>
                    <input type="text" name="length" id="length" size="20" value="<?=$Expiration?>" />
                </td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>
<?php   if (!$GlobalNotification['Message']) { ?>
                    <input type="submit" name="set" value="Create Notification" />
<?php   } else {?>
                    <input type="submit" name="delete" value="Delete Notification" />
<?php   } ?>
                </td>
            </tr>
        </table>
    </form>
</div>

<?php
View::show_footer();
