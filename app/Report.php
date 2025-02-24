<?php

namespace Gazelle;

class Report extends BaseObject {
    final public const tableName = 'reports';

    protected Manager\User $userMan;

    public function flush(): static {
        $this->info = [];
        return $this;
    }

    public function link(): string {
        return sprintf('<a href="%s">Report #%d</a>', $this->url(), $this->id());
    }

    public function location(): string {
        return "reports.php?id={$this->id}#report{$this->id}";
    }

    public function setUserManager(Manager\User $userMan): static {
        $this->userMan = $userMan;
        return $this;
    }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $this->info = self::$db->rowAssoc("
            SELECT r.UserID    AS reporter_user_id,
                r.ThingID      AS subject_id,
                r.Type         AS subject_type,
                r.ReportedTime AS created,
                r.Reason       AS reason,
                r.Status       AS status,
                r.ClaimerID    AS claimer_user_id,
                r.Notes        AS notes,
                r.ResolverID   AS resolver_user_id,
                r.ResolvedTime AS resolved_time
            FROM reports AS r
            WHERE r.ID = ?
            ", $this->id
        );
        return $this->info;
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function notes(): ?string {
        return $this->info()['notes'];
    }

    public function reason(): string {
        return $this->info()['reason'];
    }

    public function resolved(): ?string {
        return $this->info()['resolved_time'];
    }

    public function status(): string {
        return $this->info()['status'];
    }

    public function subjectId(): int {
        return $this->info()['subject_id'];
    }

    public function subjectType(): string {
        return $this->info()['subject_type'];
    }

    public function isClaimed(): bool {
        return (bool)$this->info()['claimer_user_id'];
    }

    public function claimer(): ?User {
        return $this->userMan->findById((int)$this->info()['claimer_user_id']);
    }

    public function reporter(): ?User {
        return $this->userMan->findById((int)$this->info()['reporter_user_id']);
    }

    public function resolver(): ?User {
        return $this->userMan->findById((int)$this->info()['resolver_user_id']);
    }

    /**
     * Claim a report. (Pass null to unclaim a currently claimed report)
     */
    public function claim(?User $user): int {
        return (int)$this
            ->setField('ClaimerID', (int)$user?->id())
            ->setField('Status', 'InProgress')
            ->modify();
    }

    public function addNote(string $note): static {
        $this->setField('Notes', str_replace("<br />", "\n", trim($note)))->modify();
        return $this;
    }

    public function resolve(User $user): int {
        $affected = $this
            ->setField('Status', 'Resolved')
            ->setField('ResolverID', $user->id())
            ->setFieldNow('ResolvedTime')
            ->modify();

        self::$cache->delete_value('num_other_reports');
        if ($this->subjectType() == 'request_update') {
            self::$cache->decrement('num_update_reports');
        } elseif (in_array($this->subjectType(), ['comment', 'post', 'thread'])) {
            self::$cache->decrement('num_forum_reports');
        }

        return (int)$affected;
    }

    /**
     * You should never call this in production - it is only for unit tests
     */
    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM reports WHERE ID = ?
            ", $this->id
        );
        $this->flush();
        self::$cache->delete_value('num_other_reports');
        return $this->id();
    }
}
