<?php

namespace Gazelle\Json;

class Requests extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Search\Request $search,
        protected int                     $page,
        protected \Gazelle\Manager\User   $userMan = new \Gazelle\Manager\User(),
    ) {}

    public function payload(): array {
        if ($this->search->total() == 0) {
            return [
                'currentPage' => 1,
                'pages'       => 0,
                'results'     => [],
            ];
        }
        $list = [];
        foreach ($this->search->page(REQUESTS_PER_PAGE, ($this->page - 1) * REQUESTS_PER_PAGE) as $request) {
            $user   = $this->userMan->findById($request->userId());
            $filler = $this->userMan->findById((int)$request->fillerId());
            $list[] = [
                ...$request->ajaxInfo(),
                'requestorName' => $user->username(),
                'fillerName'    => (string)$filler?->username(),
                'bounty'        => $request->bountyTotal(),
                'artists'       => array_values($request->artistRole()?->idList() ?? []),
            ];
        }
        return [
            'currentPage' => $this->page,
            'pages'       => ceil($this->search->total() / REQUESTS_PER_PAGE),
            'results'     => $list,
        ];
    }
}
