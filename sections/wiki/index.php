<?php

function class_list(int $Selected = 0): string {
    global $Viewer;
    $Return = '';
    $Classes = (new Gazelle\Manager\User)->classList();
    foreach ($Classes as $Class) {
        if ($Class['Level'] <= $Viewer->effectiveClass()) {
            $Return.='<option value="'.$Class['Level'].'"';
            if ($Selected == $Class['Level']) {
                $Return.=' selected="selected"';
            }
            $Return.='>'.shortenString($Class['Name'], 20, true).'</option>'."\n";
        }
    }
    return $Return;
}

require_once(match ($_REQUEST['action'] ?? '') {
    'add_alias'    => 'add_alias.php',
    'browse'       => 'wiki_browse.php',
    'compare'      => 'compare.php',
    'create'       => isset($_POST['action']) ? 'takecreate.php' : 'create.php',
    'delete'       => isset($_POST['action']) ? 'takedelete.php' : 'delete.php',
    'delete_alias' => 'delete_alias.php',
    'edit'         => isset($_POST['action']) ? 'takeedit.php' : 'edit.php',
    'revisions'    => 'revisions.php',
    'search'       => 'search.php',
    default        => 'article.php',
});
