<?php

namespace Gazelle;

class ErrorLog extends BaseObject {

    protected array $info;

    public function tableName(): string {return 'error_log';}
    public function flush() {}
    public function url(): string {return '';}
    public function link(): string {return '';}

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $info = self::$db->rowAssoc("
            SELECT error_log_id,
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
        $info['error_list'] = json_decode($info['error_list'], true);
        $this->info = $info;
        return $this->info;
    }

    public function duration(): float {
        return $this->info()['duration'];
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

    public function seen(): int {
        return $this->info()['seen'];
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function updated(): string {
        return $this->info()['updated'];
    }

    public function uri(): string {
        return $this->info()['uri'];
    }

    public function trace(): array {
        return $this->info()['trace'];
    }

    public function request(): array {
        return $this->info()['request'];
    }

    public function errorList(): array {
        return $this->info()['error_list'];
    }
}
