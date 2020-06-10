<?php
/**********|| Page to show individual forums || ********************************\

Things to expect in $_GET:
    forumId: ID of the forum curently being browsed
    page:    The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

$forumId = (int)$_GET['forumid'];
if ($forumId < 1) {
    error(404);
}
if (!Forums::check_forumperm($forumId)) {
    error(403);
}
if (!check_perms('site_moderate_forums')) {
    if (isset($LoggedUser['CustomForums'][$forumId]) && $LoggedUser['CustomForums'][$forumId] === 0) {
        error(403);
    }
}

$forum = new \Gazelle\Forum($forumId);
if (!$forum->exists()) {
    error(404);
}
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$forumToc = $forum->tableOfContentsForum($page);

$Pages        = Format::get_pages($page, $forum->topicCount(), TOPICS_PER_PAGE, 9);
$isDonorForum = $forumId == DONOR_FORUM ? true : false;
$perPage      = $LoggedUser['PostsPerPage'] ?? POSTS_PER_PAGE;
$userLastRead = $forum->userLastRead($LoggedUser['ID'], $perPage);

foreach ($forumToc as &$thread) {

    $userRead = isset($userLastRead[$thread['LastPostID']]);
    $latestPostRead = $userRead ? $userLastRead[$thread['LastPostID']]['PostID'] : 0;
    $isRead = (!$thread['IsLocked'] || $thread['IsSticky'])
        && ($latestPostRead > $thread['LastPostID']
        && strtotime($thread['LastPostTime']) > G::$LoggedUser['CatchupTime']
    ) ? 'read' : 'unread';

    $thread['read'] = $isRead;
    if ($thread['IsLocked']) {
        $thread['read'] .= '_locked';
    }
    if ($thread['IsSticky']) {
        $thread['read'] .= '_sticky';
    }

    $links = [];
    $threadPages = ceil($thread['NumPosts'] / $perPage);
    if ($threadPages > 1) {
        $ellipsis = false;
        for ($i = 1; $i <= $threadPages; $i++) {
            if ($threadPages > 4 && ($i > 2 && $i <= $threadPages - 2)) {
                if (!$ellipsis) {
                    $links[] = '-';
                    $ellipsis = true;
                }
                continue;
            }
            $links[] = sprintf('<a href="forums.php?action=viewthread&amp;threadid=%d&amp;page=%d">%d</a>',
                $thread['ID'], $i, $i);
        }
    }
    $thread = array_merge($thread, [
        'cut_title'  => Format::cut_string($thread['Title'], 75 - (2 * count($links))),
        'page_links' => $links ? (' (' . implode(' ', $links) . ')') : '',
    ]);
    unset($thread); // because looping by reference
}

View::show_header('Forums &gt; ' . $Forums[$forumId]['Name'], '', $isDonorForum ? 'donor' : '');
?>
<div class="thin">
<?php
echo G::$Twig->render('forum/header.twig', [
    'create' => Forums::check_forumperm($forumId, 'Write') && Forums::check_forumperm($forumId, 'Create'),
    'id'     => $forumId,
    'name'   => display_str($Forums[$forumId]['Name']),
]);
?>
    <div class="linkbox pager">
    <?= $Pages ?>
    </div>
    <table class="forum_index m_table" width="100%">
        <tr class="colhead">
            <td style="width: 2%;"></td>
            <td class="m_th_left">Latest</td>
            <td class="m_th_right" style="width: 7%;">Replies</td>
            <td style="width: 14%;">Author</td>
        </tr>
<?php if (!$forumToc) { ?>
        <tr>
            <td colspan="4">
                No threads to display in this forum!
            </td>
        </tr>
<?php
} else {
    foreach ($forumToc as $thread) {
        // echo '<tr><td colspan="4"><pre>'; var_dump($thread); echo "</pre></td></tr>";
        $userRead = isset($userLastRead[$thread['ID']]);
        echo G::$Twig->render('forum/toc.twig', [
            'author'         => Users::format_username($thread['AuthorID'], false, false, false, false, false, $isDonorForum),
            'cut_title'      => $thread['cut_title'],
            'icon_text'      => ucwords(str_replace('_', ' ', $thread['read'])),
            'id'             => $thread['ID'],
            'is_read'        => isset($userLastRead[$thread['ID']]),
            'last_post_diff' => time_diff($thread['LastPostTime'], 1),
            'last_post_user' => Users::format_username($thread['LastPostAuthorID'], false, false, false, false, false, $isDonorForum),
            'last_read_page' => $userRead ? $userLastRead[$thread['ID']]['Page'] : null,
            'last_read_post' => $userRead ? $userLastRead[$thread['ID']]['PostID'] : null,
            'page_links'     => $thread['page_links'],
            'read'           => $thread['read'],
            'replies'        => number_format($thread['NumPosts'] - 1),
            'title'          => $thread['Title'],
            'tooltip'        => $forumId == DONOR_FORUM ? "tooltip_gold" : "tooltip",
        ]);
    }
}
?>
    </table>
    <div class="linkbox pager">
        <?= $Pages ?>
    </div>
    <div class="linkbox"><a href="forums.php?action=catchup&amp;forumid=<?= $forumId ?>&amp;auth=<?= $LoggedUser['AuthKey'] ?>" class="brackets">Catch up</a></div>
</div>
<?php
View::show_footer();
