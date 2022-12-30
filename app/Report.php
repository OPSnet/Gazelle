<?php

namespace Gazelle;

class Report extends BaseObject {
    protected array $info;
    protected Manager\User $userMan;

    public function flush(): Report { return $this; }
    public function link(): string { return sprintf('<a href="%s">Report #%d</a>', $this->url(), $this->id()); }
    public function location(): string { return "reports.php?id={$this->id}#report{$this->id}"; }
    public function tableName(): string { return 'reports'; }

    public function setUserManager(Manager\User $userMan) {
        $this->userMan = $userMan;
        return $this;
    }

    public function info(): array {
        return $this->info ??= self::$db->rowAssoc("
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

    public function resolved(): string {
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

    public function claim(int $userId): int {
        self::$db->prepared_query("
            UPDATE reports SET
                ClaimerID = ?
            WHERE ID = ?
            ", $userId, $this->id
        );
        return self::$db->affected_rows();
    }
}
