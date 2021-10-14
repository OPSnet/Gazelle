<?php

if (!$Viewer->permitted('admin_manage_stylesheets')) {
    error(403);
}

$heading = new Gazelle\Util\SortableTableHeader('id', [
    'id'      => ['dbColumn' => 's.ID', 'defaultSort' => 'asc'],
    'name'    => ['dbColumn' => 's.Name', 'defaultSort' => 'asc',  'text' => 'Name'],
    'enabled' => ['dbColumn' => 'total_enabled', 'defaultSort' => 'desc', 'text' => 'Enabled Users'],
    'total'   => ['dbColumn' => 'total', 'defaultSort' => 'desc', 'text' => 'Total Users'],
]);

echo $Twig->render('admin/stylesheet.twig', [
    'heading' => $heading,
    'list'    => (new Gazelle\Stylesheet)->usageList($heading->getOrderBy(), $heading->getOrderDir()),
]);
