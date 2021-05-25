<?php

namespace Gazelle;

class Login extends Base {

    public const NO_ERROR = 0;
    public const ERR_USERNAME    = 1;
    public const ERR_PASSWORD    = 2;
    public const ERR_CREDENTIALS = 3;
    public const ERR_UNCONFIRMED = 4;

    protected $error = self::NO_ERROR;
    protected $ipaddr;
    protected $password;
    protected $persistent = 0;
    protected $twofa;
    protected $userId = 0;
    protected $username;
    protected $watch;

    public function __construct() {
        $this->ipaddr = $_SERVER['REMOTE_ADDR'];
    }

    public function setPassword($password) {
        $this->password = $password;
        return $this;
    }

    public function setPersistent($persistent) {
        $this->persistent = $persistent ? 1 : 0;
        return $this;
    }

    public function setWatch(LoginWatch $watch) {
        $this->watch = $watch;
        return $this;
    }

    public function set2FA($twofa) {
        $this->twofa = trim($twofa);
        return $this;
    }

    public function setUsername($username) {
        $this->username = trim($username);
        return $this;
    }

    public function error() {
        return $this->error;
    }

    public function ipaddr(): ?string {
        return $this->ipaddr;
    }

    public function persistent(): ?string {
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
     * @return true on success, false on failure and reason will be available from error()
     */
    public function login(): ?User {
        $begin = microtime(true);
        $user = $this->attemptLogin();
        if ($user) {
            $this->watch->clearAttempts();
        } else {
            // we might not have an authenticated user, but still have the id of the username
            $this->watch->increment($this->userId, $this->username);
            if ($this->watch->nrBans() > 9) {
                (new Manager\IPv4)->createBan(
                    $this->userId, $this->ipaddr, $this->ipaddr, 'Automated ban, too many failed login attempts'
                );
                global $Twig;
                (new Manager\User)->sendPM($this->userId, 0, "Too many login attempts on your account",
                    $Twig->render('login/too-many-failures.twig', [
                    'ipaddr' => $this->ipaddr,
                    'username' => $this->username,
                ]));
            }
        }
        usleep(600000 - (microtime(true) - $begin));
        return $user;
    }

    /**
     * Attempt to log into the system.
     * Need a viable user/password and eventual 2FA code or recovery key.
     *
     * @return a User object if the credentials are successfully authenticated
     */
    protected function attemptLogin(): ?User {
        if (is_null($this->username)) {
            $this->error = self::ERR_USERNAME;
            return null;
        } elseif (is_null($this->password)) {
            $this->error = self::ERR_PASSWORD;
            return null;
        }
        $validator = new Util\Validator;
        $validator->setFields([
            ['username', true, 'regex', self::ERR_USERNAME, ['regex' => USERNAME_REGEXP]],
            ['password', '1', 'string', self::ERR_PASSWORD, ['minlength' => 6]],
        ]);
        if (!$validator->validate([
            'password' => $this->password,
            'username' => $this->username,
        ])) {
            $this->error = $validator->errorMessage();
            return null;
        }

        // we have all we need to go forward
        $userMan = new Manager\User;
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

        // We have a user!
        return $user;
    }
}
