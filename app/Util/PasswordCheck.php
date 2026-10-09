<?php

namespace Gazelle\Util;

use Gazelle\User;

class PasswordCheck {
    public const REGEXP = '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/';
    public const ERROR_MSG = 'You have specified a weak or known-compromised password.';

    public static function checkPasswordStrength(#[\SensitiveParameter] string $password, ?User $user, bool $skipRegex = true): bool {
        return static::checkPasswordStrengthNoUser($password, $user?->username(), $user?->email(), $skipRegex);
    }

    public static function checkPasswordStrengthNoUser(#[\SensitiveParameter] string $password, ?string $username, ?string $email, bool $skipRegex = true): bool {
        $butcheredPass = $password;
        if ($username && $email) {
            if (!static::checkUserPassword($password, $username, $email)) {
                return false;
            }
            $butcheredPass = static::butcherPassword($password, $username, $email);
        }
        if (!$skipRegex && !preg_match(static::REGEXP, $butcheredPass)) {
            return false;
        }
        if (PASSWORD_CHECK_URL) {
            $c = new Curl()->setUseProxy(false);
            foreach (array_unique([$password, $butcheredPass]) as $candidate) {
                // phpcs:disable Generic.PHP.ForbiddenFunctions.Found
                $c->setPostData(sha1($candidate, true))->fetch(PASSWORD_CHECK_URL);
                // phpcs:enable Generic.PHP.ForbiddenFunctions.Found
                if ($c->responseCode() === 205) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function checkUserPassword(#[\SensitiveParameter] string $password, string $username, string $email): bool {
        $lpw      = strtolower($password);
        $username = strtolower($username);
        $email    = strtolower($email);
        [$lhs, $rhs] = explode('@', $email, 2);
        if (in_array($lpw, [$username, $email, $lhs, "$username@$rhs"], true)) {
            return false;
        }
        return true;
    }

    protected static function butcherPassword(#[\SensitiveParameter] string $password, string $username, string $email): string {
        $lhs = explode('@', $email, 2)[0];
        return str_ireplace([$lhs, $username, SITE_HOST, SITE_NAME], '', $password);
    }
}
