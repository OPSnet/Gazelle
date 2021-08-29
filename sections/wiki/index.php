<?php

function class_list($Selected = 0) {
    global $Viewer;
    $Return = '';
    $Classes = (new Gazelle\Manager\User)->classList();
    foreach ($Classes as $ID => $Class) {
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

switch ($_REQUEST['action'] ?? '') {
    case 'create':
        require_once(isset($_POST['action']) ? 'takecreate.php' : 'create.php');
        break;
    case 'edit':
        require_once(isset($_POST['action']) ? 'takeedit.php' : 'edit.php');
        break;
    case 'delete':
        require_once(isset($_POST['action']) ? 'takedelete.php' : 'delete.php');
        break;
    case 'revisions':
        require_once('revisions.php');
        break;
    case 'compare':
        require_once('compare.php');
        break;
    case 'add_alias':
        require_once('add_alias.php');
        break;
    case 'delete_alias':
        require_once('delete_alias.php');
        break;
    case 'browse':
        require_once('wiki_browse.php');
        break;
    case 'search':
        require_once('search.php');
        break;
    case 'article':
    default:
        require_once('article.php');
        break;
}
