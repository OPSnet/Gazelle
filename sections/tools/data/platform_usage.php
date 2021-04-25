<?php

if (!check_perms('site_debug')) {
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
$DB->prepared_query("SELECT OperatingSystem, OperatingSystemVersion, COUNT(*) FROM users_sessions WHERE OperatingSystem IS NOT NULL GROUP BY OperatingSystem, OperatingSystemVersion ORDER BY COUNT(*) DESC");
while (list($OperatingSystem, $OperatingSystemVersion, $Count) = $DB->fetch_record(0, 'OperatingSystem', 1, 'OperatingSystemVersion')) {
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
$DB->prepared_query("SELECT Browser, BrowserVersion, COUNT(*) FROM users_sessions WHERE Browser IS NOT NULL GROUP BY Browser, BrowserVersion ORDER BY COUNT(*) DESC");
while (list($Browser, $BrowserVersion, $Count) = $DB->fetch_record(0, 'Browser', 1, 'BrowserVersion')) {
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
