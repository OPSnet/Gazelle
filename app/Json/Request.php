<?php

namespace Gazelle\Json;

class Request extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Request         $request,
        protected \Gazelle\User            $viewer,
        protected \Gazelle\User\Bookmark   $bookmark,
        protected \Gazelle\Comment\Request $commentPage,
        protected \Gazelle\Manager\User    $userMan,
    ) {}

    public function payload(): array {
        $commentPage = $this->commentPage;
        $request     = $this->request;
        $filler      = $this->userMan->findById($request->fillerId());
        $user        = $this->userMan->findById($request->userId());

        return [
            ...$request->ajaxInfo(),
            'requestorName'   => $user->username(),
            'isBookmarked'    => $this->bookmark->isRequestBookmarked($request->id()),
            'requestTax'      => REQUEST_TAX,
            'canEdit'         => $request->canEdit($this->viewer),
            'canVote'         => $request->canVote($this->viewer),
            'minimumVote'     => REQUEST_MIN * 1024 * 1024,
            'topContributors' => array_map(
                fn ($u) => [
                    'userId'   => $u['user_id'],
                    'userName' => $u['user']->username(),
                    'bounty'   => $u['bounty'],
                ],
                array_slice($request->userVoteList($this->userMan), 0, 5)
            ),
            'musicInfo'       => $request->artistRole()?->roleListByType(),
            'fillerName'      => $filler?->username() ?? '',
            'comments'        => $commentPage->load()->threadList($this->userMan),
            'commentPage'     => $commentPage->pageNum(),
            'commentPages'    => (int)ceil($commentPage->total() / TORRENT_COMMENTS_PER_PAGE),
        ];
    }
}
