<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$newsMan = new Manager\News();
$newsReader = new \Gazelle\WitnessTable\UserReadNews();
if ($newsMan->latestId() < $newsReader->lastRead($Viewer)) {
    $newsReader->witness($Viewer);
}

$headlines = $newsMan->headlines();
$news = [];
$show = 5;
foreach ($headlines as $item) {
    if (--$show < 0) {
        break;
    }
    $news[] = [
        'newsId'   => $item['id'],
        'title'    => $item['title'],
        'bbBody'   => $item['body'],
        'body'     => \Text::full_format($item['body']),
        'newsTime' => $item['created'],
    ];
}

$headlines = new Manager\Blog()->headlines();
$blog = [];
foreach ($headlines as $item) {
    $blog[] = [
        'blogId'   => $item->id,
        'author'   => $item->userId(),
        'title'    => $item->title(),
        'bbBody'   => $item->body(),
        'body'     => \Text::full_format($item->body()),
        'blogTime' => $item->created(),
        'threadId' => $item->threadId(),
    ];
}

json_print("success", [
    'announcements' => $news,
    'blogPosts'     => $blog,
]);
