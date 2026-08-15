<?php

declare(strict_types=1);

namespace Gazelle;

class ReportAuto extends BasePgObject {
    final public const tableName = 'report_auto';
    final public const pkName = 'id_report_auto';
    protected array $comments;

    public function __construct(
        public readonly int $id,
        protected $typeMan = new Manager\ReportAutoType(),
    ) {}

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return sprintf('<a href="%s">Auto Report #%d</a>', $this->url(), $this->id);
    }

    public function location(): string {
        return "report_auto.php?id={$this->id}#report{$this->id}";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info = $this->pg()->rowAssocex("
            select
                id_user,
                id_report_auto_type,
                created,
                resolved,
                id_owner,
                data
            from report_auto
            where id_report_auto = $1
            ", $this->id
        );
        return $this->info;
    }

    public function comments(): array {
        if (isset($this->comments)) {
            return $this->comments;
        }
        $this->comments = $this->pg()->allex("
            select
                id_user,
                created,
                comment
            from report_auto_comment
            where id_report_auto = $1
            order by created
            ", $this->id
        );
        return $this->comments;
    }

    /**
     * returns an ISO timestring
     */
    public function created(): \DateTimeImmutable {
        return $this->info()['created'];
    }

    public function state(): Enum\ReportAutoState {
        if ($this->resolved() !== null) {
            return Enum\ReportAutoState::closed;
        }
        if ($this->isClaimed()) {
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

    public function resolved(): \DateTimeImmutable|null {
        return $this->info()['resolved'];
    }

    public function hasComments(): bool {
        return (bool)$this->comments();
    }

    public function ownerId(): int|null {
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

    public function data(): array|null {
        return $this->info()['data'];
    }

    /**
     * returns a string with the report details, to be rendered by Twig
     *
     * you will want to override this in a custom class for most report types
     */
    public function details(): string {
        return "<pre>" . json_encode($this->data(), JSON_PRETTY_PRINT) . '</pre>';
    }

    /**
     * Claim a report. (Pass null to unclaim a currently claimed report)
     */
    public function claim(User|null $user): bool {
        return $this
            ->setField('id_owner', $user?->id)
            ->modify();
    }

    public function unclaim(): bool {
        return $this->claim(null);
    }

    /**
     * returns comment id
     */
    public function addComment(User $user, string $comment): int {
        $commentId = $this->pg()->scalarex("
            insert into report_auto_comment
                   (id_report_auto, id_user, comment)
            values ($1,             $2,      $3)
            returning id_report_auto_comment
            ", $this->id, $user->id, $comment
        );
        unset($this->comments);
        return $commentId;
    }

    public function resolve(User $user): int {
        // can't use setField() because there is no elegant way to say `resolved = now()`
        $affected = $this->pg()->query("
            update report_auto set
                resolved = now(),
                id_owner = $1
            where id_report_auto = $2
            ", $user->id, $this->id
        );
        $this->flush();
        return $affected;
    }

    public function unresolve(User $user): bool {
        return $this
            ->setField('id_owner', $user->id)
            ->setField('resolved', null)
            ->modify();
    }
}
