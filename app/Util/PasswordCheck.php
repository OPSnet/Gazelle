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
        if (!$skipRegex && !preg_match(static::REGEXP, $password)) {
            return false;
        }
        if ($username && $email && !static::checkUserPassword($password, $username, $email)) {
            return false;
        }
        if (PASSWORD_CHECK_URL) {
            $c = new \Gazelle\Util\Curl();
            $c->setUseProxy(false)->setPostData(sha1($password, true))->fetch(PASSWORD_CHECK_URL);
            if ($c->responseCode() === 205) {
                return false;
            }
        }
        return true;
    }

    public static function checkUserPassword(#[\SensitiveParameter] string $password, string $username, string $email): bool {
        $lpw = strtolower($password);
        $username = strtolower($username);
        $email = strtolower($email);
        [$lhs, $rhs] = explode('@', $email, 2);
        if (in_array($lpw, [$username, $email, $lhs, "$username@$rhs"], true)) {
            return false;
        }
        return true;
    }
}
