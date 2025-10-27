<?php

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserTokenType;
use Gazelle\Manager;
use Gazelle\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use RobThree\Auth\TwoFactorAuth;

class MultiFactorAuth extends \Gazelle\BaseUser {
    protected const RECOVERY_KEY_LEN = 20;

    private string|false $secret;

    public function flush(): static {
        unset($this->secret);
        return $this;
    }

    /**
     * Create the recovery keys for the user
     */
    public function create(
        Manager\UserToken $manager,
        #[\SensitiveParameter] string $key,
        ?User $editor = null
    ): ?array {
        $affectedRows = $this->pg()->prepared_query("
            insert into multi_factor_auth
                   (id_user, secret, ip)
            values (?,       ?,      ?)
            ", $this->user->id, $key, $this->requestContext()->remoteAddr()
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
        if ($editor?->id === $this->user->id) {
            $msg .= ' from ' . $this->requestContext()->remoteAddr();
        }
        $this->user->auditTrail()->addEvent(UserAuditEvent::mfa, $msg, $editor ?? $this->user);

        $this->flush();
        return $recovery;
    }

    public function generateSessionSecret(): string {
        return new TwoFactorAuth()->createSecret();
    }

    public function generateQrCode(string $secret, string $logo): ResultInterface {
        $qrCode = new QrCode(
            data:     'otpauth://totp/' . SITE_NAME . "?secret=$secret",
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 400,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::None,
            backgroundColor: new Color(1, 8, 1),
            foregroundColor: new Color(250, 250, 250)
        );
        $logo = new Logo(
            path: $logo,
            resizeToWidth: 96,
            punchoutBackground: false,
        );
        $label = new Label(
            text: SITE_NAME,
            textColor: new Color(0, 192, 0),
        );
        $writer = new PngWriter();
        return $writer->write($qrCode, $logo, $label);
    }

    protected function secret(): string|false {
        if (!isset($this->secret)) {
            $this->secret = $this->pg()->scalar('
                select secret from multi_factor_auth where id_user = ?
                ', $this->user->id
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
            ', $this->user->id
        );
    }

    /**
     * A user is attempting to log in with MFA via a recovery key
     * If we have the key on record, burn it and let them in.
     *
     * @param string $token Recovery token given by user
     * @return bool Valid key, they may log in.
     */
    public function burnRecovery(string $token): bool {
        $userToken = new Manager\UserToken()->findByToken($token);
        if (
            $userToken instanceof Token
            && $userToken->user->id === $this->user->id
            && $userToken->type() === UserTokenType::mfa
            && $userToken->consume()
        ) {
            if ($this->user->permitted('site_disable_ip_history')) {
                $this->requestContext()->anonymize();
            }
            $this->user->auditTrail()->addEvent(
                UserAuditEvent::mfa,
                "used recovery token $token from {$this->user->requestContext()->remoteAddr()}"
            );
            return true;
        }
        return false;
    }

    public function verifyCode(string $truth, string $code): bool {
        return new TwoFactorAuth()->verifyCode($truth, $code, 2);
    }

    public function verify(string $token): bool {
        if (!new TwoFactorAuth()->verifyCode($this->secret() ?: '', $token, 2)) {
            // They have MFA but the device key did not match
            // Fallback to considering it as a recovery key.
            if (strlen($token) === self::RECOVERY_KEY_LEN) {
                return $this->burnRecovery($token);
            }
            return false;
        }
        return true;
    }

    public function verifyToken(string $token): bool {
        if ($token === '') {
            // empty token only works if they have not set up MFA
            return !$this->enabled();
        }
        // otherwise they must have set up MFA and the token must be verified
        return $this->enabled() && $this->verify($token);
    }

    public function remove(): int {
        $this->user->auditTrail()->addEvent(
            UserAuditEvent::mfa,
            "removed via {$this->requestContext()->remoteAddr()}",
            $this->requestContext()->viewer(),
        );
        $affected = $this->pg()->prepared_query("
            delete from multi_factor_auth where id_user = ?
            ", $this->user->id
        );
        new Manager\UserToken()->removeTokens($this->user, UserTokenType::mfa);
        $this->flush();
        return $affected;
    }
}
