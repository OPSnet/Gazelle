<?php

namespace Gazelle;

use Gazelle\Exception\UserCreatorException;

class UserCreator extends Base {
    protected $newInstall;

    protected $adminComment;
    protected $email;
    protected $enabled;
    protected $id;
    protected $inviteKey;
    protected $ipaddr;
    protected $passHash;
    protected $permissionId;
    protected $announceKey;
    protected $userName;

    public function __construct() {
        parent::__construct();

        $this->adminComment = [];
        $this->email = [];
    }

    public function create() {
        $this->newInstall = !$this->db->scalar("SELECT ID FROM users_main LIMIT 1");
        if ($this->newInstall) {
            $this->enabled = true;
            $this->permissionId = SYSOP;
        } else {
            $this->enabled = false;
            $this->permissionId = USER;
        }
        if (!$this->ipaddr) {
            throw new UserCreatorException('ipaddr');
        }
        if (!$this->passHash) {
            throw new UserCreatorException('password');
        }
        if (!$this->userName) {
            throw new UserCreatorException('username');
        }
        if ($this->db->scalar("SELECT 1 FROM users_main WHERE Username = ?", $this->userName)) {
            throw new UserCreatorException('duplicate');
        }
        if (!preg_match(USERNAME_REGEXP, $this->userName)) {
            throw new UserCreatorException('username-invalid');
        }

        $this->announceKey = randomString();
        $infoFields = ['AuthKey'];
        $infoArgs = [randomString()];

        if (!$this->inviteKey) {
            $inviterId = null;
        } else {
            [$inviterId, $inviterReason, $email] = $this->db->row("
                SELECT InviterID, Reason, Email
                FROM invites
                WHERE InviteKey = ?
                ", $this->inviteKey
            );
            if (is_null($inviterId)) {
                throw new UserCreatorException('invitation');
            }
            if ($this->email && strtolower($email) != strtolower($this->email[0])) {
                // The invitation was sent to one email address, and the user
                // supplied a different one during registration: consider both
                // as belonging to them.
                $this->email[] = $email;
            }
            $infoFields[] = 'Inviter';
            $infoArgs[] = $inviterId;
            if ($inviterReason) {
                $this->adminComment[] = $inviterReason;
            }
            $this->adminComment[] = "invite key = " . $this->inviteKey;
        }
        if (!$this->email) {
            // neither setEmail() nor setInviteKey() produced anything useful
            throw new UserCreatorException('email');
        }

        // create users_main row
        $mainFields = ['Username', 'Email', 'PassHash', 'torrent_pass', 'IP',
            'PermissionID', 'Enabled', 'Invites', 'ipcc'
        ];
        $mainArgs = [
            $this->userName, current($this->email), $this->passHash, $this->announceKey, $this->ipaddr,
            $this->permissionId, $this->enabled ? '1' : '0', STARTING_INVITES, \Tools::geoip($this->ipaddr)
        ];

        if ($this->id) {
            $mainFields[] = 'ID';
            $mainArgs[] = $this->id;
        }

        $this->db->begin_transaction();

        $this->db->prepared_query("
            INSERT INTO users_main
                   (" . implode(',', $mainFields) . ")
            VALUES (" . placeholders($mainFields) . ")
            ", ...$mainArgs
        );
        if (!$this->id) {
            $this->id = $this->db->inserted_id();
        }

        // create users_info row
        $infoFields[] = 'UserID';
        $infoArgs[] = $this->id;
        if ($this->adminComment) {
            $infoFields[] = 'AdminComment';
            $infoArgs[] = sqltime() . " - " . implode("\n", $this->adminComment);
        }
        $this->db->prepared_query("
            INSERT INTO users_info
                   (" . implode(',', $infoFields) . ", StyleID)
            VALUES (" . placeholders($infoFields) . ", (SELECT s.ID FROM stylesheets s WHERE s.Default = '1' LIMIT 1))
            ", ...$infoArgs
        );

        if ($inviterId) {
            $this->db->prepared_query("
                DELETE FROM invites WHERE InviteKey = ?
                ", $this->inviteKey
            );
            $inviteTree = new InviteTree($inviterId);
            $inviteTree->add($this->id);
        }

        $this->db->prepared_query("
            UPDATE referral_users SET
                UserID = ?,
                Active = 1,
                Joined = now(),
                InviteKey = ''
            WHERE InviteKey = ?
            ", $this->id, $this->inviteKey
        );

        // Log the one or two email addresses known to be associated with the user.
        // Each additional previous email address is staggered one second back in the past.
        $past = count($this->email);
        foreach ($this->email as $e) {
            $this->db->prepared_query('
                INSERT INTO users_history_emails
                       (UserID, Email, IP, Time)
                VALUES (?,      ?,     ?,  now() - INTERVAL ? SECOND)
                ', $this->id, $e, $this->ipaddr, $past--
            );
        }

        // Create the remaining rows in auxilliary tables
        $this->db->prepared_query("
            INSERT INTO user_bonus (user_id) VALUES (?)
            ", $this->id
        );

        $this->db->prepared_query("
            INSERT INTO user_flt (user_id) VALUES (?)
            ", $this->id
        );

        $this->db->prepared_query("
            INSERT INTO users_history_ips (UserID, IP) VALUES (?, ?)
            ", $this->id, $this->ipaddr
        );

        $this->db->prepared_query("
            INSERT INTO users_leech_stats (UserID, Uploaded) VALUES (?, ?)
            ", $this->id, STARTING_UPLOAD
        );

        $this->db->prepared_query("
            INSERT INTO users_notifications_settings (UserID) VALUES (?)
            ", $this->id
        );

        $this->db->commit();

        $this->cache->increment('stats_user_count');
        (new \Gazelle\Tracker)->update_tracker('add_user', [
            'id'      => $this->id,
            'passkey' => $this->announceKey
        ]);

        $id = $this->id;
        $this->reset(); // So we can create another user
        return new User($id);
    }

