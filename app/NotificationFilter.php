<?php

namespace Gazelle;

class NotificationFilter extends BaseObject {
    /** @var array */
    protected $info;

    protected const DIMENSION = [
        'artist', 'recordLabel', 'tag', 'notTag', 'category', 'format', 'encoding', 'media', 'user'
    ];

    public function __construct(int $id) {
        parent::__construct($id);
        self::$db->prepared_query("
            SELECT UserID AS user_id,
                Label        AS label,
                Artists      AS artist,
                RecordLabels AS recordLabel,
                Users        AS user,
                Tags         AS tag,
                NotTags      AS notTag,
                Categories   AS category,
                Formats      AS format,
                Encodings    AS encoding,
                Media        AS media,
                FromYear     AS from_year,
                ToYear       AS to_year,
                CASE WHEN ExcludeVA = '1' THEN 'Yes' ELSE 'No' END AS exclude_va,
                CASE WHEN NewGroupsOnly = '1' THEN 'Yes' ELSE 'No' END AS new_groups_only
            FROM users_notify_filters
            WHERE ID = ?
            ", $this->id
        );
        $this->info = self::$db->next_row(MYSQLI_ASSOC);
        foreach (self::DIMENSION as $dimension) {
            $this->info[$dimension] = $this->expand($this->info[$dimension]);
        }
    }

    public function tableName(): string {
        return 'users_notify_filters';
    }

    public function location(): string {
        return 'user.php?action=notify';
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), $this->url());
    }

    public function flush() {
        return $this;
    }

    protected function expand($info): array {
        if (is_null($info) || $info === '') {
            return [];
        };
        // FIXME: stop leaving '||' in database when a trigger field is emptied
        if ($info === '||') {
            return [];
        }
        $expand = explode('|', substr($info, 1, strlen($info) - 2));
        return $expand;
    }

    public function info(): array {
        return $this->info;
    }
}
