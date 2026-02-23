<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Util\Time;

$search = new Search\Forum($Viewer);
$search->setSearchType($_GET['type'] ?? 'title')
    ->setSearchText(trim($_GET['search'] ?? ''));

// Searching for posts in a specific thread
$threadId = (int)($_GET['threadid'] ?? 0);
if ($threadId) {
    $title = $search->threadTitle($threadId);
    if (is_null($title)) {
        // naughty naughty
        Error403::error();
    }
    $search->setSearchType('body');
    $search->setThreadId($threadId);
}

$userSearch = trim($_GET['user'] ?? '');
if (!empty($userSearch)) {
    $search->setAuthor($userSearch);
}

$threadCreatedBefore = $_GET['thread_created_before'] ?? '';
if (!empty($threadCreatedBefore)) {
    if (!Time::isValidDate($threadCreatedBefore)) {
        Error400::error("Incorrect topic created before date");
    }
    $search->setThreadCreatedBefore($threadCreatedBefore);
}
$threadCreatedAfter = $_GET['thread_created_after'] ?? '';
if (!empty($threadCreatedAfter)) {
    if (!Time::isValidDate($threadCreatedAfter)) {
        Error400::error("Incorrect topic created after date");
    }
    $search->setThreadCreatedAfter($threadCreatedAfter);
}

if ($search->isBodySearch()) {
    $postCreatedBefore = $_GET['post_created_before'] ?? '';
    if (!empty($postCreatedBefore)) {
        if (!Time::isValidDate($postCreatedBefore)) {
            Error400::error("Incorrect post created before date");
        }
        $search->setPostCreatedBefore($postCreatedBefore);
    }
    $postCreatedAfter = $_GET['post_created_after'] ?? '';
    if (!empty($postCreatedAfter)) {
        if (!Time::isValidDate($postCreatedAfter)) {
            Error400::error("Incorrect post created after date");
        }
        $search->setPostCreatedAfter($postCreatedAfter);
    }
}

// Has the user checked individual forums?
if (isset($_GET['forums']) && is_array($_GET['forums'])) {
    $search->setForumList($_GET['forums']);
}

$paginator = new Util\Paginator(POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->totalHits());

View::show_header('Forums › Search', ['js' => 'bbcode,forum_search']);

echo $Twig->render('forum/search.twig', [
    'forum_list'    => new Manager\Forum()->forumList(),
    'id_list'       => $_GET['forums'] ?? [],
    'post_after'    => $postCreatedAfter ?? '',
    'post_before'   => $postCreatedBefore ?? '',
    'search'        => $search,
    'thread_after'  => $threadCreatedAfter,
    'thread_before' => $threadCreatedBefore,
    'thread_id'     => $threadId,
    'viewer'        => $Viewer,
]);

$results = $search->results($paginator);
?>
    <?= $paginator->linkbox() ?>
    <table cellpadding="6" cellspacing="1" border="0" class="forum_list border" width="100%">
    <tr class="colhead">
        <td>Forum</td>
        <td><?= $threadId ? 'Post begins' : 'Topic' ?></td>
        <td>Topic creation time</td>
        <td>Last post time</td>
    </tr>
<?php if (empty($results)) { ?>
        <tr><td colspan="4">Nothing found<?= !empty($_GET['user']) ? ' (unknown username)' : '' ?>!</td></tr>
<?php }

$Row = 'a'; // For the pretty colours
foreach ($results as $r) {
    [$ID, $title, $ForumID, $ForumName, $LastTime, $PostID, $Body, $ThreadCreatedTime] = $r;
    $Row = $Row === 'a' ? 'b' : 'a';
    // Print results
?>
        <tr class="row<?=$Row?>">
            <td>
                <a href="forums.php?action=viewforum&amp;forumid=<?=$ForumID?>"><?=$ForumName?></a>
            </td>
            <td>
<?php    if ($threadId) { ?>
                <?=shortenString($title, 80); ?>
<?php    } else { ?>
                <a href="forums.php?action=viewthread&amp;threadid=<?=$ID?>"><?= shortenString($title, 80) ?></a>
<?php
    }
    if ($search->isBodySearch()) { ?>
                <a href="#" onclick="$('#post_<?=$PostID?>_text').gtoggle(); return false;">(Show)</a> <span style="float: right;" class="tooltip last_read" title="Jump to post"><a href="forums.php?action=viewthread&amp;threadid=<?=$ID?><?php if (!empty($PostID)) {
echo "&amp;postid=$PostID#post$PostID"; } ?>"></a></span>
<?php    } ?>
            </td>
            <td>
                <?=time_diff($ThreadCreatedTime)?>
            </td>
            <td>
                <?=time_diff($LastTime)?>
            </td>
        </tr>
<?php    if ($search->isBodySearch()) { ?>
        <tr class="row<?=$Row?> hidden" id="post_<?=$PostID?>_text">
            <td colspan="4"><?=\Text::full_format($Body, cache: Enum\ImageCacheMode::cache, bucket: Enum\CacheBucket::forum)?></td>
        </tr>
<?php    }
}
?>
    </table>

    <?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
