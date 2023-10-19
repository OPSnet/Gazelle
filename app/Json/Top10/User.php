<?php

namespace Gazelle\Json\Top10;

class User extends \Gazelle\Json {
    public function __construct(
        protected string                $details,
        protected int                   $limit,
        protected \Gazelle\Stats\Users  $stats,
        protected \Gazelle\Manager\User $userMan,
    ) {}

    protected function userMetrics(array $idList): array {
        $result = [];
        foreach ($idList as $id) {
            $user = $this->userMan->findById($id);
            if (is_null($user)) {
                continue;
            }
            $result[] = [
                'id'         => $id,
                'username'   => $user->username(),
                'uploaded'   => $user->uploadedSize(),
                'upSpeed'    => $user->uploadSpeed(),
                'downloaded' => $user->downloadedSize(),
                'downSpeed'  => $user->downloadSpeed(),
                'numUploads' => $user->stats()->uploadTotal(),
                'joinDate'   => $user->created(),
            ];
        }
        return $result;
    }

    public function payload(): array {
        $payload = [];

        if (in_array($this->details, ['all', 'ul'])) {
            $payload[] = [
                'caption' => 'Uploaders',
                'tag'     => 'ul',
                'limit'   => $this->limit,
                'results' => $this->userMetrics($this->stats->topUploadList($this->limit)),
            ];
        }
        if (in_array($this->details, ['all', 'dl'])) {
            $payload[] = [
                'caption' => 'Downloaders',
                'tag'     => 'dl',
                'limit'   => $this->limit,
                'results' => $this->userMetrics($this->stats->topDownloadList($this->limit)),
            ];
        }
        if (in_array($this->details, ['all', 'numul'])) {
            $payload[] = [
                'caption' => 'Torrents Uploaded',
                'tag'     => 'numul',
                'limit'   => $this->limit,
                'results' => $this->userMetrics($this->stats->topTotalUploadList($this->limit)),
            ];
        }
        if (in_array($this->details, ['all', 'uls'])) {
            $payload[] = [
                'caption' => 'Fastest Uploaders',
                'tag'     => 'uls',
                'limit'   => $this->limit,
                'results' => $this->userMetrics($this->stats->topUpSpeedList($this->limit)),
            ];
        }
        if (in_array($this->details, ['all', 'dls'])) {
            $payload[] = [
                'caption' => 'Fastest Downloaders',
                'tag'     => 'dls',
                'limit'   => $this->limit,
                'results' => $this->userMetrics($this->stats->topDownSpeedList($this->limit)),
            ];
        }

        return $payload;
    }
}
