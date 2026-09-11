<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

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
        Error404::error('No such thread id found');
    }
    $search->setSearchType('body');
    $search->setThreadId($threadId);
}

$userSearch = trim($_GET['user'] ?? '');
if ($userSearch !== '') {
    $search->setAuthor($userSearch);
}

$threadCreatedBefore = $_GET['thread_created_before'] ?? '';
if ($threadCreatedBefore !== '') {
    if (!Time::isValidDateTime($threadCreatedBefore, 'Y-m-d')) {
        Error400::error("Incorrect topic created before date");
    }
    $search->setThreadCreatedBefore($threadCreatedBefore);
}
$threadCreatedAfter = $_GET['thread_created_after'] ?? '';
if ($threadCreatedAfter !== '') {
    if (!Time::isValidDateTime($threadCreatedAfter, 'Y-m-d')) {
        Error400::error("Incorrect topic created after date");
    }
    $search->setThreadCreatedAfter($threadCreatedAfter);
}

if ($search->isBodySearch()) {
    $postCreatedBefore = $_GET['post_created_before'] ?? '';
    if ($postCreatedBefore !== '') {
        if (!Time::isValidDateTime($postCreatedBefore, 'Y-m-d')) {
            Error400::error("Incorrect post created before date");
        }
        $search->setPostCreatedBefore($postCreatedBefore);
    }
    $postCreatedAfter = $_GET['post_created_after'] ?? '';
    if ($postCreatedAfter !== '') {
        if (!Time::isValidDateTime($postCreatedAfter, 'Y-m-d')) {
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
$paginator->setTotal($search->total());

echo $Twig->render('forum/search.twig', [
    'forum_list'    => new Manager\Forum()->forumList(),
    'id_list'       => $_GET['forums'] ?? [],
    'paginator'     => $paginator,
    'post_after'    => $postCreatedAfter ?? '',
    'post_before'   => $postCreatedBefore ?? '',
    'search'        => $search,
    'thread_after'  => $threadCreatedAfter,
    'thread_before' => $threadCreatedBefore,
    'thread_id'     => $threadId,
    'username'      => $_GET['user'] ?? null,
    'viewer'        => $Viewer,
]);
