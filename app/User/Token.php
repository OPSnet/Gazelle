<?php

namespace Gazelle\User;

use Gazelle\Enum\UserTokenType;

class Token extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    final public const tableName = 'user_token';

    public function flush(): static { unset($this->info); return $this; }

    public function __construct(protected int $tokenId, \Gazelle\User $user) {
        parent::__construct($user);
    }

    public function info(): array {
        if (empty($this->info)) {
            $this->info = $this->pg()->rowAssoc("
                select type,
                    token,
                    expiry
                from user_token
                where id_user_token = ?
                    and id_user = ?
                ", $this->tokenId, $this->user()->id()
            );
        }
        return $this->info;
    }

    public function id(): int {
        return $this->tokenId;
    }

    public function expiry(): string {
        return $this->info()['expiry'];
    }

    public function isValid(): bool {
        return (bool)$this->pg()->scalar("
            select 1
            from user_token
            where expiry > now()
                and id_user_token = ?
            ", $this->id()
        );
    }

    public function type(): UserTokenType {
        return match ($this->info()['type']) {
            'confirm' => UserTokenType::confirm,
            'mfa'     => UserTokenType::mfa,
            default   => UserTokenType::password,
        };
    }

    public function value(): string {
        return $this->info()['token'];
    }

    /**
     * Burn a token, return false if it has already been burnt
     */
    public function consume(): bool {
        $affected = $this->pg()->prepared_query("
            update user_token set
                expiry = now()
            where expiry > now()
                and id_user_token = ?
            ", $this->tokenId
        );
        $this->flush();
        return $affected == 1;
    }
}
