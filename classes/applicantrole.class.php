<?php

class ApplicantRole {
    private $id;
    private $title;
    private $published;
    private $description;
    private $user_id;
    private $created;
    private $modified;

    const CACHE_KEY           = 'approle_%d';
    const CACHE_KEY_ALL       = 'approle_list_all';
    const CACHE_KEY_PUBLISHED = 'approle_list_published';

    public function __construct ($title = null, $description = null, $published = null, $user_id = null) {
        if (!isset($title)) {
            return;
        }
        $this->title       = $title;
        $this->description = $description;
        $this->published   = $published ? 1 : 0;
        $this->user_id     = $user_id;
        $this->created     = strftime('%Y-%m-%d %H:%M:%S', time());
        $this->modified    = $this->created;
        G::$DB->prepared_query("
            INSERT INTO applicant_role (Title, Description, Published, UserID, Created, Modified)
            VALUES (?, ?, ?, ?, ?, ?)
        ", $this->title, $this->description, $this->published, $this->user_id, $this->created, $this->modified);
        $this->id = G::$DB->inserted_id();
        G::$Cache->delete_value(self::CACHE_KEY_ALL);
        G::$Cache->delete_value(self::CACHE_KEY_PUBLISHED);
        G::$Cache->cache_value(sprintf(self::CACHE_KEY, $this->id),
            [
                'Title'       => $this->title,
                'Published'   => $this->published,
                'Description' => $this->description,
                'UserID'      => $this->user_id,
                'Created'     => $this->created,
                'Modified'    => $this->modified
            ]
        );
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

    public function is_published() {
        return $this->published;
    }

    public function user_id() {
        return $this->user_id;
    }

    public function created() {
        return $this->created;
    }

    public function modified() {
        return $this->modified;
    }

    public function update($title, $description, $published) {
        $this->title       = $title;
        $this->description = $description;
        $this->published   = $published ? 1 : 0;
        $this->modified    = strftime('%Y-%m-%d %H:%M:%S', time());

        G::$DB->prepared_query("
            UPDATE applicant_role
            SET Title = ?, Published = ?, Description = ?, Modified = ?
            WHERE ID = ?
        ", $this->title, $this->published, $this->description, $this->modified,
            $this->id);
        G::$Cache->delete_value(self::CACHE_KEY_ALL);
        G::$Cache->delete_value(self::CACHE_KEY_PUBLISHED);
        G::$Cache->replace_value(sprintf(self::CACHE_KEY, $this->id),
            [
                'Title'          => $this->title,
                'Published'   => $this->published,
                'Description' => $this->description,
                'UserID'      => $this->user_id,
                'Created'     => $this->created,
                'Modified'    => $this->modified
            ]
        );
        return $this;
    }

    // FACTORY METHODS

    static public function factory($id) {
        $approle = new self();
        $key = sprintf(self::CACHE_KEY, $id);
        $data = G::$Cache->get_value($key);
        if ($data === false) {
            G::$DB->prepared_query("
                SELECT Title, Published, Description, UserID, Created, Modified
                FROM applicant_role
                WHERE ID = ?
            ", $id);
            if (G::$DB->has_results()) {
                $data = G::$DB->next_record(MYSQLI_ASSOC);
                G::$Cache->cache_value($key, $data, 86400);
            }
        }
        $approle->id            = $id;
        $approle->title        = $data['Title'];
        $approle->published   = $data['Published'] ? 1 : 0;
        $approle->description = $data['Description'];
        $approle->user_id     = $data['UserID'];
        $approle->created     = $data['Created'];
        $approle->modified    = $data['Modified'];
        return $approle;
    }

    static public function get_id($role) {
        $list = self::get_list(true);
        return $list[$role]['id'];
    }

    static public function get_title($id) {
        $list = self::get_list(true);
        foreach ($list as $role => $data) {
            if ($data['id'] == $id) {
                return $role;
            }
        }
        return null;
    }

    static public function get_list($all = false) {
        $key = $all ? self::CACHE_KEY_ALL : self::CACHE_KEY_PUBLISHED;
        $list = G::$Cache->get_value($key);
        if ($list === false) {
            $where = $all ? '/* all */' : 'WHERE r.Published = 1';
            G::$DB->query("
                SELECT r.ID as role_id, r.Title as role, r.Published, r.Description, r.UserID, r.Created, r.Modified
                FROM applicant_role r
                $where
                ORDER BY r.Title
            ");
            $list = [];
            while (($row = G::$DB->next_record(MYSQLI_ASSOC))) {
                $list[$row['role']] = [
                    'id'          => $row['role_id'],
                    'published'   => $row['Published'] ? 1 : 0,
                    'description' => $row['Description'],
                    'user_id'     => $row['UserID'],
                    'created'     => $row['Created'],
                    'modified'    => $row['Modified']
                ];
            }
            G::$Cache->cache_value($key, $list, 86400 * 10);
        }
        return $list;
    }
}
