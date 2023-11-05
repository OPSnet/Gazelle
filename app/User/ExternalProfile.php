<?php

namespace Gazelle\User;

class ExternalProfile extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    final const tableName = 'user_external_profile';
    final const pkName    = 'id_user';

    protected array|null $info;

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info['profile'] = (string)$this->pg()->scalar("
            select profile from user_external_profile where id_user = ?
            ", $this->id()
        );
        return $this->info;
    }

    public function profile(): string {
        return $this->info()['profile'];
    }

    public function modifyProfile(string $profile): int {
        $affected = $this->pg()->prepared_query("
            insert into user_external_profile
                   (id_user, profile)
            values (?,       ?)
            on conflict (id_user) do update set profile = ?
            ", $this->id(), $profile, $profile
        );
        $this->flush();
        return $affected;
    }

    public function remove(): int {
        $affected = $this->pg()->prepared_query("
            delete from user_external_profile where id_user = ?
            ", $this->id()
        );
        $this->flush();
        return $affected;
    }
}
