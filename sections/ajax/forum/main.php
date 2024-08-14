<?php
/** @phpstan-var \Gazelle\User $Viewer */

$userMan = new Gazelle\Manager\User();
$user = [$Viewer->id() => $Viewer];

$category = [];
foreach ((new Gazelle\Manager\Forum())->forumList() as $forum) {
    if (!$Viewer->readAccess($forum)) {
        continue;
    }
    $lastAuthorId = $forum->lastAuthorId();
    if ($lastAuthorId && !isset($user[$lastAuthorId])) {
        $user[$lastAuthorId] = $userMan->findById($lastAuthorId);
    }

    if (empty($category) || end($category)['categoryID'] != $forum->categoryId()) {
        $category[] = [
            'categoryID'   => $forum->categoryId(),
            'categoryName' => $forum->categoryName(),
            'forums'       => [],
        ];
    }

    $category[count($category) - 1]['forums'][] = [
        'forumId'            => $forum->id(),
        'forumName'          => $forum->name(),
        'forumDescription'   => $forum->description(),
        'numTopics'          => $forum->numThreads(),
        'numPosts'           => $forum->numPosts(),
        'lastPostId'         => $forum->lastPostId(),
        'lastAuthorId'       => $lastAuthorId,
        'lastPostAuthorName' => $user[$lastAuthorId] ? $user[$lastAuthorId]->username() : null,
        'lastTopicId'        => $forum->lastThreadId(),
        'lastTime'           => $forum->lastPostTime(),
        'lastTopic'          => $forum->lastThread(),
        'read'               => $Viewer->hasReadLastPost($forum),
        'locked'             => $forum->isLocked(),
    ];
}

print json_encode([
    'status' => 'success',
    'response' => [
        'categories' => $category,
    ]
]);
