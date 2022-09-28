<?php

namespace Gazelle\Json;

class News extends \Gazelle\Json {
    public function __construct(protected int $limit, protected int $offset) { }

    public function payload(): array {
        \Text::$TOC = true;
        return [
            'items' => array_map(
                fn($r) => [
                    $r['id'],
                    \Text::full_format($r['title']),
                    time_diff($r['created']),
                    \Text::full_format($r['body']),
                    $r['created'],
                    time_diff($r['created'], 2, false),
                ],
                (new \Gazelle\Manager\News)->list($this->limit, $this->offset)
            )
        ];
    }
}
