<?php

namespace Gazelle;

class ReportAuto extends BasePgObject {
    final public const tableName = 'report_auto';
    final public const pkName = 'id_report_auto';
    protected array $comments;

    public function __construct(
        protected int $id,
        protected $typeMan = new \Gazelle\Manager\ReportAutoType(),
    ) {
        parent::__construct($id);
    }

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return sprintf('<a href="%s">Auto Report #%d</a>', $this->url(), $this->id());
    }

    public function location(): string {
        return "report_auto.php?id={$this->id}#report{$this->id}";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info = $this->pg()->rowAssoc("
            SELECT
                id_user,
                id_report_auto_type,
                created,
                resolved,
                id_owner,
                data
            FROM report_auto
            WHERE id_report_auto = ?
            ", $this->id
        );
        return $this->info;
    }

    public function comments(): array {
        if (isset($this->comments)) {
            return $this->comments;
        }
        $this->comments = $this->pg()->all("
            SELECT
                id_user,
                created,
                comment
            FROM report_auto_comment
            WHERE id_report_auto = ?
            ORDER BY created
            ", $this->id
        );
        return $this->comments;
    }

    /**
     * returns an ISO timestring
     */
    public function created(): string {
        return $this->info()['created'];
    }

    public function state(): Enum\ReportAutoState {
        if ($this->isResolved()) {
            return Enum\ReportAutoState::closed;
        } elseif ($this->isClaimed()) {
            return Enum\ReportAutoState::in_progress;
        }
        return Enum\ReportAutoState::open;
    }

    public function typeId(): int {
        return $this->info()['id_report_auto_type'];
    }

    public function isClaimed(): bool {
        return $this->info()['id_owner'] !== null;
    }

    public function isResolved(): bool {
        return $this->info()['resolved'] !== null;
    }

    public function hasComments(): bool {
        return (bool)$this->comments();
    }

    public function ownerId(): ?int {
        return $this->isClaimed() ? (int)$this->info()['id_owner'] : null;
    }

    public function userId(): int {
        return (int)$this->info()['id_user'];
    }

    /**
     * returns a short title text of the type of report
     */
    public function text(): string {
        $type = $this->typeMan->findById($this->typeId());
        if ($type?->category()) {
            return "[{$type->category()}] {$type->name()}";
        }
        return $type->name();
    }

    public function data(): ?array {
        return json_decode($this->info()['data'], true);
    }

    /**
     * returns html string with details about the report
     *
     * you will want to override this in a custom class for most report types
     */
    public function details(): string {
        return \Text::full_format('[pre]' . json_encode($this->data(), JSON_PRETTY_PRINT) . '[/pre]');
    }

    /**
     * Claim a report. (Pass null to unclaim a currently claimed report)
     */
    public function claim(?User $user): bool {
        return $this
            ->setField('id_owner', $user?->id())
            ->modify();
    }

    public function unclaim(): bool {
        return $this->claim(null);
    }

    /**
     * returns comment id on success, null otherwise
     */
    public function addComment(\Gazelle\User $user, string $comment): ?int {
        $commentId = $this->pg()->scalar("
            INSERT INTO report_auto_comment
                   (id_report_auto, id_user, comment)
            VALUES (?,              ?,       ?)
            RETURNING id_report_auto_comment
            ", $this->id, $user->id(), $comment
        );
        unset($this->comments);
        return $commentId;
    }

    public function resolve(User $user): int {
        // can't use setField() because there is no elegant way to say `resolved = now()`
        $affected = $this->pg()->prepared_query("
            UPDATE report_auto SET
                resolved = now(),
                id_owner = ?
            WHERE id_report_auto = ?
            ", $user->id(), $this->id
        );
        $this->flush();
        return $affected;
    }

    public function unresolve(User $user): bool {
        return $this
            ->setField('id_owner', $user->id())
            ->setField('resolved', null)
            ->modify();
    }
}
