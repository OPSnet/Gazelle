<?php

namespace Gazelle\Manager;

class Report extends \Gazelle\Base {

    protected const ID_KEY = 'zz_r_%d';

    protected User $userMan;

    public function setUserManager(User $userMan): \Gazelle\Manager\Report {
        $this->userMan = $userMan;
        return $this;
    }

    public function findById(int $reportId): ?\Gazelle\Report {
        $key = sprintf(self::ID_KEY, $reportId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM reports WHERE ID = ?
                ", $reportId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        if (!$id) {
            return null;
        }
        $report = new \Gazelle\Report($id);
        if (isset($this->userMan)) {
            $report->setUserManager($this->userMan);
        }
        return $report;
    }
}
