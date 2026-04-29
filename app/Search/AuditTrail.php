<?php

namespace Gazelle\Search;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Manager\User as UserManager;
use Gazelle\Util\SortableTableHeader;
use Gazelle\User;

class AuditTrail extends \Gazelle\Base {
    protected array $info;
    protected array $args;
    protected array $cond;
    protected array $join;
    protected SortableTableHeader $heading;

    public function heading(): SortableTableHeader {
        return $this->heading ??= new SortableTableHeader(
            'created',
            [
                'user'    => ['dbColumn' => 'um."Username"', 'defaultSort' => 'asc',  'text' => 'User'],
                'author'  => ['dbColumn' => 'ua."Username"', 'defaultSort' => 'asc',  'text' => 'Author'],
                'event'   => ['dbColumn' => 'atu.event',     'defaultSort' => 'asc',  'text' => 'Event'],
                'created' => ['dbColumn' => 'atu.created',   'defaultSort' => 'desc', 'text' => 'Created'],
                'note'    => ['dbColumn' => 'atu.note',      'defaultSort' => 'asc',  'text' => 'Note'],
            ]
        );
    }

    public function setCreatedAfter(string $after): static {
        $this->info['after'] = $after;
        return $this;
    }

    public function setCreatedBefore(string $before): static {
        $this->info['before'] = $before;
        return $this;
    }

    /**
     * @param $list array<string>
     */
    public function setEventList(array $list): static {
        $eventList = [];
        foreach ($list as $value) {
            $event = match ($value) {
                'activity'   => UserAuditEvent::activity,
                'historical' => UserAuditEvent::historical,
                'invite'     => UserAuditEvent::invite,
                'link'       => UserAuditEvent::link,
                'staff-note' => UserAuditEvent::staffNote,
                'mfa'        => UserAuditEvent::mfa,
                'ratio'      => UserAuditEvent::ratio,
                'request'    => UserAuditEvent::request,
                'userclass'  => UserAuditEvent::userclass,
                'warning'    => UserAuditEvent::warning,
                default      => null,
            };
            if ($event) {
                $eventList[] = $event->value;
            }
        }
        if ($eventList) {
            $this->info['event'] = $eventList;
        }
        return $this;
    }

    /**
     * setEventList() is the only method that does its own parsing.
     * The result therefore needs to be communicated back to the
     * caller to allow it to populate the checkboxes in an edit form.
     */
    public function eventList(): array {
        return $this->info['event'] ?? [];
    }

    public function setNote(string $note): static {
        $this->info['note'] = $note;
        return $this;
    }

    /**
     * @param $list array<User>
     */
    public function setAuthorList(array $list): static {
        $this->info['author'] = array_map(fn ($u) => $u->id, $list);
        return $this;
    }

    /**
     * @param $list array<User>
     */
    public function setUserList(array $list): static {
        $this->info['user'] = array_map(fn ($u) => $u->id, $list);
        return $this;
    }

    public function configure(): void {
        if (isset($this->args)) {
            return;
        }
        $this->args = [];
        $this->cond = [];
        $this->join = [];
        if (isset($this->info['after'])) {
            $this->cond[] = 'atu.created >= ' . $this->pg()->placeholder(count($this->args));
            $this->args[] = $this->info['after'];
        }
        if (isset($this->info['before'])) {
            $this->cond[] = 'atu.created <= ' . $this->pg()->placeholder(count($this->args));
            $this->args[] = $this->info['before'];
        }
        if (isset($this->info['event'])) {
            $this->cond[] = "atu.event = any({$this->pg()->placeholder(count($this->args))}::text[])";
            $this->args[] = $this->pg()->arrayConverter()->output($this->info['event']);
        }
        if (isset($this->info['note'])) {
            $this->cond[] = "atu.note_ts @@ websearch_to_tsquery('simple', {$this->pg()->placeholder(count($this->args))})";
            $this->args[] = $this->info['note'];
        }
        if (isset($this->info['author'])) {
            $this->join[] = 'inner join relay.users_main ua on (ua."ID" = atu.id_user_creator)';
            $this->cond[] = "atu.id_user_creator = any({$this->pg()->placeholder(count($this->args))}::int[])";
            $this->args[] = $this->pg()->integerConverter()->output($this->info['author']);
        }
        if (isset($this->info['user'])) {
            $this->join[] = 'inner join relay.users_main um on (um."ID" = atu.id_user)';
            $this->cond[] = "atu.id_user = any({$this->pg()->placeholder(count($this->args))}::int[])";
            $this->args[] = $this->pg()->integerConverter()->output($this->info['user']);
        }

        // check if we need to join users_main for order by
        if ($this->heading()->orderKey() === 'author' && !isset($this->info['author'])) {
            $this->join[] = 'inner join relay.users_main ua on (ua."ID" = atu.id_user_creator)';
        }
        if ($this->heading()->orderKey() === 'user' && !isset($this->info['user'])) {
            $this->join[] = 'inner join relay.users_main um on (um."ID" = atu.id_user)';
        }
    }

    public function where(): string {
        return $this->cond === [] ? '' : ("where " . implode(' and ', $this->cond));
    }

    public function totalSql(): string {
        $this->configure();
        $join = isset($this->join) ? implode(' ', $this->join) : '';
        return <<<END_SQL
            select count(*)
            from audit_trail_user atu
            $join
            {$this->where()}
        END_SQL;
    }

    public function total(): int {
        return (int)$this->pg()->scalarex(
            $this->totalSql(),
            ...$this->args,
        );
    }

    public function pageSql(): string {
        $this->configure();
        $limitPlaceholder  = $this->pg()->placeholder(count($this->args));
        $offsetPlaceholder = $this->pg()->placeholder(count($this->args) + 1);
        $join = isset($this->join) ? implode(' ', $this->join) : '';
        return <<<END_SQL
            select atu.id_user, atu.id_user_creator, atu.created, atu.event, atu.note
            from audit_trail_user atu
            $join
            {$this->where()}
            order by {$this->heading()->orderBy()} {$this->heading()->dir()}
            limit $limitPlaceholder offset $offsetPlaceholder
        END_SQL;
    }

    public function page(int $limit, int $offset): array {
        return $this->pg()->allex(
            $this->pageSql(),
            ...[...$this->args, $limit, $offset],
        );
    }
}
