<?php

namespace Gazelle\Manager;

class Applicant extends \Gazelle\Base {
    final const ID_KEY   = 'zz_appl_%d';
    final const LIST_KEY = 'applicant_list';
    final const RESOLVED_KEY = 'applicant_resolved';

    // There is no create() function here. Objects of this class are created
    // by a User applying for an ApplicantRole:
    // Applicant = ApplicantRole->apply(User)

    public function flush(): Applicant {
        self::$cache->deleteMulti([self::LIST_KEY, self::RESOLVED_KEY]);
        return $this;
    }

    public function findById(int $applicantId): ?\Gazelle\Applicant {
        $key = sprintf(self::ID_KEY, $applicantId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = (int)self::$db->scalar("
                SELECT ID FROM applicant WHERE ID = ?
                ", $applicantId
            );
            if ($id) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Applicant($id) : null;
    }

    public function list(): array {
        $list = self::$cache->get_value(self::LIST_KEY);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT a.ID
                FROM applicant               a
                INNER JOIN applicant_role AS ar ON (ar.ID = a.RoleID)
                LEFT JOIN thread_note        tn USING (ThreadID)
                WHERE a.Resolved = 0
                GROUP BY a.ID
                ORDER by ar.Title,
                    coalesce(max(tn.Created), a.Created) DESC
            ");
            $list = self::$db->collect(0, false);
            self::$cache->cache_value(self::LIST_KEY, $list, 0);
        }
        return array_map(fn($id) => $this->findById($id), $list);
    }

    public function resolvedList(): array {
        $list = self::$cache->get_value(self::RESOLVED_KEY);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT a.ID
                FROM applicant               a
                INNER JOIN applicant_role AS ar ON (ar.ID = a.RoleID)
                LEFT JOIN thread_note        tn USING (ThreadID)
                WHERE a.Resolved = 1
                GROUP BY a.ID
                ORDER by ar.Title,
                    coalesce(max(tn.Created), a.Created) DESC
            ");
            $list = self::$db->collect(0, false);
            self::$cache->cache_value(self::RESOLVED_KEY, $list, 0);
        }
        return array_map(fn($id) => $this->findById($id), $list);
    }

    public function openList(\Gazelle\User $user): array {
        return array_filter(
            $this->list(),
            fn($applicant) => $applicant->role()->isStaffViewer($user)
        );
    }

    public function userList(\Gazelle\User $user): array {
        return array_filter($this->list(), fn($applicant) => $applicant->userId() == $user->id());
    }

    public function userIsApplicant(\Gazelle\User $user): bool {
        return count($this->userList($user)) > 0;
    }

    public function newTotal(\Gazelle\User $user): int {
        return count(
            array_filter(
                $this->openList($user),
                fn($applicant) => count($applicant->thread()->story()) == 0
            )
        );
    }

    public function newReplyTotal(\Gazelle\User $user): int {
        return count(
            array_filter(
                $this->openList($user),
                fn($applicant) => $applicant->thread()->lastNoteUserID() == $applicant->userId()
            )
        );
    }
}
