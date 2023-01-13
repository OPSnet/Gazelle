<?php

namespace Gazelle;

class Tag extends BaseObject {
    public function info(): array {
        return $this->info ??= self::$db->rowAssoc("
            SELECT t.Name AS name,
                t.TagType AS type,
                t.Uses AS uses,
                t.UserID AS user_id
            FROM tags t
            WHERE t.ID = ?
            ", $this->id
        );
    }

    public function flush(): Tag { return $this; }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name())); }
    public function location(): string { return 'torrents.php?taglist=' . $this->name(); }
    public function tableName(): string { return 'tags'; }

    public function name(): string {
        return $this->info()['name'];
    }

    /**
     * Tag type
     * @return string one of 'genre' or 'other' ('genre' designates an official tag).
     */
    public function type(): string {
        return $this->info()['type'];
    }

    /**
     * Number of uses of the tag.
     */
    public function uses(): int {
        return $this->info()['uses'];
    }

    /**
     * Who created the tag.
     */
    public function userId(): int {
        return $this->info()['user_id'];
    }
}
