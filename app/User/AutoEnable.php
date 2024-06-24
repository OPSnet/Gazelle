<?php

namespace Gazelle\User;

use Gazelle\Util\Mail;
use Gazelle\Util\Time;

class AutoEnable extends \Gazelle\BaseUser {
    final public const tableName        = 'users_enable_requests';
    final protected const CACHE_TOTAL_OPEN = 'num_enable_requests';

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    /**
     * Note: When calling this directly, it is your responsibility
     * to enure that the Gazelle\User object corresponds to the
     * users_enable_requests.UserID column. Instantiating objects
     * via the Gazelle\Manager\AutoEnable::find*() methods will take
     * care of this for you.
     */
    public function __construct(protected int $id, \Gazelle\User $user) {
        parent::__construct($user);
    }

    public function id(): int {
        return $this->id;
    }

    public function info(): array {
        if (!isset($this->info)) {
            $this->info = self::$db->rowAssoc("
                SELECT uer.UserID        AS user_id,
                    uer.Email            AS email,
                    uer.IP               AS ipv4,
                    uer.UserAgent        AS useragent,
                    uer.Timestamp        AS created,
                    uer.HandledTimestamp AS handled,
                    uer.Token            AS token,
                    uer.CheckedBy        AS admin_user_id,
                    uer.Outcome          AS outcome,
                    ui.BanReason         AS ban_reason
                FROM users_enable_requests uer
                LEFT JOIN users_info ui USING (UserID)
                WHERE uer.ID = ?
                ", $this->id
            );
        }
        return $this->info;
    }

    public function adminUserId(): ?int {
        return $this->info()['admin_user_id'];
    }

    public function banReason(): int {
        return (int)$this->info()['ban_reason'];
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function email(): string {
        return $this->info()['email'];
    }

    public function createdAfter(string $interval): bool {
        return strtotime($this->created()) >= strtotime($interval);
    }

    public function createdBefore(string $interval): bool {
        return strtotime($this->created()) < strtotime($interval);
    }

    public function handled(): ?string {
        return $this->info()['handled'];
    }

    public function ipv4(): string {
        return $this->info()['ipv4'];
    }

    public function isPending(): bool {
        return is_null($this->info()['outcome']);
    }

    public function isApproved(): bool {
        return $this->info()['outcome'] === \Gazelle\Manager\AutoEnable::APPROVED;
    }

    public function isDenied(): bool {
        return $this->info()['outcome'] === \Gazelle\Manager\AutoEnable::DENIED;
    }

    public function isDiscarded(): bool {
        return $this->info()['outcome'] === \Gazelle\Manager\AutoEnable::DISCARDED;
    }

    public function isRejected(): bool {
        return $this->isDenied() || $this->isDiscarded();
    }

    public function outcome(): int {
        return $this->info()['outcome'] ?? \Gazelle\Manager\AutoEnable::PENDING;
    }

    public function outcomeLabel(): string {
        return match ($this->outcome()) {
            \Gazelle\Manager\AutoEnable::APPROVED  => "Approved",
            \Gazelle\Manager\AutoEnable::DENIED    => "Rejected",
            \Gazelle\Manager\AutoEnable::DISCARDED => "Discarded",
            \Gazelle\Manager\AutoEnable::PENDING   => "Pending",
            default                                => "Unknown",
        };
    }

    public function token(): string {
        return $this->info()['token'];
    }

    public function useragent(): string {
        return $this->info()['useragent'];
    }

    /**
     * Resolve an request to reenable (either accept, which continues the workflow, or
     * discard/deny, which stops the process immediately.
     * Deny sends an email, discard does not.
     */
    public function resolve(\Gazelle\User $viewer, int $status, string $comment): int {
        switch ($status) {
            case \Gazelle\Manager\AutoEnable::APPROVED:
                $subject  = "Your enable request for " . SITE_NAME . " has been approved";
                $template = 'enable/email-accepted.twig';
                $token = randomString();
                break;
            case \Gazelle\Manager\AutoEnable::DENIED:
                $subject  = "Your enable request for " . SITE_NAME . " has been denied";
                $template = 'enable/email-denied.twig';
                $token = null;
                break;
            default:
                $subject = null;
                $template = null;
                $token = null;
                break;
        }
        if ($template) {
            if (!is_null($token)) {
                self::$db->prepared_query("
                    UPDATE users_enable_requests SET
                        HandledTimestamp = now(),
                        Token = ?
                    WHERE ID = ?
                    ", $token, $this->id
                );
            }
            (new Mail())->send($this->email(), $subject, self::$twig->render($template, ['token' => $token]));
            $this->user->addStaffNote(
                "Enable request {$this->id} " . strtolower($this->outcomeLabel())
                    . ' by [user]' . $viewer->username() . '[/user]' . (!empty($comment) ? "\nReason: $comment" : "")
            )->modify();
        }

        self::$db->prepared_query("
            UPDATE users_enable_requests SET
                HandledTimestamp = now(),
                CheckedBy = ?,
                Outcome = ?
            WHERE ID = ?
            ", $viewer->id(), $status, $this->id
        );
        self::$cache->delete_value(self::CACHE_TOTAL_OPEN);
        return self::$db->affected_rows();
    }

    /**
     * Handle a user's request to enable an account
     *
     * return @bool user was re-enabled
     */
    public function processToken(): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE users_enable_requests SET Token = NULL WHERE Token = ?
            ", $this->token()
        );
        if ($this->created() < Time::offset(-3600 * 48)) {
            $this->user
                ->addStaffNote("Tried to use an expired enable token from {$this->requestContext()->remoteAddr()}")
                ->modify();
            $success = false;
        } else {
            $userId = $this->user->id();
            self::$db->prepared_query("
                UPDATE users_main um
                INNER JOIN users_info ui ON (ui.UserID = um.ID)
                SET
                    um.Enabled = '1',
                    um.can_leech = 1,
                    ui.BanReason = '0'
                WHERE um.ID = ?
                ", $userId
            );
            (new \Gazelle\Tracker())->addUser($this->user);
            self::$cache->delete_value(self::CACHE_TOTAL_OPEN);
            $success = true;
        }
        self::$db->commit();
        $this->user->flush();
        return $success;
    }

    /**
     * Unresolve a discarded request
     */
    public function unresolve(\Gazelle\User $viewer): int {
        $this->user->addStaffNote("Enable request {$this->id} unresolved by [user]" . $viewer->username() . '[/user]')->modify();
        self::$db->prepared_query("
            UPDATE users_enable_requests SET
                Outcome = NULL,
                HandledTimestamp = NULL,
                CheckedBy = NUL
            WHERE ID = ?
            ", $this->id
        );
        self::$cache->delete_value(self::CACHE_TOTAL_OPEN);
        return self::$db->affected_rows();
    }
}
