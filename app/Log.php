<?php

namespace Gazelle;

use Gazelle\Intf\CategoryHasArtist;

class Log extends Base {
    /**
     * Write a general message to the system log.
     */
    public function general(string $message): static {
        $this->pg()->insert("
            insert into site_log (note) values (?)
            ", trim($message)
        );
        return $this;
    }

    /**
     * Write a group entry
     */
    public function group(TGroup|CategoryHasArtist $tgroup, ?User $user, string $note): static {
        $this->pg()->query("
            insert into audit_trail_tgroup
                   (id_tgroup, id_user, note)
            values ($1,        $2,      $3)
            ", $tgroup->id, $user?->id, $note /** @phpstan-ignore-line property.notFound */
        );
        return $this;
    }

    /**
     * Write a torrent entry
     */
    public function torrent(TorrentAbstract $torrent, ?User $user, string $note): static {
        $this->pg()->query("
            insert into audit_trail_tgroup
                   (id_tgroup, id_torrent, id_user, note)
            values ($1,        $2,         $3,      $4)
            ", $torrent->groupId(), $torrent->id, $user?->id, $note
        );
        return $this;
    }

    public function merge(TGroup $old, TGroup $new): int {
        return $this->pg()->query('
            update audit_trail_tgroup set
                id_tgroup = $1
            where id_tgroup = $2
            ', $new->id, $old->id
        );
    }
}
