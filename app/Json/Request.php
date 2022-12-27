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
            'requestId'       => $request->id(),
            'requestorId'     => $request->userId(),
            'requestorName'   => $user->username(),
            'isBookmarked'    => $this->bookmark->isRequestBookmarked($request->id()),
            'requestTax'      => REQUEST_TAX,
            'timeAdded'       => $request->created(),
            'canEdit'         => $request->canEdit($this->viewer),
            'canVote'         => $request->canVote($this->viewer),
            'minimumVote'     => REQUEST_MIN * 1024 * 1024,
            'voteCount'       => $request->userVotedTotal(),
            'lastVote'        => $request->lastVoteDate(),
            'topContributors' => array_map(
                fn ($u) => [
                    'userId'   => $u['user_id'],
                    'userName' => $u['user']->username(),
                    'bounty'   => $u['bounty'],
                ],
                array_slice($request->userVoteList($this->userMan), 0, 5)
            ),
            'totalBounty'     => $request->bountyTotal(),
            'categoryId'      => $request->categoryId(),
            'categoryName'    => $request->categoryName(),
            'title'           => $request->title(),
            'year'            => (int)$request->year(),
            'image'           => (string)$request->image(),
            'bbDescription'   => $request->description(),
            'description'     => \Text::full_format($request->description()),
            'musicInfo'       => $request->artistRole()?->roleListByType(),
            'catalogueNumber' => $request->catalogueNumber(),
            'recordLabel'     => $request->recordLabel(),
            'oclc'            => $request->oclc(),
            'releaseType'     => $request->releaseType(),
            'releaseTypeName' => $request->releaseTypeName(),
            'bitrateList'     => $request->needEncodingList(),
            'formatList'      => $request->needformatList(),
            'mediaList'       => $request->needMediaList(),
            'logCue'          => html_entity_decode($request->legacyLogCue()),
            'isFilled'        => $request->isFilled(),
            'fillerId'        => (int)$request->fillerId(),
            'fillerName'      => $filler?->username() ?? '',
            'torrentId'       => (int)$request->torrentId(),
            'timeFilled'      => (string)$request->fillDate(),
            'tags'            => $request->tagNameList(),
            'comments'        => $commentPage->load()->threadList($this->userMan),
            'commentPage'     => $commentPage->pageNum(),
            'commentPages'    => (int)ceil($commentPage->total() / TORRENT_COMMENTS_PER_PAGE),
        ];
    }
}
