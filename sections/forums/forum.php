<?php
/**********|| Page to show individual forums || ********************************\

Things to expect in $_GET:
    forumId: ID of the forum curently being browsed
    page:    The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

$forum = (new Gazelle\Manager\Forum)->findById((int)$_GET['forumid']);
if (!$forum) {
    error(404);
}
$forumId = $forum->id();
if (!$Viewer->readAccess($forum)) {
    error(403);
}

$paginator = new Gazelle\Util\Paginator(TOPICS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($forum->threadCount());

$perPage      = $Viewer->postsPerPage();
$userLastRead = $forum->userLastRead($Viewer->id(), $perPage);
$forumToc     = $forum->tableOfContentsForum($paginator->page());

foreach ($forumToc as &$thread) {
    if (isset($userLastRead[$thread['ID']])) {
        $thread['last_read_page'] = (int)$userLastRead[$thread['ID']]['Page'];
        $thread['last_read_post'] = $userLastRead[$thread['ID']]['PostID'];
        $catchup = $userLastRead[$thread['ID']]['PostID'] >= $thread['LastPostID']
            || $Viewer->forumCatchupEpoch() >= strtotime($thread['LastPostTime']);
        $thread['is_read'] = true;
    } else {
        $thread['last_read_page'] = null;
        $thread['last_read_post'] = null;
        $catchup = $Viewer->forumCatchupEpoch() >= strtotime($thread['LastPostTime']);
        $thread['is_read'] = false;
    }

    $thread['icon_class'] = (($thread['IsLocked'] && !$thread['IsSticky']) || $catchup ? 'read' : 'unread')
        . ($thread['IsLocked'] ? '_locked' : '')
        . ($thread['IsSticky'] ? '_sticky' : '');

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
        'cut_title'  => shortenString($thread['Title'], 75 - (2 * count($links))),
        'page_links' => $links ? (' (' . implode(' ', $links) . ')') : '',
    ]);
    unset($thread); // because looping by reference
}

$isDonorForum = $forumId == DONOR_FORUM ? true : false;

View::show_header('Forums &rsaquo; ' . $forum->name(), $isDonorForum ? ['js' => 'donor'] : []);
?>
<div class="thin">
<?php
echo $Twig->render('forum/header.twig', [
    'auth'      => $Viewer->auth(),
    'create'    => $Viewer->writeAccess($forum) && $Viewer->createAccess($forum) && !$Viewer->disablePosting(),
    'dept_list' => $forum->departmentList($Viewer),
    'forum'     => $forum,
]);
?>
    <?= $paginator->linkbox() ?>
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
        echo $Twig->render('forum/toc.twig', [
            'author'         => Users::format_username($thread['AuthorID'], false, false, false, false, false, $isDonorForum),
            'cut_title'      => $thread['cut_title'],
            'icon_class'     => $thread['icon_class'],
            'id'             => $thread['ID'],
            'is_read'        => $thread['is_read'],
            'last_post_diff' => time_diff($thread['LastPostTime'], 1),
            'last_post_user' => Users::format_username($thread['LastPostAuthorID'], false, false, false, false, false, $isDonorForum),
            'last_read_page' => $thread['last_read_page'],
            'last_read_post' => $thread['last_read_post'],
            'page_links'     => $thread['page_links'],
            'replies'        => $thread['NumPosts'] - 1,
            'title'          => $thread['Title'],
            'tooltip'        => $forumId == DONOR_FORUM ? "tooltip_gold" : "tooltip",
        ]);
    }
}
?>
    </table>
    <?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
