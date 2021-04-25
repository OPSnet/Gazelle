<?php

if (!check_perms('site_analysis')) {
    error(403);
}

View::show_header('Analysis List');

$keys = array_filter($Cache->getAllKeys(), function ($key) { return strpos($key, 'analysis_') === 0; });
$items = array_map(function($key) {
    $value = $Cache->get_value($key);
    $value['time'] = $value['time'] ?? 0;
    $value['key'] = substr($key, strlen('analysis_'));
    return $value;
}, $keys);
usort($items, function ($a, $b) { return $a['time'] > $b['time'] ? -1 : ($a['time'] === $b['time'] ? 0 : 1); });
?>
<div class="header">
    <h2>Site Analysis List</h2>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Case</td>
        <td>Errors</td>
        <td>Queries</td>
        <td>Cache</td>
        <td>Elapsed</td>
        <td>Date</td>
        <td>Message</td>
    </tr>
    <?php
    $row = 'b';
    foreach ($items as $item) {
        $row = $row === 'a' ? 'b' : 'a';
        ?>
        <tr class="row<?=$row?>">
            <td><a href="tools.php?action=analysis&amp;case=<?= $item['key'] ?>"><?= $item['key'] ?></a></td>
            <td><?= count($item['errors']) ?></td>
            <td><?= count($item['queries']) ?></td>
            <td><?= count($item['cache']) ?></td>
            <td><?= display_str($item['perf']['Page process time'] ?? '?') ?></td>
            <td><?= date('Y-m-d H:i:s', $item['time'] ?? 0) ?></td>
            <td><pre><?= display_str($item['message']) ?></pre></td>
        </tr>
        <?php
    }
    ?>
</table>
<?php
View::show_footer();
