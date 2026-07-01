<?php

namespace Gazelle\User;

class ExternalProfile extends \Gazelle\BaseUser {
    final public const tableName = 'user_external_profile';
    final public const pkName    = 'id_user';

    protected array $info;

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info['profile'] = (string)$this->pg()->scalarex("
            select profile from user_external_profile where id_user = $1
            ", $this->user->id
        );
        return $this->info;
    }

    public function profile(): string {
        return $this->info()['profile'];
    }

    public function modifyProfile(string $profile): int {
        $affected = $this->pg()->query("
            insert into user_external_profile
                   (id_user, profile)
            values ($1,      $2)
            on conflict (id_user) do update set profile = $2
            ", $this->user->id, $profile
        );
        $this->flush();
        return $affected;
    }

    public function remove(): int {
        $affected = $this->pg()->query("
            delete from user_external_profile where id_user = $1
            ", $this->user->id
        );
        $this->flush();
        return $affected;
    }
}
