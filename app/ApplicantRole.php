<?php

namespace Gazelle;

class ApplicantRole extends Base {
    protected $id;
    protected $title;
    protected $published;
    protected $description;
    protected $userId;
    protected $created;
    protected $modified;

    const CACHE_KEY           = 'approle_%d';
    const CACHE_KEY_ALL       = 'approle_list_all';
    const CACHE_KEY_PUBLISHED = 'approle_list_published';

    public function __construct(int $id) {
        $key = sprintf(self::CACHE_KEY, $id);
        $data = self::$cache->get_value($key);
        if ($data === false) {
            self::$db->prepared_query("
                SELECT Title, Published, Description, UserID, Created, Modified
                FROM applicant_role
                WHERE ID = ?
            ", $id);
            if (!self::$db->has_results()) {
                throw new Exception\ResourceNotFoundException($id);
            }
            $data = self::$db->next_record(MYSQLI_ASSOC);
            self::$cache->cache_value($key, $data, 86400);
        }
        $this->id          = $id;
        $this->title       = $data['Title'];
        $this->published   = $data['Published'];
        $this->description = $data['Description'];
        $this->userId      = $data['UserID'];
        $this->created     = $data['Created'];
        $this->modified    = $data['Modified'];
    }

    public function id() {
        return $this->id;
    }

    public function title() {
        return $this->title;
    }

    public function description() {
        return $this->description;
    }

    public function isPublished() {
        return $this->published;
    }

    public function userId() {
        return $this->userId;
    }

    public function created() {
        return $this->created;
    }

    public function modified() {
        return $this->modified;
    }

    public function modify($title, $description, $published) {
        $this->title       = $title;
        $this->description = $description;
        $this->published   = $published ? 1 : 0;
        $this->modified    = strftime('%Y-%m-%d %H:%M:%S', time());

        self::$db->prepared_query("
            UPDATE applicant_role SET
                Title = ?,
                Published = ?,
                Description = ?,
                Modified = ?
            WHERE ID = ?
        ", $this->title, $this->published, $this->description, $this->modified,
            $this->id);
        self::$cache->delete_value(self::CACHE_KEY_ALL);
        self::$cache->delete_value(self::CACHE_KEY_PUBLISHED);
        self::$cache->cache_value(sprintf(self::CACHE_KEY, $this->id),
            [
                'Title'       => $this->title,
                'Published'   => $this->published,
                'Description' => $this->description,
                'UserID'      => $this->userId,
                'Created'     => $this->created,
                'Modified'    => $this->modified
            ]
        );
        return $this;
    }
}
