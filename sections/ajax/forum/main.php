<?php

$userMan = new Gazelle\Manager\User;
$user = [$Viewer->id() => $Viewer];

$category = [];
$forumList = (new Gazelle\Manager\Forum)->forumList();
foreach ($forumList as $forumId) {
    $forum = new Gazelle\Forum($forumId);
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

    $category[count($category)-1]['forums'][] = [
        'forumId'            => $forumId,
        'forumName'          => $forum->name(),
        'forumDescription'   => $forum->description(),
        'numTopics'          => $forum->numThreads(),
        'numPosts'           => $forum->numPosts(),
        'lastPostId'         => $forum->lastPostId(),
        'lastAuthorId'       => $lastAuthorId,
        'lastPostAuthorName' => $user[$lastAuthorId] ? $user[$lastAuthorId]->username() : null,
        'lastTopicId'        => $forum->lastThreadId(),
        'lastTime'           => strftime('%Y-%m-%d %H:%M:%S', $forum->lastPostTime()),
        'lastTopic'          => $forum->lastThread(),
        'read'               => $Viewer->hasReadLastPost($forum),
        'locked'             => $forum->isLocked(),
        'sticky'             => $forum->isSticky(),
    ];
}

print json_encode([
    'status' => 'success',
    'response' => [
        'categories' => $category,
    ]
]);
