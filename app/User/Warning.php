<?php

namespace Gazelle\User;

class Warning extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    final const tableName = 'user_warning';

    protected array|null $info;

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function add(string $reason, string $interval, \Gazelle\User $warner): string {
        $args = [$this->id(), $warner->id(), $reason];
        $oldExpire = $this->warningExpiry();
        if ($oldExpire) {
            $tsrange = 'tstzrange(?::timestamptz, ?::timestamptz + ?::interval)';
            array_push($args, $oldExpire, $oldExpire);
        } else {
            $tsrange = 'tstzrange(now(), now() + ?::interval)';
        }
        $args[] = $interval;
        $end = (string)$this->pg()->scalar("
            insert into user_warning
                   (id_user, id_user_warner, reason, warning)
            values (?,       ?,              ?,      $tsrange)
            returning to_char(upper(warning), 'YYYY-MM-DD HH24:MI')
            ", ...$args
        );
        $this->user()->addStaffNote("Warned for $interval (expiry $end) by {$warner->username()}. Reason: $reason")->modify();
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
                warning = tstzrange(lower(warning), greatest(lower(warning), now()))
            where upper(warning) > now()
                and id_user = ?
            ", $this->id()
        );
        $this->flush();
        return $affected;
    }
}
