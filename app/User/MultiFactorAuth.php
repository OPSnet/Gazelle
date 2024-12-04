<?php

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserTokenType;
use Gazelle\Manager;
use Gazelle\User;

class MultiFactorAuth extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    protected const RECOVERY_KEY_LEN = 20;

    private string|false $secret;

    protected function secret(): string|false {
        if (!isset($this->secret)) {
            $this->secret = $this->pg()->scalar('
                select secret from multi_factor_auth where id_user = ?
                ', $this->id()
            ) ?? false;
        }
        return $this->secret;
    }

    public function enabled(): bool {
        return $this->secret() !== false;
    }

    public function details(): ?array {
        return $this->pg()->rowAssoc('
            select ip, created from multi_factor_auth where id_user = ?
            ', $this->id()
        );
    }

    /**
     * Create the recovery keys for the user
     */
    public function create(Manager\UserToken $manager, #[\SensitiveParameter] string $key, ?User $editor = null): ?array {
        $affectedRows = $this->pg()->prepared_query("
            insert into multi_factor_auth
                   (id_user, secret, ip)
            values (?,       ?,      ?)
            ", $this->id(), $key, $this->requestContext()->remoteAddr()
        );
        if ($affectedRows < 1) {
            return null;
        }

        $unique = [];
        while (count($unique) < 10) {
            $unique[randomString(self::RECOVERY_KEY_LEN)] = 1;
        }
        $recovery = array_keys($unique);
        foreach ($recovery as $value) {
            $manager->create(UserTokenType::mfa, user: $this->user, value: $value);
        }

        $msg = 'configured';
        if (!$editor || $editor->id() === $this->id()) {
            $msg .= ' from ' . $this->requestContext()->remoteAddr();
        }
        $this->user->auditTrail()->addEvent(UserAuditEvent::mfa, $msg, $editor ?? $this->user);

        $this->flush();
        return $recovery;
    }

    /**
     * A user is attempting to log in with MFA via a recovery key
     * If we have the key on record, burn it and let them in.
     *
     * @param string $token Recovery token given by user
     * @return bool Valid key, they may log in.
     */
    public function burnRecovery(string $token): bool {
        $userToken = (new Manager\UserToken())->findByToken($token);
        if (
            $userToken
            && $userToken->user->id() === $this->id()
            && $userToken->type() === UserTokenType::mfa
            && $userToken->consume()
        ) {
            $this->user->auditTrail()->addEvent(UserAuditEvent::mfa, "used recovery token $token");
            return true;
        }
        return false;
    }

    public function remove(?User $editor = null): static {
        $msg = 'removed';
        if (!$editor || $editor->id() === $this->id()) {
            $msg .= ' from ' . $this->requestContext()->remoteAddr();
        }
        $this->user->auditTrail()->addEvent(UserAuditEvent::mfa, $msg, $editor ?? $this->user);
        $this->pg()->prepared_query('
            delete from multi_factor_auth where id_user = ?
            ', $this->id()
        );
        (new Manager\UserToken())->removeTokens($this->user, UserTokenType::mfa);
        return $this->flush();
    }

    public function verify(string $token): bool {
        $tfa = new \RobThree\Auth\TwoFactorAuth();
        if (!$tfa->verifyCode($this->secret() ?: '', $token, 2)) {
            // They have 2FA but the device key did not match
            // Fallback to considering it as a recovery key.
            if (strlen($token) === self::RECOVERY_KEY_LEN) {
                return $this->burnRecovery($token);
            }
            return false;
        }
        return true;
    }

    public function flush(): static {
        unset($this->secret);
        return $this;
    }
}
