<?php

$user = new Gazelle\User($LoggedUser['ID']);

$newsMan = new Gazelle\Manager\News;
$headlines = $newsMan->headlines();
$news = [];
$show = 5;
foreach ($headlines as $item) {
    if (--$show < 0) {
        break;
    }
    [$id, $title, $body, $time] = $item;
    $news[] = [
        'newsId'   => (int)$id,
        'title'    => $title,
        'bbBody'   => $body,
        'body'     => Text::full_format($body),
        'newsTime' => $time,
    ];
}

$latestNewsId = $newsMan->latestId();
if ($LoggedUser['LastReadNews'] < $latestNewsId) {
    $user->updateLastReadNews($latestNewsId);
    $LoggedUser['LastReadNews'] = $latestNewsId;
}

$blogMan = new Gazelle\Manager\News;
$headlines = $blogMan->headlines();
$blog = [];
foreach ($headlines as $item) {
    [$id, $author, , $title, $body, $time, $threadId] = $item;
    $blog[] = [
        'blogId'   => (int)$id,
        'author'   => $author,
        'title'    => $title,
        'bbBody'   => $body,
        'body'     => Text::full_format($body),
        'blogTime' => $time,
        'threadId' => (int)$threadId,
    ];
}

json_print("success", [
    'announcements' => $news,
    'blogPosts'     => $blog,
]);
