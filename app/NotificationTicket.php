<?php

namespace Gazelle;

class NotificationTicket {
    use Pg;

    protected array $info;

    public function __construct(
        protected int $torrentId,
    ) {}

    public function flush(): static {
        $this->info  = [];
        return $this;
    }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $this->info = $this->pg()->rowAssoc("
            select state,
                reach,
                retry,
                created,
                modified
            from notification_ticket
            where id_torrent = ?
            ", $this->torrentId
        );
        $this->info['ticket_state'] = $this->ticketState($this->info['state']);
        return $this->info;
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function modified(): string {
        return $this->info()['modified'];
    }

    public function reach(): int {
        return $this->info()['reach'];
    }

    public function retry(): int {
        return $this->info()['retry'];
    }

    public function state(): NotificationTicketState {
        return $this->info()['ticket_state'];
    }

    public function isDone(): bool {
        return $this->state() == NotificationTicketState::Done;
    }

    public function isPending(): bool {
        return $this->state() == NotificationTicketState::Pending;
    }

    public function isStale(): bool {
        return $this->state() == NotificationTicketState::Stale;
    }

    public function torrentId(): int {
        return $this->torrentId;
    }

    // ---------------------------------------------------

    public function incrementRetry(): static {
        $this->pg()->prepared_query("
            update notification_ticket set
                retry = retry + 1
            where id_torrent = ?
            ", $this->torrentId
        );
        $this->flush();
        if ($this->isPending() && $this->retry() >= 60) {
            // after an hour we stop checking it as frequently
            $this->setStale();
        }
        return $this->flush();
    }

    public function setReach(int $reach): static {
        $this->pg()->prepared_query("
            update notification_ticket set
                reach = ?
            where id_torrent = ?
            ", $reach, $this->torrentId
        );
        return $this->flush();
    }

    public function setState(NotificationTicketState $state): static {
        $this->pg()->prepared_query("
            update notification_ticket set
                state = ?
            where id_torrent = ?
            ", $state->value, $this->torrentId
        );
        $this->info['state']        = $state->value;
        $this->info['ticket_state'] = $state;
        return $this;
    }

    public function setActive(): static {
        return $this->setState(NotificationTicketState::Active);
    }

    public function setDone(): static {
        return $this->setState(NotificationTicketState::Done);
    }

    public function setError(): static {
        return $this->setState(NotificationTicketState::Error);
    }

    public function setPending(): static {
        return $this->setState(NotificationTicketState::Pending);
    }

    public function setRemoved(): static {
        return $this->setState(NotificationTicketState::Removed);
    }

    public function setStale(): static {
        return $this->setState(NotificationTicketState::Stale);
    }

    public function ticketState(string $state): NotificationTicketState {
        return match ($state) {
            'pending' => NotificationTicketState::Pending,
            'stale'   => NotificationTicketState::Stale,
            'active'  => NotificationTicketState::Active,
            'done'    => NotificationTicketState::Done,
            'removed' => NotificationTicketState::Removed,
            default   => NotificationTicketState::Error,
        };
    }
}
