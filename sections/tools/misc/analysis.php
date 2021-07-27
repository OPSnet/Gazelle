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
    <h2>Case Analysis (<a href="<?=display_str($Analysis['url'] ?? '-none-')?>"><?=$_GET['case']?></a>)</h2>
</div>
<div class="linkbox">
    <a href="tools.php?action=analysis_list" class="brackets">Error list</a>
</div>
<pre id="debug_report"><?=display_str($Analysis['message'])?></pre>
<?php
echo $Twig->render('debug/performance.twig', ['list' => $Analysis['perf']]);
echo $Twig->render('debug/flag.twig', ['list' => $Analysis['flags']]);
echo $Twig->render('debug/include.twig', ['list' => $Analysis['includes']]);
echo $Twig->render('debug/error.twig', ['list' => $Analysis['errors']]);
echo $Twig->render('debug/sphinxql.twig', ['list' => $Analysis['searches'], 'time' => $Analysis['searches_time']]);
echo $Twig->render('debug/query.twig', ['list' => $Analysis['queries'], 'time' => $Analysis['queries_time']]);
if (check_perms('admin_clear_cache')) {
    echo $Twig->render('debug/cache.twig', ['list' => $Debug->get_cache_keys(), 'time' => $Analysis['cache_time']]);
}
if (check_perms('site_debug')) {
    echo $Twig->render('debug/class.twig', ['list' => $Debug->get_classes()]);
    echo $Twig->render('debug/extension.twig', ['list' => $Debug->get_extensions()]);
}
if (check_perms('admin_periodic_task_view') && array_key_exists('Script start', $Analysis['perf'])) {
    echo $Twig->render('debug/task.twig', [
        'list' => (new \Gazelle\Schedule\Scheduler)->getTaskSnapshot(
            (float)$Analysis['perf']['Script start'],
            (float)$Analysis['perf']['Script end']
        )
    ]);
}
echo $Twig->render('debug/var.twig', ['list' => $Analysis['vars']]);
View::show_footer();
