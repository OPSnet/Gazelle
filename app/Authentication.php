<?php

namespace Gazelle;

use Gazelle\Enum\AuthnMechanism;
use Gazelle\Exception\AuthnError;

class Authentication extends Base {
    protected AuthnMechanism $mechanism = AuthnMechanism::none;
    protected ?string $data = null;

    public function __construct(
        protected $banMan  = new Manager\Ban(),
        protected $userMan = new Manager\User(),
    ) {}

    public function data(): ?string {
        return $this->data;
    }

    public function mechanism(): AuthnMechanism {
        return $this->mechanism;
    }

    /**
     * Same as tryAuthenticate() but will not return if authentications fails,
     * unless access is to a public endpoint.
     *
     * @return User|null|never
     */
    public function authenticate(): ?User {
        try {
            return $this->tryAuthenticate();
        } catch (AuthnError $e) {
            if ($e->mechanism === AuthnMechanism::apikey) {
                header('Content-type: application/json');
                json_die('failure', $e->getMessage());
            }
            if ($e->mechanism === AuthnMechanism::session) {
                SessionCookie::expire();
                header('Location: login.php');
                exit;
            }
            if ($e->mechanism === AuthnMechanism::passkey) {
                http_response_code(403);
                exit;
            }
            if ($msg = $e->getMessage()) {
                Error403::error($msg);
            }
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Try to authenticate user by api key/session cookie/passkey. Throws error on invalid
     * authentication attempts or unauthenticated access attempts to non-public endpoints.
     * Returns null on permissible, unauthenticated access to public endpoints.
     *
     * @return User|null
     * @throws AuthnError
     */
    public function tryAuthenticate(): ?User {
        $ctx    = $this->requestContext();
        $module = $ctx->module();
        $user   = null;
        $isApi  = $module === 'ajax';

        if (!empty($_SERVER['HTTP_AUTHORIZATION']) && $isApi) {
            $user = $this->findByAuthorization($_SERVER['HTTP_AUTHORIZATION']);  // throws
            $this->mechanism = AuthnMechanism::apikey;
        } elseif (isset($_COOKIE['session'])) {
            $cookie = new SessionCookie($_COOKIE['session']);
            $user = $this->findByCookie($cookie);  // throws
            $this->mechanism = AuthnMechanism::session;
        } elseif ($module === 'torrents' && ($_REQUEST['action'] ?? '') == 'download' && isset($_REQUEST['torrent_pass'])) {
            $user = $this->userMan->findByAnnounceKey($_REQUEST['torrent_pass']);
            if (is_null($user) || !$user->isEnabled() || $user->isLocked()) {
                throw new AuthnError('invalid passkey', AuthnMechanism::passkey);
            }
            $this->mechanism = AuthnMechanism::passkey;
            $this->data = $_REQUEST['torrent_pass'];
        } elseif (!in_array($module, ['chat', 'enable', 'index', 'login', 'recovery', 'register'])) {
            if (
                // Ocelot is allowed
                !($module === 'tools' && ($_GET['action'] ?? '') === 'ocelot' && ($_GET['key'] ?? '') === TRACKER_SECRET)
            ) {
                // but for everything else, we need a $Viewer
                throw new AuthnError('', AuthnMechanism::none);
            }
            $this->data = 'ocelot';
        }

        if ($user) {
            $ctx->setViewer($user);
            // To proxify images (or not), or e.g. not render the name of a thread
            // for a user who may lack the privileges to see it in the first place.
            \Text::setViewer($user);
            Util\Twig::setViewer($user);
        }

        if ($this->mechanism !== AuthnMechanism::session && $this->banMan->isBanned($ctx->remoteAddr())) {
            throw new AuthnError(
                'Your IP address has been banned.',
                $isApi ? AuthnMechanism::apikey : AuthnMechanism::none
            );
        }

        return $user;
    }

    public function updateIp(?User $user): void {
        if (!$user) {
            return;
        }

        $ctx = $this->requestContext();
        if ($user->permitted('site_disable_ip_history')) {
            $ctx->anonymize();
        }

        if ($this->mechanism === AuthnMechanism::session) {
            $user->session()->refresh($ctx->sessionId());
        } else {
            new Manager\IPv4()->registerApi($user, $ctx->remoteAddr());
        }
    }

    /**
     * Find a user based on an Authorization header and validate it.
     *
     * @throws AuthnError on invalid header/token/restricted user
     */
    public function findByAuthorization(string $authorization): User {
        $info = explode(" ", $authorization);
        // this first case is for compatibility with RED
        if (count($info) === 1) {
            $token = $info[0];
        } elseif (count($info) === 2) {
            if ($info[0] !== 'token') {
                throw new AuthnError('invalid authorization type, must be "token"', AuthnMechanism::apikey);
            }
            $token = $info[1];
        } else {
            throw new AuthnError('invalid authorization header', AuthnMechanism::apikey);
        }
        [$userId, $tokenName] = self::$db->row("
            SELECT user_id, name FROM api_tokens WHERE token = ?
            ", $token
        );
        if (
            $userId === null
            || ($user = $this->userMan->findById($userId)) === null
            || !$user->isEnabled()
            || $user->isLocked()
            || !$user->hasApiToken($token)
        ) {
            $username = match (isset($user)) {
                true  => '[t:' . substr($user->username(), 0, 16) . ']',
                false => '[it:' . substr($token, 0, 15) . ']',
            };
            $ipaddr = $this->requestContext()->remoteAddr();
            $watch  = new LoginWatch($ipaddr);
            $watch->increment($userId, $username);
            if ($watch->attemptTotal() >= 5) {
                $watch->block($userId, $username);
                if ($watch->blockTotal() >= 10) {
                    $this->banMan->create($ipaddr, 'Automated ban per failed token usage', $user ?? null);
                }
            }
            throw new AuthnError('invalid token', AuthnMechanism::apikey);
        }
        $this->data = $tokenName;
        return $user;
    }

    /**
     * Find a user based on a session cookie.
     *
     * @throws AuthnError on invalid cookie/disabled user
     */
    public function findByCookie(SessionCookie $cookie): User {
        if (!$cookie->isValid()) {
            throw new AuthnError('', AuthnMechanism::session);
        }
        $user = $this->userMan->findById($cookie->userId());
        if (is_null($user)) {
            throw new AuthnError('', AuthnMechanism::session);
        }
        $session = $user->session();
        if (!$user->isEnabled() && !in_array($this->requestContext()->module(), ['index', 'login'])) {
            $session->logoutEverywhere();
            throw new AuthnError('', AuthnMechanism::session);
        }
        if (!$session->valid($cookie->sessionKey())) {
            $session->logout();
            throw new AuthnError('', AuthnMechanism::session);
        }
        $this->requestContext()->setSessionId($cookie->sessionKey());
        $this->data = $cookie->sessionKey();
        return $user;
    }
}
