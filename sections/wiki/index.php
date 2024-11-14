<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

function class_list(int $Selected = 0): string {
    /** @phpstan-var \Gazelle\User $Viewer */
    global $Viewer;
    $Return = '';
    $Classes = (new Gazelle\Manager\User())->classList();
    foreach ($Classes as $Class) {
        if ($Class['Level'] <= $Viewer->privilege()->effectiveClassLevel()) {
            $Return .= '<option value="' . $Class['Level'] . '"';
            if ($Selected == $Class['Level']) {
                $Return .= ' selected="selected"';
            }
            $Return .= '>' . shortenString($Class['Name'], 20, true) . '</option>' . "\n";
        }
    }
    return $Return;
}

require_once match ($_REQUEST['action'] ?? '') {
    'add_alias'    => 'add_alias.php',
    'browse'       => 'wiki_browse.php',
    'compare'      => 'compare.php',
    'create'       => isset($_POST['action']) ? 'create_handle.php' : 'create.php',
    'delete'       => isset($_POST['action']) ? 'delete_handle.php' : 'delete.php',
    'delete_alias' => 'delete_alias.php',
    'edit'         => isset($_POST['action']) ? 'edit_handle.php' : 'edit.php',
    'revisions'    => 'revisions.php',
    'search'       => 'search.php',
    default        => 'article.php',
};
