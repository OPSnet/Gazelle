<?php

namespace Gazelle\User;

class Warning extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    final const tableName = 'user_warning';

    protected array|null $info;

    public function flush(): Warning {
        unset($this->info);
        return $this;
    }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }

    public function create(string $reason, string $interval, \Gazelle\User $warner): string {
        $end = (string)$this->pg()->scalar("
            insert into user_warning
                   (id_user, id_user_warner, reason, warning)
            values (?,       ?,              ?,      tstzrange(now(), now() + ?::interval))
            returning date_trunc('second', upper(warning))
            ", $this->id(), $warner->id(), $reason, $interval
        );
        $this->user()->addStaffNote("Warned for $interval, expiry $end, reason: $reason");
        $this->flush();
        return $end;
    }

    public function info(): array {
        if (!isset($this->info)) {
            $expiry = $this->pg()->scalar("
                select max(upper(warning))
                from user_warning
                where now() <@ warning
                    and id_user = ?
                ", $this->id()
            );
            $this->info = ['expiry' => is_null($expiry) ? null : (string)$expiry];
        }
        return $this->info;
    }

    public function isWarned(): bool {
        return !is_null($this->warningExpiry());
    }

    public function warningExpiry(): ?string {
        return $this->info()['expiry'];
    }

    public function total(): int {
        return count($this->warningList());
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
}
