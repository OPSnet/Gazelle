<?php

$newsMan = new Gazelle\Manager\News;
$newsReader = new \Gazelle\WitnessTable\UserReadNews;
if ($newsMan->latest() < $newsReader->lastRead($Viewer->id())) {
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
    [$id, $title, $author, , $body, $time, $threadId] = $item;
    $blog[] = [
        'blogId'   => $id,
        'author'   => $author,
        'title'    => $title,
        'bbBody'   => $body,
        'body'     => Text::full_format($body),
        'blogTime' => $time,
        'threadId' => $threadId,
    ];
}

json_print("success", [
    'announcements' => $news,
    'blogPosts'     => $blog,
]);
