<?php

$newsMan = new Gazelle\Manager\News;
$newsReader = new \Gazelle\WitnessTable\UserReadNews;
if ($newsMan->latestId() < $newsReader->lastRead($Viewer->id())) {
    $newsReader->witness($Viewer->id());
}

$headlines = $newsMan->headlines();
$news = [];
$show = 5;
foreach ($headlines as $item) {
    if (--$show < 0) {
        break;
    }
    [$id, $title, $body, $time] = $item;
    $news[] = [
        'newsId'   => $id,
        'title'    => $title,
        'bbBody'   => $body,
        'body'     => Text::full_format($body),
        'newsTime' => $time,
    ];
}

$headlines = (new Gazelle\Manager\Blog)->headlines();
$blog = [];
foreach ($headlines as $item) {
    $blog[] = [
        'blogId'   => $item->id(),
        'author'   => $item->userId(),
        'title'    => $item->title(),
        'bbBody'   => $item->body(),
        'body'     => Text::full_format($item->body()),
        'blogTime' => $item->created(),
        'threadId' => $item->threadId(),
    ];
}

json_print("success", [
    'announcements' => $news,
    'blogPosts'     => $blog,
]);
