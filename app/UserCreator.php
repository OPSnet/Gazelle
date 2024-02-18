<?php

namespace Gazelle;

use Gazelle\Enum\UserStatus;

use Gazelle\Exception\UserCreatorException;
use Gazelle\Util\Time;

class UserCreator extends Base {
    protected bool   $newInstall;
    protected array  $adminComment = [];
    protected array  $email = [];
    protected int    $id;
    protected int    $permissionId;
    protected string $announceKey;
    protected string $inviteKey;
    protected string $ipaddr;
    protected string $passHash;
    protected string $username;

    public function create(): User {
        if (!isset($this->ipaddr)) {
            throw new UserCreatorException('ipaddr');
        }
        if (!isset($this->passHash)) {
            throw new UserCreatorException('password');
        }
        if (!isset($this->username)) {
            throw new UserCreatorException('username');
        }
        if (self::$db->scalar("SELECT 1 FROM users_main WHERE Username = ?", $this->username)) {
            throw new UserCreatorException('duplicate');
        }
        if (!preg_match(USERNAME_REGEXP, $this->username, $match)) {
            throw new UserCreatorException('username-invalid');
        }

        $this->newInstall = !(bool)self::$db->scalar("SELECT ID FROM users_main LIMIT 1");
        if ($this->newInstall) {
            $this->permissionId = SYSOP;
            $this->adminComment[] = 'Initial account created on first registration';
        } else {
            $this->permissionId = USER;
        }
        $manager = new Manager\User();
        if (!isset($this->inviteKey)) {
            $inviter = null;
        } else {
            [$inviterId, $inviterNotes, $inviterReason, $email] = self::$db->row("
                SELECT InviterID, Notes, Reason, Email
                FROM invites
                WHERE InviteKey = ?
                ", $this->inviteKey
            );
            $inviter = $manager->findById((int)$inviterId);
            if (is_null($inviter)) {
                throw new UserCreatorException('invitation');
            }
            if ($this->email && strtolower($email) != strtolower($this->email[0])) {
                // The invitation was sent to one email address, and the user
                // supplied a different one during registration: consider both
                // as belonging to them.
                $this->email[] = $email;
            }
            if (!empty($inviterReason)) {
                $this->adminComment[] = $inviterReason;
            }
            $this->adminComment[] = "invite key = " . $this->inviteKey;
            if (!empty($inviterNotes)) {
                $this->adminComment[] = $inviterNotes;
            }
        }

        if (!$this->email) {
            // neither setEmail() nor setInviteKey() produced anything useful
            throw new UserCreatorException('email');
        }
        $domainManager = new \Gazelle\Manager\EmailBlacklist();
        foreach ($this->email as $email) {
            if ($domainManager->exists($email)) {
                throw new UserCreatorException('email');
            }
        }

        // create users_main row
        $this->announceKey = randomString();
        $mainFields = ['inviter_user_id', 'Username', 'Email', 'PassHash', 'torrent_pass', 'IP',
            'PermissionID', 'Enabled', 'Invites', 'ipcc', 'auth_key'
        ];
        $mainArgs = [
            (int)$inviter?->id(), $this->username, current($this->email), $this->passHash, $this->announceKey, $this->ipaddr,
            $this->permissionId, $this->permissionId == SYSOP ? UserStatus::enabled->value : UserStatus::unconfirmed->value, STARTING_INVITES, geoip($this->ipaddr), authKey()
        ];

        if (isset($this->id)) {
            $mainFields[] = 'ID';
            $mainArgs[] = $this->id;
        }

        self::$db->begin_transaction();

        self::$db->prepared_query("
            INSERT INTO users_main
                   (" . implode(',', $mainFields) . ", stylesheet_id)
            VALUES (" . placeholders($mainFields) . ", (SELECT s.ID FROM stylesheets s WHERE s.Default = '1' LIMIT 1))
            ", ...$mainArgs
        );
        if (!isset($this->id)) {
            $this->id = self::$db->inserted_id();
        }
        $user = new User($this->id);

        // create users_info row
        self::$db->prepared_query("
            INSERT INTO users_info
                   (UserID, AdminComment)
            VALUES (?,      ?)
            ",  $this->id, Time::sqlTime() . " - " . implode("\n", $this->adminComment)
        );

        if ($inviter) {
            (new Manager\InviteSource())->resolveInviteSource($this->inviteKey, $user);
            (new User\InviteTree($inviter, $manager))->add($user);
            $inviter->stats()->increment('invited_total');
            $user->externalProfile()->modifyProfile($inviterReason);
            self::$db->prepared_query("
                DELETE FROM invites WHERE InviteKey = ?
                ", $this->inviteKey
            );
        }

        if (isset($this->inviteKey)) {
            self::$db->prepared_query("
                UPDATE referral_users SET
                    UserID = ?,
                    Active = 1,
                    Joined = now(),
                    InviteKey = ''
                WHERE InviteKey = ?
                ", $this->id, $this->inviteKey
            );
        }

        // Log the one or two email addresses known to be associated with the user.
        // Each additional previous email address is staggered one second back in the past.
        $past = count($this->email);
        foreach ($this->email as $e) {
            self::$db->prepared_query('
                INSERT INTO users_history_emails
                       (UserID, Email, IP, useragent, created)
                VALUES (?,      ?,     ?,  ?,         now() - INTERVAL ? SECOND)
                ', $this->id, $e, $this->ipaddr, $_SERVER['HTTP_USER_AGENT'], $past--
            );
        }

        // Create the remaining rows in auxilliary tables
        self::$db->prepared_query("
            INSERT INTO user_bonus (user_id) VALUES (?)
            ", $this->id
        );
        self::$db->prepared_query("
            INSERT INTO user_flt (user_id) VALUES (?)
            ", $this->id
        );
        self::$db->prepared_query("
            INSERT INTO user_summary (user_id) VALUES (?)
            ", $this->id
        );
        self::$db->prepared_query("
            INSERT INTO users_history_ips (UserID, IP) VALUES (?, ?)
            ", $this->id, $this->ipaddr
        );
        self::$db->prepared_query("
            INSERT INTO users_leech_stats (UserID, Uploaded) VALUES (?, ?)
            ", $this->id, STARTING_UPLOAD
        );
        self::$db->prepared_query("
            INSERT INTO users_notifications_settings (UserID) VALUES (?)
            ", $this->id
        );
        self::$db->commit();

        (new Tracker())->addUser($user);

        $this->reset(); // So we can create another user
        return $user;
    }

