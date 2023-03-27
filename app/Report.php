<?php

namespace Gazelle;

class Report extends BaseObject {
    protected Manager\User $userMan;

    public function flush(): Report {
        $this->info = [];
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">Report #%d</a>', $this->url(), $this->id()); }
    public function location(): string { return "reports.php?id={$this->id}#report{$this->id}"; }
    public function tableName(): string { return 'reports'; }

    public function setUserManager(Manager\User $userMan): Report {
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
            ->setUpdate('ClaimerID', (int)$user?->id())
            ->setUpdate('Status', 'InProgress')
            ->modify();
    }

    public function addNote(string $note): Report {
        $this->setUpdate('Notes', str_replace("<br />", "\n", trim($note)))->modify();
        return $this;
    }

    public function resolve(User $user, Manager\Report $manager): int {
        // can't use setUpdate() because there is no elegant way to say `ResolvedTime = now()`
        self::$db->prepared_query("
            UPDATE reports SET
                Status = 'Resolved',
                ResolvedTime = now(),
                ResolverID = ?
            WHERE ID = ?
            ", $user->id(), $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        self::$cache->delete_value('num_other_reports');

        $channelList = [];
        if ($this->subjectType() == 'request_update') {
            $channelList[] = IRC_CHAN_REPORT_REQUEST;
            self::$cache->decrement('num_update_reports');
        } elseif (in_array($this->subjectType(), ['comment', 'post', 'thread'])) {
            $channelList[] = IRC_CHAN_REPORT_FORUM;
            self::$cache->decrement('num_forum_reports');
        }
        $message = "Report {$this->id()} resolved by {$user->username()} ({$manager->remainingTotal()} remaining).";
        foreach ($channelList as $channel) {
            Util\Irc::sendMessage($channel, $message);
        }

        return $affected;
    }
}
