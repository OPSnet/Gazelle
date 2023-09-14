<?php

namespace Gazelle;

class NotificationFilter extends BaseObject {
    final const tableName = 'users_notify_filters';
    protected const DIMENSION = [
        'artist', 'recordLabel', 'tag', 'notTag', 'category', 'format', 'encoding', 'media', 'user'
    ];

    public function flush(): static { return $this; }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), $this->url()); }
    public function location(): string { return 'user.php?action=notify'; }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $info = self::$db->rowAssoc("
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
        foreach (self::DIMENSION as $dimension) {
            $info[$dimension] = $this->expand($info[$dimension]);
        }
        $this->info = $info;
        return $this->info;
    }

    protected function expand($dimension): array {
        if (is_null($dimension) || $dimension === '') {
            return [];
        };
        // FIXME: stop leaving '||' in database when a trigger field is emptied
        if ($dimension === '||') {
            return [];
        }
        return explode('|', trim($dimension, '|'));
    }

    public function artistList(): ?array {
        return $this->info()['artist'];
    }

    public function label(): ?string {
        return $this->info()['label'];
    }
}
