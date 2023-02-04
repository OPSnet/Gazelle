<?php

namespace Gazelle\Manager;

class Applicant extends \Gazelle\Base {
    final const ID_KEY              = 'zz_appl_%d';
    final const CACHE_KEY           = 'applicant_%d';
    final const CACHE_KEY_OPEN      = 'applicant_list_open_%d';
    final const CACHE_KEY_RESOLVED  = 'applicant_list_resolved_%d';
    final const CACHE_KEY_NEW_COUNT = 'applicant_new_count';
    final const CACHE_KEY_NEW_REPLY = 'applicant_new_reply';
    final const ENTRIES_PER_PAGE    = 1000; // TODO: change to 50 and implement pagination

    public function findById(int $applicantId): ?\Gazelle\Applicant {
        $key = sprintf(self::ID_KEY, $applicantId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM applicant WHERE ID = ?
                ", $applicantId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Applicant($id) : null;
    }

    public function create(int $userId, int $roleId, string $body) {
        self::$db->prepared_query("
            INSERT INTO applicant
                   (RoleID, UserID, Body, ThreadID)
            VALUES (?,      ?,      ?,    ?)
            ", $roleId, $userId, $body,
                (new Thread())->createThread('staff-role')->id()
        );
        self::$cache->delete_value(self::CACHE_KEY_NEW_COUNT);
        return $this->findById(self::$db->inserted_id());
    }

    /**
     * Get an array of applicant entries (e.g. for an index/table of contents page).
     * Each array element is a hash with the following keys:
     *  ID            - the Applicant id (in the applicant table)
     *  RoleID        - the role the applicant is applying for
     *  UserID        - the id of the member making the application
     *  UserName      - the name of the member making the application
     *  ThreadID      - the thread story associated with this application
     *  Created       - when this application was created
     *  Modified      - when this application was last modified
     *  nr_notes      - the number of notes (0 or more) in the story
     *  last_UserID   - user ID of the most recent person to comment in the thread
     *  last_Username - username of the most recent person to comment in the thread
     *  last_Created  - the timestamp of the most recent comment
     *
     * @return array list of Applicant information
     */
    public function list(int $page = 1, bool $resolved = false, int $userId = 0): array {
        $key = sprintf($resolved ? self::CACHE_KEY_RESOLVED : self::CACHE_KEY_OPEN, $page);
        if ($userId) {
            $key .= ".$userId";
        }
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $userCond = $userId ? 'a.UserID = ?' : '0 = ? /* manager */';
            self::$db->prepared_query($sql = <<<END_SQL
SELECT APP.ID, r.Title as Role, APP.UserID, u.Username, APP.Created, APP.Modified, APP.nr_notes,
    last.UserID as last_UserID, ulast.Username as last_Username, last.Created as last_Created
FROM
(
    SELECT a.ID as ID, a.RoleID, a.UserID, a.ThreadID, a.Created, a.Modified,
    count(n.ID) as nr_notes, max(n.ID) as last_noteid
    FROM applicant a
    LEFT JOIN thread_note n using (ThreadID)
    WHERE a.Resolved = ?
    AND $userCond
    GROUP BY a.ID, a.RoleID, a.UserID, a.ThreadID, a.Created, a.Modified
) APP
INNER JOIN applicant_role r     ON (r.ID = APP.RoleID)
INNER JOIN users_main     u     ON (u.ID = APP.UserID)
LEFT JOIN thread_note     last  USING (ThreadID)
LEFT JOIN users_main      ulast ON (ulast.ID = last.UserID)
WHERE (last.ID IS NULL or last.ID = APP.last_noteid)
ORDER by r.Modified DESC,
    GREATEST(coalesce(last.Created, '1970-01-01 00:00:00'), APP.Created) DESC
LIMIT ? OFFSET ?
END_SQL
            , $resolved ? 1 : 0, $userId, self::ENTRIES_PER_PAGE, ($page-1) * self::ENTRIES_PER_PAGE);
            $list = self::$db->has_results() ? self::$db->to_array() : [];
            self::$cache->cache_value($key, $list, 86400);
        }
        return $list;
    }

    public function userIsApplicant(int $userId): bool {
        $key = 'user_applicant_' . $userId;
        $hasApplication = self::$cache->get_value($key);
        if ($hasApplication === false) {
            $hasApplication = self::$db->scalar('SELECT 1 FROM applicant WHERE UserID = ? LIMIT 1', $userId) ? true : false;
            self::$cache->cache_value($key, $hasApplication, 86400);
        }
        return $hasApplication;
    }

    public function newApplicantCount(): int {
        $key = self::CACHE_KEY_NEW_COUNT;
        $count = self::$cache->get_value($key);
        if ($count === false) {
            $count = self::$db->scalar("
                SELECT count(a.ID) as nr
                FROM applicant a
                INNER JOIN thread t ON (a.ThreadID = t.ID
                    AND t.ThreadTypeID = (SELECT ID FROM thread_type WHERE Name = ?)
                )
                LEFT JOIN thread_note n ON (n.threadid = t.id)
                WHERE a.Resolved = 0
                    AND n.id IS NULL
                ", 'staff-role'
            ) ?? 0;
            self::$cache->cache_value($key, $count, 3600);
        }
        return $count;
    }

    public function newReplyCount(): int {
        $key = self::CACHE_KEY_NEW_REPLY;
        $count = self::$cache->get_value($key);
        if ($count === false) {
            $count = self::$db->scalar("
                SELECT count(*) AS nr
                FROM applicant a
                INNER JOIN thread_note n USING (ThreadID)
                INNER JOIN (
                    /* find the last person to comment in an applicant thread */
                    SELECT a.ID, max(n.ID) AS noteid
                    FROM applicant a
                    INNER JOIN thread t ON (a.threadid = t.id
                        AND t.ThreadTypeID = (SELECT ID FROM thread_type WHERE Name = ?)
                    )
                    INNER JOIN thread_note n ON (n.ThreadID = a.ThreadID)
                    WHERE a.Resolved = 0
                    GROUP BY a.ID
                ) X ON (n.ID = X.noteid)
                WHERE a.UserID = n.UserID /* if they are the applicant: not a staff response Q.E.D. */
                ", 'staff-role'
            ) ?? 0;
            self::$cache->cache_value($key, $count, 3600);
        }
        return $count;
    }
}