    /**
     * Reset the internal state so that a new user may be created.
     * Calling create() calls this as a side effect.
     */
    public function reset() {
        $this->newInstall   = false;
        $this->adminComment = [];
        $this->email        = [];
        $this->enabled      = false;
        $this->id           = null;
        $this->announceKey  = null;
        $this->inviteKey    = null;
        $this->ipaddr       = null;
        $this->passHash     = null;
        $this->permissionId = null;
        $this->userName     = null;
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
     * param string $adminComment
     */
    public function setAdminComment(string $adminComment) {
        $this->adminComment[] = trim($adminComment);
        return $this;
    }

    /**
     * Set the email address. Does not have to be valid (for staff).
     * If an invitation was used, this method does not need to be called:
     * the email will be taken from the invitation. (Corollary: if an
     * invitation was used, calling this method afterwards will override
     * the invitation email).
     *
     * @param string email
     */
    public function setEmail(string $email) {
        $this->email[] = trim($email);
        return $this;
    }

    /**
     * Set the user id. Only needed when you want to specify the id
     * of a user. Should not be higher than the current auto-increment
     * value, otherwise regular creation will wind up stumbling over
     * it and causing a duplicate key error.
     *
     * @param int id of the user
     */
    public function setId(int $id) {
        $this->id = $id;
        return $this;
    }

    /**
     * Set the invite key (only required if this is a creation via an invitation)
     * @param string inviteKey
     */
    public function setInviteKey(string $inviteKey) {
        $this->inviteKey = trim($inviteKey);
        return $this;
    }

    /**
     * Set the user IPv4 address.
     * @param string ipaddr
     */
    public function setIpaddr(string $ipaddr) {
        $this->ipaddr = trim($ipaddr);
        return $this;
    }

    /**
     * Set the password. Will be hashed before being stored.
     * @param string password
     */
    public function setPassword(string $password) {
        $this->passHash = self::hashPassword($password);
        return $this;
    }

    /**
     * Set the user name.
     * @param string password
     */
    public function setUserName(string $userName) {
        $this->userName = trim($userName);
        return $this;
    }

    /**
     * Create salted crypt hash for a given string with
     * settings specified in CRYPT_HASH_PREFIX
     *
     * @param string  $plaintext string to hash
     * @return string hashed password
     */
    static public function hashPassword(string $plaintext): string {
        return password_hash(hash('sha256', $plaintext), PASSWORD_DEFAULT);
    }
}
