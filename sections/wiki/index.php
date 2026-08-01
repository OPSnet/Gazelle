<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_REQUEST['action'] ?? '') {
    'add_alias'    => '/add_alias.php',
    'browse'       => '/wiki_browse.php',
    'compare'      => '/compare.php',
    'create'       => isset($_POST['action']) ? '/create_handle.php' : '/create.php',
    'delete'       => isset($_POST['action']) ? '/delete_handle.php' : '/delete.php',
    'delete_alias' => '/delete_alias.php',
    'edit'         => isset($_POST['action']) ? '/edit_handle.php' : '/edit.php',
    'revisions'    => '/revisions.php',
    'search'       => '/search.php',
    default        => '/article.php',
};
