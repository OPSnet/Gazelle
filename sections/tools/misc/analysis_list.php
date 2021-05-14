<?php

if (!check_perms('site_analysis')) {
    error(403);
}

$keys = array_filter($Cache->getAllKeys(), function ($key) { return strpos($key, 'analysis_') === 0; });
$items = array_map(function($key) {
    global $Cache;
    $value = $Cache->get_value($key);
    $value['time'] = date('Y-m-d H:i:s', $value['time'] ?? 0);
    $value['key'] = substr($key, strlen('analysis_'));
    return $value;
}, $keys);
usort($items, function ($a, $b) { return $a['time'] > $b['time'] ? -1 : ($a['time'] === $b['time'] ? 0 : 1); });
View::show_header('Analysis List');
echo $Twig->render('admin/error-analysis.twig', [
    'list' => $items,
]);
View::show_footer();
