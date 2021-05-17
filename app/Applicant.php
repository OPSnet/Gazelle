<?php

namespace Gazelle;

class Applicant extends Base {
    protected $id;
    protected $roleId;
    protected $userId;
    protected $body;
    protected $thread;
    protected $resolved;
    protected $created;
    protected $modified;

    const CACHE_KEY           = 'applicant_%d';
    const CACHE_KEY_OPEN      = 'applicant_list_open_%d';
    const CACHE_KEY_RESOLVED  = 'applicant_list_resolved_%d';
    const CACHE_KEY_NEW_COUNT = 'applicant_new_count';
    const CACHE_KEY_NEW_REPLY = 'applicant_new_reply';
    const ENTRIES_PER_PAGE    = 1000; // TODO: change to 50 and implement pagination

    public function __construct(int $id) {
        parent::__construct();
        $key = sprintf(self::CACHE_KEY, $id);
        $data = $this->cache->get_value($key);
        if ($data === false) {
            $this->db->prepared_query("
                SELECT a.RoleID, a.UserID, a.ThreadID, a.Body, a.Resolved, a.Created, a.Modified
                FROM applicant a
                WHERE a.ID = ?
            ", $id);
            if (!$this->db->has_results()) {
                throw new Exception\ResourceNotFoundException($id);
            }
            $data = $this->db->next_record();
            $this->cache->cache_value($key, $data, 86400);
        }
        $this->id       = $id;
        $this->roleId   = $data['RoleID'];
        $this->userId   = $data['UserID'];
        $this->body     = $data['Body'];
        $this->resolved = $data['Resolved'];
        $this->created  = $data['Created'];
        $this->modified = $data['Modified'];
        $this->thread   = new Thread($data['ThreadID']);
        // If we are coming from createApplicant() we need to wipe the applicant list
        $this->flushApplicantList();
    }

    protected function flushApplicantList() {
        $this->cache->deleteMulti([
            'user_applicant_' . $this->userId,
            self::CACHE_KEY_NEW_COUNT,
            self::CACHE_KEY_NEW_REPLY,
        ]);
        for ($page = 1; $page; ++$page) {
            $hit = 0;
            $cache_key = [
                sprintf(self::CACHE_KEY_OPEN,     $page),
                sprintf(self::CACHE_KEY_RESOLVED, $page),
                sprintf(self::CACHE_KEY_OPEN     . ".$this->userId", $page),
                sprintf(self::CACHE_KEY_RESOLVED . ".$this->userId", $page)
            ];
            foreach ($cache_key as $key) {
                if ($this->cache->get_value($key) !== false) {
                    ++$hit;
                    $this->cache->delete_value($key);
                }
            }
            if (!$hit) {
                break;
            }
        }
        return $this;
    }

    public function id() {
        return $this->id;
    }

    public function userId() {
        return $this->userId;
    }

    public function thread() {
        return $this->thread;
    }

    public function threadId() {
        return $this->thread->id();
    }

    public function roleTitle() {
        $appRoleMan = new Manager\ApplicantRole;
        return $appRoleMan->title($this->roleId);
    }

    public function body() {
        return $this->body;
    }

    public function created() {
        return $this->created;
    }

    public function resolve($resolved = true) {
        $this->resolved = $resolved;
        $this->db->prepared_query("
            UPDATE applicant
            SET Resolved = ?
            WHERE ID = ?
        ", $this->resolved, $this->id);
        $key = sprintf(self::CACHE_KEY, $this->id);
        $data = $this->cache->get_value($key);
        if ($data !== false) {
            $data['Resolved'] = $this->resolved;
            $this->cache->replace_value($key, $data, 86400);
        }
        $this->cache->delete_value('user_applicant_' . $this->userId);
        return $this->flushApplicantList();
    }

    public function isResolved() {
        return $this->resolved;
    }

    // DELEGATES

    /**
     * Save the applicant thread note (see Thread class)
     */
    public function saveNote($posterId, $body, $visibility) {
        $this->thread->saveNote($posterId, $body, $visibility);
        $this->cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        $this->cache->delete_value(self::CACHE_KEY_NEW_REPLY);
        $this->cache->delete_value(self::CACHE_KEY_NEW_COUNT);
        if ($visibility == 'public' && \Permissions::has_permission($posterId, 'admin_manage_applicants')) {
            (new Manager\User)->sendPM(
                $this->userId(),
                0,
                sprintf('You have a reply to your %s application', $this->roleTitle()),
                sprintf(<<<END_MSG
Hello %s,

%s has replied to your application for the role of %s.
You can view the reply [url=%s]here[/url].

~%s Staff <3
END_MSG
                    , (new User($this->userId()))->username()
                    , (new User($posterId))->username()
                    , $this->roleTitle()
                    , 'apply.php?action=view&id=' . $this->id()
                    , SITE_NAME
                )
            );
        }
        return $this->flushApplicantList();
    }

    public function removeNote($note_id) {
        $this->cache->delete_value(self::CACHE_KEY_NEW_REPLY);
        $this->thread()->removeNote($note_id);
        return $this->flushApplicantList();
    }

    /**
     * Get the applicant thread story (see Thread class)
     * Notes will be filtered out if viewer is not staff
     *
     * @param bool Is the viewer staff?
     * @return array of story notes
     */
    public function story(bool $isStaff): array {
        $story = $this->thread->story();
        if ($isStaff) {
            return $story;
        }
        $public = [];
        foreach ($story as $note) {
            if ($note['visibility'] != 'staff') {
                $public[] = $note;
            }
        }
        return $public;
    }
}
