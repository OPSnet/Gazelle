<?php

namespace Gazelle\User;

class Warning extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    protected bool|null $isWarned;

    public function flush(): Warning {
        $this->isWarned = null;
        return $this;
    }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }
    public function tableName(): string { return 'user_warning'; }

    public function create(string $reason, string $interval, \Gazelle\User $warner): string {
        $end = (string)$this->pg()->scalar("
            insert into user_warning
                   (id_user, id_user_warner, reason, warning)
            values (?,       ?,              ?,      tstzrange(now(), now() + ?::interval))
            returning upper(warning)
            ", $this->id(), $warner->id(), $reason, $interval
        );
        $this->flush();
        return $end;
    }

    public function total(): int {
        return (int)$this->pg()->scalar("
            select count(*) from user_warning where id_user = ?
            ", $this->id()
        );
    }

    public function isWarned(): bool {
        return $this->isWarned ??= (bool)$this->pg()->scalar("
            select 1
            from user_warning
            where now() <@ warning
                and id_user = ?
            limit 1
            ", $this->id()
        );
    }

    /**
     * Remove any current warnings
     */
    public function clear(): int {
        $affected = (int)$this->pg()->prepared_query("
            update user_warning set
                warning = tstzrange(lower(warning), now())
            where upper(warning) > now()
                and id_user = ?
            ", $this->id()
        );
        $this->flush();
        return $affected;
    }

    public function warningList(): array {
        return $this->pg()->all("
            select id_user_warner as id_warner,
                lower(warning)    as begin,
                upper(warning)    as end,
                now() <@ warning  as active,
                reason
            from user_warning
            where id_user = ?
            order by lower(warning)
            ", $this->id()
        );
    }
}
