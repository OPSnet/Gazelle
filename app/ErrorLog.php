<?php

namespace Gazelle;

class ErrorLog extends BaseObject {
    final public const tableName = 'error_log';

    public function flush(): static { return $this; }
    public function link(): string { return ''; }
    public function location(): string { return ''; }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $info = self::$db->rowAssoc("
            SELECT error_log_id,
                user_id,
                duration,
                memory,
                nr_query,
                nr_cache,
                seen,
                created,
                updated,
                uri,
                trace,
                request,
                error_list
            FROM error_log
            WHERE error_log_id = ?
            ", $this->id
        );
        $info['trace'] = explode("\n", $info['trace']);
        $info['request'] = json_decode($info['request'], true);
        $info['error_list'] = json_decode($info['error_list'], true) ?? [];
        $this->info = $info;
        return $this->info;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM error_log WHERE error_log_id = ?
            ", $this->id
        );
        $this->info = [];
        return self::$db->affected_rows();
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function duration(): float {
        return $this->info()['duration'];
    }

    public function errorList(): array {
        return $this->info()['error_list'];
    }

    public function memory(): int {
        return $this->info()['memory'];
    }

    public function nrCache(): int {
        return $this->info()['nr_cache'];
    }

    public function nrQuery(): int {
        return $this->info()['nr_query'];
    }

    public function request(): array {
        return $this->info()['request'];
    }

    public function seen(): int {
        return $this->info()['seen'];
    }

    public function trace(): array {
        return $this->info()['trace'];
    }

    public function updated(): string {
        return $this->info()['updated'];
    }

    public function uri(): string {
        return $this->info()['uri'];
    }

    public function userId(): float {
        return $this->info()['user_id'];
    }
}
