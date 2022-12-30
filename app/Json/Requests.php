<?php

namespace Gazelle\Json;

class Requests extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Search\Request  $search,
        protected int                      $page,
        protected \Gazelle\Manager\User    $userMan,
    ) {}

    public function payload(): array {
        if ($this->search->total() == 0) {
            return [
                'currentPage' => 1,
                'pages'       => 0,
                'results'     => []
            ];
        }
        $list = [];
        foreach ($this->search->list() as $request) {
            $user   = $this->userMan->findById($request->userId());
            $filler = $this->userMan->findById($request->fillerId());
            $list[] = [
                ...$request->ajaxInfo(),
                'requestorName' => $user->username(),
                'fillerName'    => (string)$filler?->username(),
                'bounty'        => $request->bountyTotal(),
                'artists'       => $request->categoryName() === 'Music'
                    ? array_values($request->artistRole()->idList())
                    : [],
            ];
        }
        return [
            'currentPage' => $this->page,
            'pages'       => ceil($this->search->total() / REQUESTS_PER_PAGE),
            'results'     => $list,
        ];
    }
}
