<?php

namespace Gazelle;

class Login extends Base {
    use Pg;

    final public const NO_ERROR = 0;
    final public const ERR_CREDENTIALS = 1;
    final public const ERR_UNCONFIRMED = 2;
    final public const FLOOD_COUNT = 'login_flood_total_%d';

    protected int $error = self::NO_ERROR;
    protected bool $persistent = false;
    protected int $userId = 0;
    protected string $password;
    protected string $twofa;
    protected string $username;
    protected LoginWatch $watch;

    public function error(): int {
        return $this->error;
    }

    public function persistent(): bool {
        return $this->persistent;
    }

    public function username(): ?string {
        return $this->username;
    }

    /**
     * Login into the system.
     * Sleep to ensure the call always lasts a certain duration to avoid timing attacks.
     * If successful, clear any login rate-limiting on the associated IP address.
     * If unsuccessul, record a failure and if there are too many failures, ban.
     *
     * @return \Gazelle\User|null on failure, reason will be available from error()
     */
    public function login(
        string $username,
        string $password,
        LoginWatch $watch,
        bool $persistent = false,
        string $twofa    = '',
    ): ?User {
        $this->username   = trim($username);
        $this->password   = $password;
        $this->watch      = $watch;
        $this->persistent = $persistent;
        $this->twofa      = trim($twofa);

        $begin  = microtime(true);
        $user   = $this->attemptLogin();
        $ipaddr = $this->requestContext()->remoteAddr();
        if ($user) {
            $this->watch->clearAttempts();
            $user->toggleAttr('inactive-warning-sent', false);
            self::$cache->delete_value(sprintf(self::FLOOD_COUNT, $user->id()));
        } else {
            // we might not have an authenticated user, but still have the id of the username
            $this->watch->increment($this->userId, $this->username);
            if ($this->watch->nrAttempts() > 10) {
                $this->watch->ban($this->username);

                if ($this->userId) {
                    $key = 'login_flood_' . $this->userId;
                    if (self::$cache->get_value($key) === false) {
                        self::$cache->cache_value($key, true, 86400);
                        // fake a user object temporarily to send them some email
                        (new User($this->userId))->inbox()->createSystem(
                            "Too many login attempts on your account",
                            self::$twig->render('login/too-many-failures.bbcode.twig', [
                                'ipaddr'   => $ipaddr,
                                'username' => $this->username,
                            ])
                        );
                    }
                    $key = sprintf(self::FLOOD_COUNT, $this->userId);
                    if (self::$cache->get_value($key) === false) {
                        self::$cache->cache_value($key, 0, 86400 * 7);
                    }
                    self::$cache->increment($key);
                }
            } elseif ($this->watch->nrBans() > 3) {
                (new Manager\IPv4())->createBan(
                    null, $ipaddr, $ipaddr, 'Automated ban, too many failed login attempts'
                );
            }
        }
        usleep((int)(600000 - (microtime(true) - $begin)));
        return $user;
    }

    /**
     * Attempt to log into the system.
     * Need a viable user/password and eventual 2FA code or recovery key.
     *
     * @return \Gazelle\User|null a User object if the credentials are successfully authenticated
     */
    protected function attemptLogin(): ?User {
        // we have all we need to go forward
        $userMan = new Manager\User();
        if (!preg_match(USERNAME_REGEXP, $this->username)) {
            $this->error = self::ERR_CREDENTIALS;
            return null;
        }
        $user = $userMan->findByUsername($this->username);
        if (is_null($user)) {
            $this->error = self::ERR_CREDENTIALS;
            return null;
        }
        $this->userId = $user->id();
        if (!$user->validatePassword($this->password)) {
            $this->error = self::ERR_CREDENTIALS;
            return null;
        }

        // password checks out, if they have 2FA, does that check out?
        $TFAKey = $user->TFAKey();
        if ($TFAKey && !$this->twofa || !$TFAKey && $this->twofa) {
            $this->error = self::ERR_CREDENTIALS;
            return null;
        }
        if ($TFAKey) {
            $tfa = new \RobThree\Auth\TwoFactorAuth();
            if (!$tfa->verifyCode($TFAKey, $this->twofa, 2)) {
                // They have 2FA but the device key did not match
                // Fallback to considering it as a recovery key.
                $userToken = (new Manager\UserToken())->findByToken($this->twofa);
                if ($userToken) {
                    $userToken->consume();
                }
                if (!$user->burn2FARecovery($this->twofa)) {
                    $this->error = self::ERR_CREDENTIALS;
                    return null;
                }
            }
        }

        if ($user->isUnconfirmed()) {
            $this->error = self::ERR_UNCONFIRMED;
            return null;
        }

        // Did they come in over Tor?
        $ipaddr = $this->requestContext()->remoteAddr();
        if (BLOCK_TOR && !$user->permitted('can_use_tor') && (new Manager\Tor())->isExitNode($ipaddr)) {
            $userMan->disableUserList(
                new Tracker(),
                [$user->id()],
                "Logged in via Tor ($ipaddr)",
                Manager\User::DISABLE_TOR
            );
            // return a newly disabled instance
            $this->pg()->prepared_query("
                insert into ip_history
                       (id_user, ip, data_origin)
                values (?,       ?,  'login-fail')
                on conflict (id_user, ip, data_origin) do update set
                    total = ip_history.total + 1,
                    seen = tstzrange(lower(ip_history.seen), now(), '[]')
                ", $user->id(), $ipaddr
            );
            return $userMan->findById($user->id());
        }

        (new User\History($user))->registerSiteIp($ipaddr);

        // We have a user!
        return $user;
    }
}
