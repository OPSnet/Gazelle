<?php

if (!$Viewer->permitted('admin_site_debug')) {
    error(403);
}

View::show_header('PHP Processes');
preg_match('/.*\/(.*)/', PHP_BINARY, $match, PREG_UNMATCHED_AS_NULL);
$binary = $match[1] ?? 'php-fpm';
$pidList = trim(`ps -C ${binary} -o pid --no-header`);
$pids = explode("\n", $pidList);
?>
<div class="thin">
    <table class="process_info">
        <colgroup>
            <col class="process_info_pid" />
            <col class="process_info_data" />
        </colgroup>
        <tr class="colhead_dark">
            <td colspan="2">
                <?=count($pids) . ' processes'?>
            </td>
        </tr>
<?php
foreach ($pids as $pid) {
    $pid = trim($pid);
    if (!$ProcessInfo = $Cache->get_value("php_$pid")) {
        continue;
    }
?>
        <tr>
            <td>
                <?=$pid?>
            </td>
            <td>
                <pre><?php print_r($ProcessInfo); ?></pre>
            </td>
        </tr>
<?php
} ?>
    </table>
</div>
<?php
View::show_footer();
