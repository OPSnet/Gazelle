<?php

namespace Gazelle\User;

use Gazelle\Comment\Artist  as ArtistPost;
use Gazelle\Comment\Collage as CollagePost;
use Gazelle\Comment\Request as RequestPost;
use Gazelle\Comment\Torrent as TorrentPost;
use Gazelle\ForumPost;
use Gazelle\User;
use Gazelle\Enum\UserAuditEvent;
use Gazelle\Util\Time;
use sad_spirit\pg_wrapper\types\DateTimeRange;

class Warning extends \Gazelle\BaseUser {
    final public const tableName = 'user_warning';

    protected array $info;

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function add(string $staffReason, string $interval, User $warner): DateTimeRange {
        $end = $this->pg()->scalarex("
            with cte as (
                select max(upper(warning)) as warning_end
                from user_warning
                where id_user = $1 and now() < upper(warning)
            )
            insert into user_warning
                   (id_user, id_user_warner, reason, warning)
            values ($1,      $2,             $3,     tstzrange(
                coalesce((select warning_end from cte), now()),
                coalesce((select warning_end from cte), now()) + $4::interval
            ))
            returning warning
            ", $this->user->id, $warner->id, $staffReason, $interval
        );
        $this->user()->auditTrail()->addEvent(
            UserAuditEvent::warning,
            "Warned for $interval (expiry {$end->upper->format('Y-m-d H:i:s')}) by {$warner->username()}\nReason: $staffReason"
        );
        $this->flush();
        return $end;
    }

    public function info(): array {
        return $this->info ??= $this->pg()->rowAssocex("
            select warning
            from user_warning
            where now() < upper(warning)
                and id_user = $1
            ", $this->user->id
        );
    }

    public function isWarned(): bool {
        return $this->info() !== [];
    }

    public function expiry(): \DateTimeImmutable|null {
        return $this->isWarned() ? $this->info()['warning']->upper : null;
    }

    public function total(): int {
        return count($this->warningList());
    }

    public function warningList(): array {
        return $this->pg()->allex("
            select id_user_warner as id_warner,
                lower(warning)    as begin,
                upper(warning)    as end,
                now() <@ warning  as active,
                reason
            from user_warning
            where id_user = $1
            order by id_user_warning
            ", $this->user->id
        );
    }

    /**
     * Warn a user. Returns the date range of the warning (upper, lower)
     */
    public function warn(int $week, string $staffReason, User $admin, string $userMessage): DateTimeRange {
        $prior  = $this->isWarned() ? $this->expiry()->format('Y-m-d H:i') : false;
        $warning = $this->add($staffReason, "$week week" . plural($week), $admin);
        if ($prior !== false) {
            $subject = 'You have received a new warning';
            $message = "You have received a new warning by [user]{$admin->username()}[/user]. You had an existing warning (set to expire at $prior).\n\nDue to this prior warning, you will remain warned until {$warning->upper->format('Y-m-d H:i')}.\nReason: $userMessage";
        } else {
            $subject = 'You have been warned';
            $message = "You have been warned by [user]{$admin->username()}[/user]. The warning is set to expire on {$warning->upper->format('Y-m-d H:i')}. Remember, repeated warnings may jeopardize your account.\nReason: $userMessage";
        }
        $this->user()->inbox()->createSystem($subject, $message);
        return $warning;
    }

    /**
     * Issue a warning for a comment or forum post
     */
    public function warnPost(
        ArtistPost|CollagePost|RequestPost|TorrentPost|ForumPost $post,
        int $week,
        User $admin,
        string $staffReason,
        string $userMessage,
    ): void {
        if (!$week) {  // verbal warning
            $warned  = "Verbally warned";
            $this->user()->inbox()->createSystem(
                "You have received a verbal warning",
                "You have received a verbal warning by [user]{$admin->username()}[/user] for {$post->publicLocation()}.\n\n[quote]{$userMessage}[/quote]"
            );
        } else {
            $warned = "warned for {$post->publicLocation()}";
            $this->warn(
                $week,
                $warned,
                $admin,
                "You have been warned by [user]{$admin->username()}[/user] for {$post->publicLocation()}",
            );
        }
        $this->user()->addForumWarning("$warned by {$admin->username()} for {$post->publicLocation()}\nReason: $staffReason")
            ->modify();
    }

    /**
     * Remove any current warnings
     */
    public function clear(): int {
        $affected = $this->pg()->query("
            update user_warning set
                warning = NULL
            where upper(warning) > now()
                and id_user = $1
            ", $this->user->id
        );
        $this->flush();
        return $affected;
    }
}
