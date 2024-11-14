<?php

namespace Gazelle;

use Gazelle\Enum\FeaturedAlbumType;

class FeaturedAlbum extends BaseObject {
    final public const tableName = 'featured_albums';
    final public const FEATURED  = 'feat_%s_%d';

    public function __construct(
        protected FeaturedAlbumType $type,
        protected int               $id,
    ) {
        parent::__construct($id);
    }

    public function flush(): static {
        unset($this->info);
        self::$cache->delete_value(sprintf(self::FEATURED, $this->type->value, $this->id));
        return $this;
    }

    public function link(): string {
        return $this->thread()->link();
    }

    public function location(): string {
        return $this->thread()->location();
    }

    public function info(): array {
        if (!isset($this->info)) {
            $key = sprintf(self::FEATURED, $this->type->value, $this->id);
            $info = self::$cache->get_value($key);
            if ($info === false) {
                $info = self::$db->rowAssoc("
                    SELECT fa.GroupID AS tgroup_id,
                        fa.ThreadID   AS thread_id,
                        fa.Type       AS type,
                        fa.Started    AS date_begin,
                        fa.Ended      AS date_end
                    FROM featured_albums AS fa
                    INNER JOIN torrents_group AS tg ON (tg.ID = fa.GroupID)
                    WHERE fa.GroupID = ?
                    ", $this->id
                );
                self::$cache->cache_value($key, $info, 86400 * 7);
            }
            $this->info = $info;
        }
        return $this->info;
    }

    public function dateBegin(): string {
        return $this->info()['date_begin'];
    }

    public function dateEnd(): ?string {
        return $this->info()['date_end'];
    }

    public function tgroupId(): int {
        return $this->info()['tgroup_id'];
    }

    public function tgroup(): TGroup {
        return new TGroup($this->info()['tgroup_id']);
    }

    public function thread(): ForumThread {
        return new ForumThread($this->info()['thread_id']);
    }

    public function type(): FeaturedAlbumType {
        return match ((int)$this->info()['type']) {
            1       => FeaturedAlbumType::Showcase,
            default => FeaturedAlbumType::AlbumOfTheMonth,
        };
    }

    public function unfeature(): int {
        self::$db->prepared_query("
            UPDATE featured_albums SET
                Ended = now()
            WHERE Ended IS NULL
                AND GroupID = ?
            ", $this->tgroupId()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM featured_albums WHERE GroupID = ?
            ", $this->tgroupId()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }
}
