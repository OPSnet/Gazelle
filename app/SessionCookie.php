<?php

namespace Gazelle;

use Gazelle\Util\Crypto;

class SessionCookie {
    final protected const SEPARATOR = '|~|';
    protected bool   $isValid       = false;
    protected string $sessionKey    = '';
    protected int    $userId        = 0;

    /**
     * Cookie objects are constructed from a cookie set from a request.
     * Once it has been has been unpacked it can can be used as a basis
     * to instantiate a User.
     */
    public function __construct(string $payload) {
        $data = Crypto::decrypt($payload, ENCKEY);
        if ($data !== false) {
            $payload = explode(self::SEPARATOR, $data);
            if (count($payload) === 2) {
                $this->sessionKey = $payload[0];
                $this->userId     = (int)$payload[1];
                $this->isValid    = true;
            }
        }
    }

    /**
     * The cookie payload is built from a user and session key.
     * It will be sent out in a cookie header and returned in a
     * subsequent request from the user.
     */
    public static function encode(User $user, string $sessionKey): string {
        return Crypto::encrypt(
            implode(self::SEPARATOR, [$sessionKey, $user->id]),
            ENCKEY,
        );
    }

    /**
     * This is almost but not quite the same as the above, the main
     * difference being static versus method.
     */
    public function payload(): string {
        return Crypto::encrypt(
            implode(self::SEPARATOR, [$this->sessionKey, $this->userId]),
            ENCKEY,
        );
    }

    public function isValid(): bool {
        return $this->isValid;
    }

    public function sessionKey(): string {
        return $this->sessionKey;
    }

    public function userId(): int {
        return $this->userId;
    }

    public static function emit(User $user, string $sessionKey, int $expiryEpoch): void {
        setcookie(
            'session',
            self::encode($user, $sessionKey),
            [
                'expires'  => $expiryEpoch,
                'path'     => '/',
                'secure'   => SECURE_COOKIE,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function expire(): void {
        setcookie(
            'session',
            '',
            [
                'expires'  => time() - 86_400 * 90,
                'path'     => '/',
                'secure'   => SECURE_COOKIE,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
