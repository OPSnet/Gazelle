<?php

namespace Gazelle\Json;

class UserRecent extends \Gazelle\Json {

    protected \Gazelle\Manager\TGroup $tgMan;
    protected \Gazelle\User $user;
    protected \Gazelle\User $viewer;
    protected int $limit = 15;

    public function setManagerTGroup(\Gazelle\Manager\TGroup $tgMan) {
        $this->tgMan = $tgMan;
        return $this;
    }

    public function setUser(\Gazelle\User $user) {
        $this->user = $user;
        return $this;
    }

    public function setViewer(\Gazelle\User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

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
        if (is_null($this->tgMan)) {
            $this->failure('tgroup manager not set');
            return null;
        }
        if (is_null($this->user)) {
            $this->failure('user not set');
            return null;
        }
        if (is_null($this->viewer)) {
            $this->failure('viewer not set');
            return null;
        }

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
