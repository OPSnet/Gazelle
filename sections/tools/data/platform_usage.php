<?php

if (!check_perms('site_view_flow')) {
    error(403);
}

View::show_header('OS and Browser Usage');

?>
<div class="header">
    <h2>OS Usage</h2>
</div>
<table width="100%">
    <tr class="colhead">
        <td>OS</td>
        <td>Count</td>
    </tr>

<?php
G::$DB->prepared_query("SELECT OperatingSystem, OperatingSystemVersion, COUNT(*) FROM users_sessions GROUP BY OperatingSystem, OperatingSystemVersion ORDER BY COUNT(*) DESC");
while (list($OperatingSystem, $OperatingSystemVersion, $Count) = G::$DB->fetch_record(0, 'OperatingSystem', 1, 'OperatingSystemVersion')) {
    ?>
    <tr>
        <td><?=$OperatingSystem?> <?=$OperatingSystemVersion?></td>
        <td><?=$Count?></td>
    </tr>
    <?php
}
?>
</table>
<div class="header">
    <h2>Browser Usage</h2>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Browser</td>
        <td>Count</td>
    </tr>

<?php
G::$DB->prepared_query("SELECT Browser, BrowserVersion, COUNT(*) FROM users_sessions GROUP BY Browser, BrowserVersion ORDER BY COUNT(*) DESC");
while (list($Browser, $BrowserVersion, $Count) = G::$DB->fetch_record(0, 'Browser', 1, 'BrowserVersion')) {
    ?>
    <tr>
        <td><?=$Browser?> <?=$BrowserVersion?></td>
        <td><?=$Count?></td>
    </tr>
    <?php
}
?>
</table>
<?php

View::show_footer();
