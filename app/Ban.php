<?php

namespace Gazelle;

class Ban extends BasePgObject {
    final public const tableName = 'ip_ban';
    final public const pkName    = 'id_ip_ban';

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return sprintf("<a href=\"{$this->url()}\">IP {$this->ip()}</a>");
    }

    public function location(): string {
        return "tools.php?action=ip-ban-edit&id={$this->id}";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info = $this->pg()->rowAssoc("
            select ip,
                is_active
            from ip_ban ib
            where id_ip_ban = ?
            ", $this->id
        );
        $this->info['hist'] = $this->pg()->allex("
            select id_ip_ban_hist,
                id_user,
                created,
                note
            from ip_ban_hist
            where id_ip_ban = $1
            order by id_ip_ban_hist desc
            ", $this->id
        );
        return $this->info;
    }

    public function created(): \DateTimeImmutable {
        return $this->history()[0]['created'];
    }

    public function history(): array {
        return $this->info()['hist'];
    }

    public function ip(): string {
        return $this->info()['ip'];
    }

    public function isActive(): bool {
        return $this->info()['is_active'];
    }

    public function total(): int {
        return count($this->history());
    }

    public function addNote(string $note, User|null $user): int {
        $added = $this->pg()->scalarex("
            insert into ip_ban_hist
                   (id_ip_ban, note, id_user)
            values ($1,        $2,   $3)
            returning id_ip_ban_hist
            ", $this->id, $note, (int)$user?->id,
        );
        $this->flush();
        return $added;
    }
}