    /**
     * Reset the internal state so that a new user may be created.
     * Calling create() calls this as a side effect.
     */
    public function reset(): void {
        $this->newInstall   = false;
        $this->adminComment = [];
        $this->email        = [];
        unset($this->id);
        unset($this->announceKey);
        unset($this->inviteKey);
        unset($this->ipaddr);
        unset($this->passHash);
        unset($this->permissionId);
        unset($this->username);
    }

    /**
     * Is this the first user created?
     *
     * @return bool True if this is the first account (hence, enabled Sysop)
     */
    public function newInstall(): bool {
        return $this->newInstall;
    }

    /**
     * Return the email address to which a registration email
     * should be sent. An invite may have been sent to one
     * address, but the user specified a new address, so prefer
     * that one.
     *
     * @return string The email address to use.
     */
    public function email(): string {
        return end($this->email);
    }

    /**
     * Set the initial admin comment. Not mandatory for creation
     */
    public function setAdminComment(string $adminComment): static {
        $this->adminComment[] = trim($adminComment);
        return $this;
    }

    /**
     * Set the email address. Does not have to be valid (for staff).
     * If an invitation was used, this method does not need to be called:
     * the email will be taken from the invitation. (Corollary: if an
     * invitation was used, calling this method afterwards will override
     * the invitation email).
     */
    public function setEmail(string $email): static {
        $this->email[] = trim($email);
        return $this;
    }

    /**
     * Set the user id. Only needed when you want to specify the id
     * of a user. Should not be higher than the current auto-increment
     * value, otherwise regular creation will wind up stumbling over
     * it and causing a duplicate key error.
     *
     * @param int $id of the user
     */
    public function setId(int $id): static {
        $this->id = $id;
        return $this;
    }

    /**
     * Set the invite key (only required if this is a creation via an invitation)
     */
    public function setInviteKey(string $inviteKey): static {
        $this->inviteKey = trim($inviteKey);
        return $this;
    }

    /**
     * Set the user IPv4 address.
     */
    public function setIpaddr(string $ipaddr): static {
        $this->ipaddr = trim($ipaddr);
        return $this;
    }

    /**
     * Set the password. Will be hashed before being stored.
     */
    public function setPassword(#[\SensitiveParameter] string $password): static {
        $this->passHash = self::hashPassword($password);
        return $this;
    }

    /**
     * Set the username.
     */
    public function setUsername(string $username): static {
        if (preg_match('/^' . str_replace('/', '', USERNAME_REGEXP) . '$/', trim($username), $match)) {
            if (!empty($match['username'])) {
                $this->username = $match['username'];
            }
        }
        return $this;
    }

    /**
     * Create a password hash of a plaintext password.
     */
    public static function hashPassword(#[\SensitiveParameter] string $plaintext): string {
        return password_hash(hash('sha256', $plaintext), PASSWORD_DEFAULT);
    }
}
