<?php

namespace Gazelle\TGroup;

use Gazelle\TGroup as TGroup;
use Gazelle\User as User;

class Artwork extends \Gazelle\Base {
    final public const CACHE_KEY = 'tg_cover_2_%d';

    public function __construct(
        protected readonly TGroup $tgroup,
    ) {}

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->tgroup->id));
        return $this;
    }

    public function addArtwork(string $url, string $description, User $user): int {
        $idArtwork = $this->pg()->scalarex(<<<END_SQL
            insert into tgroup_artwork
                   (id_tgroup, id_user, url, description)
            values ($1,        $2,      $3,  $4)
            returning id_tgroup_artwork
            END_SQL,
                $this->tgroup->id, $user->id, $url, $description,
        );
        $this->logger()->group(
            $this->tgroup,
            $user,
            "Additional cover \"$description - $url\" added to group by {$user->username()}"
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->tgroup->id));
        return $idArtwork;
    }

    public function removeArtwork(int $idArtwork, User $user): int {
        [$url, $description] = $this->pg()->rowex("
            select url, description from tgroup_artwork where id_tgroup_artwork = $1
            ", $idArtwork
        );
        if (is_null($url)) {
            // no such id
            return 0;
        }
        $affected = $this->pg()->query("
            delete from tgroup_artwork where id_tgroup_artwork = $1
            ", $idArtwork
        );
        if ($affected) {
            $this->logger()->group(
                $this->tgroup,
                $user,
                "Additional cover \"$description - $url\" removed from group by {$user->username()}"
            );
            self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->tgroup->id));
        }
        return $affected;
    }

    public function artworkList(): array {
        $key = sprintf(self::CACHE_KEY, $this->tgroup->id);
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $list = $this->pg()->allex('
                select id_tgroup_artwork as id,
                    url,
                    description,
                    id_user,
                    created
                from tgroup_artwork
                where id_tgroup = $1
                order by created
                ', $this->tgroup->id
            );
            self::$cache->cache_value($key, $list, 0);
        }
        return $list;
    }
}
