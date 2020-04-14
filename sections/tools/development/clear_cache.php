<?php
if (!check_perms('users_mod') || !check_perms('admin_clear_cache')) {
    error(403);
}

if (!empty($_GET['key'])) {
    $Keys = preg_split('/\s+/', trim($_GET['key']));
}

if (isset($Keys) && $_GET['type'] == 'view') {
    foreach ($Keys as $Key) {
        foreach ($CachePermissions as $k => $v) {
            if (strpos($Key, $k) !== false && !check_perms($v)) {
                error(403);
            }
        }
    }
}

View::show_header('Cache key management');

//Make sure the form was sent
if (isset($_GET['cache'])) {
    if ($_GET['cache'] === 'users') {
        $max = $DB->scalar("SELECT max(ID) as count FROM users_main");
        for ($i = 1; $i <= $max; $i++) {
            $Cache->deleteMulti(['user_stats_' . $i, 'user_info_' . $i, 'user_info_heavy_' . $i]);
        }
        echo "<div class='save_message'>All user caches cleared.</div>";
    }
    elseif ($_GET['cache'] === 'torrent_groups') {
        $max = $DB->scalar("SELECT max(ID) as count FROM torrents_group");
        for ($i = 1; $i <= $max; $i++) {
            $Cache->deleteMulti(['torrent_group_' . $i, 'groups_artists_' . $i]);
        }
        echo "<div class='save_message'>All torrent caches cleared.</div>";
    }
}

if (isset($Keys) && $_GET['type'] == 'clear') {
    foreach ($Keys as $Key) {
        if (preg_match('/(.*?)(\d+)\.\.(\d+)$/', $Key, $Matches) && is_number($Matches[2]) && is_number($Matches[3])) {
            for ($i = $Matches[2]; $i <= $Matches[3]; $i++) {
                $Cache->delete_value($Matches[1].$i);
            }
        } else {
            $Cache->delete_value($Key);
        }
    }
    echo '<div class="save_message">Key(s) ' . implode(', ', array_map('display_str', $Keys)) . ' cleared!</div>';
}
$MultiKeyTooltip = 'Enter cache keys delimited by whitespace.';
?>
    <div class="header">
        <h2>Clear or view cache keys</h2>
    </div>
    <table class="layout" cellpadding="2" cellspacing="1" border="0" align="center">
        <tr>
            <td>Key</td>
            <td>
                <form class="manage_form" name="cache" method="get" action="">
                    <input type="hidden" name="action" value="clear_cache" />
                    <select name="type">
                        <option value="view">View</option>
                        <option value="clear">Clear</option>
                    </select>
                    <input type="text" name="key" id="key" class="inputtext" value="<?=(isset($_GET['key']) && ($_GET['submit'] ?? '') != 'Multi' ? display_str($_GET['key']) : '')?>" />
                    <br /><input type="submit" name="submit" value="Single" class="submit" />
                </form>
            </td>
        </tr>
        <tr class="tooltip" title="<?=$MultiKeyTooltip?>">
            <td style="vertical-align: top;">Multi-key</td>
            <td>
                <form class="manage_form" name="cache" method="get" action="">
                    <input type="hidden" name="action" value="clear_cache" />
                    <select name="type" style="vertical-align: top;">
                        <option value="view">View</option>
                        <option value="clear">Clear</option>
                    </select>
                    <textarea type="text" name="key" id="key" class="inputtext"><?=(isset($_GET['key']) && ($_GET['submit'] ?? '') == 'Multi' ? display_str($_GET['key']) : '')?></textarea>
                    <br /><input type="submit" name="submit" value="Multi" class="submit" />
                </form>
            </td>
        </tr>
        <tr>
            <td rowspan="2" style="vertical-align: top;">Clear Common Caches:</td>
            <td><a href="tools.php?action=clear_cache&cache=users">Users</a> (clears out user_stats_*, user_info_*, and user_info_heavy_*)</td>
        </tr>
        <tr>
            <td><a href="tools.php?action=clear_cache&cache=torrent_groups">Torrent Groups</a> (clears out torrent_group_* and groups_artists_*)</td>
        </tr>
    </table>

<?php if (isset($Keys) && $_GET['type'] == 'view') { ?>
    <table class="layout" cellpadding="2" cellspacing="1" border="0" align="center" style="margin-top: 1em;">
<?php foreach ($Keys as $Key) {
        $value = $Cache->get_value($Key);
?>
            <tr>
                <td style="vertical-align: top;"><?=display_str($Key)?></td>
                <td>
<?php   if ($value) { ?>
                    <pre style="padding: 0px; margin: 0px;"><?= print_r($value, true) ?></pre>
<?php   } else { ?>
                    <i>no value</i>
<?php   } ?>
                </td>
            </tr>
<?php } ?>
    </table>
    <?php
}

View::show_footer();
