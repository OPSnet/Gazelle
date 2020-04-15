<?php
if (!check_perms('site_analysis')) {
    error(403);
}

if (!isset($_GET['case']) || !$Analysis = $Cache->get_value('analysis_'.$_GET['case'])) {
    error(404);
}

View::show_header('Case Analysis');
?>
<div class="header">
    <h2>Case Analysis (<a href="<?=display_str($Analysis['url'])?>"><?=$_GET['case']?></a>)</h2>
</div>
<div class="linkbox">
    <a href="tools.php?action=analysis_list" class="brackets">Error list</a>
</div>
<pre id="debug_report"><?=display_str($Analysis['message'])?></pre>
<?php
$Debug->perf_table($Analysis['perf']);
$Debug->flag_table($Analysis['flags']);
$Debug->include_table($Analysis['includes'], !check_perms('admin_site_debug'));
$Debug->error_table($Analysis['errors']);
$Debug->query_table($Analysis['queries']);
if (check_perms('admin_periodic_task_view')) {
    $Debug->task_table($Analysis['perf']);
}
if (check_perms('admin_clear_cache')) {
    $Debug->cache_table($Analysis['cache']);
}
if (check_perms('site_debug')) {
    $Debug->class_table();
    $Debug->extension_table();
}
if (check_perms('admin_site_debug')) {
    $Debug->constant_table();
}
$Debug->vars_table($Analysis['vars']);
View::show_footer();
