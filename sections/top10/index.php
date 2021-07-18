<?php
enforce_login();

if (!check_perms('site_top10')) {
    View::show_header('Top 10');
?>
<div class="content_basiccontainer">
    You do not have access to view this feature.
</div>
<?php
    View::show_footer();
    die();
}

if (empty($_GET['type']) || $_GET['type'] == 'torrents') {
    require_once('torrents.php');
} else {
    switch ($_GET['type']) {
        case 'users':
            require_once('users.php');
            break;
        case 'tags':
            require_once('tags.php');
            break;
        case 'history':
            require_once('history.php');
            break;
        case 'votes':
            require_once('votes.php');
            break;
        case 'donors':
            require_once('donors.php');
            break;
        case 'lastfm':
            require_once('lastfm.php');
            break;

        default:
            error(404);
            break;
    }
}
