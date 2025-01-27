<?php

namespace Gazelle\User;

class Warning extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    final public const tableName = 'user_warning';

    protected array $info;

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function add(string $reason, string $interval, \Gazelle\User $warner): string {
        $end = (string)$this->pg()->scalar("
            with cte as (
                select max(upper(warning)) as warning_end
                from user_warning
                where id_user = ? and now() < upper(warning)
            )
            insert into user_warning
                   (id_user, id_user_warner, reason, warning)
            values (?,       ?,              ?,      tstzrange(
                coalesce((select warning_end from cte), now()),
                coalesce((select warning_end from cte), now()) + ?::interval
            ))
            returning to_char(upper(warning), 'YYYY-MM-DD HH24:MI')
            ", $this->id(), $this->id(), $warner->id(), $reason, $interval
        );
        $this->user()->auditTrail()->addEvent(
            \Gazelle\Enum\UserAuditEvent::warning,
            "Warned for $interval (expiry $end) by {$warner->username()}\nReason: $reason"
        );
        $this->flush();
        return $end;
    }

    public function info(): array {
        if (!isset($this->info)) {
            $expiry = $this->pg()->scalar("
                select max(upper(warning))
                from user_warning
                where now() < upper(warning)
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
            order by id_user_warning
            ", $this->id()
        );
    }

    /**
     * Remove any current warnings
     */
    public function clear(): int {
        $affected = $this->pg()->prepared_query("
            update user_warning set
                warning = NULL
            where upper(warning) > now()
                and id_user = ?       
            ", $this->id()
        );
        $this->flush();
        return $affected;
    }
}
