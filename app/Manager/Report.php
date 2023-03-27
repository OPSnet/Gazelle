<?php

namespace Gazelle\Manager;

class Report extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_r_%d';

    protected User $userMan;

    public function setUserManager(User $userMan): \Gazelle\Manager\Report {
        $this->userMan = $userMan;
        return $this;
    }

    public function create(\Gazelle\User $user, int $id, string $type, string $reason): \Gazelle\Report {
        self::$db->prepared_query("
            INSERT INTO reports
                   (UserID, ThingID, Type, Reason)
            VALUES (?,      ?,       ?,    ?)
            ", $user->id(), $id, $type, $reason
        );
        $id = self::$db->inserted_id();
        if ($type == 'request_update') {
            self::$cache->decrement('num_update_reports');
        }
        self::$cache->delete_value('num_other_reports');
        return $this->findById($id);
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

    public function remainingTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM reports WHERE Status = 'New'
        ");
    }
}
