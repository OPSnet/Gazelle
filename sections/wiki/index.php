<?php
enforce_login();

function class_list($Selected = 0) {
    global $LoggedUser;
    $Return = '';
    $Classes = (new Gazelle\Manager\User)->classList();
    foreach ($Classes as $ID => $Class) {
        if ($Class['Level'] <= $LoggedUser['EffectiveClass']) {
            $Return.='<option value="'.$Class['Level'].'"';
            if ($Selected == $Class['Level']) {
                $Return.=' selected="selected"';
            }
            $Return.='>'.shortenString($Class['Name'], 20, true).'</option>'."\n";
        }
    }
    return $Return;
}

if (empty($_REQUEST['action'])) {
    $_GET['id'] = INDEX_WIKI_PAGE_ID;
    require_once('article.php');
} else {
    switch ($_REQUEST['action']) {
        case 'create':
            if ($_POST['action']) {
                require_once('takecreate.php');
            } else {
                require_once('create.php');
            }
            break;
        case 'edit':
            if (!empty($_POST['action'])) {
                require_once('takeedit.php');
            } else {
                require_once('edit.php');
            }
            break;
        case 'delete':
            if ($_POST['action']) {
                require_once('takedelete.php');
            } else {
                require_once('delete.php');
            }
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
        case 'article':
            require_once('article.php');
            break;
        case 'search':
            require_once('search.php');
            break;
    }
}
