<?php

namespace Gazelle;

class Invite extends \Gazelle\Base {
    protected array $info;

    public function __construct(
        protected string $key,
    ) {}

    public function info(): array {
        if (!isset($this->info)) {
            $this->info = self::$db->rowAssoc("
                SELECT InviterID AS user_id,
                    Email        AS email,
                    Expires      AS created,
                    Reason       AS reason
                FROM invites
                WHERE InviteKey = ?
                ", $this->key
            ) ?? [];
        }
        return $this->info;
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function email(): string {
        return $this->info()['email'];
    }

    public function key(): string {
        return $this->key;
    }

    public function reason(): string {
        return $this->info()['reason'];
    }

    public function userId(): int {
        return $this->info()['user_id'];
    }
}
