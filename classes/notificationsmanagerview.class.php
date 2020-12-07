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
    <script src="<?= STATIC_SERVER . "/functions/$inc" ?>?v=<?= filemtime(SERVER_ROOT . "/public/static/functions/$inc")?>" type="text/javascript"></script>
<?php
        }
    }

    public static function format_traditional($Contents) {
        return "<a href=\"{$Contents['url']}\">{$Contents['message']}</a>";
    }
}
