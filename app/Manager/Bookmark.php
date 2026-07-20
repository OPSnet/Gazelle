<?php

namespace Gazelle\Manager;

class Bookmark extends \Gazelle\Base {
    public function merge(\Gazelle\TGroup $old, \Gazelle\TGroup $new): int {
        return $this->pg()->query("
            insert into bookmark_tgroup
                (id_tgroup, id_user, seq, created)
            select $1, id_user, seq, created
            from bookmark_tgroup
            where id_tgroup = $2
            on conflict (id_tgroup, id_user) do nothing
            ", $new->id, $old->id
        );
    }
}
