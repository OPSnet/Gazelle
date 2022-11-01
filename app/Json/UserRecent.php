<?php

namespace Gazelle\Json;

class UserRecent extends \Gazelle\Json {
    protected int $limit = 15;

    public function __construct(
        protected \Gazelle\User $user,
        protected \Gazelle\User $viewer,
        protected \Gazelle\Manager\TGroup $tgMan,
    ) {}

    public function setLimit(int $limit) {
        $this->limit = $limit;
        return $this;
    }

    protected function detail(array $list): array {
        $detail = [];
        foreach ($list as $tgroupId) {
            $tgroup = $this->tgMan->findById($tgroupId);
            if ($tgroup) {
                $detail[] = [
                    'ID'        => $tgroup->id(),
                    'Name'      => $tgroup->name(),
                    'WikiImage' => $tgroup->image(),
                    'artists'   => $tgroup->artistRole()->idList(),
                ];
            }
        }
        return $detail;
    }

    public function payload(): ?array {
        return [
            'snatches' => $this->user->propertyVisible($this->viewer, 'snatched')
                ? $this->detail($this->user->recentSnatchList($this->limit, true))
                : 'hidden',
            'uploads' => $this->user->propertyVisible($this->viewer, 'uploads')
                ? $this->detail($this->user->recentUploadList($this->limit, true))
                : 'hidden',
        ];
    }
}
